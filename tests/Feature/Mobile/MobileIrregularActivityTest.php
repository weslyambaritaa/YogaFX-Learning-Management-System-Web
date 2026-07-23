<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\EmailTemplate;
use App\Models\Lesson;
use App\Models\LessonIrregularActivity;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\StudentIrregularActivityService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileIrregularActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_tester_accounts_are_not_counted_for_irregular_activity(): void
    {
        [$student, $lesson] = $this->createStudentWithLesson();

        $admin = User::factory()->admin()->create();
        $tester = User::factory()->create([
            'role' => 'tester',
            'access_tier_id' => $student->access_tier_id,
            'is_active' => true,
        ]);

        $service = app(StudentIrregularActivityService::class);

        $adminResult = $service->recordLessonExitAttempt($admin, $lesson, 20, 10, 100, false);
        $testerResult = $service->recordLessonExitAttempt($tester, $lesson, 20, 10, 100, false);

        $this->assertFalse($adminResult['blocked']);
        $this->assertSame(0, $adminResult['violation_count']);
        $this->assertFalse($testerResult['blocked']);
        $this->assertSame(0, $testerResult['violation_count']);

        $this->assertDatabaseMissing('lesson_irregular_activities', [
            'user_id' => $admin->id,
            'lesson_id' => $lesson->id,
        ]);

        $this->assertDatabaseMissing('lesson_irregular_activities', [
            'user_id' => $tester->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_irregular_activity_tracks_first_second_and_third_violations(): void
    {
        Mail::fake();
        [$student, $lesson] = $this->createStudentWithLesson();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::IRREGULAR_ACTIVITY_SUSPENDED,
            'notification_name' => 'Irregular Activity Suspended',
            'is_enabled' => true,
            'subject_user' => 'Blocked',
            'body_user' => '<p>Dear Student,</p><p>Please be advised that we have detected irregular activity on the platform and for security purposes, the account is temporarily blocked.</p><p>Please contact us some more support for more information and assistance.</p><p>Thank you,<br>YogaFX IT Support</p>',
        ]);

        Sanctum::actingAs($student);

        $payload = [
            'watch_progress' => 20,
            'watch_time_seconds' => 10,
            'video_duration_seconds' => 100,
            'lesson_completed' => false,
        ];

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/irregular-activity", $payload)
            ->assertOk()
            ->assertJsonPath('blocked', false)
            ->assertJsonPath('violation_count', 1)
            ->assertJsonPath('lesson_id', $lesson->id)
            ->assertJsonPath('user_id', $student->id);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/irregular-activity", $payload)
            ->assertOk()
            ->assertJsonPath('blocked', false)
            ->assertJsonPath('violation_count', 2);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/irregular-activity", $payload)
            ->assertForbidden()
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('violation_count', 3)
            ->assertJsonPath('lesson_id', $lesson->id)
            ->assertJsonPath('user_id', $student->id);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
            'irregular_activity_count' => 3,
        ]);

        $this->assertDatabaseHas('lesson_irregular_activities', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'violation_count' => 3,
        ]);

        Mail::assertSent(\App\Mail\TemplatedNotificationMail::class, 2);
    }

    public function test_irregular_activity_resets_after_lesson_complete(): void
    {
        [$student, $lesson] = $this->createStudentWithLesson();

        LessonIrregularActivity::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'violation_count' => 2,
            'last_violation_at' => now()->subHour(),
        ]);

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'watch_time_seconds' => 120,
            'is_done' => true,
            'completed_at' => now(),
            'video_completed_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/irregular-activity", [
            'watch_progress' => 100,
            'watch_time_seconds' => 120,
            'video_duration_seconds' => 100,
            'lesson_completed' => true,
        ])->assertOk()
            ->assertJsonPath('blocked', false)
            ->assertJsonPath('violation_count', 0);

        $this->assertDatabaseHas('lesson_irregular_activities', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'violation_count' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'irregular_activity_count' => 0,
        ]);
    }

    /**
     * @return array{0: User, 1: Lesson}
     */
    private function createStudentWithLesson(): array
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
            'level' => 2,
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        return [$student, $lesson];
    }

}
