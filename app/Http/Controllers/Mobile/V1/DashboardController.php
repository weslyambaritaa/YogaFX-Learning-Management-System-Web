<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\CurrentStudentResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\DialogContent;
use App\Models\Ebook;
use App\Models\Module;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Mobile\V1\StudentHomeApiService;
use App\Services\Mobile\V1\StudentModuleApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StudentModuleApiService $studentModuleApiService,
        private readonly CertificateEligibilityService $certificateEligibilityService,
        private readonly StudentHomeApiService $studentHomeApiService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing('accessTier');
        $moduleItems = $this->studentModuleApiService->moduleItemsForUser($user);
        $visibleModules = $moduleItems->where('is_visible', true)->values();
        $assignmentSummary = $this->assignmentSummary($user);
        $certificateSummary = $this->certificateSummary($user);
        $homePayload = $this->studentHomeApiService->payloadForUser($request);

        return MobileApiResponse::success([
            'student' => new CurrentStudentResource($user),
            'continue_learning' => $this->studentModuleApiService->continueLearningForUser($user),
            'progress_summary' => $this->studentModuleApiService->progressSummaryForModuleItems($moduleItems),
            'module_highlights' => $visibleModules->take(6)->values()->all(),
            'dialogs' => $this->dialogSummary(),
            'ebook_resources' => $this->ebookResources($user),
            'assignment_summary' => $assignmentSummary,
            'certificate_summary' => $certificateSummary,
            'student_context' => $homePayload['student_context'],
            'access_time_summary' => $homePayload['access_time_summary'],
            'continue_learning_section' => $homePayload['continue_learning_section'],
            'progress_summary_section' => $homePayload['progress_summary_section'],
            'next_step' => $homePayload['next_step'],
            'sequential_awareness' => $homePayload['sequential_awareness'],
            'available_modules_section' => $homePayload['available_modules_section'],
            'assignment_milestone' => $homePayload['assignment_milestone'],
            'certificate_milestone' => $homePayload['certificate_milestone'],
            'ebook_resources_section' => $homePayload['ebook_resources_section'],
            'home_experience' => $homePayload['home_experience'],
            'home_stage' => $homePayload['home_stage'],
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dialogSummary(): array
    {
        $dialogs = DialogContent::query()
            ->whereIn('key', [
                DialogContent::KEY_FULL_STANDING,
                DialogContent::KEY_FULL_FLOOR,
            ])
            ->get()
            ->keyBy('key');

        return [
            [
                'key' => DialogContent::KEY_FULL_STANDING,
                'route_key' => 'full-standing',
                'title' => $dialogs->get(DialogContent::KEY_FULL_STANDING)?->title ?? 'Full Standing Series Dialogue',
                'has_content' => filled($dialogs->get(DialogContent::KEY_FULL_STANDING)?->content),
                'detail_url' => route('mobile.api.v1.dialogs.show', ['key' => 'full-standing']),
            ],
            [
                'key' => DialogContent::KEY_FULL_FLOOR,
                'route_key' => 'full-floor',
                'title' => $dialogs->get(DialogContent::KEY_FULL_FLOOR)?->title ?? 'Full Floor Series Dialogue',
                'has_content' => filled($dialogs->get(DialogContent::KEY_FULL_FLOOR)?->content),
                'detail_url' => route('mobile.api.v1.dialogs.show', ['key' => 'full-floor']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ebookResources($user): array
    {
        if (! $user->access_tier_id) {
            return [
                'total' => 0,
                'items' => [],
            ];
        }

        $items = Ebook::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->take(6)
            ->map(fn (Ebook $ebook) => [
                'id' => $ebook->id,
                'title' => $ebook->title,
                'sort_order' => $ebook->sort_order,
                'detail_url' => route('mobile.api.v1.ebooks.show', $ebook),
            ])
            ->values()
            ->all();

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }
}
