<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Module;
use App\Services\BunnyStorageService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
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
