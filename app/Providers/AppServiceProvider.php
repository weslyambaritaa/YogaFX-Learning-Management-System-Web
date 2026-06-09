<?php

namespace App\Providers;

use App\Events\EmailNotifications\UserSignedUp;
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

        Event::listen(Registered::class, function (Registered $event): void {
            $user = $event->user;

            event(new UserSignedUp([
                'user_name' => $user->name,
                'user_email' => $user->email,
                'admin_email' => config('mail.from.address'),
                'access_tier' => $user->accessTier?->slug,
                'access_tier_label' => $user->accessTier?->name,
                'registration_date' => optional($user->created_at)->toDateString() ?? now()->toDateString(),
                'dashboard_url' => route('dashboard'),
                'login_url' => route('login'),
            ], 'user', $user->id));
        });
    }
}
