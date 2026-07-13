<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonIrregularActivity;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Facades\DB;

class StudentIrregularActivityService
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * @return array{
     *     blocked: bool,
     *     violation_count: int,
     *     lesson_id: int,
     *     user_id: int,
     *     message: string,
     * }
     */
    public function recordLessonExitAttempt(
        User $user,
        Lesson $lesson,
        float $watchProgress,
        int $watchTimeSeconds,
        int $videoDurationSeconds,
        bool $lessonCompleted,
    ): array {
        if (! $user->isStudent() || in_array($user->role, ['admin', 'tester'], true)) {
            return $this->ignoredResponse($user, $lesson);
        }

        if ($user->isStudentSuspended()) {
            return [
                'blocked' => true,
                'violation_count' => (int) ($user->irregular_activity_count ?? 0),
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
                'message' => 'Your student account is temporarily blocked.',
            ];
        }

        $isValidProgress = $lessonCompleted
            || $watchProgress >= 95
            || ($lesson->lesson_video_id === null)
            || ($videoDurationSeconds > 0 && $watchTimeSeconds >= $videoDurationSeconds);

        return DB::transaction(function () use ($user, $lesson, $isValidProgress, $watchProgress, $watchTimeSeconds, $videoDurationSeconds): array {
            $activity = LessonIrregularActivity::query()->firstOrNew([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ]);

            if ($isValidProgress) {
                $activity->forceFill([
                    'violation_count' => 0,
                    'last_violation_at' => null,
                    'blocked_at' => null,
                    'blocked_reason' => null,
                    'reset_at' => now(),
                ])->save();

                if ((int) ($user->irregular_activity_count ?? 0) !== 0) {
                    $user->forceFill([
                        'irregular_activity_count' => 0,
                        'irregular_activity_last_detected_at' => null,
                    ])->save();
                }

                return [
                    'blocked' => false,
                    'violation_count' => 0,
                    'lesson_id' => $lesson->id,
                    'user_id' => $user->id,
                    'message' => 'Lesson exit event recorded successfully.',
                ];
            }

            $violationCount = ((int) $activity->violation_count) + 1;
            $blocked = $violationCount >= 3;

            $activity->forceFill([
                'violation_count' => $violationCount,
                'last_violation_at' => now(),
                'blocked_at' => $blocked ? now() : null,
                'blocked_reason' => $blocked ? 'irregular_activity_detected' : null,
            ])->save();

            $user->forceFill([
                'irregular_activity_count' => $violationCount,
                'irregular_activity_last_detected_at' => now(),
            ]);

            if ($blocked) {
                $user->setStudentAccountStatus(User::ACCOUNT_STATUS_SUSPENDED);
            }

            $user->save();

            if ($blocked) {
                $this->emailNotificationService->sendAutomated(
                    EmailNotificationTypeRegistry::IRREGULAR_ACTIVITY_SUSPENDED,
                    [
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'lesson_title' => $lesson->title,
                        'irregular_activity_count' => (string) $violationCount,
                    ],
                    'user',
                    $user->id,
                );
            }

            return [
                'blocked' => $blocked,
                'violation_count' => $violationCount,
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
                'message' => $blocked
                    ? 'Your student account has been temporarily blocked.'
                    : 'Irregular activity recorded.',
            ];
        });
    }

    /**
     * @return array{
     *     blocked: bool,
     *     violation_count: int,
     *     lesson_id: int,
     *     user_id: int,
     *     message: string
     * }
     */
    private function ignoredResponse(User $user, Lesson $lesson): array
    {
        return [
            'blocked' => false,
            'violation_count' => 0,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'message' => 'Irregular activity monitoring is not applied for this account.',
        ];
    }
}
