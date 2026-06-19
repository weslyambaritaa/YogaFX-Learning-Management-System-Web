<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'email',
    'token_hash',
    'otp_hash',
    'origin',
    'expires_at',
    'used_at',
])]
class StudentPasswordChangeRequest extends Model
{
    public const ORIGIN_WEB = 'web';
    public const ORIGIN_MOBILE = 'mobile';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isMobileOrigin(): bool
    {
        return $this->origin === self::ORIGIN_MOBILE;
    }
}
