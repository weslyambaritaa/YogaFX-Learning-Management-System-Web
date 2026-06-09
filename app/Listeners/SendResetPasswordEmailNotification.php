<?php

namespace App\Listeners;

use App\Events\EmailNotifications\ResetPasswordRequested;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendResetPasswordEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(ResetPasswordRequested $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::RESET_PASSWORD,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
