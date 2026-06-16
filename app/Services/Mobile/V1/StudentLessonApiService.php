<?php

namespace App\Services\Mobile\V1;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\BunnyStreamService;
use App\Services\StudentLearningMilestoneEmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentLessonApiService
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
        private readonly StudentLearningMilestoneEmailService $studentLearningMilestoneEmailService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function lessonDetailForUser(User $user, Lesson $lesson): ?array
    {
        $accessibleModules = $this->accessibleModulesWithLessons($user->access_tier_id);
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

        $isAccessible = $lesson->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists()
            && $lesson->module?->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists();

        if (! $isAccessible) {
            return null;
        }

        if (! ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false)) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'status' => 'locked',
                'is_locked' => true,
                'lock_reason' => $lessonUnlockMap->get($lesson->id)['reason'] ?? 'This lesson is still locked.',
            ];
        }

        $lessonNavigation = optional($accessibleModules->firstWhere('id', $lesson->module_id))->lessons ?? collect();
        $completedLessons = $lessonNavigation->filter(fn (Lesson $item) => $this->isLessonFullyComplete(
            $item,
            $progressMap->get($item->id),
            $completedAssessmentIds,
        ))->count();
        $currentProgress = $progressMap->get($lesson->id);
        $videoState = $this->videoStateForLesson($lesson);
        $currentLessonIndex = $lessonNavigation->search(fn (Lesson $item) => $item->id === $lesson->id);
        $nextLesson = $currentLessonIndex !== false
            ? $lessonNavigation->get($currentLessonIndex + 1)
            : null;

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'content' => $lesson->content,
            'thumbnail_url' => $this->protectedMediaUrl(
                'lesson',
                $lesson->id,
                'thumbnail',
                $lesson->thumbnail,
                versionSeed: $lesson->updated_at,
            ),
            'is_locked' => false,
            'lock_reason' => null,
            'video' => $videoState,
            'audio' => [
                'url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'audio_url',
                    $lesson->audio_url,
                    versionSeed: $lesson->updated_at,
                ),
                'is_available' => filled($lesson->audio_url),
            ],
            'workbook' => [
                'url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'workbook',
                    $lesson->workbook,
                    download: true,
                    versionSeed: $lesson->updated_at,
                ),
                'file_name' => $lesson->workbook ? basename((string) $lesson->workbook) : null,
                'is_available' => filled($lesson->workbook),
            ],
            'progress' => [
                'watch_progress' => (int) round((float) ($currentProgress?->watch_progress ?? 0)),
                'is_workbook_downloaded' => (bool) ($currentProgress?->is_workbook_downloaded ?? false),
                'workbook_downloaded_at' => $currentProgress?->workbook_downloaded_at?->toIso8601String(),
                'is_done' => $this->isLessonFullyComplete(
                    $lesson,
                    $currentProgress,
                    $completedAssessmentIds,
                ),
            ],
            'module' => $lesson->module ? [
                'id' => $lesson->module->id,
                'title' => $lesson->module->title,
                'slug' => $lesson->module->url_slug,
                'lesson_count' => $lessonNavigation->count(),
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $lessonNavigation->count() > 0
                    ? (int) round(($completedLessons / $lessonNavigation->count()) * 100)
                    : 0,
            ] : null,
            'assessment' => $lesson->assessment && $lesson->assessment->status === 'live' && $lesson->assessment->is_active ? [
                'id' => $lesson->assessment->id,
                'title' => $lesson->assessment->title,
                'is_unlocked' => $lesson->lesson_video_id === null
                    || (float) ($currentProgress?->watch_progress ?? 0) >= 95,
                'is_completed' => $completedAssessmentIds->contains((int) $lesson->assessment_id),
                'current_attempt_id' => AssessmentAttempt::query()
                    ->where('assessment_id', $lesson->assessment_id)
                    ->where('user_id', $user->id)
                    ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
                    ->latest('id')
                    ->value('id'),
            ] : null,
            'navigation' => $lessonNavigation->map(fn (Lesson $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'sort_order' => $item->sort_order,
                'thumbnail_url' => $this->lessonThumbnailUrl($item, $lesson->module),
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
            ])->values()->all(),
            'next_lesson' => $nextLesson ? [
                'id' => $nextLesson->id,
                'title' => $nextLesson->title,
                'sort_order' => $nextLesson->sort_order,
                'thumbnail_url' => $this->lessonThumbnailUrl($nextLesson, $lesson->module),
                'is_unlocked' => (bool) ($lessonUnlockMap->get($nextLesson->id)['is_unlocked'] ?? false),
                'lock_reason' => $lessonUnlockMap->get($nextLesson->id)['reason'] ?? null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateProgressForUser(User $user, Lesson $lesson, float $incomingProgress): ?array
    {
        $detail = $this->lessonDetailForUser($user, $lesson);

        if (! $detail || ($detail['is_locked'] ?? false)) {
            return $detail;
        }

        $incomingProgress = round($incomingProgress, 2);
        $existingProgress = (float) LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->value('watch_progress');

        $watchProgress = max($existingProgress, $incomingProgress);
        $hasCompletedAssessment = $lesson->assessment_id !== null
            && $lesson->assessment?->status === 'live'
            && $lesson->assessment?->is_active
            && AssessmentAttempt::query()
                ->where('assessment_id', $lesson->assessment_id)
                ->where('user_id', $user->id)
                ->where('status', AssessmentAttempt::STATUS_COMPLETED)
                ->exists();
        $isDone = $watchProgress >= 95 && (! $lesson->assessment_id || $hasCompletedAssessment || ! $lesson->assessment?->is_active || $lesson->assessment?->status !== 'live');

        $lessonProgress = LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'watch_progress' => $watchProgress,
                'video_completed_at' => $isDone ? now() : null,
                'is_done' => $isDone,
                'completed_at' => $isDone ? now() : null,
            ],
        );

        if ($isDone) {
            $this->studentLearningMilestoneEmailService->syncLessonMilestones($user, $lesson);
        }

        return [
            'watch_progress' => (int) round((float) $lessonProgress->watch_progress),
            'is_done' => (bool) $lessonProgress->is_done,
            'assessment_unlocked' => $lesson->lesson_video_id === null || $watchProgress >= 95,
        ];
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
                'warning_message' => 'This lesson video is not using a valid Bunny Stream video ID yet.',
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
                'warning_message' => 'Bunny Stream CDN is not configured yet in the current environment.',
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
                'warning_message' => 'This lesson video ID was not found in the configured Bunny Stream library.',
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

    private function lessonThumbnailUrl(Lesson $lesson, ?Module $module = null): ?string
    {
        return $this->protectedMediaUrl(
            'lesson',
            $lesson->id,
            'thumbnail',
            $lesson->thumbnail,
            versionSeed: $lesson->updated_at,
        ) ?: $this->bunnyStreamService->thumbnailUrl($lesson->lesson_video_id)
            ?: ($module
                ? $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                )
                : null);
    }

    private function accessibleModulesWithLessons(?int $accessTierId): Collection
    {
        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select(['id', 'module_id', 'title', 'sort_order', 'assessment_id', 'lesson_video_id', 'thumbnail', 'workbook', 'audio_url', 'content'])
                    ->with(['assessment:id,title,status,is_active'])
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
            $unlockMap->put($lesson->id, $this->lessonAdvanceGate(
                $previousLesson,
                $previousProgress,
                $completedAssessmentIds,
            ));
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
