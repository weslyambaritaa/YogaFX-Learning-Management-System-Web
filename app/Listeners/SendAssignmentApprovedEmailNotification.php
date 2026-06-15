<?php

namespace App\Listeners;

use App\Events\EmailNotifications\AssignmentApproved;
use App\Services\EmailNotificationService;

class SendAssignmentApprovedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(AssignmentApproved $event): void
    {
        $this->emailNotificationService->sendAssignmentApprovedNotification(
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
