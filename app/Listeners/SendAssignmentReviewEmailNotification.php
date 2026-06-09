<?php

namespace App\Listeners;

use App\Events\EmailNotifications\AssignmentReviewRequested;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendAssignmentReviewEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(AssignmentReviewRequested $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::ASSIGNMENT_REVIEW,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
