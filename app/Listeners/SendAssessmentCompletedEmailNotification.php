<?php

namespace App\Listeners;

use App\Events\EmailNotifications\AssessmentCompleted;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendAssessmentCompletedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(AssessmentCompleted $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::ASSESSMENT_COMPLETE,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
