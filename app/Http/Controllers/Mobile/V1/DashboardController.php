<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\CurrentStudentResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Module;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Mobile\V1\StudentModuleApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StudentModuleApiService $studentModuleApiService,
        private readonly CertificateEligibilityService $certificateEligibilityService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing('accessTier');
        $moduleItems = $this->studentModuleApiService->moduleItemsForUser($user);
        $visibleModules = $moduleItems->where('is_visible', true)->values();
        $assignmentSummary = $this->assignmentSummary($user);
        $certificateSummary = $this->certificateSummary($user);

        return MobileApiResponse::success([
            'student' => new CurrentStudentResource($user),
            'continue_learning' => $this->studentModuleApiService->continueLearningForUser($user),
            'progress_summary' => $this->studentModuleApiService->progressSummaryForModuleItems($moduleItems),
            'module_highlights' => $visibleModules->take(6)->values()->all(),
            'assignment_summary' => $assignmentSummary,
            'certificate_summary' => $certificateSummary,
        ], 'Mobile dashboard retrieved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentSummary($user): array
    {
        if (! $user->access_tier_id) {
            return [
                'total' => 0,
                'approved' => 0,
                'pending' => 0,
                'rejected' => 0,
                'has_assignments' => false,
            ];
        }

        $assignmentIds = Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->with([
                'assignments' => fn ($query) => $query
                    ->where('status', Assignment::STATUS_LIVE)
                    ->select('id', 'module_id'),
            ])
            ->get(['id'])
            ->flatMap(fn (Module $module) => $module->assignments->pluck('id'))
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return [
                'total' => 0,
                'approved' => 0,
                'pending' => 0,
                'rejected' => 0,
                'has_assignments' => false,
            ];
        }

        $submissions = AssignmentSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('assignment_id')
            ->values();

        return [
            'total' => $assignmentIds->count(),
            'approved' => $submissions->where('assignment_status', AssignmentSubmission::STATUS_APPROVED)->count(),
            'pending' => $submissions->filter(fn (AssignmentSubmission $submission) => in_array(
                $submission->assignment_status,
                [
                    AssignmentSubmission::STATUS_SUBMITTED,
                    AssignmentSubmission::STATUS_PENDING_REVIEW,
                    AssignmentSubmission::STATUS_UNDER_REVIEW,
                ],
                true,
            ))->count(),
            'rejected' => $submissions->where('assignment_status', AssignmentSubmission::STATUS_REJECTED)->count(),
            'has_assignments' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function certificateSummary($user): array
    {
        $summary = $this->certificateEligibilityService->summaryForStudent($user);
        $latestCertificate = Certificate::query()
            ->where('user_id', $user->id)
            ->latest('generated_at')
            ->latest('id')
            ->first();

        return [
            'learning_eligible' => (bool) ($summary['learning_eligible'] ?? false),
            'available_types' => $summary['available_types'] ?? [],
            'latest_certificate' => $latestCertificate ? [
                'id' => $latestCertificate->id,
                'type' => $latestCertificate->certificate_type,
                'type_label' => $latestCertificate->typeLabel(),
                'generated_at' => optional($latestCertificate->generated_at)->toDateTimeString(),
            ] : null,
        ];
    }
}
