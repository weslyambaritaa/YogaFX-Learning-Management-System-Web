<?php

namespace App\Services\Mobile\V1;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\StudentModuleVisit;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentModuleApiService
{
    use BuildsProtectedMediaUrls;

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
        $moduleAccessMap = $this->moduleAccessMap(
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
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
        Collection $resourceModuleVisitMap,
    ): bool {
        $liveAssignments = $module->assignments->where('status', Assignment::STATUS_LIVE);

        if ($module->lessons->isEmpty() && $liveAssignments->isEmpty()) {
            return $resourceModuleVisitMap->has($module->id);
        }

        $hasTrackableContent = $module->lessons->isNotEmpty() || $liveAssignments->isNotEmpty();

        if (! $hasTrackableContent) {
            return false;
        }

        $allLessonsComplete = $module->lessons->every(
            fn (Lesson $lesson) => $this->isLessonFullyComplete(
                $lesson,
                $lessonProgressMap->get($lesson->id),
                $completedAssessmentIds,
            ),
        );

        $allAssignmentsComplete = $liveAssignments
            ->every(fn (Assignment $assignment) => $this->isAssignmentComplete(
                $assignmentSubmissionMap->get($assignment->id),
            ));

        return $allLessonsComplete && $allAssignmentsComplete;
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
        return $submission?->assignment_status === AssignmentSubmission::STATUS_APPROVED;
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
}
