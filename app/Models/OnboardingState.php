<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pending_registration_id',
    'user_id',
    'status',
    'continuation_sent_at',
    'enrollment_completed_at',
    'signup_completed_at',
])]
class OnboardingState extends Model
{
    public const STATUS_AWAITING_ENROLLMENT = 'awaiting_enrollment';
    public const STATUS_AWAITING_SIGNUP = 'awaiting_signup';
    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'continuation_sent_at' => 'datetime',
            'enrollment_completed_at' => 'datetime',
            'signup_completed_at' => 'datetime',
        ];
    }

    public function pendingRegistration(): BelongsTo
    {
        return $this->belongsTo(PendingRegistration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
