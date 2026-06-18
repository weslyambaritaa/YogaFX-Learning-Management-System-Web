<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_number',
    'pending_registration_id',
    'user_id',
    'access_tier_id',
    'context',
    'payment_type',
    'total_amount',
    'balance_amount',
    'status',
    'issued_at',
    'paid_at',
])]
class Invoice extends Model
{
    public const CONTEXT_INITIAL = 'initial';
    public const CONTEXT_UPGRADE = 'upgrade';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID_FULL = 'paid_full';
    public const STATUS_INSTALLMENT = 'installment';

    public const PAYMENT_TYPE_FULL = 'pay_in_full';
    public const PAYMENT_TYPE_INSTALLMENT = 'pay_in_4_installments';

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
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

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }

    public function paymentActivities(): HasMany
    {
        return $this->hasMany(PaymentActivity::class);
    }
}
