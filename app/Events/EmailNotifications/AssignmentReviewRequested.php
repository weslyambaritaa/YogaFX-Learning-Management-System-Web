<?php

namespace App\Events\EmailNotifications;

class AssignmentReviewRequested
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'assignment_submission',
        public ?int $referenceId = null,
    ) {
    }
}
