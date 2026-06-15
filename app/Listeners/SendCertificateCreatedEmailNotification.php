<?php

namespace App\Listeners;

use App\Events\EmailNotifications\CertificateCreated;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;
use Throwable;

class SendCertificateCreatedEmailNotification
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function handle(CertificateCreated $event): void
    {
        try {
            $this->emailNotificationService->sendAutomated(
                EmailNotificationTypeRegistry::CERTIFICATE_CREATED,
                $event->payload,
                $event->referenceType,
                $event->referenceId,
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
