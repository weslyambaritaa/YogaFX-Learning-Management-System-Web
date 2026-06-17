<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'pending_registration_id',
    'user_id',
    'reference_code',
    'payment_type',
    'payment_method',
    'amount',
    'status',
    'processed_at',
    'meta',
])]
class PaymentActivity extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const METHOD_PAYPAL_CARD = 'paypal_credit_card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
