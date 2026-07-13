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