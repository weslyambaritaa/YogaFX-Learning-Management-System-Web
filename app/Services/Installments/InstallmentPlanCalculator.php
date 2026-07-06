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
        $deadlineDate = $this->resolveDeadlineDate($package);

        if ($checkoutDate->greaterThanOrEqualTo($deadlineDate)) {
            throw new DomainException('The installment window has already closed for this checkout date.');
        }

        $maximumRecurringDueDates = $this->buildMonthlyRecurringDueDates(
            checkoutDate: $checkoutDate,
            deadlineDate: $deadlineDate,
            billingDay: $billingDay,
        );

        $maximumInstallmentCount = 1 + count($maximumRecurringDueDates);

        if ($maximumInstallmentCount < 2) {
            throw new DomainException('This package does not have enough billing dates for installment checkout.');
        }

        $selectedInstallmentCount = $this->resolveInstallmentCount(
            selectedInstallmentCount: $installmentCount,
            maximumInstallmentCount: $maximumInstallmentCount,
        );

        $selectedRecurringDueDates = array_slice(
            $maximumRecurringDueDates,
            0,
            max(0, $selectedInstallmentCount - 1),
        );

        $finalDueAt = $selectedRecurringDueDates !== []
            ? end($selectedRecurringDueDates)
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
            $selectedRecurringDueDates,
        );

        $scheduleBreakdown = [[
            'cycle_number' => 1,
            'type' => 'first_payment',
            'amount' => $this->formatAmount($firstPaymentAmount),
            'due_at' => $checkoutDate->format('Y-m-d'),
            'grace_deadline' => null,
        ]];

        foreach ($selectedRecurringDueDates as $index => $dueDate) {
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
            'first_payment_amount' => $this->formatAmount($firstPaymentAmount),
            'monthly_base_amount' => $this->formatAmount($recurringAmount),
            'recurring_payment_amount' => $this->formatAmount($recurringAmount),
            'billing_day' => $billingDay,

            /*
            |--------------------------------------------------------------------------
            | Compatibility fields
            |--------------------------------------------------------------------------
            |
            | Flow baru selalu monthly dengan billing day 1/15.
            | Field ini tetap dikembalikan supaya service lama yang masih membaca
            | billing_interval_unit/count tidak langsung rusak.
            |
            */
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,

            'first_payment_date' => $checkoutDate->format('Y-m-d'),
            'recurring_due_dates' => array_map(
                fn (CarbonImmutable $date) => $date->format('Y-m-d'),
                $selectedRecurringDueDates,
            ),
            'available_recurring_due_dates' => array_map(
                fn (CarbonImmutable $date) => $date->format('Y-m-d'),
                $maximumRecurringDueDates,
            ),
            'deadline_date' => $deadlineDate->format('Y-m-d'),
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
        try {
            return $package->resolveInstallmentBillingDay($selectedBillingDay);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        }
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

    private function resolveInstallmentCount(
        ?int $selectedInstallmentCount,
        int $maximumInstallmentCount,
    ): int {
        if ($selectedInstallmentCount === null) {
            return $maximumInstallmentCount;
        }

        if ($selectedInstallmentCount < 2) {
            throw new InvalidArgumentException('Installment count must be at least 2.');
        }

        if ($selectedInstallmentCount > $maximumInstallmentCount) {
            throw new InvalidArgumentException('Installment count exceeds the maximum available installment count.');
        }

        return $selectedInstallmentCount;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function buildMonthlyRecurringDueDates(
        CarbonImmutable $checkoutDate,
        CarbonImmutable $deadlineDate,
        int $billingDay,
    ): array {
        $cursor = $this->safeMonthlyBillingDate(
            year: $checkoutDate->year,
            month: $checkoutDate->month,
            billingDay: $billingDay,
        );

        if ($cursor->lessThanOrEqualTo($checkoutDate)) {
            $nextMonth = $checkoutDate->startOfMonth()->addMonth();

            $cursor = $this->safeMonthlyBillingDate(
                year: $nextMonth->year,
                month: $nextMonth->month,
                billingDay: $billingDay,
            );
        }

        $dates = [];

        while ($cursor->lessThanOrEqualTo($deadlineDate)) {
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