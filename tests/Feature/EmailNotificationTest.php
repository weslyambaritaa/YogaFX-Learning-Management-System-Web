<?php

namespace Tests\Feature;

use App\Events\EmailNotifications\AssessmentCompleted;
use App\Events\EmailNotifications\AssignmentReviewRequested;
use App\Events\EmailNotifications\CourseCompleted;
use App\Events\EmailNotifications\ModuleCompleted;
use App\Mail\TemplatedNotificationMail;
use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\EmailTemplate;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_new_email_notification_types(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            EmailNotificationTypeRegistry::RESET_PASSWORD => 'Reset Password',
            EmailNotificationTypeRegistry::ASSESSMENT_COMPLETE => 'Assessment Complete',
            EmailNotificationTypeRegistry::COURSE_COMPLETE => 'Course Complete',
            EmailNotificationTypeRegistry::REMINDER => 'Reminder',
        ] as $notificationType => $label) {
            $this->actingAs($admin)->get(
                route('admin.email-notifications.show', ['notificationType' => $notificationType]),
            )->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Admin/EmailNotifications/Show')
                ->where('notificationType', $notificationType)
                ->where('notificationLabel', $label));
        }
    }

    public function test_admin_can_view_and_save_email_notification_template(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(
            route('admin.email-notifications.show', ['notificationType' => EmailNotificationTypeRegistry::MODULE_COMPLETION]),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailNotifications/Show')
            ->where('notificationType', EmailNotificationTypeRegistry::MODULE_COMPLETION)
            ->where('notificationLabel', 'Module Completion'));

        $this->actingAs($admin)->patch(
            route('admin.email-notifications.update', ['notificationType' => EmailNotificationTypeRegistry::MODULE_COMPLETION]),
            [
                'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
                'is_enabled' => true,
                'admin_recipients' => "ops@yogafx.test\nadmin@yogafx.test",
                'subject_admin' => 'Admin module completion {{ module_title }}',
                'body_admin' => 'Completed by {{ user_name }}',
                'subject_user' => 'Congrats {{ user_name }}',
                'body_user' => 'You finished {{ module_title }}',
            ],
        )->assertRedirect();

        $this->assertDatabaseHas('email_templates', [
            'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
            'is_enabled' => true,
            'subject_user' => 'Congrats {{ user_name }}',
        ]);
    }

    public function test_admin_can_send_test_email_and_log_is_recorded(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'notification_name' => 'Signup',
            'is_enabled' => true,
            'admin_recipients' => 'admin@yogafx.test',
            'subject_user' => 'Welcome {{ user_name }}',
            'body_user' => 'Login at {{ login_url }}',
            'subject_admin' => 'New signup {{ user_email }}',
            'body_admin' => 'Tier {{ access_tier_label }}',
        ]);

        $this->actingAs($admin)->post(
            route('admin.email-notifications.send-test', ['notificationType' => EmailNotificationTypeRegistry::SIGNUP]),
            [
                'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
                'send_to' => 'qa@yogafx.test',
            ],
        )->assertRedirect();

        Mail::assertSent(TemplatedNotificationMail::class, 2);

        $this->assertDatabaseCount('email_logs', 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'recipient_email' => 'qa@yogafx.test',
            'recipient_type' => 'test_user',
            'status' => 'sent',
        ]);
    }

    public function test_prepared_events_can_send_module_completion_and_assignment_review_notifications(): void
    {
        Mail::fake();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
            'notification_name' => 'Module Completion',
            'is_enabled' => true,
            'admin_recipients' => 'ops@yogafx.test',
            'subject_user' => 'Completed {{ module_title }}',
            'body_user' => 'Progress {{ module_progress }}',
            'subject_admin' => 'Student finished {{ module_title }}',
            'body_admin' => 'Course progress {{ course_progress }}',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ASSIGNMENT_REVIEW,
            'notification_name' => 'Assignments Review',
            'is_enabled' => true,
            'admin_recipients' => 'review@yogafx.test',
            'subject_user' => 'Assignment received',
            'body_user' => '{{ assignment_type }} is under review',
            'subject_admin' => 'Review queue {{ user_email }}',
            'body_admin' => '{{ assignment_type }} waiting',
        ]);

        event(new ModuleCompleted([
            'user_name' => 'Sample Student',
            'user_email' => 'student@yogafx.test',
            'module_title' => 'Breathwork Basics',
            'completion_date' => now()->format('Y-m-d H:i'),
            'module_progress' => '100%',
            'course_progress' => '45%',
            'study_time' => '2 hours',
        ], 'module', 11));

        event(new AssignmentReviewRequested([
            'user_name' => 'Sample Student',
            'user_email' => 'student@yogafx.test',
            'assignment_type' => 'Standing & Floor',
            'admin_email' => 'review@yogafx.test',
        ], 'assignment_submission', 99));

        Mail::assertSent(TemplatedNotificationMail::class, 4);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
            'reference_type' => 'module',
            'reference_id' => 11,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ASSIGNMENT_REVIEW,
            'reference_type' => 'assignment_submission',
            'reference_id' => 99,
        ]);
    }

    public function test_assignment_status_update_and_certificate_generation_trigger_automated_notifications(): void
    {
        Storage::fake('local');
        Mail::fake();

        [$admin, $student, $assignment] = $this->createAssignmentContext();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ASSIGNMENT_REJECTED,
            'notification_name' => 'Assignments Rejected',
            'is_enabled' => true,
            'admin_recipients' => 'review@yogafx.test',
            'subject_user' => 'Assignment rejected',
            'body_user' => '{{ feedback }}',
            'subject_admin' => 'Rejected {{ assignment_type }}',
            'body_admin' => '{{ user_email }}',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::CERTIFICATE_CREATED,
            'notification_name' => 'Certificate Created',
            'is_enabled' => true,
            'admin_recipients' => 'certificate@yogafx.test',
            'subject_user' => 'Certificate ready',
            'body_user' => '{{ certificate_type }}',
            'subject_admin' => 'Certificate generated',
            'body_admin' => '{{ certificate_file_name }}',
        ]);

        $this->actingAs($admin)->patch(
            route('admin.student-progress.assignments.update', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
            [
                'assignment_status' => AssignmentSubmission::STATUS_REJECTED,
                'assignment_feedback' => 'Please re-upload with better lighting.',
            ],
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('admin.student-progress.certificates.store', ['student' => $student]),
            ['certificate_type' => Certificate::TYPE_BIKRAM],
        )->assertRedirect();

        Mail::assertSent(TemplatedNotificationMail::class);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ASSIGNMENT_REJECTED,
            'reference_type' => 'assignment_submission',
            'reference_id' => $assignment->id,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::CERTIFICATE_CREATED,
            'reference_type' => 'certificate',
        ]);
    }

    public function test_registered_event_triggers_signup_notification(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $user = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'notification_name' => 'Signup',
            'is_enabled' => true,
            'admin_recipients' => 'signup@yogafx.test',
            'subject_user' => 'Welcome aboard',
            'body_user' => 'Dashboard {{ dashboard_url }}',
            'subject_admin' => 'Signup {{ user_email }}',
            'body_admin' => 'Tier {{ access_tier_label }}',
        ]);

        event(new Registered($user));

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'reference_type' => 'user',
            'reference_id' => $user->id,
        ]);
    }

    public function test_password_reset_request_can_use_email_notification_template_and_log_delivery(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Reset User',
            'email' => 'reset-user@yogafx.test',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::RESET_PASSWORD,
            'notification_name' => 'Reset Password',
            'is_enabled' => true,
            'admin_recipients' => 'security@yogafx.test',
            'subject_user' => 'Reset your password',
            'body_user' => 'Use this link {{ reset_url }} before {{ reset_expiry_minutes }} minutes.',
            'subject_admin' => 'Password reset requested',
            'body_admin' => '{{ user_email }} requested a password reset.',
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ])->assertSessionHas('status');

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        Notification::assertNothingSent();
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::RESET_PASSWORD,
            'reference_type' => 'user',
            'reference_id' => $user->id,
            'recipient_type' => 'user',
            'recipient_email' => $user->email,
            'status' => 'sent',
        ]);
    }

    public function test_new_email_events_can_send_assessment_and_course_completion_notifications(): void
    {
        Mail::fake();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ASSESSMENT_COMPLETE,
            'notification_name' => 'Assessment Complete',
            'is_enabled' => true,
            'admin_recipients' => 'assessment@yogafx.test',
            'subject_user' => 'Assessment complete',
            'body_user' => '{{ assessment_title }} scored {{ assessment_score }}',
            'subject_admin' => 'Assessment completed',
            'body_admin' => '{{ user_email }} finished {{ assessment_title }}',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::COURSE_COMPLETE,
            'notification_name' => 'Course Complete',
            'is_enabled' => true,
            'admin_recipients' => 'course@yogafx.test',
            'subject_user' => 'Course complete',
            'body_user' => '{{ course_title }} reached {{ course_progress }}',
            'subject_admin' => 'Course completion alert',
            'body_admin' => '{{ user_email }} completed {{ course_title }}',
        ]);

        event(new AssessmentCompleted([
            'user_name' => 'Assessment Student',
            'user_email' => 'assessment-student@yogafx.test',
            'assessment_title' => 'Core Assessment',
            'assessment_score' => '95',
            'completed_at' => now()->format('Y-m-d H:i'),
            'result_url' => route('dashboard'),
        ], 'assessment_attempt', 21));

        event(new CourseCompleted([
            'user_name' => 'Course Student',
            'user_email' => 'course-student@yogafx.test',
            'course_title' => 'YogaFX Core Journey',
            'completion_date' => now()->toDateString(),
            'course_progress' => '100%',
        ], 'course', 7));

        Mail::assertSent(TemplatedNotificationMail::class, 4);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ASSESSMENT_COMPLETE,
            'reference_type' => 'assessment_attempt',
            'reference_id' => 21,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::COURSE_COMPLETE,
            'reference_type' => 'course',
            'reference_id' => 7,
        ]);
    }

    public function test_reminder_command_sends_notification_once_for_inactive_students(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);

        $inactiveStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
            'email' => 'inactive@yogafx.test',
            'name' => 'Inactive Student',
        ]);

        $activeStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
            'email' => 'active@yogafx.test',
            'name' => 'Active Student',
        ]);

        LessonProgress::factory()->create([
            'user_id' => $inactiveStudent->id,
            'updated_at' => now()->subDays(8),
            'completed_at' => now()->subDays(8),
        ]);

        LessonProgress::factory()->create([
            'user_id' => $activeStudent->id,
            'updated_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2),
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'notification_name' => 'Reminder',
            'is_enabled' => true,
            'admin_recipients' => 'engagement@yogafx.test',
            'subject_user' => 'We miss you',
            'body_user' => 'You have been inactive for {{ inactive_days }} days.',
            'subject_admin' => 'Reminder sent',
            'body_admin' => '{{ user_email }} received a reminder.',
        ]);

        $this->artisan('email-notifications:send-reminders')
            ->assertExitCode(0);

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'reference_type' => 'user',
            'reference_id' => $inactiveStudent->id,
            'recipient_type' => 'user',
            'recipient_email' => 'inactive@yogafx.test',
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'reference_id' => $activeStudent->id,
        ]);

        Mail::fake();

        $this->artisan('email-notifications:send-reminders')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: User, 2: AssignmentSubmission}
     */
    private function createAssignmentContext(): array
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $assignment = AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'assignment_type' => 'graduation_video',
        ]);

        return [$admin, $student, $assignment];
    }
}
