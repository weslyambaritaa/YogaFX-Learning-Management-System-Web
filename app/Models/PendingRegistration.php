<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'access_tier_id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'country',
    'amount_snapshot',
    'status',
    'checkout_opened_at',
    'payment_succeeded_at',
    'completed_at',
])]
class PendingRegistration extends Model
{
    public const STATUS_CREATED = 'created';
    public const STATUS_CHECKOUT_OPENED = 'checkout_opened';
    public const STATUS_PAYMENT_SUCCESS = 'payment_success';
    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'amount_snapshot' => 'decimal:2',
            'checkout_opened_at' => 'datetime',
            'payment_succeeded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentActivities(): HasMany
    {
        return $this->hasMany(PaymentActivity::class);
    }

    public function onboardingState(): HasOne
    {
        return $this->hasOne(OnboardingState::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
