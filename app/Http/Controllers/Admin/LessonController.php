<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\AccessTier;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use App\Support\UploadConstraints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LessonController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStorageService $bunnyStorage,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Lessons/Index', [
            'lessons' => Lesson::query()
                ->with(['module', 'accessTiers', 'assessment'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('title', 'asc')
                ->get()
                ->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'module' => $lesson->module?->title,
                    'access_tiers' => $lesson->accessTiers->pluck('name')->all(),
                    'sort_order' => $lesson->sort_order,
                    'scoreboard' => $lesson->assessment?->title,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'lesson',
                        $lesson->id,
                        'thumbnail',
                        $lesson->thumbnail,
                        versionSeed: $lesson->updated_at,
                    ),
                    'has_workbook' => $lesson->workbook !== null,
                    'has_lesson_video' => $lesson->lesson_video_id !== null,
                    'has_audio' => $lesson->audio_url !== null,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Lessons/Create', [
            'accessTiers' => $this->accessTierOptions(),
            'modules' => $this->moduleOptions(),
            'scoreboards' => $this->scoreboardOptions(),
            'uploadConstraints' => $this->uploadConstraints(),
        ]);
    }

    public function store(LessonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeLessonAsset($request->file('thumbnail'), 'lessons/thumbnails');
        $data['workbook'] = $this->storeLessonAsset($request->file('workbook'), 'lessons/workbooks');
        $data['audio_url'] = $this->storeAudioAsset($request->audioUpload());
        unset($data['access_tier_ids']);
        unset($data['audio']);

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
                'lesson_video_id' => $lesson->lesson_video_id,
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
                    'file_name' => basename(BunnyAssetPath::isBunnyPath($lesson->workbook) ? BunnyAssetPath::objectKey($lesson->workbook) : (string) $lesson->workbook),
                    'mime_type' => $workbookMimeType,
                    'preview_supported' => $workbookPreviewSupported,
                    'preview_message' => $workbookPreviewSupported
                        ? null
                        : 'This workbook file cannot be previewed in the browser yet. You can still open it from the asset link.',
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
                'audio_preview_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'audio_url',
                    $lesson->audio_url,
                    versionSeed: $lesson->updated_at,
                ),
            ],
            'accessTiers' => $this->accessTierOptions(),
            'modules' => $this->moduleOptions(),
            'scoreboards' => $this->scoreboardOptions(),
            'uploadConstraints' => $this->uploadConstraints(),
            'status' => session('status'),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeLessonAsset(
            $request->file('thumbnail'),
            'lessons/thumbnails',
            $lesson->thumbnail,
        );
        $data['workbook'] = $this->storeLessonAsset(
            $request->file('workbook'),
            'lessons/workbooks',
            $lesson->workbook,
        );
        $data['audio_url'] = $this->storeAudioAsset(
            $request->audioUpload(),
            $lesson->audio_url,
        );
        unset($data['access_tier_ids']);
        unset($data['audio']);

        $lesson->update($data);
        $lesson->accessTiers()->sync($request->validated('access_tier_ids'));

        return redirect()
            ->route('admin.lessons.index')
            ->with('status', 'lesson-updated');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->bunnyStorage->delete($lesson->thumbnail);
        $this->bunnyStorage->delete($lesson->workbook);
        $this->bunnyStorage->delete($lesson->audio_url);
        $lesson->delete();

        return redirect()
            ->route('admin.lessons.index')
            ->with('status', 'lesson-deleted');
    }

    private function storeLessonAsset(mixed $file, string $directory, ?string $currentPath = null): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        return $this->bunnyStorage->upload($file, $directory, $currentPath);
    }

    private function storeAudioAsset(mixed $file, ?string $currentPath = null): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        try {
            return $this->bunnyStorage->upload($file, 'lessons/audio', $currentPath);
        } catch (RuntimeException $exception) {
            Log::error('Lesson audio upload failed.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'previous_message' => $exception->getPrevious()?->getMessage(),
                'previous_file' => $exception->getPrevious()?->getFile(),
                'previous_line' => $exception->getPrevious()?->getLine(),
                'audio_original_name' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
                'audio_mime_type' => method_exists($file, 'isValid')
                    && method_exists($file, 'getRealPath')
                    && $file->isValid()
                    && is_string($file->getRealPath())
                    && $file->getRealPath() !== ''
                    && is_readable($file->getRealPath())
                    && method_exists($file, 'getMimeType')
                        ? $file->getMimeType()
                        : null,
                'audio_size_bytes' => method_exists($file, 'getSize') ? $file->getSize() : null,
            ]);

            if (config('app.debug')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'audio' => $exception->getMessage(),
            ]);
        }
    }

    private function accessTierOptions(): array
    {
        return AccessTier::query()
            ->orderByDesc('is_active')
            ->orderBy('name', 'asc')
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
            ->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->get()
            ->map(fn (Module $module) => [
                'id' => $module->id,
                'title' => $module->title,
            ])
            ->all();
    }

    private function scoreboardOptions(): array
    {
        return Assessment::query()
            ->orderByDesc('updated_at')
            ->orderBy('title', 'asc')
            ->get()
            ->map(fn (Assessment $assessment) => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'status' => $assessment->status,
                'is_active' => $assessment->is_active,
            ])
            ->all();
    }

    private function uploadConstraints(): array
    {
        return [
            'max_size_bytes' => UploadConstraints::MAX_FILE_SIZE_KB * 1024,
            'max_size_label' => UploadConstraints::MAX_FILE_SIZE_MB.' MB',
            'audio_max_size_bytes' => UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_KB * 1024,
            'audio_max_size_label' => UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_MB),
        ];
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function previewMetadata(string $path): array
    {
        if (BunnyAssetPath::isBunnyPath($path)) {
            $extension = strtolower(pathinfo(BunnyAssetPath::objectKey($path), PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => null,
            };

            return [$extension === 'pdf', $mimeType];
        }

        $mimeType = Storage::disk('local')->mimeType($path);
        $isPdf = str($path)->lower()->endsWith('.pdf')
            || $mimeType === 'application/pdf';

        return [$isPdf, $mimeType];
    }
}
