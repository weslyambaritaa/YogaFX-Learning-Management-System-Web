<?php

namespace App\Events\EmailNotifications;

class CourseCompleted
{
    public function __construct(
        public array $payload,
        public ?string $referenceType = 'course',
        public ?int $referenceId = null,
    ) {
    }
}
