<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_id',
    'package_id',
    'pending_registration_id',
    'user_id',
    'access_tier_id',
    'provider',
    'provider_product_id',
    'provider_plan_id',
    'provider_subscription_id',
    'status',
    'installment_count',
    'installments_paid_count',
    'currency_code',
    'total_amount',
    'monthly_base_amount',
    'first_payment_amount',
    'next_billing_amount',
    'billing_day',
    'started_at',
    'first_payment_paid_at',
    'next_due_at',
    'final_due_at',
    'grace_deadline_at',
    'completed_at',
    'suspended_at',
    'cancelled_at',
    'last_payment_failed_at',
    'last_synced_at',
    'metadata',
])]
class PaymentSubscription extends Model
{
    public const PROVIDER_PAYPAL = 'paypal';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVAL_PENDING = 'approval_pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'installment_count' => 'integer',
            'installments_paid_count' => 'integer',
            'total_amount' => 'decimal:2',
            'monthly_base_amount' => 'decimal:2',
            'first_payment_amount' => 'decimal:2',
            'next_billing_amount' => 'decimal:2',
            'billing_day' => 'integer',
            'started_at' => 'datetime',
            'first_payment_paid_at' => 'datetime',
            'next_due_at' => 'datetime',
            'final_due_at' => 'datetime',
            'grace_deadline_at' => 'datetime',
            'completed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_payment_failed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
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

    public function events(): HasMany
    {
        return $this->hasMany(PaymentSubscriptionEvent::class);
    }
}
