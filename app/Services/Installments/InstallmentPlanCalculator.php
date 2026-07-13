<?php

namespace App\Services\Installments;

use App\Models\Package;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

class InstallmentPlanCalculator
{
    public function isEligible(Package $package): bool
    {
        return (bool) $package->installment_enabled && $package->access_tier_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        Package $package,
        CarbonInterface|string|null $checkoutAt = null,
        ?int $billingDay = null,
        ?int $installmentCount = null,
    ): array {
        return $this->calculateForAmount(
            $package,
            (float) $package->price,
            $checkoutAt,
            $billingDay,
            $installmentCount,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateForAmount(
        Package $package,
        float|int|string $totalAmountOverride,
        CarbonInterface|string|null $checkoutAt = null,
        ?int $billingDay = null,
        ?int $installmentCount = null,
    ): array {
        if (! $this->isEligible($package)) {
            throw new DomainException('This package is not eligible for installment checkout.');
        }

        $checkoutDate = $this->normalizeDate($checkoutAt);
        $billingDay = $this->resolveBillingDay($package, $billingDay);

        if ($package->usesNumberBasedInstallment()) {
            return $this->calculateNumberBasedPlan(
                $package,
                $totalAmountOverride,
                $checkoutDate,
                $billingDay,
                $installmentCount,
            );
        }

        return $this->calculateDateBasedPlan(
            $package,
            $totalAmountOverride,
            $checkoutDate,
            $billingDay,
            $installmentCount,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateDateBasedPlan(
        Package $package,
        float|int|string $totalAmountOverride,
        CarbonImmutable $checkoutDate,
        int $billingDay,
        ?int $installmentCount,
    ): array {
        $deadlineDate = $this->resolveDeadlineDate($package);

        if ($checkoutDate->greaterThan($deadlineDate)) {
            throw new DomainException('The installment window has already closed for this checkout date.');
        }

        $maximumRecurringDueDates = $this->buildMonthlyRecurringDueDatesUntilDeadline(
            checkoutDate: $checkoutDate,
            deadlineDate: $deadlineDate,
            billingDay: $billingDay,
        );

        $maximumInstallmentCount = min(
            1 + count($maximumRecurringDueDates),
            Package::MAX_PROVIDER_INSTALLMENT_COUNT,
        );

        if ($maximumInstallmentCount < Package::MIN_INSTALLMENT_COUNT) {
            throw new DomainException('This package does not have enough billing dates for installment checkout.');
        }

        $selectedInstallmentCount = $this->resolveFlexibleInstallmentCount(
            selectedInstallmentCount: $installmentCount,
            maximumInstallmentCount: $maximumInstallmentCount,
        );

        $selectedRecurringDueDates = array_slice(
            $maximumRecurringDueDates,
            0,
            max(0, $selectedInstallmentCount - 1),
        );

        return $this->buildPlanPayload(
            package: $package,
            totalAmountOverride: $totalAmountOverride,
            checkoutDate: $checkoutDate,
            billingDay: $billingDay,
            selectedInstallmentCount: $selectedInstallmentCount,
            minimumInstallmentCount: Package::MIN_INSTALLMENT_COUNT,
            maximumInstallmentCount: $maximumInstallmentCount,
            recurringDueDates: $selectedRecurringDueDates,
            availableRecurringDueDates: array_slice(
                $maximumRecurringDueDates,
                0,
                max(0, $maximumInstallmentCount - 1),
            ),
            deadlineDate: $deadlineDate,
            configuredInstallmentCount: null,
            selectable: true,
            fixedInstallmentCount: null,
            calculationMethod: Package::INSTALLMENT_CALCULATION_DATE,
            countMode: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateNumberBasedPlan(
        Package $package,
        float|int|string $totalAmountOverride,
        CarbonImmutable $checkoutDate,
        int $billingDay,
        ?int $installmentCount,
    ): array {
        $configuredInstallmentCount = $package->configuredInstallmentCount();

        if ($configuredInstallmentCount === null) {
            throw new InvalidArgumentException('Package installment count is required for number-based installment.');
        }

        if ($package->usesFixedInstallmentCount()) {
            $selectedInstallmentCount = $this->resolveFixedInstallmentCount(
                configuredInstallmentCount: $configuredInstallmentCount,
                selectedInstallmentCount: $installmentCount,
            );
            $minimumInstallmentCount = $configuredInstallmentCount;
            $maximumInstallmentCount = $configuredInstallmentCount;
            $selectable = false;
            $fixedInstallmentCount = $configuredInstallmentCount;
        } else {
            $selectedInstallmentCount = $this->resolveFlexibleInstallmentCount(
                selectedInstallmentCount: $installmentCount,
                maximumInstallmentCount: $configuredInstallmentCount,
            );
            $minimumInstallmentCount = Package::MIN_INSTALLMENT_COUNT;
            $maximumInstallmentCount = $configuredInstallmentCount;
            $selectable = true;
            $fixedInstallmentCount = null;
        }

        $selectedRecurringDueDates = $this->buildMonthlyRecurringDueDatesByCount(
            checkoutDate: $checkoutDate,
            billingDay: $billingDay,
            recurringCount: max(0, $selectedInstallmentCount - 1),
        );

        $availableRecurringDueDates = $this->buildMonthlyRecurringDueDatesByCount(
            checkoutDate: $checkoutDate,
            billingDay: $billingDay,
            recurringCount: max(0, $maximumInstallmentCount - 1),
        );

        return $this->buildPlanPayload(
            package: $package,
            totalAmountOverride: $totalAmountOverride,
            checkoutDate: $checkoutDate,
            billingDay: $billingDay,
            selectedInstallmentCount: $selectedInstallmentCount,
            minimumInstallmentCount: $minimumInstallmentCount,
            maximumInstallmentCount: $maximumInstallmentCount,
            recurringDueDates: $selectedRecurringDueDates,
            availableRecurringDueDates: $availableRecurringDueDates,
            deadlineDate: null,
            configuredInstallmentCount: $configuredInstallmentCount,
            selectable: $selectable,
            fixedInstallmentCount: $fixedInstallmentCount,
            calculationMethod: Package::INSTALLMENT_CALCULATION_NUMBER,
            countMode: $package->normalizedInstallmentCountMode(),
        );
    }

    /**
     * @param  array<int, CarbonImmutable>  $recurringDueDates
     * @param  array<int, CarbonImmutable>  $availableRecurringDueDates
     * @return array<string, mixed>
     */
    private function buildPlanPayload(
        Package $package,
        float|int|string $totalAmountOverride,
        CarbonImmutable $checkoutDate,
        int $billingDay,
        int $selectedInstallmentCount,
        int $minimumInstallmentCount,
        int $maximumInstallmentCount,
        array $recurringDueDates,
        array $availableRecurringDueDates,
        ?CarbonImmutable $deadlineDate,
        ?int $configuredInstallmentCount,
        bool $selectable,
        ?int $fixedInstallmentCount,
        string $calculationMethod,
        ?string $countMode,
    ): array {
        $finalDueAt = $recurringDueDates !== []
            ? end($recurringDueDates)
            : $checkoutDate;

        if (! $finalDueAt instanceof CarbonImmutable) {
            $finalDueAt = $checkoutDate;
        }

        $totalAmount = $this->normalizeAmount($totalAmountOverride);
        $totalAmountCents = $this->amountToCents($totalAmount);
        $recurringAmountCents = intdiv($totalAmountCents, $selectedInstallmentCount);
        $firstPaymentAmountCents = $totalAmountCents - ($recurringAmountCents * ($selectedInstallmentCount - 1));

        $recurringAmount = $this->centsToAmount($recurringAmountCents);
        $firstPaymentAmount = $this->centsToAmount($firstPaymentAmountCents);

        $graceDeadlines = array_map(
            fn (CarbonImmutable $dueDate) => $dueDate->addDays(3)->format('Y-m-d'),
            $recurringDueDates,
        );

        $scheduleBreakdown = [[
            'cycle_number' => 1,
            'type' => 'first_payment',
            'amount' => $this->formatAmount($firstPaymentAmount),
            'due_at' => $checkoutDate->format('Y-m-d'),
            'grace_deadline' => null,
        ]];

        foreach ($recurringDueDates as $index => $dueDate) {
            $scheduleBreakdown[] = [
                'cycle_number' => $index + 2,
                'type' => 'recurring',
                'amount' => $this->formatAmount($recurringAmount),
                'due_at' => $dueDate->format('Y-m-d'),
                'grace_deadline' => $dueDate->addDays(3)->format('Y-m-d'),
            ];
        }

        return [
            'total_amount' => $this->formatAmount($totalAmount),
            'currency_code' => (string) $package->currency_code,
            'installment_count' => $selectedInstallmentCount,
            'maximum_installment_count' => $maximumInstallmentCount,
            'installment_maximum_count' => $maximumInstallmentCount,
            'minimum_installment_count' => $minimumInstallmentCount,
            'installment_count_selectable' => $selectable,
            'configured_installment_count' => $configuredInstallmentCount,
            'fixed_installment_count' => $fixedInstallmentCount,
            'installment_calculation_method' => $calculationMethod,
            'installment_count_mode' => $countMode,
            'first_payment_amount' => $this->formatAmount($firstPaymentAmount),
            'monthly_base_amount' => $this->formatAmount($recurringAmount),
            'recurring_payment_amount' => $this->formatAmount($recurringAmount),
            'billing_day' => $billingDay,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'first_payment_date' => $checkoutDate->format('Y-m-d'),
            'recurring_due_dates' => array_map(
                fn (CarbonImmutable $date) => $date->format('Y-m-d'),
                $recurringDueDates,
            ),
            'available_recurring_due_dates' => array_map(
                fn (CarbonImmutable $date) => $date->format('Y-m-d'),
                $availableRecurringDueDates,
            ),
            'deadline_date' => $deadlineDate?->format('Y-m-d'),
            'final_due_at' => $finalDueAt->format('Y-m-d'),
            'grace_deadlines' => $graceDeadlines,
            'schedule_breakdown' => $scheduleBreakdown,
        ];
    }

    private function normalizeDate(CarbonInterface|string|null $checkoutAt): CarbonImmutable
    {
        if ($checkoutAt instanceof CarbonInterface) {
            return CarbonImmutable::instance($checkoutAt)->startOfDay();
        }

        if (is_string($checkoutAt)) {
            return CarbonImmutable::parse($checkoutAt)->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }

    private function resolveBillingDay(Package $package, ?int $selectedBillingDay = null): int
    {
        return $package->resolveInstallmentBillingDay($selectedBillingDay);
    }

    private function resolveDeadlineDate(Package $package): CarbonImmutable
    {
        if ($package->installment_deadline_date === null) {
            throw new InvalidArgumentException('Package installment deadline date is required.');
        }

        if ($package->installment_deadline_date instanceof CarbonInterface) {
            return CarbonImmutable::instance($package->installment_deadline_date)->startOfDay();
        }

        return CarbonImmutable::parse((string) $package->installment_deadline_date)->startOfDay();
    }

    private function resolveFlexibleInstallmentCount(
        ?int $selectedInstallmentCount,
        int $maximumInstallmentCount,
    ): int {
        if ($selectedInstallmentCount === null) {
            return $maximumInstallmentCount;
        }

        if ($selectedInstallmentCount < Package::MIN_INSTALLMENT_COUNT) {
            throw new InvalidArgumentException('Installment count must be at least 2.');
        }

        if ($selectedInstallmentCount > $maximumInstallmentCount) {
            throw new InvalidArgumentException('Installment count exceeds the maximum available installment count.');
        }

        return $selectedInstallmentCount;
    }

    private function resolveFixedInstallmentCount(
        int $configuredInstallmentCount,
        ?int $selectedInstallmentCount,
    ): int {
        if ($selectedInstallmentCount === null) {
            return $configuredInstallmentCount;
        }

        if ($selectedInstallmentCount !== $configuredInstallmentCount) {
            throw new InvalidArgumentException('Installment count must match the configured fixed installment count.');
        }

        return $configuredInstallmentCount;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function buildMonthlyRecurringDueDatesUntilDeadline(
        CarbonImmutable $checkoutDate,
        CarbonImmutable $deadlineDate,
        int $billingDay,
    ): array {
        $firstRecurringMonth = $checkoutDate->startOfMonth()->addMonth();

        $cursor = $this->safeMonthlyBillingDate(
            year: $firstRecurringMonth->year,
            month: $firstRecurringMonth->month,
            billingDay: $billingDay,
        );

        $dates = [];

        while ($cursor->lessThanOrEqualTo($deadlineDate) && count($dates) < (Package::MAX_PROVIDER_INSTALLMENT_COUNT - 1)) {
            $dates[] = $cursor;

            $nextMonth = $cursor->startOfMonth()->addMonth();

            $cursor = $this->safeMonthlyBillingDate(
                year: $nextMonth->year,
                month: $nextMonth->month,
                billingDay: $billingDay,
            );
        }

        return $dates;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function buildMonthlyRecurringDueDatesByCount(
        CarbonImmutable $checkoutDate,
        int $billingDay,
        int $recurringCount,
    ): array {
        if ($recurringCount <= 0) {
            return [];
        }

        $dates = [];
        $cursorMonth = $checkoutDate->startOfMonth()->addMonth();

        while (count($dates) < $recurringCount) {
            $dates[] = $this->safeMonthlyBillingDate(
                year: $cursorMonth->year,
                month: $cursorMonth->month,
                billingDay: $billingDay,
            );

            $cursorMonth = $cursorMonth->addMonth();
        }

        return $dates;
    }

    private function safeMonthlyBillingDate(int $year, int $month, int $billingDay): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $safeDay = min($billingDay, $date->daysInMonth);

        return $date->day($safeDay)->startOfDay();
    }

    private function normalizeAmount(float|int|string $amount): float
    {
        return round((float) $amount, 2);
    }

    private function amountToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function centsToAmount(int $amountCents): float
    {
        return $amountCents / 100;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
