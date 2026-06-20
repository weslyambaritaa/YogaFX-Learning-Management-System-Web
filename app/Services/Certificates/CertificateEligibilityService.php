<?php

namespace App\Services\Certificates;

use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\StudentLearningPathService;
use Illuminate\Support\Collection;

class CertificateEligibilityService
{
    public function __construct(
        private readonly StudentLearningPathService $studentLearningPathService,
    ) {}

    public function summaryForStudent(User $student): array
    {
        $student->loadMissing('accessTier');

        $tier = $student->accessTier;
        $tierSlug = $tier?->slug;
        $availableTypes = collect(config("certificates.tiers.{$tierSlug}", []))
            ->filter(fn ($type) => isset(Certificate::TYPES[$type]))
            ->values();

        $accessibleModules = $this->studentLearningPathService->accessibleModulesForStudent($student, withAssessments: true);
        $pathSummary = $this->learningPathRequirementSummary($student, $accessibleModules);
        $assignmentSummary = $this->assignmentRequirementSummary($student);

        $requirements = collect([
            [
                'key' => 'learning_path',
                'label' => 'Learning Path',
                'completed' => $pathSummary['completed'],
                'total' => $pathSummary['total'],
            ],
            [
                'key' => 'assignments',
                'label' => 'Assignments',
                'completed' => $assignmentSummary['completed'],
                'total' => $assignmentSummary['total'],
            ],
        ])->map(function (array $item) {
            $item['is_complete'] = $item['completed'] >= $item['total'];
            $item['status'] = $item['total'] === 0
                ? 'Not required'
                : ($item['is_complete'] ? 'Completed' : "{$item['completed']}/{$item['total']} completed");

            return $item;
        })->values();

        $hasRelevantFlow = $tier !== null && $availableTypes->isNotEmpty();
        $learningEligible = $tier !== null
            && $availableTypes->isNotEmpty()
            && $pathSummary['completed'] >= $pathSummary['total']
            && $assignmentSummary['completed'] >= $assignmentSummary['total'];

        return [
            'tier' => $tier ? [
                'id' => $tier->id,
                'name' => $tier->name,
                'slug' => $tier->slug,
            ] : null,
            'available_types' => $availableTypes->all(),
            'has_required_name' => $this->hasRequiredStudentName($student),
            'student_name' => trim((string) $student->name),
            'learning_eligible' => $learningEligible,
            'has_relevant_flow' => $hasRelevantFlow,
            'requirements' => $requirements->all(),
            'message' => $this->eligibilityMessage(
                $tierSlug,
                $availableTypes,
                $tier !== null,
                $hasRelevantFlow,
                $requirements,
                $pathSummary,
                $assignmentSummary,
            ),
        ];
    }

    public function rowsForStudent(User $student): array
    {
        $summary = $this->summaryForStudent($student);
        $generatedCertificates = $this->latestCertificatesByType($student, $summary['available_types']);

        return collect($summary['available_types'])
            ->map(function (string $type) use ($summary, $generatedCertificates, $student) {
                /** @var Certificate|null $certificate */
                $certificate = $generatedCertificates->get($type);
                $generatedAt = $certificate?->generated_at;
                $isGenerated = $certificate !== null;
                $status = $isGenerated
                    ? 'Generated'
                    : ($summary['learning_eligible'] ? 'Eligible' : 'Not Eligible');

                $detail = $isGenerated
                    ? 'PDF has been generated and stored internally.'
                    : ($summary['learning_eligible']
                        ? ($summary['has_required_name']
                            ? 'All relevant learning flow is completed and ready for manual generation.'
                            : 'Learning flow is complete, but the student primary account name is still required before generation.')
                        : $summary['message']);

                return [
                    'type' => $type,
                    'label' => Certificate::TYPES[$type] ?? $type,
                    'status' => $status,
                    'status_tone' => $isGenerated
                        ? 'emerald'
                        : ($summary['learning_eligible'] ? 'amber' : 'slate'),
                    'detail' => $detail,
                    'generated_at' => $generatedAt?->format('Y-m-d H:i'),
                    'generated_by' => $certificate?->generator?->name,
                    'certificate_id' => $certificate?->id,
                    'download_url' => $certificate
                        ? route('admin.student-progress.certificates.download', [
                            'student' => $student,
                            'certificate' => $certificate,
                        ])
                        : null,
                    'can_generate' => ! $isGenerated && $summary['learning_eligible'] && $summary['has_required_name'],
                    'can_regenerate' => $isGenerated && $summary['learning_eligible'] && $summary['has_required_name'],
                    'action_block_reason' => $summary['has_required_name']
                        ? $summary['message']
                        : 'Student primary account name is required before certificate generation.',
                ];
            })
            ->values()
            ->all();
    }

