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
    'payment_type',
    'image',
    'price',
    'minimum_donation_amount',
    'suggested_donation_amount',
    'currency_code',
    'is_active',
    'installment_enabled',
    'setup_fee',


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
    'installment_calculation_method',
    'installment_count_mode',
    'installment_count',

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

    public const PAYMENT_TYPE_PAID = 'paid';
    public const PAYMENT_TYPE_FREE = 'free';
    public const PAYMENT_TYPE_DONATION = 'donation';

    public const INSTALLMENT_CALCULATION_DATE = 'date';
    public const INSTALLMENT_CALCULATION_NUMBER = 'number';

    public const INSTALLMENT_COUNT_MODE_FLEX = 'flex';
    public const INSTALLMENT_COUNT_MODE_FIXED = 'fixed';

    public const MIN_INSTALLMENT_COUNT = 2;
    public const MAX_INSTALLMENT_COUNT = 99;
    public const MAX_PROVIDER_INSTALLMENT_COUNT = 99;

    public const CUSTOMER_BILLING_DAY_OPTIONS = [1, 15];

    protected function casts(): array
    {
        return [
            'payment_type' => 'string',
            'price' => 'decimal:2',
            'minimum_donation_amount' => 'decimal:2',
            'suggested_donation_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'setup_fee' => 'decimal:2',
            'installment_enabled' => 'boolean',
            'installment_count' => 'integer',

            /*
            |--------------------------------------------------------------------------
            | New Installment Casts
            |--------------------------------------------------------------------------
            */
            'installment_deadline_date' => 'date',
            'allowed_billing_days' => 'array',
            'installment_calculation_method' => 'string',
            'installment_count_mode' => 'string',

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

    public function normalizedPaymentType(): string
    {
        $type = strtolower(trim((string) ($this->payment_type ?? '')));

        return in_array($type, [
            self::PAYMENT_TYPE_PAID,
            self::PAYMENT_TYPE_FREE,
            self::PAYMENT_TYPE_DONATION,
        ], true)
            ? $type
            : self::PAYMENT_TYPE_PAID;
    }

    public function isPaidPackage(): bool
    {
        return $this->normalizedPaymentType() === self::PAYMENT_TYPE_PAID;
    }

    public function isFreePackage(): bool
    {
        return $this->normalizedPaymentType() === self::PAYMENT_TYPE_FREE;
    }

    public function isDonationPackage(): bool
    {
        return $this->normalizedPaymentType() === self::PAYMENT_TYPE_DONATION;
    }

    public function minimumDonationAmount(): float
    {
        return round((float) ($this->minimum_donation_amount ?? 0), 2);
    }

    public function suggestedDonationAmount(): ?float
{
    if (! $this->isDonationPackage()) {
        return null;
    }

    return $this->minimumDonationAmount();
}

    public function checkoutBaseAmount(): float
    {
        return match ($this->normalizedPaymentType()) {
            self::PAYMENT_TYPE_FREE => 0.0,
            self::PAYMENT_TYPE_DONATION => $this->minimumDonationAmount(),
            default => round((float) $this->price, 2),
        };
    }

    public function suggestedCheckoutAmount(): float
{
    if ($this->isDonationPackage()) {
        return $this->minimumDonationAmount();
    }

    return $this->checkoutBaseAmount();
}

    public function supportsInstallments(): bool
{
    return $this->isPaidPackage() && $this->installment_enabled;
}

public function initialInstallmentSetupFee(): ?float
{
    if (! $this->supportsInstallments()) {
        return null;
    }

    if ($this->setup_fee === null || $this->setup_fee === '') {
        return null;
    }

    $setupFee = round((float) $this->setup_fee, 2);

    return $setupFee > 0
        ? $setupFee
        : null;
}

/*
|--------------------------------------------------------------------------
| Legacy Billing Interval Helpers
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

    public function normalizedInstallmentCalculationMethod(): string
    {
        $method = strtolower(trim((string) ($this->installment_calculation_method ?? '')));

        return in_array($method, [
            self::INSTALLMENT_CALCULATION_DATE,
            self::INSTALLMENT_CALCULATION_NUMBER,
        ], true)
            ? $method
            : self::INSTALLMENT_CALCULATION_DATE;
    }

    public function usesDateBasedInstallment(): bool
    {
        return $this->normalizedInstallmentCalculationMethod() === self::INSTALLMENT_CALCULATION_DATE;
    }

    public function usesNumberBasedInstallment(): bool
    {
        return $this->normalizedInstallmentCalculationMethod() === self::INSTALLMENT_CALCULATION_NUMBER;
    }

    public function normalizedInstallmentCountMode(): ?string
    {
        if (! $this->usesNumberBasedInstallment()) {
            return null;
        }

        $mode = strtolower(trim((string) ($this->installment_count_mode ?? '')));

        return in_array($mode, [
            self::INSTALLMENT_COUNT_MODE_FLEX,
            self::INSTALLMENT_COUNT_MODE_FIXED,
        ], true)
            ? $mode
            : null;
    }

    public function usesFlexibleInstallmentCount(): bool
    {
        return $this->normalizedInstallmentCountMode() === self::INSTALLMENT_COUNT_MODE_FLEX;
    }

    public function usesFixedInstallmentCount(): bool
    {
        return $this->normalizedInstallmentCountMode() === self::INSTALLMENT_COUNT_MODE_FIXED;
    }

    public function configuredInstallmentCount(): ?int
    {
        if (! $this->usesNumberBasedInstallment()) {
            return null;
        }

        $count = (int) ($this->installment_count ?? 0);

        if ($count < self::MIN_INSTALLMENT_COUNT) {
            return null;
        }

        return min($count, self::MAX_INSTALLMENT_COUNT);
    }

    public function customerCanChooseInstallmentCount(): bool
    {
        if (! $this->supportsInstallments()) {
            return false;
        }

        if ($this->usesFixedInstallmentCount()) {
            return false;
        }

        return true;
    }

    public function installmentCountSelectable(): bool
    {
        return $this->customerCanChooseInstallmentCount();
    }

    public function minimumInstallmentCount(): int
    {
        if ($this->usesFixedInstallmentCount()) {
            return $this->configuredInstallmentCount() ?? self::MIN_INSTALLMENT_COUNT;
        }

        return self::MIN_INSTALLMENT_COUNT;
    }

    public function fixedInstallmentCount(): ?int
    {
        return $this->usesFixedInstallmentCount()
            ? $this->configuredInstallmentCount()
            : null;
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
        return $this->supportsInstallments() && $this->resolvedAllowedBillingDays() !== [];
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
