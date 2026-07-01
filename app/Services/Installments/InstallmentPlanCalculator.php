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
    ): array
    {
        return $this->calculateForAmount(
            $package,
            (float) $package->price,
            $checkoutAt,
            $billingDay,
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
    ): array
    {
        if (! $this->isEligible($package)) {
            throw new DomainException('This package is not eligible for installment checkout.');
        }

        $checkoutDate = $this->normalizeDate($checkoutAt);
        $billingDay = $this->resolveBillingDay($package, $billingDay);
        [$deadlineMonth, $deadlineDay] = $this->resolveDeadline($package, $billingDay);
        $intervalUnit = $this->resolveIntervalUnit($package);
        $intervalCount = $this->resolveIntervalCount($package);
        $finalDueAt = $this->resolveFinalDueAt($checkoutDate, $deadlineMonth, $deadlineDay);

        if ($checkoutDate->greaterThan($finalDueAt)) {
            throw new DomainException('The installment window has already closed for this checkout date.');
        }

        $recurringDueDates = $this->buildRecurringDueDates(
            $checkoutDate,
            $finalDueAt,
            $billingDay,
            $intervalUnit,
            $intervalCount,
        );

        $installmentCount = 1 + count($recurringDueDates);
        $totalAmount = $this->normalizeAmount($totalAmountOverride);

        if ($intervalUnit === 'MONTH') {
            $recurringAmount = floor($totalAmount / $installmentCount);
            $firstPaymentAmount = $totalAmount - ($recurringAmount * ($installmentCount - 1));
        } else {
            $totalAmountCents = $this->amountToCents($totalAmount);
            $recurringAmountCents = intdiv($totalAmountCents, $installmentCount);
            $firstPaymentAmountCents = $totalAmountCents - ($recurringAmountCents * ($installmentCount - 1));
            $recurringAmount = $this->centsToAmount($recurringAmountCents);
            $firstPaymentAmount = $this->centsToAmount($firstPaymentAmountCents);
        }

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
            'installment_count' => $installmentCount,
            'first_payment_amount' => $this->formatAmount($firstPaymentAmount),
            'monthly_base_amount' => $this->formatAmount($recurringAmount),
            'recurring_payment_amount' => $this->formatAmount($recurringAmount),
            'billing_day' => $billingDay,
            'billing_interval_unit' => $intervalUnit,
            'billing_interval_count' => $intervalCount,
            'first_payment_date' => $checkoutDate->format('Y-m-d'),
            'recurring_due_dates' => array_map(
                fn (CarbonImmutable $date) => $date->format('Y-m-d'),
                $recurringDueDates,
            ),
            'final_due_at' => $finalDueAt->format('Y-m-d'),
            'grace_deadlines' => $graceDeadlines,
            'schedule_breakdown' => $scheduleBreakdown,
        ];
    }

    private function normalizeDate(CarbonInterface|string|null $checkoutAt): CarbonImmutable
    {
        if ($checkoutAt instanceof CarbonInterface) {
            return CarbonImmutable::instance($checkoutAt);
        }

        if (is_string($checkoutAt)) {
            return CarbonImmutable::parse($checkoutAt)->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }

    private function resolveBillingDay(Package $package, ?int $selectedBillingDay = null): ?int
    {
        if (! $package->usesMonthlyInstallmentCycle()) {
            return null;
        }

        if ($selectedBillingDay !== null) {
            if (! in_array($selectedBillingDay, [1, 15], true)) {
                throw new InvalidArgumentException('Selected billing day must be either 1 or 15.');
            }

            if (! in_array($selectedBillingDay, $package->resolvedAllowedBillingDays(), true)) {
                throw new InvalidArgumentException('Selected billing day is not available for this package.');
            }

            return $selectedBillingDay;
        }

        return $package->resolvedAllowedBillingDays()[0] ?? 15;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveDeadline(Package $package, ?int $billingDay): array
    {
        $month = (int) ($package->installment_deadline_month ?: 1);
        $day = $package->usesMonthlyInstallmentCycle()
            ? (int) ($billingDay ?? 15)
            : (int) ($package->installment_deadline_day ?: 15);

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Package installment deadline month is invalid.');
        }

        return [$month, $day];
    }

    private function resolveFinalDueAt(
        CarbonImmutable $checkoutDate,
        int $deadlineMonth,
        int $deadlineDay,
    ): CarbonImmutable {
        $year = $checkoutDate->month > $deadlineMonth
            ? $checkoutDate->year + 1
            : $checkoutDate->year;

        return CarbonImmutable::create($year, $deadlineMonth, $deadlineDay)->startOfDay();
    }

    private function resolveIntervalUnit(Package $package): string
    {
        return $package->normalizedBillingIntervalUnit() ?: 'MONTH';
    }

    private function resolveIntervalCount(Package $package): int
    {
        $intervalCount = (int) ($package->billing_interval_count ?: 1);

        return max(1, $intervalCount);
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function buildRecurringDueDates(
        CarbonImmutable $checkoutDate,
        CarbonImmutable $finalDueAt,
        ?int $billingDay,
        string $intervalUnit,
        int $intervalCount,
    ): array {
        if ($intervalUnit === 'MONTH') {
            $firstRecurringMonth = $checkoutDate->startOfMonth()->addMonth();
            $cursor = CarbonImmutable::create(
                $firstRecurringMonth->year,
                $firstRecurringMonth->month,
                (int) ($billingDay ?? 15),
            )->startOfDay();

            $dates = [];

            while ($cursor->lessThanOrEqualTo($finalDueAt)) {
                $dates[] = $cursor;
                $cursor = $cursor->addMonthsNoOverflow($intervalCount);
            }

            return $dates;
        }

        $dates = [];
        $cursor = match ($intervalUnit) {
            'DAY' => $checkoutDate->addDays($intervalCount),
            'WEEK' => $checkoutDate->addWeeks($intervalCount),
            'YEAR' => $checkoutDate->addYears($intervalCount),
            default => $checkoutDate->addDays($intervalCount),
        };

        while ($cursor->lessThanOrEqualTo($finalDueAt)) {
            $dates[] = $cursor;
            $cursor = match ($intervalUnit) {
                'DAY' => $cursor->addDays($intervalCount),
                'WEEK' => $cursor->addWeeks($intervalCount),
                'YEAR' => $cursor->addYears($intervalCount),
                default => $cursor->addDays($intervalCount),
            };
        }

        return $dates;
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
