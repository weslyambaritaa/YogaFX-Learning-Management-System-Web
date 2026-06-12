<?php

namespace App\Http\Controllers\Admin;

use App\Events\EmailNotifications\AssignmentApproved;
use App\Events\EmailNotifications\AssignmentRejected;
use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Module;
use App\Models\User;
use App\Services\BunnyStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStorageService $bunnyStorage,
    ) {
    }

    public function index(Module $module): Response
    {
        $this->normalizeAssignmentSortOrder($module);
        $module->loadCount(['lessons', 'assignments']);

        return Inertia::render('Admin/Assignments/Index', [
            'module' => $this->modulePayload($module),
            'assignments' => $module->assignments()
                ->withCount('submissions')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Assignment $assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'sort_order' => $assignment->sort_order,
                    'status' => $assignment->status,
                    'is_required' => $assignment->is_required,
                    'submissions_count' => $assignment->submissions_count,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(Module $module): Response
    {
        $this->normalizeAssignmentSortOrder($module);
        $module->loadCount(['lessons', 'assignments']);

        return Inertia::render('Admin/Assignments/Create', [
            'module' => $this->modulePayload($module),
            'assignmentStatuses' => $this->assignmentStatusOptions(),
            'nextSortOrder' => ((int) $module->assignments()->count()) + 1,
        ]);
    }

    public function store(AssignmentRequest $request, Module $module): RedirectResponse
    {
        $data = $request->validated();
        $data['module_id'] = $module->id;
        $data['is_required'] = (bool) ($data['is_required'] ?? true);
        $requestedSortOrder = (int) ($data['sort_order'] ?? 0);

        $assignment = Assignment::query()->create($data);
        $this->moveAssignmentToSortOrder($assignment, $requestedSortOrder);

        return redirect()
            ->route('admin.modules.assignments.index', $module)
            ->with('status', 'assignment-created');
    }

    public function edit(Module $module, Assignment $assignment): Response
    {
        abort_unless($assignment->module_id === $module->id, 404);
        $this->normalizeAssignmentSortOrder($module);
        $module->loadCount(['lessons', 'assignments']);
        $assignment->refresh();

        return Inertia::render('Admin/Assignments/Edit', [
            'module' => $this->modulePayload($module),
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'sort_order' => $assignment->sort_order,
                'status' => $assignment->status,
                'is_required' => $assignment->is_required,
            ],
            'assignmentStatuses' => $this->assignmentStatusOptions(),
            'status' => session('status'),
        ]);
    }

    public function update(AssignmentRequest $request, Module $module, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->module_id === $module->id, 404);
        $data = $request->validated();
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $requestedSortOrder = (int) ($data['sort_order'] ?? $assignment->sort_order);

        $assignment->update($data);
        $this->moveAssignmentToSortOrder($assignment, $requestedSortOrder);

        return redirect()
            ->route('admin.modules.assignments.index', $module)
            ->with('status', 'assignment-updated');
    }

    public function destroy(Module $module, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->module_id === $module->id, 404);

        foreach ($assignment->submissions as $submission) {
            $this->bunnyStorage->delete($submission->assignment_video);
        }

        $assignment->delete();
        $this->normalizeAssignmentSortOrder($module);

        return redirect()
            ->route('admin.modules.assignments.index', $module)
            ->with('status', 'assignment-deleted');
    }

    public function show(Module $module, Assignment $assignment): Response
    {
        abort_unless($assignment->module_id === $module->id, 404);
        $module->loadCount(['lessons', 'assignments']);

        return Inertia::render('Admin/Assignments/Show', [
            'module' => $this->modulePayload($module),
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'sort_order' => $assignment->sort_order,
                'status' => $assignment->status,
                'is_required' => $assignment->is_required,
            ],
            'submissionStatuses' => $this->submissionStatusOptions(),
            'submissions' => $assignment->submissions()
                ->with('user:id,name,email')
                ->latest('submitted_at')
                ->latest('id')
                ->get()
                ->map(fn (AssignmentSubmission $submission) => [
                    'id' => $submission->id,
                    'student' => [
                        'id' => $submission->user?->id,
                        'name' => $submission->user?->name ?? 'Unknown student',
                        'email' => $submission->user?->email,
                    ],
                    'status' => $submission->assignment_status,
                    'feedback' => $submission->assignment_feedback,
                    'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i'),
                    'reviewed_at' => $submission->reviewed_at?->format('Y-m-d H:i'),
                    'video_url' => $this->protectedMediaUrl(
                        'assignment-submission',
                        $submission->id,
                        'assignment_video',
                        $submission->assignment_video,
                        versionSeed: $submission->updated_at,
                    ),
                    'video_path' => $submission->assignment_video,
                ]),
            'status' => session('status'),
        ]);
    }

    public function updateSubmission(Request $request, Module $module, Assignment $assignment, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        abort_unless($assignment->module_id === $module->id, 404);
        abort_unless($assignmentSubmission->assignment_id === $assignment->id, 404);

        $data = $request->validate([
            'assignment_status' => ['required', 'in:'.implode(',', AssignmentSubmission::STATUSES)],
            'assignment_feedback' => ['nullable', 'string'],
        ]);

        $assignmentSubmission->fill($data);
        $isReviewed = in_array(
            $assignmentSubmission->assignment_status,
            [AssignmentSubmission::STATUS_APPROVED, AssignmentSubmission::STATUS_REJECTED],
            true,
        );

        $assignmentSubmission->graded_at = $isReviewed ? now() : null;
        $assignmentSubmission->reviewed_at = $isReviewed ? now() : null;
        $assignmentSubmission->reviewed_by = $isReviewed ? auth()->id() : null;
        $assignmentSubmission->save();

        $student = $assignmentSubmission->user;
        $emailPayload = [
            'user_name' => $student?->name,
            'user_email' => $student?->email,
            'assignment_type' => $assignmentSubmission->title(),
            'feedback' => $assignmentSubmission->assignment_feedback,
            'admin_email' => config('mail.from.address'),
        ];

        if ($assignmentSubmission->assignment_status === AssignmentSubmission::STATUS_APPROVED) {
            event(new AssignmentApproved($emailPayload, 'assignment_submission', $assignmentSubmission->id));
        }

        if ($assignmentSubmission->assignment_status === AssignmentSubmission::STATUS_REJECTED) {
            event(new AssignmentRejected($emailPayload, 'assignment_submission', $assignmentSubmission->id));
        }

        return redirect()
            ->route('admin.modules.assignments.show', [$module, $assignment])
            ->with('status', 'assignment-submission-updated');
    }

    public function deleteSubmissionVideo(Module $module, Assignment $assignment, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        abort_unless($assignment->module_id === $module->id, 404);
        abort_unless($assignmentSubmission->assignment_id === $assignment->id, 404);

        $this->bunnyStorage->delete($assignmentSubmission->assignment_video);
        $assignmentSubmission->update([
            'assignment_video' => null,
        ]);

        return redirect()
            ->route('admin.modules.assignments.show', [$module, $assignment])
            ->with('status', 'assignment-submission-video-deleted');
    }

    private function modulePayload(Module $module): array
    {
        return [
            'id' => $module->id,
            'title' => $module->title,
            'url_slug' => $module->url_slug,
            'lessons_count' => $module->lessons_count ?? $module->lessons()->count(),
            'assignments_count' => $module->assignments_count ?? $module->assignments()->count(),
        ];
    }

    private function assignmentStatusOptions(): array
    {
        return collect(Assignment::STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => str($status)->replace('_', ' ')->title()->value(),
            ])
            ->values()
            ->all();
    }

    private function submissionStatusOptions(): array
    {
        return collect([
            AssignmentSubmission::STATUS_SUBMITTED,
            AssignmentSubmission::STATUS_UNDER_REVIEW,
            AssignmentSubmission::STATUS_APPROVED,
            AssignmentSubmission::STATUS_REJECTED,
        ])->map(fn (string $status) => [
            'value' => $status,
            'label' => str($status)->replace('_', ' ')->title()->value(),
        ])->values()->all();
    }

    private function normalizeAssignmentSortOrder(Module $module): void
    {
        $module->assignments()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (Assignment $assignment, int $index): void {
                $expectedOrder = $index + 1;

                if ((int) $assignment->sort_order !== $expectedOrder) {
                    $assignment->updateQuietly([
                        'sort_order' => $expectedOrder,
                    ]);
                }
            });
    }

    private function moveAssignmentToSortOrder(Assignment $assignment, int $requestedSortOrder): void
    {
        $module = $assignment->module;
        $this->normalizeAssignmentSortOrder($module);
        $assignment->refresh();

        $assignmentCount = (int) $module->assignments()->count();
        $targetOrder = max(1, min($requestedSortOrder > 0 ? $requestedSortOrder : $assignmentCount, $assignmentCount));
        $currentOrder = (int) $assignment->sort_order;

        if ($currentOrder === $targetOrder) {
            return;
        }

        if ($targetOrder < $currentOrder) {
            $module->assignments()
                ->whereKeyNot($assignment->id)
                ->whereBetween('sort_order', [$targetOrder, $currentOrder - 1])
                ->increment('sort_order');
        } else {
            $module->assignments()
                ->whereKeyNot($assignment->id)
                ->whereBetween('sort_order', [$currentOrder + 1, $targetOrder])
                ->decrement('sort_order');
        }

        $assignment->updateQuietly([
            'sort_order' => $targetOrder,
        ]);

        $this->normalizeAssignmentSortOrder($module);
    }
}
