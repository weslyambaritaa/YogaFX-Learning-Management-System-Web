<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'payment_method',
    'payment_type',
    'amount_paid',
    'currency_code',
    'status',
    'payment_reference',
    'notes',
])]
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const TYPE_PAY_FULL = 'pay_full';
    public const TYPE_INSTALLMENT = 'installment';

    public const METHOD_PAYPAL = 'paypal';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $table = 'payments';

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getAmountAttribute(): string
    {
        return (string) $this->amount_paid;
    }

    public function getReferenceCodeAttribute(): ?string
    {
        return $this->payment_reference;
    }
}
