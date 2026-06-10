<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Support\UploadConstraints;
use App\Models\AccessTier;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Lessons/Index', [
            'lessons' => Lesson::query()
                ->with(['module', 'accessTiers'])
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'module' => $lesson->module?->title,
                    'access_tiers' => $lesson->accessTiers->pluck('name')->all(),
                    'sort_order' => $lesson->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'lesson',
                        $lesson->id,
                        'thumbnail',
                        $lesson->thumbnail,
                        versionSeed: $lesson->updated_at,
                    ),
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
            'uploadConstraints' => $this->uploadConstraints(),
        ]);
    }

    public function store(LessonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile($request->file('thumbnail'), 'lessons/thumbnails');
        $data['workbook'] = $this->storeUploadedFile($request->file('workbook'), 'lessons/workbooks');
        unset($data['access_tier_ids']);

        $lesson = Lesson::query()->create($data);
        $lesson->accessTiers()->sync($request->validated('access_tier_ids'));

        return redirect()
            ->route('admin.lessons.index')
            ->with('status', 'lesson-created');
    }

    public function edit(Lesson $lesson): Response
    {
        [$workbookPreviewSupported, $workbookMimeType] = $lesson->workbook
            ? $this->previewMetadata($lesson->workbook)
            : [false, null];

        return Inertia::render('Admin/Lessons/Edit', [
                'lesson' => [
                    'id' => $lesson->id,
                    'module_id' => $lesson->module_id,
                'access_tier_ids' => $lesson->accessTiers()->pluck('access_tiers.id')->all(),
                'assessment_id' => $lesson->assessment_id,
                'title' => $lesson->title,
                'video' => $lesson->video,
                'audio' => $lesson->audio,
                'content' => $lesson->content,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'thumbnail',
                    $lesson->thumbnail,
                    versionSeed: $lesson->updated_at,
                ),
                'workbook_preview' => $lesson->workbook ? [
                    'title' => "{$lesson->title} Workbook",
                    'file_name' => basename((string) $lesson->workbook),
                    'mime_type' => $workbookMimeType,
                    'preview_supported' => $workbookPreviewSupported,
                    'preview_message' => $workbookPreviewSupported
                        ? null
                        : 'This workbook file cannot be previewed in the browser yet. You can still download it.',
                    'preview_url' => $this->protectedMediaUrl(
                        'lesson',
                        $lesson->id,
                        'workbook',
                        $lesson->workbook,
                        versionSeed: $lesson->updated_at,
                        extraParameters: ['inline' => 1],
                    ),
                    'download_url' => $this->protectedMediaUrl(
                        'lesson',
                        $lesson->id,
                        'workbook',
                        $lesson->workbook,
                        download: true,
                        versionSeed: $lesson->updated_at,
                    ),
                ] : null,
            ],
            'accessTiers' => $this->accessTierOptions(),
            'modules' => $this->moduleOptions(),
            'uploadConstraints' => $this->uploadConstraints(),
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
        unset($data['access_tier_ids']);

        $lesson->update($data);
        $lesson->accessTiers()->sync($request->validated('access_tier_ids'));

        return redirect()
            ->route('admin.lessons.index')
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

    private function uploadConstraints(): array
    {
        return [
            'max_size_bytes' => UploadConstraints::MAX_FILE_SIZE_KB * 1024,
            'max_size_label' => UploadConstraints::MAX_FILE_SIZE_MB.' MB',
        ];
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function previewMetadata(string $path): array
    {
        $mimeType = Storage::disk('local')->mimeType($path);
        $isPdf = str($path)->lower()->endsWith('.pdf')
            || $mimeType === 'application/pdf';

        return [$isPdf, $mimeType];
    }
}
