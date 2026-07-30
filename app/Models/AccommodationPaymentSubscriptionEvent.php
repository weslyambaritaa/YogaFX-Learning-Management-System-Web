<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'accommodation_payment_subscription_id',
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
class AccommodationPaymentSubscriptionEvent extends Model
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

    public function accommodationPaymentSubscription(): BelongsTo
    {
        return $this->belongsTo(AccommodationPaymentSubscription::class);
    }
}
