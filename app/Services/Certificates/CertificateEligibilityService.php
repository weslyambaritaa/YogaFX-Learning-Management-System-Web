<?php

namespace App\Services\Certificates;

use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentProgress;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Collection;

class CertificateEligibilityService
{
    public function summaryForStudent(User $student): array
    {
        $student->loadMissing('accessTier');

        $tier = $student->accessTier;
        $tierSlug = $tier?->slug;
        $availableTypes = collect(config("certificates.tiers.{$tierSlug}", []))
            ->filter(fn ($type) => isset(Certificate::TYPES[$type]))
            ->values();

        $lessonIds = $this->relevantLessonIds($student);
        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('is_done', true)
            ->pluck('lesson_id')
            ->map(fn ($lessonId) => (int) $lessonId)
            ->unique()
            ->values();

        $assessmentIds = $this->relevantAssessmentIds($student);
        $completedAssessmentIds = AssessmentProgress::query()
            ->where('user_id', $student->id)
            ->whereIn('assessment_id', $assessmentIds)
            ->where('is_done', true)
            ->pluck('assessment_id')
            ->map(fn ($assessmentId) => (int) $assessmentId)
            ->unique()
            ->values();

        $assignmentSummary = $this->assignmentRequirementSummary($student);

        $requirements = collect([
            [
                'key' => 'lessons',
                'label' => 'Lessons',
                'completed' => $completedLessonIds->count(),
                'total' => $lessonIds->count(),
            ],
            [
                'key' => 'assessments',
                'label' => 'Assessments',
                'completed' => $completedAssessmentIds->count(),
                'total' => $assessmentIds->count(),
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

        $hasRelevantFlow = $requirements->contains(fn (array $item) => $item['total'] > 0);
        $learningEligible = $tier !== null
            && $availableTypes->isNotEmpty()
            && $hasRelevantFlow
            && $requirements->every(fn (array $item) => $item['is_complete']);

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
            'message' => $this->eligibilityMessage($tierSlug, $availableTypes, $tier !== null, $hasRelevantFlow, $requirements, $assignmentSummary),
        ];
    }

    public function rowsForStudent(User $student): array
    {
        $summary = $this->summaryForStudent($student);
        $generatedCertificates = $this->latestCertificatesByType($student, $summary['available_types']);

        return collect($summary['available_types'])
            ->map(function (string $type) use ($summary, $generatedCertificates) {
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

    private function relevantLessonIds(User $student): Collection
    {
        $tierId = $student->access_tier_id;

        if (! $tierId) {
            return collect();
        }

        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $tierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $tierId))
                    ->select('id', 'module_id', 'assessment_id'),
            ])
            ->get(['id'])
            ->flatMap(fn (Module $module) => $module->lessons->pluck('id'))
            ->map(fn ($lessonId) => (int) $lessonId)
            ->unique()
            ->values();
    }

    private function relevantAssessmentIds(User $student): Collection
    {
        $tierId = $student->access_tier_id;

        if (! $tierId) {
            return collect();
        }

        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $tierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $tierId))
                    ->whereNotNull('assessment_id')
                    ->select('id', 'module_id', 'assessment_id'),
            ])
            ->get(['id'])
            ->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))
            ->filter()
            ->map(fn ($assessmentId) => (int) $assessmentId)
            ->unique()
            ->values();
    }

    private function assignmentRequirementSummary(User $student): array
    {
        $tierSlug = $student->accessTier?->slug;

        if ($tierSlug !== AccessTier::SLUG_ONLINE) {
            return [
                'completed' => 0,
                'total' => 0,
                'detail' => 'Assignments are not part of this tier certificate path.',
            ];
        }

        $submittedTypes = AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->whereNotNull('assignment_video')
            ->pluck('assignment_type')
            ->map(fn (string $type) => str($type)->lower()->value())
            ->values();

        $hasLegacy = $submittedTypes->contains('graduation_video');
        $hasStanding = $submittedTypes->contains(fn (string $type) => str($type)->contains('standing'));
        $hasFloor = $submittedTypes->contains(fn (string $type) => str($type)->contains('floor'));

        if ($hasLegacy) {
            return [
                'completed' => 2,
                'total' => 2,
                'detail' => 'Legacy graduation video already satisfies the assignment package.',
            ];
        }

        return [
            'completed' => collect([$hasStanding, $hasFloor])->filter()->count(),
            'total' => 2,
            'detail' => 'Online tier requires standing and floor assignment submissions.',
        ];
    }

    private function eligibilityMessage(
        ?string $tierSlug,
        Collection $availableTypes,
        bool $hasTier,
        bool $hasRelevantFlow,
        Collection $requirements,
        array $assignmentSummary,
    ): string {
        if (! $hasTier) {
            return 'Student does not have an active access tier yet.';
        }

        if ($availableTypes->isEmpty()) {
            return 'This active tier does not have any certificate template mapping.';
        }

        if (! $hasRelevantFlow) {
            return 'No relevant lesson, assessment, or assignment flow is configured for this tier yet.';
        }

        $incomplete = $requirements
            ->filter(fn (array $item) => ! $item['is_complete'])
            ->map(fn (array $item) => $item['label'])
            ->values();

        if ($incomplete->isNotEmpty()) {
            if ($tierSlug === AccessTier::SLUG_ONLINE && $incomplete->contains('Assignments')) {
                return $assignmentSummary['detail'];
            }

            return 'Student must complete all relevant '.str($incomplete->join(', '))->lower()->value().' before certificate generation.';
        }

        return 'All relevant learning flow is complete.';
    }
}
