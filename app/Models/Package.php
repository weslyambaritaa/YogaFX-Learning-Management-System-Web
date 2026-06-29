<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'access_tier_id',
    'title',
    'slug',
    'description',
    'image',
    'price',
    'currency_code',
    'is_active',
    'installment_enabled',
    'billing_interval_unit',
    'billing_interval_count',
    'fixed_billing_day',
    'allowed_billing_days',
    'installment_deadline_month',
    'installment_deadline_day',
    'paypal_product_id',
    'paypal_plan_id',
    'metadata',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'installment_enabled' => 'boolean',
            'billing_interval_count' => 'integer',
            'fixed_billing_day' => 'integer',
            'allowed_billing_days' => 'array',
            'installment_deadline_month' => 'integer',
            'installment_deadline_day' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }

    public function pendingRegistrations(): HasMany
    {
        return $this->hasMany(PendingRegistration::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class);
    }

    public function isCheckoutAvailable(): bool
    {
        return $this->is_active && $this->access_tier_id !== null;
    }

    /**
     * @return array<int, int>
     */
    public function resolvedAllowedBillingDays(): array
    {
        $allowedDays = collect($this->allowed_billing_days ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => in_array($day, [1, 15], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($allowedDays !== []) {
            return $allowedDays;
        }

        $legacyDay = (int) $this->fixed_billing_day;

        if (in_array($legacyDay, [1, 15], true)) {
            return [$legacyDay];
        }

        return [15];
    }
}
