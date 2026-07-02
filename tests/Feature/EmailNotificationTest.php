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
use App\Models\EmailBranding;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\OnboardingState;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserSession;
use App\Services\PaymentFinalizerService;
use App\Services\StudentLearningMilestoneEmailService;
use App\Support\PublicUrl;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
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
            EmailNotificationTypeRegistry::WORKBOOK_SENT => 'Workbook Sent',
            EmailNotificationTypeRegistry::PAYMENT_SUCCESS => 'Payment Success',
            EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS => 'Enrollment Success',
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS => 'Installment Payment Success',
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_FAILED => 'Installment Payment Failed',
            EmailNotificationTypeRegistry::INSTALLMENT_OVERDUE_INACTIVE => 'Installment Overdue Inactive',
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_COMPLETED => 'Installment Payment Completed',
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

    public function test_admin_can_view_and_save_global_email_branding(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.email-branding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/EmailNotifications/Branding'));

        $this->actingAs($admin)->post(route('admin.email-branding.update'), [
            '_method' => 'patch',
            'logo' => UploadedFile::fake()->image('branding-logo.png'),
            'header_html' => '<p>Global Header</p>',
            'footer_html' => '<p>Global Footer</p>',
        ])->assertRedirect(route('admin.email-branding.show'));

        $branding = EmailBranding::query()->firstOrFail();

        $this->assertSame(EmailBranding::GLOBAL_KEY, $branding->singleton_key);
        $this->assertSame('<p>Global Header</p>', $branding->header_html);
        $this->assertSame('<p>Global Footer</p>', $branding->footer_html);
        Storage::disk('local')->assertExists((string) $branding->logo_path);
    }

    public function test_signup_notification_ui_exposes_only_current_merge_tags_and_new_defaults(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(
            route('admin.email-notifications.show', ['notificationType' => EmailNotificationTypeRegistry::SIGNUP]),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailNotifications/Show')
            ->where('notificationType', EmailNotificationTypeRegistry::SIGNUP)
            ->where('availableMergeTags', [
                '{{ user_name }}',
                '{{ user_email }}',
                '{{ admin_email }}',
                '{{ access_tier }}',
                '{{ access_tier_label }}',
                '{{ registration_date }}',
                '{{ dashboard_url }}',
                '{{ login_url }}',
            ])
            ->where('template.subject_admin', 'YogaFX signup completed: {user_email}')
            ->where('template.body_admin', fn (string $body) => str_contains($body, 'A new student has completed the YogaFX signup process') && ! str_contains($body, 'continuation_url'))
            ->where('template.body_user', fn (string $body) => str_contains($body, 'Your enrollment and password setup have been completed successfully') && ! str_contains($body, 'continuation_url')));
    }

    public function test_payment_and_enrollment_notifications_expose_defaults_and_merge_tags_in_admin_email_ui(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(
            route('admin.email-notifications.show', ['notificationType' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS]),
        )->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailNotifications/Show')
            ->where('notificationType', EmailNotificationTypeRegistry::PAYMENT_SUCCESS)
            ->where('availableMergeTags', [
                '{{ user_name }}',
                '{{ user_email }}',
                '{{ access_tier }}',
                '{{ access_tier_label }}',
                '{{ invoice_number }}',
                '{{ payment_reference }}',
                '{{ amount }}',
                '{{ currency_code }}',
                '{{ enrollment_url }}',
            ])
            ->where('template.subject_user', 'Payment successful: welcome to YogaFX, {user_name}')
            ->where('template.subject_admin', 'YogaFX payment successful: {user_email}')
            ->where('template.body_user', fn (string $body) => str_contains($body, '{enrollment_url}'))
            ->where('template.body_admin', fn (string $body) => str_contains($body, '{payment_reference}')));

        $this->actingAs($admin)->get(
            route('admin.email-notifications.show', ['notificationType' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS]),
        )->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailNotifications/Show')
            ->where('notificationType', EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS)
            ->where('availableMergeTags', [
                '{{ user_name }}',
                '{{ user_email }}',
                '{{ access_tier }}',
                '{{ access_tier_label }}',
                '{{ signup_url }}',
            ])
            ->where('template.subject_user', 'Enrollment completed: your YogaFX signup is ready')
            ->where('template.subject_admin', 'YogaFX enrollment completed: {user_email}')
            ->where('template.body_user', fn (string $body) => str_contains($body, '{signup_url}'))
            ->where('template.body_admin', fn (string $body) => str_contains($body, '{signup_url}')));

        $this->actingAs($admin)->get(
            route('admin.email-notifications.show', ['notificationType' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS]),
        )->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailNotifications/Show')
            ->where('notificationType', EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS)
            ->where('template.subject_user', 'Your YogaFX installment payment was successful')
            ->where('template.subject_admin', 'Installment payment success for {user_email}'));
    }

    public function test_admin_can_send_test_email_for_payment_and_enrollment_notifications_and_logs_are_recorded(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        foreach ([
            EmailNotificationTypeRegistry::PAYMENT_SUCCESS => [
                'subject_user' => 'Payment success {{ invoice_number }}',
                'body_user' => 'Continue here {{ enrollment_url }}',
                'subject_admin' => 'Payment success {{ user_email }}',
                'body_admin' => '{{ payment_reference }} {{ amount }}',
            ],
            EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS => [
                'subject_user' => 'Enrollment complete {{ access_tier_label }}',
                'body_user' => 'Create password {{ signup_url }}',
                'subject_admin' => 'Enrollment complete {{ user_email }}',
                'body_admin' => 'Signup link {{ signup_url }}',
            ],
        ] as $notificationType => $templateData) {
            EmailTemplate::factory()->create([
                'notification_type' => $notificationType,
                'notification_name' => EmailNotificationTypeRegistry::labelFor($notificationType),
                'is_enabled' => true,
                'admin_recipients' => 'ops@yogafx.test',
                ...$templateData,
            ]);

            $this->actingAs($admin)->post(
                route('admin.email-notifications.send-test', ['notificationType' => $notificationType]),
                [
                    'notification_type' => $notificationType,
                    'send_to' => 'qa@yogafx.test',
                ],
            )->assertRedirect();
        }

        Mail::assertSent(TemplatedNotificationMail::class, 4);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'recipient_type' => 'test_user',
            'recipient_email' => 'qa@yogafx.test',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'recipient_type' => 'test_admin',
            'recipient_email' => 'qa@yogafx.test',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'recipient_type' => 'test_user',
            'recipient_email' => 'qa@yogafx.test',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'recipient_type' => 'test_admin',
            'recipient_email' => 'qa@yogafx.test',
            'status' => 'sent',
        ]);
    }

    public function test_global_email_branding_is_applied_to_multiple_notification_types_and_updates_once(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        EmailBranding::query()->create([
            'singleton_key' => EmailBranding::GLOBAL_KEY,
            'header_html' => '<p>Unified Header A</p>',
            'footer_html' => '<p>Unified Footer A</p>',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'notification_name' => 'Payment Success',
            'is_enabled' => true,
            'admin_recipients' => 'ops@yogafx.test',
            'subject_user' => 'Payment success',
            'body_user' => '<p>Payment Body A</p>',
            'subject_admin' => 'Payment success admin',
            'body_admin' => '<p>Payment Admin Body A</p>',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'notification_name' => 'Enrollment Success',
            'is_enabled' => true,
            'admin_recipients' => 'ops@yogafx.test',
            'subject_user' => 'Enrollment success',
            'body_user' => '<p>Enrollment Body A</p>',
            'subject_admin' => 'Enrollment success admin',
            'body_admin' => '<p>Enrollment Admin Body A</p>',
        ]);

        foreach ([
            EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
        ] as $notificationType) {
            $this->actingAs($admin)->post(
                route('admin.email-notifications.send-test', ['notificationType' => $notificationType]),
                [
                    'notification_type' => $notificationType,
                    'send_to' => 'qa@yogafx.test',
                ],
            )->assertRedirect();
        }

        $paymentLog = \App\Models\EmailLog::query()
            ->where('notification_type', EmailNotificationTypeRegistry::PAYMENT_SUCCESS)
            ->where('recipient_type', 'test_user')
            ->firstOrFail();
        $enrollmentLog = \App\Models\EmailLog::query()
            ->where('notification_type', EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS)
            ->where('recipient_type', 'test_user')
            ->firstOrFail();

        $this->assertStringContainsString('Unified Header A', $paymentLog->body_snapshot);
        $this->assertStringContainsString('Unified Footer A', $paymentLog->body_snapshot);
        $this->assertStringContainsString('Payment Body A', $paymentLog->body_snapshot);
        $this->assertStringContainsString('Unified Header A', $enrollmentLog->body_snapshot);
        $this->assertStringContainsString('Unified Footer A', $enrollmentLog->body_snapshot);
        $this->assertStringContainsString('Enrollment Body A', $enrollmentLog->body_snapshot);

        EmailBranding::query()->update([
            'header_html' => '<p>Unified Header B</p>',
            'footer_html' => '<p>Unified Footer B</p>',
        ]);

        $this->actingAs($admin)->post(
            route('admin.email-notifications.send-test', ['notificationType' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS]),
            [
                'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
                'send_to' => 'qa-second@yogafx.test',
            ],
        )->assertRedirect();

        $updatedLog = \App\Models\EmailLog::query()
            ->where('notification_type', EmailNotificationTypeRegistry::PAYMENT_SUCCESS)
            ->where('recipient_email', 'qa-second@yogafx.test')
            ->where('recipient_type', 'test_user')
            ->firstOrFail();

        $this->assertStringContainsString('Unified Header B', $updatedLog->body_snapshot);
        $this->assertStringContainsString('Unified Footer B', $updatedLog->body_snapshot);
        $this->assertStringNotContainsString('Unified Header A', $updatedLog->body_snapshot);
    }

    public function test_signup_send_test_supports_legacy_continuation_url_templates_without_missing_variable_error(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'notification_name' => 'Signup',
            'is_enabled' => true,
            'admin_recipients' => 'legacy-admin@yogafx.test',
            'subject_user' => 'Welcome {{ user_name }}',
            'body_user' => 'Login here {{ continuation_url }}',
            'subject_admin' => 'Legacy signup {{ user_email }}',
            'body_admin' => 'Student login {{ continuation_url }}',
        ]);

        $this->actingAs($admin)->post(
            route('admin.email-notifications.send-test', ['notificationType' => EmailNotificationTypeRegistry::SIGNUP]),
            [
                'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
                'send_to' => 'legacy-qa@yogafx.test',
            ],
        )->assertRedirect();

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'recipient_email' => 'legacy-qa@yogafx.test',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'status' => 'failed',
            'error_message' => 'The user subject could not be rendered because test data is missing for: continuation_url.',
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

    public function test_assignment_approved_notification_also_sends_to_admin_recipients(): void
    {
        Mail::fake();

        [$admin, $student, $assignment] = $this->createAssignmentContext();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ASSIGNMENT_APPROVED,
            'notification_name' => 'Assignments Approved',
            'is_enabled' => true,
            'admin_recipients' => 'approved-admin@yogafx.test',
            'subject_user' => 'Assignment approved',
            'body_user' => 'Dashboard {{ dashboard_url }}',
            'subject_admin' => 'Assignment approved for {{ user_email }}',
            'body_admin' => '{{ assignment_type }} approved',
        ]);

        $this->actingAs($admin)->patch(
            route('admin.student-progress.assignments.update', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
            [
                'assignment_status' => AssignmentSubmission::STATUS_APPROVED,
                'assignment_feedback' => 'Approved and complete.',
            ],
        )->assertRedirect();

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ASSIGNMENT_APPROVED,
            'reference_type' => 'assignment_submission',
            'reference_id' => $assignment->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'approved-admin@yogafx.test',
            'status' => 'sent',
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

    public function test_signup_notification_is_sent_only_after_signup_completion(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $user = User::factory()->student()->create([
            'name' => 'Onboarding Student',
            'email' => 'onboarding-student@yogafx.test',
            'is_active' => false,
            'access_tier_id' => $tier->id,
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'notification_name' => 'Enrollment Success',
            'is_enabled' => true,
            'admin_recipients' => 'enrollment-admin@yogafx.test',
            'subject_user' => 'Enrollment complete for {{ access_tier_label }}',
            'body_user' => 'Finish here {{ signup_url }}',
            'subject_admin' => 'Enrollment completed {{ user_email }}',
            'body_admin' => 'Next step {{ signup_url }}',
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'notification_name' => 'Signup',
            'is_enabled' => true,
            'admin_recipients' => 'signup-admin@yogafx.test',
            'subject_user' => 'Welcome to YogaFX, {{ user_name }}',
            'body_user' => 'Your LMS account is now ready. Login here {{ login_url }}',
            'subject_admin' => 'YogaFX signup completed: {{ user_email }}',
            'body_admin' => 'Student {{ user_name }} tier {{ access_tier_label }} login {{ login_url }}',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Onboarding',
            'last_name' => 'Student',
            'email' => $user->email,
            'phone' => '+6281234567001',
            'country' => 'Indonesia',
            'amount_snapshot' => 499,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
            'payment_succeeded_at' => now(),
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_ENROLLMENT,
        ]);

        $this->post(
            URL::temporarySignedRoute('onboarding.enrollment.store', now()->addMinutes(5), [
                'onboardingState' => $onboardingState->id,
            ]),
            [
                'first_name' => 'Onboarding',
                'last_name' => 'Student',
                'email' => $user->email,
                'whatsapp_country_code' => '+62',
                'whatsapp_number' => '81234567001',
                'profile_photo' => UploadedFile::fake()->image('onboarding-student.jpg'),
                'instagram' => '@onboardingstudent',
                'country' => 'Indonesia',
                'birth_date' => '1995-05-10',
                'gender' => 'female',
                'practicing_yoga_for' => '0_to_3_years',
                'yoga_sequence_experience' => ['vinyasa'],
                'hours_per_week' => '4_7',
                'current_fitness_level' => 'average',
                'flexibility_rating' => 'good',
                'motivation' => 'Complete onboarding.',
                'why_yogafx' => 'Guided learning path.',
                'how_did_you_find_us' => ['instagram'],
                'terms_accepted' => true,
                'recaptcha_confirmed' => true,
            ],
        )->assertSessionHasNoErrors();

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'reference_type' => 'onboarding_state',
            'reference_id' => $onboardingState->id,
            'recipient_type' => 'user',
            'recipient_email' => $user->email,
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
        ]);

        Mail::fake();

        $this->post(
            URL::temporarySignedRoute('onboarding.signup.store', now()->addMinutes(5), [
                'onboardingState' => $onboardingState->id,
            ]),
            [
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ],
        )->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user);

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            return str_contains($mail->render(), PublicUrl::studentLogin());
        });
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'reference_type' => 'user',
            'reference_id' => $user->id,
            'recipient_type' => 'user',
            'recipient_email' => $user->email,
            'status' => 'sent',
        ]);
    }

    public function test_payment_success_notification_is_sent_once_after_initial_payment_is_finalized(): void
    {
        Mail::fake();

        [$pendingRegistration, , $paymentActivity] = $this->createInitialPaymentFixture();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'notification_name' => 'Payment Success',
            'is_enabled' => true,
            'admin_recipients' => 'payments-admin@yogafx.test',
            'subject_user' => 'Payment success {{ invoice_number }}',
            'body_user' => 'Continue {{ enrollment_url }}',
            'subject_admin' => 'Payment success {{ user_email }}',
            'body_admin' => '{{ payment_reference }} {{ amount }}',
        ]);

        $result = app(PaymentFinalizerService::class)->finalizeSuccessfulPayment($paymentActivity, 'PAYPAL-ORDER-001');

        $this->assertFalse($result['skipped']);
        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'reference_type' => 'payment_activity',
            'reference_id' => $paymentActivity->id,
            'recipient_type' => 'user',
            'recipient_email' => $pendingRegistration->email,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'reference_type' => 'payment_activity',
            'reference_id' => $paymentActivity->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'payments-admin@yogafx.test',
            'status' => 'sent',
        ]);
    }

    public function test_payment_success_notification_is_not_double_sent_when_finalizer_runs_again(): void
    {
        Mail::fake();

        [, , $paymentActivity] = $this->createInitialPaymentFixture();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'notification_name' => 'Payment Success',
            'is_enabled' => true,
            'admin_recipients' => 'payments-admin@yogafx.test',
            'subject_user' => 'Payment success {{ invoice_number }}',
            'body_user' => 'Continue {{ enrollment_url }}',
            'subject_admin' => 'Payment success {{ user_email }}',
            'body_admin' => '{{ payment_reference }} {{ amount }}',
        ]);

        $service = app(PaymentFinalizerService::class);

        $first = $service->finalizeSuccessfulPayment($paymentActivity, 'PAYPAL-ORDER-001');
        $second = $service->finalizeSuccessfulPayment($paymentActivity->fresh(), 'PAYPAL-ORDER-001');

        $this->assertFalse($first['skipped']);
        $this->assertTrue($second['skipped']);
        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertSame(2, \App\Models\EmailLog::query()
            ->where('notification_type', EmailNotificationTypeRegistry::PAYMENT_SUCCESS)
            ->count());
    }

    public function test_disabled_payment_success_template_prevents_email_delivery_and_logs(): void
    {
        Mail::fake();

        [$pendingRegistration, , $paymentActivity] = $this->createInitialPaymentFixture();

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'notification_name' => 'Payment Success',
            'is_enabled' => false,
            'admin_recipients' => 'payments-admin@yogafx.test',
            'subject_user' => 'Payment success {{ invoice_number }}',
            'body_user' => 'Continue {{ enrollment_url }}',
            'subject_admin' => 'Payment success {{ user_email }}',
            'body_admin' => '{{ payment_reference }} {{ amount }}',
        ]);

        app(PaymentFinalizerService::class)->finalizeSuccessfulPayment($paymentActivity, 'PAYPAL-ORDER-001');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'reference_type' => 'payment_activity',
            'reference_id' => $paymentActivity->id,
        ]);
        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);
    }

    public function test_disabled_enrollment_success_template_prevents_email_delivery_and_logs(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $user = User::factory()->student()->create([
            'name' => 'Disabled Enrollment Student',
            'email' => 'disabled-enrollment-student@yogafx.test',
            'is_active' => false,
            'access_tier_id' => $tier->id,
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'notification_name' => 'Enrollment Success',
            'is_enabled' => false,
            'admin_recipients' => 'enrollment-admin@yogafx.test',
            'subject_user' => 'Enrollment complete {{ access_tier_label }}',
            'body_user' => 'Finish here {{ signup_url }}',
            'subject_admin' => 'Enrollment completed {{ user_email }}',
            'body_admin' => 'Next step {{ signup_url }}',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Disabled',
            'last_name' => 'Enrollment',
            'email' => $user->email,
            'phone' => '+6281234567999',
            'country' => 'Indonesia',
            'amount_snapshot' => 499,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
            'payment_succeeded_at' => now(),
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_ENROLLMENT,
        ]);

        $this->post(
            URL::temporarySignedRoute('onboarding.enrollment.store', now()->addMinutes(5), [
                'onboardingState' => $onboardingState->id,
            ]),
            [
                'first_name' => 'Disabled',
                'last_name' => 'Enrollment',
                'email' => $user->email,
                'whatsapp_country_code' => '+62',
                'whatsapp_number' => '81234567999',
                'profile_photo' => UploadedFile::fake()->image('disabled-enrollment.jpg'),
                'instagram' => '@disabledenrollment',
                'country' => 'Indonesia',
                'birth_date' => '1995-05-10',
                'gender' => 'female',
                'practicing_yoga_for' => '0_to_3_years',
                'yoga_sequence_experience' => ['vinyasa'],
                'hours_per_week' => '4_7',
                'current_fitness_level' => 'average',
                'flexibility_rating' => 'good',
                'motivation' => 'Complete onboarding.',
                'why_yogafx' => 'Guided learning path.',
                'how_did_you_find_us' => ['instagram'],
                'terms_accepted' => true,
                'recaptcha_confirmed' => true,
            ],
        )->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'reference_type' => 'onboarding_state',
            'reference_id' => $onboardingState->id,
        ]);
        $this->assertDatabaseHas('onboarding_states', [
            'id' => $onboardingState->id,
            'status' => OnboardingState::STATUS_AWAITING_SIGNUP,
        ]);
    }

    public function test_payment_success_page_is_rendered_with_enrollment_cta(): void
    {
        [$pendingRegistration, , $paymentActivity] = $this->createInitialPaymentFixture();

        app(PaymentFinalizerService::class)->finalizeSuccessfulPayment($paymentActivity, 'PAYPAL-ORDER-001');

        $onboardingState = OnboardingState::query()
            ->where('pending_registration_id', $pendingRegistration->id)
            ->firstOrFail();

        $response = $this->get(
            URL::temporarySignedRoute('onboarding.payment-success.show', now()->addMinutes(5), [
                'onboardingState' => $onboardingState->id,
            ]),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/PaymentSuccess')
            ->where('student.email', $pendingRegistration->email)
            ->where('onboarding.access_tier.name', $pendingRegistration->accessTier->name)
            ->where('onboarding.continue_url', fn (string $url) => str_contains($url, '/onboarding/'.$onboardingState->id.'/enrollment')));
    }

    public function test_signup_completion_does_not_double_send_signup_notification_on_repeat_submit(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $user = User::factory()->student()->create([
            'name' => 'Repeat Student',
            'email' => 'repeat-student@yogafx.test',
            'is_active' => false,
            'access_tier_id' => $tier->id,
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'notification_name' => 'Signup',
            'is_enabled' => true,
            'admin_recipients' => 'signup-admin@yogafx.test',
            'subject_user' => 'Welcome {{ user_name }}',
            'body_user' => 'Dashboard {{ dashboard_url }}',
            'subject_admin' => 'Signup {{ user_email }}',
            'body_admin' => 'Tier {{ access_tier_label }}',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Repeat',
            'last_name' => 'Student',
            'email' => $user->email,
            'phone' => '+6281234567002',
            'country' => 'Indonesia',
            'amount_snapshot' => 499,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
            'payment_succeeded_at' => now(),
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_SIGNUP,
            'enrollment_completed_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute('onboarding.signup.store', now()->addMinutes(5), [
            'onboardingState' => $onboardingState->id,
        ]);

        $this->post($signedUrl, [
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post($signedUrl, [
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertStatus(409);

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        $this->assertDatabaseCount('email_logs', 2);
    }

    public function test_signup_completion_does_not_send_email_when_signup_template_is_disabled(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $user = User::factory()->student()->create([
            'name' => 'Disabled Template Student',
            'email' => 'disabled-template-student@yogafx.test',
            'is_active' => false,
            'access_tier_id' => $tier->id,
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::SIGNUP,
            'notification_name' => 'Signup',
            'is_enabled' => false,
            'subject_user' => 'Welcome {{ user_name }}',
            'body_user' => 'Dashboard {{ dashboard_url }}',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Disabled',
            'last_name' => 'Template',
            'email' => $user->email,
            'phone' => '+6281234567003',
            'country' => 'Indonesia',
            'amount_snapshot' => 499,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
            'payment_succeeded_at' => now(),
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_SIGNUP,
            'enrollment_completed_at' => now(),
        ]);

        $this->post(
            URL::temporarySignedRoute('onboarding.signup.store', now()->addMinutes(5), [
                'onboardingState' => $onboardingState->id,
            ]),
            [
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ],
        )->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('email_logs', [
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

    public function test_module_and_course_notifications_can_send_again_after_a_new_completion_cycle(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
            'email' => 'simangunsongmoses696@gmail.com',
            'name' => 'Moses Simangunsong',
        ]);

        $firstModule = Module::factory()->create([
            'title' => 'Premier Online Introduction to Yoga',
        ]);
        $secondModule = Module::factory()->create([
            'title' => 'asdfsdf',
        ]);
        $firstModule->accessTiers()->sync([$tier->id]);
        $secondModule->accessTiers()->sync([$tier->id]);

        $firstLesson = Lesson::factory()->create([
            'module_id' => $firstModule->id,
        ]);
        $secondLesson = Lesson::factory()->create([
            'module_id' => $secondModule->id,
        ]);
        $firstLesson->accessTiers()->sync([$tier->id]);
        $secondLesson->accessTiers()->sync([$tier->id]);

        $firstCompletedAt = now()->subMinutes(2);
        $secondCompletedAt = now()->subMinute();

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $firstLesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => $firstCompletedAt,
            'video_completed_at' => $firstCompletedAt,
        ]);
        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $secondLesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => $secondCompletedAt,
            'video_completed_at' => $secondCompletedAt,
        ]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
            'notification_name' => 'Module Completion',
            'is_enabled' => true,
            'admin_recipients' => 'ops@yogafx.test',
            'subject_user' => 'Module completed {{ module_title }}',
            'body_user' => '{{ course_progress }}',
            'subject_admin' => 'Admin module {{ module_title }}',
            'body_admin' => '{{ user_email }}',
        ]);
        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::COURSE_COMPLETE,
            'notification_name' => 'Course Complete',
            'is_enabled' => true,
            'admin_recipients' => 'course@yogafx.test',
            'subject_user' => 'Course completed {{ course_title }}',
            'body_user' => '{{ course_progress }}',
            'subject_admin' => 'Admin course {{ course_title }}',
            'body_admin' => '{{ user_email }}',
        ]);

        $templateModule = EmailTemplate::query()->where('notification_type', EmailNotificationTypeRegistry::MODULE_COMPLETION)->firstOrFail();
        $templateCourse = EmailTemplate::query()->where('notification_type', EmailNotificationTypeRegistry::COURSE_COMPLETE)->firstOrFail();

        \App\Models\EmailLog::factory()->create([
            'email_template_id' => $templateModule->id,
            'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
            'reference_type' => 'module',
            'reference_id' => $secondModule->id,
            'recipient_type' => 'user',
            'recipient_email' => $student->email,
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);
        \App\Models\EmailLog::factory()->create([
            'email_template_id' => $templateCourse->id,
            'notification_type' => EmailNotificationTypeRegistry::COURSE_COMPLETE,
            'reference_type' => 'learning_path',
            'reference_id' => $student->id,
            'recipient_type' => 'user',
            'recipient_email' => $student->email,
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);

        app(StudentLearningMilestoneEmailService::class)->syncLessonMilestones($student, $secondLesson);

        Mail::assertSent(TemplatedNotificationMail::class, 4);
        $this->assertDatabaseCount('email_logs', 6);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::MODULE_COMPLETION,
            'reference_type' => 'module',
            'reference_id' => $secondModule->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'ops@yogafx.test',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::COURSE_COMPLETE,
            'reference_type' => 'learning_path',
            'reference_id' => $student->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'course@yogafx.test',
            'status' => 'sent',
        ]);
    }

    public function test_reminder_command_sends_notification_for_idle_or_logged_out_students_after_ten_minutes(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);

        $idleStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
            'email' => 'idle@yogafx.test',
            'name' => 'Idle Student',
            'created_at' => now()->subHours(3),
        ]);

        $loggedOutStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
            'email' => 'logged-out@yogafx.test',
            'name' => 'Logged Out Student',
            'created_at' => now()->subHours(3),
        ]);

        $recentlyActiveStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
            'email' => 'active@yogafx.test',
            'name' => 'Recently Active Student',
            'created_at' => now()->subHours(3),
        ]);

        $completedStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
            'email' => 'completed@yogafx.test',
            'name' => 'Completed Student',
            'created_at' => now()->subHours(3),
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        LessonProgress::factory()->create([
            'user_id' => $completedStudent->id,
            'lesson_id' => $lesson->id,
            'is_done' => true,
            'completed_at' => now()->subHours(2),
        ]);

        UserSession::query()->create([
            'user_id' => $idleStudent->id,
            'session_id' => 'idle-session',
            'login_at' => now()->subMinutes(20),
            'last_activity_at' => now()->subMinutes(20),
            'session_duration_seconds' => 120,
            'is_active' => true,
        ]);

        UserSession::query()->create([
            'user_id' => $loggedOutStudent->id,
            'session_id' => 'logged-out-session',
            'login_at' => now()->subMinutes(20),
            'last_activity_at' => now()->subMinutes(20),
            'logout_at' => now()->subMinutes(20),
            'session_duration_seconds' => 120,
            'is_active' => false,
        ]);

        UserSession::query()->create([
            'user_id' => $recentlyActiveStudent->id,
            'session_id' => 'active-session',
            'login_at' => now()->subMinutes(20),
            'last_activity_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        UserSession::query()->create([
            'user_id' => $completedStudent->id,
            'session_id' => 'completed-session',
            'login_at' => now()->subMinutes(20),
            'last_activity_at' => now()->subMinutes(20),
            'logout_at' => now()->subMinutes(20),
            'session_duration_seconds' => 180,
            'is_active' => false,
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

        Mail::assertSent(TemplatedNotificationMail::class, 4);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'reference_type' => 'user',
            'reference_id' => $idleStudent->id,
            'recipient_type' => 'user',
            'recipient_email' => 'idle@yogafx.test',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'reference_type' => 'user',
            'reference_id' => $loggedOutStudent->id,
            'recipient_type' => 'user',
            'recipient_email' => 'logged-out@yogafx.test',
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'reference_id' => $recentlyActiveStudent->id,
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::REMINDER,
            'reference_id' => $completedStudent->id,
        ]);

        Mail::fake();

        $this->artisan('email-notifications:send-reminders')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_workbook_trigger_sends_one_email_with_attachment_and_marks_progress_once(): void
    {
        Storage::fake('local');
        Mail::fake();

        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
            'email' => 'student-workbook@yogafx.test',
            'name' => 'Workbook Student',
        ]);
        $module = Module::factory()->create([
            'title' => 'Workbook Module',
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $workbookPath = 'lessons/workbooks/lesson-workbook.pdf';
        Storage::disk('local')->put($workbookPath, 'sample workbook pdf content');

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Workbook Lesson',
            'workbook' => $workbookPath,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        EmailTemplate::factory()->create([
            'notification_type' => EmailNotificationTypeRegistry::WORKBOOK_SENT,
            'notification_name' => 'Workbook Sent',
            'is_enabled' => true,
            'admin_recipients' => 'ops-workbook@yogafx.test',
            'subject_user' => 'Workbook for {{ lesson_title }}',
            'body_user' => 'Workbook file {{ workbook_file_name }}',
            'subject_admin' => 'Workbook sent to {{ user_email }}',
            'body_admin' => '{{ lesson_title }} workbook triggered',
        ]);

        $this->actingAs($student)
            ->postJson(route('lessons.workbook.trigger', $lesson))
            ->assertOk()
            ->assertJson([
                'was_first_trigger' => true,
                'is_workbook_downloaded' => true,
            ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_workbook_downloaded' => true,
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, 2);
        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($student): bool {
            if ($mail->hasTo($student->email)) {
                return count($mail->attachmentPayloads) === 1
                    && $mail->attachmentPayloads[0]['name'] === 'lesson-workbook.pdf';
            }

            return true;
        });

        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::WORKBOOK_SENT,
            'reference_type' => 'lesson_workbook',
            'reference_id' => $lesson->id,
            'recipient_type' => 'user',
            'recipient_email' => $student->email,
            'status' => 'sent',
        ]);

        Mail::fake();

        $this->actingAs($student)
            ->postJson(route('lessons.workbook.trigger', $lesson))
            ->assertOk()
            ->assertJson([
                'was_first_trigger' => false,
                'is_workbook_downloaded' => true,
            ]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_logs', 2);
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
        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_done' => true,
            'completed_at' => now()->subHour(),
        ]);

        $assignment = AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'assignment_type' => 'graduation_video',
        ]);

        return [$admin, $student, $assignment];
    }

    /**
     * @return array{0: PendingRegistration, 1: Invoice, 2: Payment}
     */
    private function createInitialPaymentFixture(): array
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);

        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Online Premium',
            'slug' => 'online-premium',
            'price' => 499,
            'currency_code' => 'USD',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'package_id' => $package->id,
            'first_name' => 'Payment',
            'last_name' => 'Student',
            'email' => 'payment-student@yogafx.test',
            'phone' => '+6281234500001',
            'country' => 'Indonesia',
            'amount_snapshot' => 499,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-PAYMENT-SUCCESS-001',
            'pending_registration_id' => $pendingRegistration->id,
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'total_amount' => 499,
            'balance_due' => 499,
            'currency_code' => 'USD',
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
        ]);

        $paymentActivity = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'amount_paid' => 499,
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'payment_reference' => 'PAYPAL-ORDER-001',
            'notes' => 'Awaiting capture.',
        ]);

        return [
            $pendingRegistration->fresh(['accessTier']),
            $invoice->fresh(),
            $paymentActivity->fresh(),
        ];
    }
}
