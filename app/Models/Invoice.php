<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_number',
    'pending_registration_id',
    'package_id',
    'user_id',
    'access_tier_id',
    'type',
    'payment_type',
    'package_payment_type',
    'total_amount',
    'balance_due',
    'currency_code',
    'status',
    'issued_at',
    'paid_at',
])]
class Invoice extends Model
{
    public const TYPE_INITIAL = 'initial';
    public const TYPE_UPGRADE = 'upgrade';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID_FULL = 'paid_full';
    public const STATUS_INSTALLMENT = 'installment';
    public const STATUS_UPGRADED = 'upgraded';

    public const CONTEXT_INITIAL = self::TYPE_INITIAL;
    public const CONTEXT_UPGRADE = self::TYPE_UPGRADE;
    public const PAYMENT_TYPE_FULL = Payment::TYPE_PAY_FULL;
    public const PAYMENT_TYPE_INSTALLMENT = Payment::TYPE_INSTALLMENT;

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function paymentActivities(): HasMany
    {
        return $this->payments();
    }

    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class);
    }

    public function getContextAttribute(): ?string
    {
        return $this->type;
    }

    public function getBalanceAmountAttribute(): string
    {
        return (string) $this->balance_due;
    }
}