    public function latestCertificatesByType(User $student, array $types): Collection
    {
        return Certificate::query()
            ->with('generator:id,name')
            ->where('user_id', $student->id)
            ->whereIn('certificate_type', $types)
            ->where(function ($query) {
                $query
                    ->where('file_name', 'like', '%.pdf')
                    ->orWhere('file_path', 'like', '%.pdf');
            })
            ->latest('generated_at')
            ->latest('id')
            ->get()
            ->unique('certificate_type')
            ->keyBy('certificate_type');
    }

    public function hasRequiredStudentName(User $student): bool
    {
        return trim((string) $student->name) !== '';
    }

    private function assignmentRequirementSummary(User $student): array
    {
        $assignmentIds = $this->relevantAssignmentIds($student);

        if ($assignmentIds->isEmpty()) {
            return [
                'completed' => 0,
                'total' => 0,
                'detail' => 'Assignments are not part of this tier certificate path.',
            ];
        }

        $approvedAssignmentIds = AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->where('assignment_status', AssignmentSubmission::STATUS_APPROVED)
            ->pluck('assignment_id')
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->unique()
            ->values();

        return [
            'completed' => $approvedAssignmentIds->count(),
            'total' => $assignmentIds->count(),
            'detail' => 'Certificate unlocks after every required live assignment in the active tier has been approved.',
        ];
    }

    private function learningPathRequirementSummary(User $student, Collection $accessibleModules): array
    {
        $prerequisiteModules = $accessibleModules
            ->reject(fn (Module $module) => $this->isCertificateDownloadModule($module))
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();

        if ($prerequisiteModules->isEmpty()) {
            return [
                'completed' => 0,
                'total' => 0,
                'detail' => 'This tier does not have any lesson-based learning module blocking certificate access.',
            ];
        }

        $lessonIds = $prerequisiteModules->flatMap(fn (Module $module) => $module->lessons->pluck('id'))->filter()->values();
        $assessmentIds = $prerequisiteModules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter()->values();

        $lessonProgressMap = LessonProgress::query()
            ->where('user_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $completedAssessmentIds = AssessmentAttempt::query()
            ->where('user_id', $student->id)
            ->whereIn('assessment_id', $assessmentIds)
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->pluck('assessment_id')
            ->map(fn ($assessmentId) => (int) $assessmentId)
            ->unique()
            ->values();

        $completedModules = $prerequisiteModules->filter(
            fn (Module $module) => $this->isModuleComplete(
                $module,
                $lessonProgressMap,
                $completedAssessmentIds,
            ),
        )->count();

        return [
            'completed' => $completedModules,
            'total' => $prerequisiteModules->count(),
            'detail' => 'Certificate unlock follows the modules that are actually accessible in the student tier path.',
        ];
    }

    private function relevantAssignmentIds(User $student): Collection
    {
        return $this->studentLearningPathService->relevantAssignmentIdsForStudent($student);
    }

    private function eligibilityMessage(
        ?string $tierSlug,
        Collection $availableTypes,
        bool $hasTier,
        bool $hasRelevantFlow,
        Collection $requirements,
        array $pathSummary,
        array $assignmentSummary,
    ): string {
        if (! $hasTier) {
            return 'Student does not have an active access tier yet.';
        }

        if ($availableTypes->isEmpty()) {
            return 'This active tier does not have any certificate template mapping.';
        }

        $incomplete = $requirements
            ->filter(fn (array $item) => ! $item['is_complete'])
            ->map(fn (array $item) => $item['label'])
            ->values();

        if ($incomplete->isNotEmpty()) {
            if ($incomplete->contains('Learning Path')) {
                return $pathSummary['detail'];
            }

            if ($incomplete->contains('Assignments')) {
                return $assignmentSummary['detail'];
            }

            return 'Student must complete all relevant '.str($incomplete->join(', '))->lower()->value().' before certificate generation.';
        }

        return 'All accessible prerequisite modules are complete and certificate access is unlocked.';
    }

    private function isModuleComplete(
        Module $module,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
    ): bool {
        return $module->lessons->isNotEmpty() && $module->lessons->every(
            fn ($lesson) => $this->isLessonComplete(
                $lesson,
                $lessonProgressMap->get($lesson->id),
                $completedAssessmentIds,
            ),
        );
    }

    private function isLessonComplete($lesson, ?LessonProgress $lessonProgress, Collection $completedAssessmentIds): bool
    {
        if ($lesson->lesson_video_id !== null && (float) ($lessonProgress?->watch_progress ?? 0) < 95) {
            return false;
        }

        if (
            $lesson->assessment_id !== null
            && $lesson->assessment?->status === 'live'
            && $lesson->assessment?->is_active
        ) {
            return $completedAssessmentIds->contains((int) $lesson->assessment_id);
        }

        return true;
    }

    private function isCertificateDownloadModule(Module $module): bool
    {
        return $module->lessons->isEmpty()
            && $module->assignments->where('status', Assignment::STATUS_LIVE)->isEmpty()
            && (bool) $module->certificate_enabled;
    }
}
