<?php

namespace App\Events\EmailNotifications;

class CertificateCreated
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'certificate',
        public ?int $referenceId = null,
    ) {
    }
}
