<?php

namespace App\Events\EmailNotifications;

class ModuleCompleted
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'module',
        public ?int $referenceId = null,
    ) {
    }
}
