<?php

namespace App\Listeners;

use App\Events\EmailNotifications\UserSignedUp;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSignupEmailNotification implements ShouldQueue
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(UserSignedUp $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::SIGNUP,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
