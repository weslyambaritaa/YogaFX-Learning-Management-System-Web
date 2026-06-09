<?php

namespace App\Listeners;

use App\Events\EmailNotifications\AssignmentRejected;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendAssignmentRejectedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(AssignmentRejected $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::ASSIGNMENT_REJECTED,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
