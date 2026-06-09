<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\AccessTier;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Lessons/Index', [
            'lessons' => Lesson::query()
                ->with(['module', 'accessTier'])
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'module' => $lesson->module?->title,
                    'access_tier' => $lesson->accessTier?->name,
                    'sort_order' => $lesson->sort_order,
                    'has_workbook' => $lesson->workbook !== null,
                    'has_video' => $lesson->video !== null,
                    'has_audio' => $lesson->audio !== null,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Lessons/Create', [
            'accessTiers' => $this->accessTierOptions(),
            'modules' => $this->moduleOptions(),
        ]);
    }

    public function store(LessonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile($request->file('thumbnail'), 'lessons/thumbnails');
        $data['workbook'] = $this->storeUploadedFile($request->file('workbook'), 'lessons/workbooks');

        $lesson = Lesson::query()->create($data);

        return redirect()
            ->route('admin.lessons.edit', $lesson)
            ->with('status', 'lesson-created');
    }

    public function edit(Lesson $lesson): Response
    {
        return Inertia::render('Admin/Lessons/Edit', [
            'lesson' => [
                'id' => $lesson->id,
                'module_id' => $lesson->module_id,
                'access_tier_id' => $lesson->access_tier_id,
                'assessment_id' => $lesson->assessment_id,
                'title' => $lesson->title,
                'video' => $lesson->video,
                'audio' => $lesson->audio,
                'content' => $lesson->content,
                'sort_order' => $lesson->sort_order,
                'thumbnail_url' => route('media.show', ['entity' => 'lesson', 'id' => $lesson->id, 'field' => 'thumbnail']),
                'workbook_url' => $lesson->workbook
                    ? route('media.show', ['entity' => 'lesson', 'id' => $lesson->id, 'field' => 'workbook'])
                    : null,
            ],
            'accessTiers' => $this->accessTierOptions(),
            'modules' => $this->moduleOptions(),
            'status' => session('status'),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile(
            $request->file('thumbnail'),
            'lessons/thumbnails',
            $lesson->thumbnail,
        );
        $data['workbook'] = $this->storeUploadedFile(
            $request->file('workbook'),
            'lessons/workbooks',
            $lesson->workbook,
        );

        $lesson->update($data);

        return redirect()
            ->route('admin.lessons.edit', $lesson)
            ->with('status', 'lesson-updated');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->deleteUploadedFile($lesson->thumbnail);
        $this->deleteUploadedFile($lesson->workbook);
        $lesson->delete();

        return redirect()
            ->route('admin.lessons.index')
            ->with('status', 'lesson-deleted');
    }

    private function accessTierOptions(): array
    {
        return AccessTier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (AccessTier $accessTier) => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'is_active' => $accessTier->is_active,
            ])
            ->all();
    }

    private function moduleOptions(): array
    {
        return Module::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Module $module) => [
                'id' => $module->id,
                'title' => $module->title,
            ])
            ->all();
    }
}
