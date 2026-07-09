<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use App\Support\PublicUrl;

class StudentIrregularActivityService
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
        private readonly SupportSettingService $supportSettingService,
    ) {}

    /**
     * @return array{
     *     is_irregular: bool,
     *     total_watch_time_seconds: int,
     *     required_watch_time_seconds: int,
     *     irregular_activity_count: int,
     *     was_suspended: bool
     * }
     */
    public function evaluateCompletionAttempt(
        User $user,
        Lesson $lesson,
        LessonProgress $lessonProgress,
        ?int $videoDurationSeconds,
    ): array {
        $requiredWatchTimeSeconds = max(0, (int) $videoDurationSeconds);
        $totalWatchTimeSeconds = max(0, (int) ($lessonProgress->watch_time_seconds ?? 0));
        $isIrregular = $lesson->lesson_video_id !== null
            && $requiredWatchTimeSeconds > 0
            && $totalWatchTimeSeconds < $requiredWatchTimeSeconds;

        if (! $isIrregular) {
            if ($user->irregular_activity_count !== 0) {
                $user->forceFill([
                    'irregular_activity_count' => 0,
                ])->save();
            }

            return [
                'is_irregular' => false,
                'total_watch_time_seconds' => $totalWatchTimeSeconds,
                'required_watch_time_seconds' => $requiredWatchTimeSeconds,
                'irregular_activity_count' => (int) ($user->irregular_activity_count ?? 0),
                'was_suspended' => false,
            ];
        }

        $nextCount = (int) ($user->irregular_activity_count ?? 0) + 1;
        $wasSuspended = false;

        $user->forceFill([
            'irregular_activity_count' => $nextCount,
            'irregular_activity_last_detected_at' => now(),
        ]);

        if ($nextCount >= 3) {
            $user->setStudentAccountStatus(User::ACCOUNT_STATUS_SUSPENDED);
            $wasSuspended = true;
        }

        $user->save();

        if ($wasSuspended) {
            $support = $this->supportSettingService->publicPayload();

            $this->emailNotificationService->sendAutomated(
                EmailNotificationTypeRegistry::IRREGULAR_ACTIVITY_SUSPENDED,
                [
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'lesson_title' => $lesson->title,
                    'irregular_activity_count' => (string) $nextCount,
                    'support_whatsapp' => $support['whatsapp'] ?? '',
                    'support_whatsapp_url' => $support['whatsapp_url'] ?? '',
                    'support_email' => $support['email'] ?? '',
                    'support_email_url' => $support['email_url'] ?? '',
                    'login_url' => PublicUrl::studentLogin(),
                ],
                'user',
                $user->id,
            );
        }

        return [
            'is_irregular' => true,
            'total_watch_time_seconds' => $totalWatchTimeSeconds,
            'required_watch_time_seconds' => $requiredWatchTimeSeconds,
            'irregular_activity_count' => $nextCount,
            'was_suspended' => $wasSuspended,
        ];
    }
}
