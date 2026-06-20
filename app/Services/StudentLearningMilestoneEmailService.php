<?php

namespace App\Services;

use App\Events\EmailNotifications\CourseCompleted;
use App\Events\EmailNotifications\ModuleCompleted;
use App\Models\AssessmentAttempt;
use App\Models\EmailLog;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentLearningMilestoneEmailService
{
    public function syncLessonMilestones(User $user, Lesson $lesson): void
    {
        if (! $user->isStudent() || $user->access_tier_id === null || ! $lesson->module_id) {
            return;
        }

        $accessibleModules = $this->accessibleModulesForUser($user);

        if ($accessibleModules->isEmpty()) {
            return;
        }

        $module = $accessibleModules->firstWhere('id', $lesson->module_id);

        if (! $module) {
            return;
        }

        $completedAssessmentIds = $this->completedAssessmentIdsForLessons(
            $user,
            $accessibleModules->flatMap(fn (Module $item) => $item->lessons->pluck('assessment_id'))->filter(),
        );
        $lessonProgressMap = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $accessibleModules->flatMap(fn (Module $item) => $item->lessons->pluck('id')))
            ->get()
            ->keyBy('lesson_id');

        $moduleCompletedAt = $this->completionMomentForModule($module, $lessonProgressMap, $completedAssessmentIds);

        if (
            $moduleCompletedAt !== null
            && ! $this->hasSentNotification(
                EmailNotificationTypeRegistry::MODULE_COMPLETION,
                'module',
                $module->id,
                $user->email,
                $moduleCompletedAt,
            )
        ) {
            event(new ModuleCompleted([
                'user_name' => $user->name,
                'user_email' => $user->email,
                'module_title' => $module->title,
                'completion_date' => now()->format('Y-m-d H:i'),
                'module_progress' => '100%',
                'course_progress' => $this->courseProgressPercentage($accessibleModules, $lessonProgressMap, $completedAssessmentIds).'%',
                'study_time' => $this->studyTimeLabel($user),
                'dashboard_url' => route('student.dashboard'),
                'login_url' => route('login'),
            ], 'module', $module->id));
        }

        $courseCompletedAt = $this->completionMomentForAccessibleModules(
            $accessibleModules,
            $lessonProgressMap,
            $completedAssessmentIds,
        );

        if (
            $courseCompletedAt !== null
            && ! $this->hasSentNotification(
                EmailNotificationTypeRegistry::COURSE_COMPLETE,
                'learning_path',
                $user->id,
                $user->email,
                $courseCompletedAt,
            )
        ) {
            $accessTierName = $user->accessTier?->name ?? 'YogaFX Learning Path';

            event(new CourseCompleted([
                'user_name' => $user->name,
                'user_email' => $user->email,
                'course_title' => $accessTierName.' Learning Path',
                'completion_date' => now()->format('Y-m-d H:i'),
                'course_progress' => '100%',
                'dashboard_url' => route('student.dashboard'),
                'login_url' => route('login'),
            ], 'learning_path', $user->id));
        }
    }

    /**
     * @return Collection<int, Module>
     */
    private function accessibleModulesForUser(User $user): Collection
    {
        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select(['id', 'module_id', 'title', 'assessment_id', 'lesson_video_id'])
                    ->with(['assessment:id,status,is_active'])
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $user->access_tier_id))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();
    }

    /**
     * @param  Collection<int, int>  $assessmentIds
     * @return Collection<int, int>
     */
    private function completedAssessmentIdsForLessons(User $user, Collection $assessmentIds): Collection
    {
        if ($assessmentIds->isEmpty()) {
            return collect();
        }

        return AssessmentAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('assessment_id', $assessmentIds)
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->pluck('assessment_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, LessonProgress>  $lessonProgressMap
     * @param  Collection<int, int>  $completedAssessmentIds
     */
    private function isModuleComplete(
        Module $module,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
    ): bool {
        return $module->lessons->isNotEmpty() && $module->lessons->every(
            fn (Lesson $item) => $this->isLessonFullyComplete(
                $item,
                $lessonProgressMap->get($item->id),
                $completedAssessmentIds,
            ),
        );
    }

    /**
     * @param  Collection<int, Module>  $accessibleModules
     * @param  Collection<int, LessonProgress>  $lessonProgressMap
     * @param  Collection<int, int>  $completedAssessmentIds
     */
    private function courseProgressPercentage(
        Collection $accessibleModules,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
    ): int {
        $lessons = $accessibleModules->flatMap(fn (Module $module) => $module->lessons)->values();

        if ($lessons->isEmpty()) {
            return 0;
        }

        $completedLessons = $lessons->filter(
            fn (Lesson $item) => $this->isLessonFullyComplete(
                $item,
                $lessonProgressMap->get($item->id),
                $completedAssessmentIds,
            ),
        )->count();

        return (int) round(($completedLessons / $lessons->count()) * 100);
    }

    /**
     * @param  Collection<int, int>  $completedAssessmentIds
     */
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

    private function hasSentNotification(
        string $notificationType,
        string $referenceType,
        int $referenceId,
        ?string $recipientEmail,
        ?Carbon $completionMoment = null,
    ): bool {
        if (! filled($recipientEmail)) {
            return false;
        }

        $query = EmailLog::query()
            ->where('notification_type', $notificationType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('recipient_email', $recipientEmail)
            ->where('status', 'sent');

        if ($completionMoment !== null) {
            $query->where('sent_at', '>=', $completionMoment);
        }

        return $query->exists();
    }

    private function completionMomentForModule(
        Module $module,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
    ): ?Carbon {
        if (! $this->isModuleComplete($module, $lessonProgressMap, $completedAssessmentIds)) {
            return null;
        }

        return $module->lessons
            ->map(fn (Lesson $item) => $this->completionMomentForLesson($lessonProgressMap->get($item->id)))
            ->filter()
            ->max();
    }

    private function completionMomentForAccessibleModules(
        Collection $accessibleModules,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
    ): ?Carbon {
        if ($accessibleModules->isEmpty()) {
            return null;
        }

        if (! $accessibleModules->every(
            fn (Module $item) => $this->isModuleComplete($item, $lessonProgressMap, $completedAssessmentIds),
        )) {
            return null;
        }

        return $accessibleModules
            ->flatMap(fn (Module $module) => $module->lessons)
            ->map(fn (Lesson $item) => $this->completionMomentForLesson($lessonProgressMap->get($item->id)))
            ->filter()
            ->max();
    }

    private function completionMomentForLesson(?LessonProgress $lessonProgress): ?Carbon
    {
        if (! $lessonProgress || ! $lessonProgress->is_done) {
            return null;
        }

        return $lessonProgress->completed_at
            ?? $lessonProgress->video_completed_at
            ?? $lessonProgress->updated_at;
    }

    private function studyTimeLabel(User $user): string
    {
        $seconds = max((int) ($user->total_access_duration_seconds ?? 0), 0);

        if ($seconds < 3600) {
            return max((int) round($seconds / 60), 1).' minutes';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $minutes > 0
            ? sprintf('%d hours %d minutes', $hours, $minutes)
            : sprintf('%d hours', $hours);
    }
}
