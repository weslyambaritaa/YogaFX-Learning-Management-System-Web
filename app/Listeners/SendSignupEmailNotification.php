<?php

namespace App\Listeners;

use App\Events\EmailNotifications\UserSignedUp;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendSignupEmailNotification
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
