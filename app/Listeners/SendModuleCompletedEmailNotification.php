<?php

namespace App\Listeners;

use App\Events\EmailNotifications\ModuleCompleted;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendModuleCompletedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(ModuleCompleted $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::MODULE_COMPLETION,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
