<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Services\BunnyStreamService;
use App\Services\StudentSessionTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LessonCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
        private readonly StudentSessionTrackingService $sessionTrackingService,
    ) {}

    public function show(Request $request, Lesson $lesson): Response
    {
        $user = $request->user();
        $this->authorizeLessonAccess($request, $lesson);

        $accessibleModules = $this->accessibleModulesWithLessons($user?->access_tier_id);
        $lessonNavigation = optional($accessibleModules->firstWhere('id', $lesson->module_id))->lessons
            ?? collect();

        $progressMap = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $accessibleModules->flatMap(fn (Module $module) => $module->lessons->pluck('id')))
            ->get()
            ->keyBy('lesson_id');
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user->id,
            $accessibleModules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $lessonUnlockMap = $this->lessonUnlockMap($user->id, $accessibleModules, $progressMap);

        $completedLessons = $lessonNavigation->filter(fn (Lesson $item) => $this->isLessonFullyComplete(
            $item,
            $progressMap->get($item->id),
            $completedAssessmentIds,
        ))->count();
        $currentProgress = $progressMap->get($lesson->id);
        $videoState = $this->videoStateForLesson($lesson);
        $currentLessonIndex = $lessonNavigation->search(
            fn (Lesson $item) => $item->id === $lesson->id,
        );
        $nextLesson = $currentLessonIndex !== false
            ? $lessonNavigation->get($currentLessonIndex + 1)
            : null;

        return Inertia::render('Student/Lessons/Show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'lesson_video_id' => $lesson->lesson_video_id,
                'video' => $videoState,
                'audio_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'audio_url',
                    $lesson->audio_url,
                    versionSeed: $lesson->updated_at,
                ),
                'content' => $lesson->content,
                'assessment_id' => $lesson->assessment_id,
                'assessment' => $lesson->assessment && $lesson->assessment->status === 'live' && $lesson->assessment->is_active ? [
                    'id' => $lesson->assessment->id,
                    'title' => $lesson->assessment->title,
                    'is_unlocked' => $lesson->lesson_video_id === null
                        || (float) LessonProgress::query()
                            ->where('lesson_id', $lesson->id)
                            ->where('user_id', $user->id)
                            ->value('watch_progress') >= 95,
                    'is_completed' => $completedAssessmentIds->contains((int) $lesson->assessment_id),
                    'current_attempt_id' => AssessmentAttempt::query()
                        ->where('assessment_id', $lesson->assessment_id)
                        ->where('user_id', $user->id)
                        ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
                        ->latest('id')
                        ->value('id'),
                ] : null,
                'module' => $lesson->module ? [
                    'id' => $lesson->module->id,
                    'title' => $lesson->module->title,
                    'url_slug' => $lesson->module->url_slug,
                    'lesson_count' => $lessonNavigation->count(),
                    'completed_lessons' => $completedLessons,
                    'progress_percentage' => $lessonNavigation->count() > 0
                        ? (int) round(($completedLessons / $lessonNavigation->count()) * 100)
                        : 0,
                ] : null,
                'progress' => [
                    'watch_progress' => (int) round((float) ($currentProgress?->watch_progress ?? 0)),
                    'is_done' => $this->isLessonFullyComplete(
                        $lesson,
                        $currentProgress,
                        $completedAssessmentIds,
                    ),
                ],
                'autoplay' => $request->boolean('autoplay'),
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'thumbnail',
                    $lesson->thumbnail,
                    versionSeed: $lesson->updated_at,
                ),
                'workbook_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'workbook',
                    $lesson->workbook,
                    versionSeed: $lesson->updated_at,
                ),
                'navigation' => $lessonNavigation->map(fn (Lesson $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'sort_order' => $item->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'lesson',
                        $item->id,
                        'thumbnail',
                        $item->thumbnail,
                        versionSeed: $item->updated_at,
                    ) ?: $this->protectedMediaUrl(
                        'lesson',
                        $lesson->id,
                        'thumbnail',
                        $lesson->thumbnail,
                        versionSeed: $lesson->updated_at,
                    ),
                    'is_locked' => ! ($lessonUnlockMap->get($item->id)['is_unlocked'] ?? false),
                    'lock_reason' => $lessonUnlockMap->get($item->id)['reason'] ?? null,
                    'status' => $this->isLessonFullyComplete(
                        $item,
                        $progressMap->get($item->id),
                        $completedAssessmentIds,
                    )
                        ? 'completed'
                        : (! ($lessonUnlockMap->get($item->id)['is_unlocked'] ?? false)
                            ? 'locked'
                            : ($item->id === $lesson->id ? 'current' : 'available')),
                    'progress_percentage' => (int) round((float) (optional($progressMap->get($item->id))->watch_progress ?? 0)),
                    'url' => ($lessonUnlockMap->get($item->id)['is_unlocked'] ?? false)
                        ? route('lessons.show', $item)
                        : null,
                ]),
                'next_lesson' => $nextLesson ? [
                    'id' => $nextLesson->id,
                    'title' => $nextLesson->title,
                    'sort_order' => $nextLesson->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'lesson',
                        $nextLesson->id,
                        'thumbnail',
                        $nextLesson->thumbnail,
                        versionSeed: $nextLesson->updated_at,
                    ),
                    'is_unlocked' => (bool) ($lessonUnlockMap->get($nextLesson->id)['is_unlocked'] ?? false),
                    'lock_reason' => $lessonUnlockMap->get($nextLesson->id)['reason'] ?? null,
                    'url' => ($lessonUnlockMap->get($nextLesson->id)['is_unlocked'] ?? false)
                        ? route('lessons.show', $nextLesson)
                        : null,
                ] : null,
            ],
            'accessTimeSummary' => $this->sessionTrackingService->summaryForUser($user),
        ]);
    }

    public function updateProgress(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $this->authorizeLessonAccess($request, $lesson);

        $validated = $request->validate([
            'watch_progress' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $incomingProgress = round((float) $validated['watch_progress'], 2);
        $existingProgress = (float) LessonProgress::query()
            ->where('user_id', $user?->id)
            ->where('lesson_id', $lesson->id)
            ->value('watch_progress');

        $watchProgress = max($existingProgress, $incomingProgress);
        $hasCompletedAssessment = $lesson->assessment_id !== null
            && $lesson->assessment?->status === 'live'
            && $lesson->assessment?->is_active
            && AssessmentAttempt::query()
                ->where('assessment_id', $lesson->assessment_id)
                ->where('user_id', $user?->id)
                ->where('status', AssessmentAttempt::STATUS_COMPLETED)
                ->exists();
        $isDone = $watchProgress >= 95 && (! $lesson->assessment_id || $hasCompletedAssessment || ! $lesson->assessment?->is_active || $lesson->assessment?->status !== 'live');

        $lessonProgress = LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $user?->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'watch_progress' => $watchProgress,
                'video_completed_at' => $isDone ? now() : null,
                'is_done' => $isDone,
                'completed_at' => $isDone ? now() : null,
            ],
        );

        return response()->json([
            'watch_progress' => (int) round((float) $lessonProgress->watch_progress),
            'is_done' => (bool) $lessonProgress->is_done,
            'assessment_unlocked' => $lesson->lesson_video_id === null || $watchProgress >= 95,
        ]);
    }

    /**
     * @return array{
     *     video_id: string|null,
     *     hls_url: string|null,
     *     is_ready: bool,
     *     is_configured: bool,
     *     is_valid_id: bool,
     *     is_found_in_library: bool|null,
     *     warning_message: string|null
     * }
     */
    private function videoStateForLesson(Lesson $lesson): array
    {
        $videoId = is_string($lesson->lesson_video_id)
            ? trim($lesson->lesson_video_id)
            : null;

        if (! filled($videoId)) {
            return [
                'video_id' => null,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => $this->bunnyStreamService->hasPlaybackConfig(),
                'is_valid_id' => false,
                'is_found_in_library' => null,
                'warning_message' => null,
            ];
        }

        if (! Str::isUuid($videoId)) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => $this->bunnyStreamService->hasPlaybackConfig(),
                'is_valid_id' => false,
                'is_found_in_library' => null,
                'warning_message' => 'This lesson video is not using a valid Bunny Stream video ID yet. Please update it from admin before playback can start.',
            ];
        }

        if (! $this->bunnyStreamService->hasPlaybackConfig()) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => false,
                'is_valid_id' => true,
                'is_found_in_library' => null,
                'warning_message' => 'This lesson already has a Bunny Stream video ID, but Bunny Stream CDN is not configured yet in the current environment.',
            ];
        }

        $videoInspection = $this->bunnyStreamService->inspectVideoId($videoId);

        if ($videoInspection['is_verified'] && ! $videoInspection['is_found']) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => true,
                'is_valid_id' => true,
                'is_found_in_library' => false,
                'warning_message' => 'This lesson video ID is valid in format, but it was not found in the configured Bunny Stream library. Please update the lesson with the correct Bunny Stream video GUID from admin.',
            ];
        }

        return [
            'video_id' => $videoId,
            'hls_url' => $this->bunnyStreamService->hlsUrl($videoId),
            'is_ready' => true,
            'is_configured' => true,
            'is_valid_id' => true,
            'is_found_in_library' => $videoInspection['is_verified'] ? true : null,
            'warning_message' => null,
        ];
    }

    private function authorizeLessonAccess(Request $request, Lesson $lesson): void
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;
        $accessibleModules = $this->accessibleModulesWithLessons($accessTierId);
        $lessonUnlockMap = $this->lessonUnlockMap(
            $user?->id,
            $accessibleModules,
            LessonProgress::query()
                ->where('user_id', $user?->id)
                ->whereIn('lesson_id', $accessibleModules->flatMap(fn (Module $module) => $module->lessons->pluck('id')))
                ->get()
                ->keyBy('lesson_id'),
        );

        abort_unless(
            $user
            && $lesson->accessTiers()->where('access_tiers.id', $accessTierId)->exists()
            && $lesson->module?->accessTiers()->where('access_tiers.id', $accessTierId)->exists()
            && ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false),
            403,
        );
    }

    private function accessibleModulesWithLessons(?int $accessTierId): Collection
    {
        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select(['id', 'module_id', 'title', 'sort_order', 'assessment_id', 'lesson_video_id'])
                    ->with(['assessment:id,status,is_active'])
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    private function lessonUnlockMap(?int $userId, Collection $modules, Collection $lessonProgressMap): Collection
    {
        $orderedLessons = $modules->flatMap(fn (Module $module) => $module->lessons)->values();
        $completedAssessmentIds = $this->completedAssessmentIds(
            $userId,
            $orderedLessons->pluck('assessment_id')->filter(),
        );
        $unlockMap = collect();

        foreach ($orderedLessons as $index => $lesson) {
            if ($index === 0) {
                $unlockMap->put($lesson->id, [
                    'is_unlocked' => true,
                    'reason' => null,
                ]);

                continue;
            }

            $previousLesson = $orderedLessons[$index - 1];
            $previousProgress = $lessonProgressMap->get($previousLesson->id);
            $unlockMap->put(
                $lesson->id,
                $this->lessonAdvanceGate($previousLesson, $previousProgress, $completedAssessmentIds),
            );
        }

        return $unlockMap;
    }

    private function completedAssessmentIds(?int $userId, Collection $assessmentIds): Collection
    {
        if (! $userId || $assessmentIds->isEmpty()) {
            return collect();
        }

        return AssessmentAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('assessment_id', $assessmentIds)
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->pluck('assessment_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
    }

    private function lessonAdvanceGate(
        Lesson $lesson,
        ?LessonProgress $lessonProgress,
        Collection $completedAssessmentIds,
    ): array {
        $watchProgress = (float) ($lessonProgress?->watch_progress ?? 0);

        if ($lesson->lesson_video_id !== null && $watchProgress < 95) {
            return [
                'is_unlocked' => false,
                'reason' => 'Complete the lesson video to at least 95% before continuing.',
            ];
        }

        if (
            $lesson->assessment_id !== null
            && $lesson->assessment?->status === 'live'
            && $lesson->assessment?->is_active
            && ! $completedAssessmentIds->contains((int) $lesson->assessment_id)
        ) {
            return [
                'is_unlocked' => false,
                'reason' => 'Complete the lesson assessment before continuing.',
            ];
        }

        return [
            'is_unlocked' => true,
            'reason' => null,
        ];
    }

    private function isLessonFullyComplete(
        Lesson $lesson,
        ?LessonProgress $lessonProgress,
        Collection $completedAssessmentIds,
    ): bool {
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
}
