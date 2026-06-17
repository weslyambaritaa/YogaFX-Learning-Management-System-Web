<?php

namespace App\Providers;

use App\Events\EmailNotifications\AssessmentCompleted;
use App\Events\EmailNotifications\AssignmentApproved;
use App\Events\EmailNotifications\AssignmentRejected;
use App\Events\EmailNotifications\AssignmentReviewRequested;
use App\Events\EmailNotifications\CertificateCreated;
use App\Events\EmailNotifications\CourseCompleted;
use App\Events\EmailNotifications\ModuleCompleted;
use App\Events\EmailNotifications\ReminderTriggered;
use App\Events\EmailNotifications\ResetPasswordRequested;
use App\Events\EmailNotifications\UserSignedUp;
use App\Listeners\SendAssessmentCompletedEmailNotification;
use App\Listeners\SendAssignmentApprovedEmailNotification;
use App\Listeners\SendAssignmentRejectedEmailNotification;
use App\Listeners\SendAssignmentReviewEmailNotification;
use App\Listeners\SendCertificateCreatedEmailNotification;
use App\Listeners\SendCourseCompletedEmailNotification;
use App\Listeners\SendModuleCompletedEmailNotification;
use App\Listeners\SendReminderEmailNotification;
use App\Listeners\SendResetPasswordEmailNotification;
use App\Listeners\SendSignupEmailNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if ($this->app->bound('yogafx.email-notification-events-registered')) {
            return;
        }

        $this->app->instance('yogafx.email-notification-events-registered', true);

        // Register Laravel's built-in Registered event -> UserSignedUp bridge
        Event::listen(Registered::class, function (Registered $event): void {
            $user = $event->user;

            event(new UserSignedUp([
                'user_name' => $user->name,
                'user_email' => $user->email,
                'admin_email' => config('mail.from.address'),
                'access_tier' => $user->accessTier?->slug,
                'access_tier_label' => $user->accessTier?->name,
                'registration_date' => optional($user->created_at)->toDateString() ?? now()->toDateString(),
                'continuation_url' => route('student.dashboard'),
                'dashboard_url' => route('student.dashboard'),
                'login_url' => route('login'),
            ], 'user', $user->id));
        });

        // Register email notification listeners
        Event::listen(UserSignedUp::class, SendSignupEmailNotification::class);
        Event::listen(ResetPasswordRequested::class, SendResetPasswordEmailNotification::class);
        Event::listen(ModuleCompleted::class, SendModuleCompletedEmailNotification::class);
        Event::listen(AssignmentReviewRequested::class, SendAssignmentReviewEmailNotification::class);
        Event::listen(AssignmentApproved::class, SendAssignmentApprovedEmailNotification::class);
        Event::listen(AssignmentRejected::class, SendAssignmentRejectedEmailNotification::class);
        Event::listen(AssessmentCompleted::class, SendAssessmentCompletedEmailNotification::class);
        Event::listen(CertificateCreated::class, SendCertificateCreatedEmailNotification::class);
        Event::listen(CourseCompleted::class, SendCourseCompletedEmailNotification::class);
        Event::listen(ReminderTriggered::class, SendReminderEmailNotification::class);
    }
}
