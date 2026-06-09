<?php

namespace App\Listeners;

use App\Events\EmailNotifications\ReminderTriggered;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendReminderEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(ReminderTriggered $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::REMINDER,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
