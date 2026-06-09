<?php

namespace App\Listeners;

use App\Events\EmailNotifications\CourseCompleted;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;

class SendCourseCompletedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(CourseCompleted $event): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::COURSE_COMPLETE,
            $event->payload,
            $event->referenceType,
            $event->referenceId,
        );
    }
}
