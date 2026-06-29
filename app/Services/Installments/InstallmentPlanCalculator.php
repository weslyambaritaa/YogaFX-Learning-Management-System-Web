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
        if (! $this->isEligible($package)) {
            throw new DomainException('This package is not eligible for installment checkout.');
        }

        $checkoutDate = $this->normalizeDate($checkoutAt);
        $billingDay = $this->resolveBillingDay($package, $billingDay);
        [$deadlineMonth, $deadlineDay] = $this->resolveDeadline($package, $billingDay);
        $finalDueAt = $this->resolveFinalDueAt($checkoutDate, $deadlineMonth, $deadlineDay);

        if ($checkoutDate->greaterThan($finalDueAt)) {
            throw new DomainException('The installment window has already closed for this checkout date.');
        }

        $recurringDueDates = $this->buildRecurringDueDates(
            $checkoutDate,
            $finalDueAt,
            $billingDay,
        );

        $installmentCount = 1 + count($recurringDueDates);
        $totalAmount = $this->normalizeAmount($package->price);
        $monthlyBaseAmount = floor($totalAmount / $installmentCount);
        $firstPaymentAmount = $totalAmount - ($monthlyBaseAmount * ($installmentCount - 1));

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
                'amount' => $this->formatAmount($monthlyBaseAmount),
                'due_at' => $dueDate->format('Y-m-d'),
                'grace_deadline' => $dueDate->addDays(3)->format('Y-m-d'),
            ];
        }

        return [
            'total_amount' => $this->formatAmount($totalAmount),
            'currency_code' => (string) $package->currency_code,
            'installment_count' => $installmentCount,
            'first_payment_amount' => $this->formatAmount($firstPaymentAmount),
            'monthly_base_amount' => $this->formatAmount($monthlyBaseAmount),
            'recurring_payment_amount' => $this->formatAmount($monthlyBaseAmount),
            'billing_day' => $billingDay,
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

    private function resolveBillingDay(Package $package, ?int $selectedBillingDay = null): int
    {
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
    private function resolveDeadline(Package $package, int $billingDay): array
    {
        $month = (int) ($package->installment_deadline_month ?: 1);
        $day = $billingDay;

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

    /**
     * @return array<int, CarbonImmutable>
     */
    private function buildRecurringDueDates(
        CarbonImmutable $checkoutDate,
        CarbonImmutable $finalDueAt,
        int $billingDay,
    ): array {
        $firstRecurringMonth = $checkoutDate->startOfMonth()->addMonth();
        $cursor = CarbonImmutable::create(
            $firstRecurringMonth->year,
            $firstRecurringMonth->month,
            $billingDay,
        )->startOfDay();

        $dates = [];

        while ($cursor->lessThanOrEqualTo($finalDueAt)) {
            $dates[] = $cursor;
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $dates;
    }

    private function normalizeAmount(float|int|string $amount): float
    {
        return round((float) $amount, 2);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
