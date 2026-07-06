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

    /*
    |--------------------------------------------------------------------------
    | New Installment Fields
    |--------------------------------------------------------------------------
    |
    | installment_deadline_date:
    | Tanggal batas akhir pembayaran installment untuk package.
    |
    | allowed_billing_days:
    | Tanggal billing yang diaktifkan admin untuk calon student.
    | Hanya boleh berisi 1 dan/atau 15.
    |
    */
    'installment_deadline_date',
    'allowed_billing_days',

    /*
    |--------------------------------------------------------------------------
    | Legacy Installment Fields
    |--------------------------------------------------------------------------
    |
    | Field lama ini masih dipertahankan sementara supaya bagian lain yang belum
    | dimigrasikan tidak langsung error. Nanti setelah service checkout dan PayPal
    | subscription logic sudah memakai flow baru, field ini bisa dipertimbangkan
    | untuk dihapus.
    |
    */
    'billing_interval_unit',
    'billing_interval_count',
    'fixed_billing_day',
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

    public const CUSTOMER_BILLING_DAY_OPTIONS = [1, 15];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'installment_enabled' => 'boolean',

            /*
            |--------------------------------------------------------------------------
            | New Installment Casts
            |--------------------------------------------------------------------------
            */
            'installment_deadline_date' => 'date',
            'allowed_billing_days' => 'array',

            /*
            |--------------------------------------------------------------------------
            | Legacy Installment Casts
            |--------------------------------------------------------------------------
            */
            'billing_interval_count' => 'integer',
            'fixed_billing_day' => 'integer',
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

    /*
    |--------------------------------------------------------------------------
    | Legacy Billing Interval Helpers
    |--------------------------------------------------------------------------
    |
    | Method ini masih dipertahankan sementara untuk menjaga kompatibilitas.
    | Flow baru tidak lagi membutuhkan billing_interval_count.
    |
    */

    public function normalizedBillingIntervalUnit(): ?string
    {
        $unit = strtoupper(trim((string) ($this->billing_interval_unit ?? '')));

        if ($unit !== '') {
            return $unit;
        }

        return $this->installment_enabled ? 'MONTH' : null;
    }

    public function usesMonthlyInstallmentCycle(): bool
    {
        return $this->normalizedBillingIntervalUnit() === 'MONTH';
    }

    /*
    |--------------------------------------------------------------------------
    | New Installment Deadline Helpers
    |--------------------------------------------------------------------------
    */

    public function hasInstallmentDeadlineDate(): bool
    {
        return $this->installment_deadline_date !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Billing Day Helpers
    |--------------------------------------------------------------------------
    |
    | Pada flow baru:
    | - Admin mengaktifkan tanggal billing 1 dan/atau 15.
    | - Calon student hanya boleh memilih satu tanggal.
    | - Jika hanya ada satu tanggal yang aktif, sistem bisa memakai tanggal itu.
    |
    */

    public function checkoutAcceptsBillingDay(): bool
    {
        return $this->installment_enabled && $this->resolvedAllowedBillingDays() !== [];
    }

    public function checkoutRequiresBillingDayChoice(): bool
    {
        return $this->checkoutAcceptsBillingDay();
    }

    /**
     * @return array<int, int>
     */
    public function checkoutBillingDayOptions(): array
    {
        if (! $this->checkoutAcceptsBillingDay()) {
            return [];
        }

        return $this->resolvedAllowedBillingDays();
    }

    public function defaultInstallmentBillingDay(): int
    {
        $allowedDays = $this->resolvedAllowedBillingDays();

        if ($allowedDays === []) {
            throw new \InvalidArgumentException('This package does not have any available billing day.');
        }

        return $allowedDays[0];
    }

    public function resolveInstallmentBillingDay(?int $selectedBillingDay = null): int
    {
        $allowedDays = $this->resolvedAllowedBillingDays();

        if ($allowedDays === []) {
            throw new \InvalidArgumentException('This package does not have any available billing day.');
        }

        if ($selectedBillingDay === null) {
            if (count($allowedDays) === 1) {
                return $allowedDays[0];
            }

            throw new \InvalidArgumentException('Please select a billing day.');
        }

        $selectedBillingDay = (int) $selectedBillingDay;

        if (! in_array($selectedBillingDay, self::CUSTOMER_BILLING_DAY_OPTIONS, true)) {
            throw new \InvalidArgumentException('Selected billing day must be either 1 or 15.');
        }

        if (! in_array($selectedBillingDay, $allowedDays, true)) {
            throw new \InvalidArgumentException('Selected billing day is not available for this package.');
        }

        return $selectedBillingDay;
    }

    /**
     * @return array<int, int>
     */
    public function resolvedAllowedBillingDays(): array
    {
        $days = $this->allowed_billing_days;

        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($days)) {
            $days = [];
        }

        $allowedDays = collect($days)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => in_array($day, self::CUSTOMER_BILLING_DAY_OPTIONS, true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($allowedDays !== []) {
            return $allowedDays;
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy Fallback
        |--------------------------------------------------------------------------
        |
        | Jika package lama belum punya allowed_billing_days, sistem mencoba memakai
        | fixed_billing_day lama. Jika fixed_billing_day juga tidak valid, fallback
        | ke tanggal 15 supaya data lama tetap aman.
        |
        */
        $legacyDay = (int) $this->fixed_billing_day;

        if (in_array($legacyDay, self::CUSTOMER_BILLING_DAY_OPTIONS, true)) {
            return [$legacyDay];
        }

        return [15];
    }
}