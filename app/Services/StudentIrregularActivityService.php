<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonIrregularActivity;
use App\Models\LessonProgress;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use App\Support\Impersonation;
use Illuminate\Support\Facades\DB;

class StudentIrregularActivityService
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * Web lesson flow uses watch progress updates instead of a dedicated
     * "lesson exit" endpoint, so we evaluate the completion attempt here.
     *
     * @return array{
     *     is_irregular: bool,
     *     total_watch_time_seconds: int,
     *     required_watch_time_seconds: int,
     *     irregular_activity_count: int,
     *     was_suspended: bool,
     *     warning_required: bool
     * }
     */
    public function evaluateCompletionAttempt(
        User $user,
        Lesson $lesson,
        LessonProgress $lessonProgress,
        ?int $videoDurationSeconds,
    ): array {
        if (! $user->isStudent() || $user->isTesterStudent() || Impersonation::active()) {
            return [
                'is_irregular' => false,
                'total_watch_time_seconds' => max(0, (int) ($lessonProgress->watch_time_seconds ?? 0)),
                'required_watch_time_seconds' => max(0, (int) $videoDurationSeconds),
                'irregular_activity_count' => (int) ($user->irregular_activity_count ?? 0),
                'was_suspended' => false,
                'warning_required' => false,
            ];
        }

        if ($user->isStudentSuspended()) {
            return [
                'is_irregular' => true,
                'total_watch_time_seconds' => max(0, (int) ($lessonProgress->watch_time_seconds ?? 0)),
                'required_watch_time_seconds' => max(0, (int) $videoDurationSeconds),
                'irregular_activity_count' => (int) ($user->irregular_activity_count ?? 0),
                'was_suspended' => true,
                'warning_required' => false,
            ];
        }

        $requiredWatchTimeSeconds = max(0, (int) $videoDurationSeconds);
        $totalWatchTimeSeconds = max(0, (int) ($lessonProgress->watch_time_seconds ?? 0));
        $isIrregular = $lesson->lesson_video_id !== null
            && $requiredWatchTimeSeconds > 0
            && $totalWatchTimeSeconds < $requiredWatchTimeSeconds;

        if (! $isIrregular) {
            return [
                'is_irregular' => false,
                'total_watch_time_seconds' => $totalWatchTimeSeconds,
                'required_watch_time_seconds' => $requiredWatchTimeSeconds,
                'irregular_activity_count' => (int) ($user->irregular_activity_count ?? 0),
                'was_suspended' => false,
                'warning_required' => false,
            ];
        }

        return DB::transaction(function () use ($user, $lesson, $requiredWatchTimeSeconds, $totalWatchTimeSeconds): array {
            $activity = LessonIrregularActivity::query()->firstOrNew([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ]);

            $lessonViolationCount = (int) ($activity->violation_count ?? 0) + 1;
            $globalViolationCount = (int) ($user->irregular_activity_count ?? 0) + 1;
            $wasSuspended = $globalViolationCount >= 3;

            $activity->forceFill([
                'violation_count' => $lessonViolationCount,
                'last_violation_at' => now(),
                'blocked_at' => $wasSuspended ? now() : null,
                'blocked_reason' => $wasSuspended ? 'irregular_activity_detected' : null,
                'reset_at' => null,
            ])->save();

            $user->forceFill([
                'irregular_activity_count' => $globalViolationCount,
                'irregular_activity_last_detected_at' => now(),
            ]);

            if ($wasSuspended) {
                $user->setStudentAccountStatus(User::ACCOUNT_STATUS_SUSPENDED);
            }

            $user->save();

            if ($wasSuspended) {
                $this->emailNotificationService->sendAutomated(
                    EmailNotificationTypeRegistry::IRREGULAR_ACTIVITY_SUSPENDED,
                    [
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'lesson_title' => $lesson->title,
                        'irregular_activity_count' => (string) $globalViolationCount,
                    ],
                    'user',
                    $user->id,
                );
            }

            return [
                'is_irregular' => true,
                'total_watch_time_seconds' => $totalWatchTimeSeconds,
                'required_watch_time_seconds' => $requiredWatchTimeSeconds,
                'irregular_activity_count' => $globalViolationCount,
                'was_suspended' => $wasSuspended,
                'warning_required' => ! $wasSuspended && $globalViolationCount < 3,
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
    public function recordLessonExitAttempt(
        User $user,
        Lesson $lesson,
        float $watchProgress,
        int $watchTimeSeconds,
        int $videoDurationSeconds,
        bool $lessonCompleted,
    ): array {
        /*
         * Irregular activity monitoring hanya berlaku untuk student biasa.
         *
         * Admin, tester, tester student, dan akun non-student tidak akan
         * mendapatkan violation atau suspension.
         */
        if (
            ! $user->isStudent()
            || in_array($user->role, ['admin', 'tester'], true)
            || $user->isTesterStudent()
            || Impersonation::active()
        ) {
            return $this->ignoredResponse($user, $lesson);
        }

        /*
         * Apabila akun sudah suspended, jangan menambahkan violation baru.
         */
        if ($user->isStudentSuspended()) {
            return [
                'blocked' => true,
                'violation_count' => (int) ($user->irregular_activity_count ?? 0),
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
                'message' => 'Your student account is temporarily blocked.',
            ];
        }

        /*
         * Progress dianggap valid apabila:
         * - lesson sudah completed;
         * - watch progress minimal 95%;
         * - lesson tidak mempunyai video;
         * - atau waktu menonton sudah mencapai durasi video.
         */
        $isValidProgress = $lessonCompleted
            || $watchProgress >= 95
            || $lesson->lesson_video_id === null
            || (
                $videoDurationSeconds > 0
                && $watchTimeSeconds >= $videoDurationSeconds
            );

        return DB::transaction(function () use (
            $user,
            $lesson,
            $isValidProgress
        ): array {
            /*
             * Satu record irregular activity disimpan untuk setiap kombinasi
             * user dan lesson.
             */
            $activity = LessonIrregularActivity::query()->firstOrNew([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ]);

            /*
             * Jika progress valid, reset violation untuk lesson dan user.
             */
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

            /*
             * Progress tidak valid, sehingga violation ditambahkan.
             */
            $violationCount = ((int) ($activity->violation_count ?? 0)) + 1;
            $blocked = $violationCount >= 3;

            $activity->forceFill([
                'violation_count' => $violationCount,
                'last_violation_at' => now(),
                'blocked_at' => $blocked ? now() : null,
                'blocked_reason' => $blocked
                    ? 'irregular_activity_detected'
                    : null,
                'reset_at' => null,
            ])->save();

            $user->forceFill([
                'irregular_activity_count' => $violationCount,
                'irregular_activity_last_detected_at' => now(),
            ]);

            /*
             * Suspend student setelah mencapai tiga violation.
             */
            if ($blocked) {
                $user->setStudentAccountStatus(
                    User::ACCOUNT_STATUS_SUSPENDED
                );
            }

            $user->save();

            /*
             * Email suspension hanya dikirim saat akun baru saja diblokir.
             */
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
