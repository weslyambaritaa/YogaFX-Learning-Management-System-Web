<?php

namespace App\Events\EmailNotifications;

class UserSignedUp
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'user',
        public ?int $referenceId = null,
    ) {
    }
}
