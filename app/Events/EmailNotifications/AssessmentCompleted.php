<?php

namespace App\Events\EmailNotifications;

class AssessmentCompleted
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'assessment_attempt',
        public ?int $referenceId = null,
    ) {
    }
}
