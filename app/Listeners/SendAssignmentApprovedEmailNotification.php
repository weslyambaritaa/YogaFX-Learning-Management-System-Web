<?php

namespace App\Listeners;

use App\Events\EmailNotifications\AssignmentApproved;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendAssignmentApprovedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(AssignmentApproved $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::ASSIGNMENT_APPROVED,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
