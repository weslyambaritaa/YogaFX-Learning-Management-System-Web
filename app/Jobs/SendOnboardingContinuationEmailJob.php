<?php

namespace App\Jobs;

use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOnboardingContinuationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public int $onboardingStateId,
    ) {}

    public function handle(EmailNotificationService $emailNotificationService): void
    {
        $emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::SIGNUP,
            $this->payload,
            'onboarding_state',
            $this->onboardingStateId,
        );
    }
}
