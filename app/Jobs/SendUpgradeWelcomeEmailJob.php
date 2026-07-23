<?php

namespace App\Jobs;

use App\Mail\TemplatedNotificationMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendUpgradeWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $tierName,
    ) {}

    public function handle(): void
    {
        /** @var User|null $user */
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $subject = 'Welcome to your upgraded YogaFX program';
        $body = implode('', [
            '<p>Hi '.e($user->name).',</p>',
            '<p>Your payment was successful and your YogaFX access has been upgraded.</p>',
            '<p>Your new tier: <strong>'.e($this->tierName).'</strong></p>',
            '<p>You can continue your learning journey from your dashboard: <a href="'.e(route('student.dashboard')).'">'.e(route('student.dashboard')).'</a></p>',
            '<p>We are excited to have you continue with the next level of your YogaFX experience.</p>',
        ]);

        Mail::to($user->email)->send(
            new TemplatedNotificationMail($subject, $body, 'Upgrade Welcome'),
        );
    }
}
