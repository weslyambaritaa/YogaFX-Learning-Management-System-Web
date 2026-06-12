<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ModuleCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        $modules = $this->accessibleModulesWithLessons($accessTierId);

        $lessonProgressMap = $this->lessonProgressMap(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $lessonUnlockMap = $this->lessonUnlockMap($user?->id, $modules, $lessonProgressMap);
        $activeLessonId = $this->latestProgressLessonId($user?->id, $lessonProgressMap);

        return Inertia::render('Student/Modules/Index', [
            'modules' => $modules->map(function (Module $module) use ($activeLessonId, $lessonProgressMap, $lessonUnlockMap, $completedAssessmentIds) {
                $totalLessons = $module->lessons->count();
                $totalAssignments = $module->assignments->count();
                $completedLessons = $module->lessons->filter(function (Lesson $lesson) use ($lessonProgressMap, $completedAssessmentIds) {
                    return $this->isLessonFullyComplete(
                        $lesson,
                        $lessonProgressMap->get($lesson->id),
                        $completedAssessmentIds,
                    );
                })->count();
                $isActive = $module->lessons->contains(fn (Lesson $lesson) => $lesson->id === $activeLessonId);
                $hasUnlockedLesson = $module->lessons->contains(
                    fn (Lesson $lesson) => (bool) ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false),
                );
                $hasAccessibleContent = $hasUnlockedLesson || $totalAssignments > 0;
                $status = $totalLessons > 0 && $completedLessons === $totalLessons
                    ? 'completed'
                    : (! $hasAccessibleContent ? 'locked' : ($isActive ? 'active' : 'available'));

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'url_slug' => $module->url_slug,
                    'url' => $hasAccessibleContent ? route('modules.show', $module->url_slug) : null,
                    'sort_order' => $module->sort_order,
                    'lesson_count' => $totalLessons,
                    'assignments_count' => $totalAssignments,
                    'completed_lessons' => $completedLessons,
                    'progress_percentage' => $totalLessons > 0
                        ? (int) round(($completedLessons / $totalLessons) * 100)
                        : 0,
                    'status' => $status,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'module',
                        $module->id,
                        'thumbnail',
                        $module->thumbnail,
                        versionSeed: $module->updated_at,
                    ),
                ];
            }),
        ]);
    }

    public function show(Request $request, Module $module): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;
        abort_unless(
            $user
            && $module->accessTiers()->where('access_tiers.id', $accessTierId)->exists(),
            403,
        );

        $modules = $this->accessibleModulesWithLessons($accessTierId);
        $currentModule = $modules->firstWhere('id', $module->id);
        abort_unless($currentModule, 404);
        $lessons = $currentModule->lessons;

        $lessonProgressMap = $this->lessonProgressMap(
            $user?->id,
            $modules->flatMap(fn (Module $item) => $item->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user?->id,
            $modules->flatMap(fn (Module $item) => $item->lessons->pluck('assessment_id'))->filter(),
        );
        $lessonUnlockMap = $this->lessonUnlockMap($user?->id, $modules, $lessonProgressMap);
        $activeLessonId = $this->latestProgressLessonId($user?->id, $lessonProgressMap);
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->assignments->pluck('id')),
        );
        $completedLessons = $lessons->filter(fn (Lesson $lesson) => $this->isLessonFullyComplete(
            $lesson,
            $lessonProgressMap->get($lesson->id),
            $completedAssessmentIds,
        ))->count();

        return Inertia::render('Student/Modules/Show', [
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'url_slug' => $module->url_slug,
                'sort_order' => $module->sort_order,
                'lesson_count' => $lessons->count(),
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $lessons->count() > 0
                    ? (int) round(($completedLessons / $lessons->count()) * 100)
                    : 0,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
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
                        'thumbnail_url' => $this->protectedMediaUrl(
                            'lesson',
                            $lesson->id,
                            'thumbnail',
                            $lesson->thumbnail,
                            versionSeed: $lesson->updated_at,
                        ) ?: $this->protectedMediaUrl(
                            'module',
                            $module->id,
                            'thumbnail',
                            $module->thumbnail,
                            versionSeed: $module->updated_at,
                        ),
                        'url' => ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false)
                            ? route('lessons.show', $lesson)
                            : null,
                    ]),
                'assignments' => $currentModule->assignments
                    ->map(function ($assignment) use ($assignmentSubmissionMap) {
                        $submission = $assignmentSubmissionMap->get($assignment->id);

                        return [
                            'id' => $assignment->id,
                            'title' => $assignment->title,
                            'description' => $assignment->description,
                            'sort_order' => $assignment->sort_order,
                            'status' => $assignment->status,
                            'is_required' => $assignment->is_required,
                            'submission_status' => $submission?->assignment_status,
                            'submission_feedback' => $submission?->assignment_feedback,
                            'submitted_at' => $submission?->submitted_at?->format('Y-m-d H:i'),
                            'url' => route('assignments.show', $assignment),
                        ];
                    })
                    ->values(),
            ],
        ]);
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
                    ->where('status', \App\Models\Assignment::STATUS_LIVE)
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
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
            $gate = $this->lessonAdvanceGate($previousLesson, $previousProgress, $completedAssessmentIds);

            $unlockMap->put($lesson->id, $gate);
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

    protected function lessonProgressMap(?int $userId, iterable $lessonIds): Collection
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

    protected function latestProgressLessonId(?int $userId, Collection $lessonProgressMap): ?int
    {
        if (! $userId || $lessonProgressMap->isEmpty()) {
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
