<?php

namespace App\Events\EmailNotifications;

class ResetPasswordRequested
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'user',
        public ?int $referenceId = null,
    ) {
    }
}
