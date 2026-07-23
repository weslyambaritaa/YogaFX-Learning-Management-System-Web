<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_subscription_id',
    'invoice_id',
    'payment_activity_id',
    'provider',
    'provider_event_id',
    'provider_event_type',
    'provider_subscription_id',
    'provider_order_id',
    'provider_capture_id',
    'occurred_at',
    'processed_at',
    'status',
    'payload',
    'notes',
])]
class PaymentSubscriptionEvent extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function paymentSubscription(): BelongsTo
    {
        return $this->belongsTo(PaymentSubscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentActivity(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_activity_id');
    }
}
