<?php

namespace App\Providers;

use App\Events\EmailNotifications\UserSignedUp;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if ((bool) config('app.force_https')) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        // Bridge Laravel's built-in Registered event into the app-specific
        // signup notification event. The downstream email listeners are
        // discovered automatically by Laravel and should not be registered
        // manually here, otherwise automated notifications fire twice.
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
    }
}
