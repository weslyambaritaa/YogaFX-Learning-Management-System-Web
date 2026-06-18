<?php

namespace App\Services\Mobile\V1;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentAttempt;
use App\Models\CertificateDownloadEvent;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\StudentModuleVisit;
use App\Models\User;
use App\Services\BunnyStreamService;
use App\Services\Certificates\CertificateEligibilityService;
use Illuminate\Support\Collection;

class StudentModuleApiService
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
        private readonly CertificateEligibilityService $certificateEligibilityService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function moduleItemsForUser(User $user): Collection
    {
        $accessTierId = $user->access_tier_id;

        if (! $accessTierId) {
            return collect();
        }

        $modules = $this->accessibleModulesWithLessons($accessTierId);
        $resourceModuleVisitMap = $this->resourceModuleVisitMap($user->id, $modules->pluck('id'));
        $lessonProgressMap = $this->lessonProgressMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->assignments->pluck('id')),
        );
        $certificateDownloadMap = $this->certificateDownloadMap($user->id, $modules->pluck('id'));
        $moduleAccessMap = $this->moduleAccessMap(
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
            $certificateDownloadMap,
            $resourceModuleVisitMap,
        );
        $activeLessonId = $this->latestProgressLessonId($lessonProgressMap);

        return $modules->map(function (Module $module) use ($moduleAccessMap, $lessonProgressMap, $completedAssessmentIds, $activeLessonId) {
            $moduleAccess = $moduleAccessMap->get($module->id, [
                'is_visible' => false,
                'status' => 'locked',
                'is_complete' => false,
            ]);
            $totalLessons = $module->lessons->count();
            $completedLessons = $module->lessons->filter(
                fn (Lesson $lesson) => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                )
            )->count();
            $isActive = $module->lessons->contains(fn (Lesson $lesson) => $lesson->id === $activeLessonId);
            $status = $moduleAccess['status'] === 'available' && $isActive
                ? 'active'
                : $moduleAccess['status'];

            return [
                'id' => $module->id,
                'title' => $module->title,
                'slug' => $module->url_slug,
                'description' => $moduleAccess['description'] ?? $module->description,
                'sort_order' => $module->sort_order,
                'lesson_count' => $totalLessons,
                'assignments_count' => $module->assignments->count(),
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $totalLessons > 0
                    ? (int) round(($completedLessons / $totalLessons) * 100)
                    : (($moduleAccess['is_complete'] ?? false) ? 100 : 0),
                'show_progress' => $totalLessons > 0,
                'status' => $status,
                'is_visible' => (bool) ($moduleAccess['is_visible'] ?? false),
                'is_complete' => (bool) ($moduleAccess['is_complete'] ?? false),
                'certificate_enabled' => (bool) $module->certificate_enabled,
                'ebook_enabled' => (bool) $module->ebook_enabled,
                'video_lecturer_enabled' => (bool) $module->video_lecturer_enabled,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $moduleItems
     * @return array<string, mixed>
     */
    public function progressSummaryForModuleItems(Collection $moduleItems): array
    {
        $visibleModules = $moduleItems->where('is_visible', true)->values();
        $lessonBearingModules = $visibleModules->where('lesson_count', '>', 0)->values();
        $modulesTotal = $lessonBearingModules->count();
        $modulesCompleted = $lessonBearingModules->where('status', 'completed')->count();
        $lessonsTotal = (int) $lessonBearingModules->sum('lesson_count');
        $lessonsCompleted = (int) $lessonBearingModules->sum('completed_lessons');

        return [
            'modules_total' => $modulesTotal,
            'modules_completed' => $modulesCompleted,
            'lessons_total' => $lessonsTotal,
            'lessons_completed' => $lessonsCompleted,
            'overall_progress_percentage' => $lessonsTotal > 0
                ? (int) round(($lessonsCompleted / $lessonsTotal) * 100)
                : 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function continueLearningForUser(User $user): ?array
    {
        $accessTierId = $user->access_tier_id;

        if (! $accessTierId) {
            return null;
        }

        $modules = $this->accessibleModulesWithLessons($accessTierId);
        $latestProgress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('lesson', function ($query) use ($accessTierId) {
                $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->whereHas('module', fn ($moduleQuery) => $moduleQuery->whereHas('accessTiers', fn ($tierQuery) => $tierQuery->where('access_tiers.id', $accessTierId)));
            })
            ->with(['lesson.module'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($latestProgress?->lesson && $latestProgress->lesson->module) {
            $lesson = $latestProgress->lesson;
            $module = $lesson->module;
            $watchProgress = (int) round((float) $latestProgress->watch_progress);

            return [
                'state' => $latestProgress->is_done ? 'resume_completed' : 'resume',
                'progress_percentage' => $latestProgress->is_done ? 100 : max(0, min(100, $watchProgress)),
                'lesson' => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'sort_order' => $lesson->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'lesson',
                        $lesson->id,
                        'thumbnail',
                        $lesson->thumbnail,
                        versionSeed: $lesson->updated_at,
                    ),
                ],
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'slug' => $module->url_slug,
                ],
            ];
        }

        $startingModule = $modules->first(fn (Module $module) => $module->lessons->isNotEmpty());
        $startingLesson = $startingModule?->lessons->first();

        if ($startingModule && $startingLesson) {
            return [
                'state' => 'start',
                'progress_percentage' => 0,
                'lesson' => [
                    'id' => $startingLesson->id,
                    'title' => $startingLesson->title,
                    'sort_order' => $startingLesson->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'lesson',
                        $startingLesson->id,
                        'thumbnail',
                        $startingLesson->thumbnail,
                        versionSeed: $startingLesson->updated_at,
                    ),
                ],
                'module' => [
                    'id' => $startingModule->id,
                    'title' => $startingModule->title,
                    'slug' => $startingModule->url_slug,
                ],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function moduleDetailForUser(User $user, int $moduleId): ?array
    {
        $accessTierId = $user->access_tier_id;

        if (! $accessTierId) {
            return null;
        }

        $modules = $this->accessibleModulesWithLessons($accessTierId);
        /** @var Module|null $currentModule */
        $currentModule = $modules->firstWhere('id', $moduleId);

        if (! $currentModule) {
            return null;
        }

        $resourceModuleVisitMap = $this->resourceModuleVisitMap($user->id, $modules->pluck('id'));
        $lessonProgressMap = $this->lessonProgressMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->assignments->pluck('id')),
        );
        $certificateDownloadMap = $this->certificateDownloadMap($user->id, $modules->pluck('id'));
        $moduleAccessMap = $this->moduleAccessMap(
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
            $certificateDownloadMap,
            $resourceModuleVisitMap,
        );
        $currentModuleAccess = $moduleAccessMap->get($currentModule->id);

        if (! ($currentModuleAccess['is_visible'] ?? false)) {
            return [
                'id' => $currentModule->id,
                'title' => $currentModule->title,
                'slug' => $currentModule->url_slug,
                'status' => 'locked',
                'is_visible' => false,
                'lock_reason' => 'Complete the previous module requirements before opening this module.',
            ];
        }

        $lessonUnlockMap = $this->lessonUnlockMap($modules, $lessonProgressMap, $completedAssessmentIds);
        $activeLessonId = $this->latestProgressLessonId($lessonProgressMap);
        $lessons = $currentModule->lessons;
        $completedLessons = $lessons->filter(
            fn (Lesson $lesson) => $this->isLessonFullyComplete(
                $lesson,
                $lessonProgressMap->get($lesson->id),
                $completedAssessmentIds,
            )
        )->count();

        return [
            'id' => $currentModule->id,
            'title' => $currentModule->title,
            'slug' => $currentModule->url_slug,
            'description' => $currentModule->description,
            'sort_order' => $currentModule->sort_order,
            'lesson_count' => $lessons->count(),
            'assignments_count' => $currentModule->assignments->count(),
            'completed_lessons' => $completedLessons,
            'progress_percentage' => $lessons->count() > 0
                ? (int) round(($completedLessons / $lessons->count()) * 100)
                : (($currentModuleAccess['is_complete'] ?? false) ? 100 : 0),
            'show_progress' => $lessons->count() > 0,
            'status' => $currentModuleAccess['status'] ?? 'available',
            'is_visible' => true,
            'is_complete' => (bool) ($currentModuleAccess['is_complete'] ?? false),
            'certificate_enabled' => (bool) $currentModule->certificate_enabled,
            'ebook_enabled' => (bool) $currentModule->ebook_enabled,
            'video_lecturer_enabled' => (bool) $currentModule->video_lecturer_enabled,
            'thumbnail_url' => $this->protectedMediaUrl(
                'module',
                $currentModule->id,
                'thumbnail',
                $currentModule->thumbnail,
                versionSeed: $currentModule->updated_at,
            ),
            'lessons' => $lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'sort_order' => $lesson->sort_order,
                'has_workbook' => $lesson->workbook !== null,
                'has_video' => $lesson->lesson_video_id !== null,
                'has_audio' => $lesson->audio_url !== null,
                'has_content' => $lesson->content !== null,
                'is_locked' => ! ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false),
                'lock_reason' => $lessonUnlockMap->get($lesson->id)['reason'] ?? null,
                'status' => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                )
                    ? 'completed'
                    : (! ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false)
                        ? 'locked'
                        : ($lesson->id === $activeLessonId ? 'active' : 'available')),
                'progress_percentage' => (int) round((float) (optional($lessonProgressMap->get($lesson->id))->watch_progress ?? 0)),
                'thumbnail_url' => $this->lessonThumbnailUrl($lesson, $currentModule),
            ])->values()->all(),
            'assignments' => $currentModule->assignments
                ->map(function (Assignment $assignment) use ($assignmentSubmissionMap) {
                    $submission = $assignmentSubmissionMap->get($assignment->id);

                    return [
                        'id' => $assignment->id,
                        'title' => $assignment->title,
                        'description' => $assignment->description,
                        'sort_order' => $assignment->sort_order,
                        'status' => $assignment->status,
                        'submission_status' => $submission?->assignment_status,
                        'submission_feedback' => $submission?->assignment_feedback,
                        'submitted_at' => $submission?->submitted_at?->toDateTimeString(),
                    ];
                })
                ->values()
                ->all(),
            'certificate_summary' => $currentModule->certificate_enabled
                ? [
                    'generated_items' => $this->certificateEligibilityService
                        ->latestCertificatesByType($user, $this->certificateEligibilityService->summaryForStudent($user)['available_types'])
                        ->sortByDesc(fn ($certificate) => sprintf(
                            '%010d-%010d',
                            $certificate->generated_at?->getTimestamp() ?? 0,
                            $certificate->id,
                        ))
                        ->values()
                        ->map(fn ($certificate) => [
                            'id' => $certificate->id,
                            'type_label' => $certificate->typeLabel(),
                            'generated_at' => optional($certificate->generated_at)->toDateTimeString(),
                        ])
                        ->all(),
                ]
                : null,
        ];
    }

    private function accessibleModulesWithLessons(?int $accessTierId): Collection
    {
        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select(['id', 'module_id', 'title', 'sort_order', 'assessment_id', 'lesson_video_id', 'workbook', 'audio_url', 'content', 'thumbnail'])
                    ->with(['assessment:id,status,is_active'])
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
                'assignments' => fn ($query) => $query
                    ->where('status', Assignment::STATUS_LIVE)
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    private function moduleAccessMap(
        Collection $modules,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
        Collection $assignmentSubmissionMap,
        Collection $certificateDownloadMap,
        Collection $resourceModuleVisitMap,
    ): Collection {
        $accessMap = collect();
        $allPreviousModulesComplete = true;

        foreach ($modules as $module) {
            $isComplete = $this->isModuleFullyComplete(
                $module,
                $lessonProgressMap,
                $completedAssessmentIds,
                $assignmentSubmissionMap,
                $certificateDownloadMap,
                $resourceModuleVisitMap,
            );

            $accessMap->put($module->id, [
                'is_visible' => $allPreviousModulesComplete,
                'status' => $isComplete
                    ? 'completed'
                    : ($allPreviousModulesComplete ? 'available' : 'locked'),
                'description' => $module->description,
                'is_complete' => $isComplete,
            ]);

            $allPreviousModulesComplete = $allPreviousModulesComplete && $isComplete;
        }

        return $accessMap;
    }

    private function isModuleFullyComplete(
        Module $module,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
        Collection $assignmentSubmissionMap,
        Collection $certificateDownloadMap,
        Collection $resourceModuleVisitMap,
    ): bool {
        $liveAssignments = $module->assignments->where('status', Assignment::STATUS_LIVE);

        if ($module->lessons->isNotEmpty()) {
            return $module->lessons->every(
                fn (Lesson $lesson) => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                ),
            );
        }

        if ($liveAssignments->isNotEmpty()) {
            return $liveAssignments
                ->every(fn (Assignment $assignment) => $this->isAssignmentComplete(
                    $assignmentSubmissionMap->get($assignment->id),
                ));
        }

        if ($this->isCertificateDownloadModule($module)) {
            return $certificateDownloadMap->has($module->id);
        }

        if ($this->isOpenOnceResourceModule($module)) {
            return $resourceModuleVisitMap->has($module->id);
        }

        return false;
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

    private function isAssignmentComplete(?AssignmentSubmission $submission): bool
    {
        return $submission !== null;
    }

    private function assignmentSubmissionMap(?int $userId, iterable $assignmentIds): Collection
    {
        $assignmentIds = collect($assignmentIds)->filter()->values();

        if (! $userId || $assignmentIds->isEmpty()) {
            return collect();
        }

        return AssignmentSubmission::query()
            ->where('user_id', $userId)
            ->whereIn('assignment_id', $assignmentIds)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('assignment_id')
            ->keyBy('assignment_id');
    }

    private function lessonProgressMap(?int $userId, iterable $lessonIds): Collection
    {
        $lessonIds = collect($lessonIds)->filter()->values();

        if (! $userId || $lessonIds->isEmpty()) {
            return collect();
        }

        return LessonProgress::query()
            ->where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');
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

    private function resourceModuleVisitMap(?int $userId, iterable $moduleIds): Collection
    {
        $moduleIds = collect($moduleIds)->filter()->values();

        if (! $userId || $moduleIds->isEmpty()) {
            return collect();
        }

        return StudentModuleVisit::query()
            ->where('user_id', $userId)
            ->whereIn('module_id', $moduleIds)
            ->pluck('module_id')
            ->map(fn ($moduleId) => (int) $moduleId)
            ->flip();
    }

    private function certificateDownloadMap(?int $userId, iterable $moduleIds): Collection
    {
        $moduleIds = collect($moduleIds)->filter()->values();

        if (! $userId || $moduleIds->isEmpty()) {
            return collect();
        }

        return CertificateDownloadEvent::query()
            ->where('user_id', $userId)
            ->whereIn('module_id', $moduleIds)
            ->pluck('module_id')
            ->map(fn ($moduleId) => (int) $moduleId)
            ->flip();
    }

    private function isCertificateDownloadModule(Module $module): bool
    {
        return $module->lessons->isEmpty()
            && $module->assignments->where('status', Assignment::STATUS_LIVE)->isEmpty()
            && (bool) $module->certificate_enabled;
    }

    private function isOpenOnceResourceModule(Module $module): bool
    {
        return $module->lessons->isEmpty()
            && $module->assignments->where('status', Assignment::STATUS_LIVE)->isEmpty()
            && ! $this->isCertificateDownloadModule($module)
            && ((bool) $module->ebook_enabled || (bool) $module->video_lecturer_enabled);
    }

    private function latestProgressLessonId(Collection $lessonProgressMap): ?int
    {
        if ($lessonProgressMap->isEmpty()) {
            return null;
        }

        return $lessonProgressMap
            ->sortByDesc(fn (LessonProgress $progress) => sprintf(
                '%010d-%010d',
                $progress->updated_at?->getTimestamp() ?? 0,
                $progress->id,
            ))
            ->keys()
            ->first();
    }

    private function lessonUnlockMap(Collection $modules, Collection $lessonProgressMap, Collection $completedAssessmentIds): Collection
    {
        $orderedLessons = $modules->flatMap(fn (Module $module) => $module->lessons)->values();
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
            $gate = $this->lessonAdvanceGate($previousLesson, $previousProgress, $completedAssessmentIds);

            $unlockMap->put($lesson->id, $gate);
        }

        return $unlockMap;
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

    private function lessonThumbnailUrl(Lesson $lesson, Module $module): ?string
    {
        return $this->protectedMediaUrl(
            'lesson',
            $lesson->id,
            'thumbnail',
            $lesson->thumbnail,
            versionSeed: $lesson->updated_at,
        ) ?: $this->bunnyStreamService->thumbnailUrl($lesson->lesson_video_id)
            ?: $this->protectedMediaUrl(
                'module',
                $module->id,
                'thumbnail',
                $module->thumbnail,
                versionSeed: $module->updated_at,
            );
    }
}
