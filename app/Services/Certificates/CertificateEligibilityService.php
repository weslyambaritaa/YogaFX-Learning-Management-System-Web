<?php

namespace App\Services\Certificates;

use App\Models\AssignmentSubmission;
use App\Models\Assignment;
use App\Models\Certificate;
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
        $assignmentSummary = $this->assignmentRequirementSummary($student);

        $requirements = collect([
            [
                'key' => 'approved_videos',
                'label' => 'Approved Videos',
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
                $availableTypes,
                $tier !== null,
                $requirements,
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
        $assignmentIds = $this->certificateRelevantAssignmentIds($student);

        if ($assignmentIds->isEmpty()) {
            return [
                'completed' => 0,
                'total' => 0,
                'detail' => 'This certificate path does not require any approved assignment videos.',
            ];
        }

        $assignments = Assignment::query()
            ->whereIn('id', $assignmentIds)
            ->get(['id', 'title']);
        $approvedAssignmentIds = AssignmentSubmission::latestMapForUserAssignments($student->id, $assignments)
            ->filter(fn (AssignmentSubmission $submission) => $submission->assignment_status === AssignmentSubmission::STATUS_APPROVED)
            ->keys()
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->values();

        return [
            'completed' => $approvedAssignmentIds->count(),
            'total' => $assignmentIds->count(),
            'detail' => 'Certificate unlocks after every required submitted assignment video in the active certificate path has been approved by admin.',
        ];
    }

    private function eligibilityMessage(
        Collection $availableTypes,
        bool $hasTier,
        Collection $requirements,
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
            if ($incomplete->contains('Approved Videos')) {
                return $assignmentSummary['detail'];
            }

            return 'Student must complete all relevant '.str($incomplete->join(', '))->lower()->value().' before certificate generation.';
        }

        return 'All required submitted assignment videos have been approved and certificate access is unlocked.';
    }

    private function certificateRelevantAssignmentIds(User $student): Collection
    {
        return $this->studentLearningPathService->certificateAssignmentIdsForStudent($student);
    }
}
