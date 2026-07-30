<?php

namespace App\Services\Accommodations;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

class AccommodationInstallmentPlanCalculator
{
    public function isEligible(Accommodation $accommodation): bool
    {
        return (bool) $accommodation->installment_enabled;
    }

    /**
     * Non-throwing eligibility check for a specific booking, used to decide
     * whether the installment option should even be shown to the guest.
     */
    public function isEligibleForBooking(
        AccommodationBooking $booking,
        CarbonInterface|string|null $checkoutAt = null,
    ): bool {
        if (! $this->isEligible($booking->accommodation)) {
            return false;
        }

        return $this->maximumInstallmentCount($booking, $checkoutAt) >= Accommodation::MIN_INSTALLMENT_COUNT;
    }

    public function maximumInstallmentCount(
        AccommodationBooking $booking,
        CarbonInterface|string|null $checkoutAt = null,
    ): int {
        $checkoutDate = $this->normalizeDate($checkoutAt);
        $deadlineDate = $this->resolveDeadlineDate($booking);
        $billingDay = $checkoutDate->day;

        $recurringDueDates = $this->buildMonthlyRecurringDueDatesUntilDeadline(
            checkoutDate: $checkoutDate,
            deadlineDate: $deadlineDate,
            billingDay: $billingDay,
        );

        return min(1 + count($recurringDueDates), Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        AccommodationBooking $booking,
        CarbonInterface|string|null $checkoutAt = null,
        ?int $installmentCount = null,
    ): array {
        $accommodation = $booking->accommodation;

        if (! $this->isEligible($accommodation)) {
            throw new DomainException('This accommodation is not eligible for installment checkout.');
        }

        $checkoutDate = $this->normalizeDate($checkoutAt);
        $deadlineDate = $this->resolveDeadlineDate($booking);

        if ($checkoutDate->greaterThanOrEqualTo($deadlineDate)) {
            throw new DomainException('The installment window has already closed for this booking.');
        }

        /*
        |--------------------------------------------------------------------------
        | Billing day
        |--------------------------------------------------------------------------
        |
        | Unlike Package, accommodations have no admin-configurable billing day
        | (no allowed_billing_days/fixed_billing_day equivalent). The recurring
        | day-of-month is simply the day the installment checkout happens on.
        */
        $billingDay = $checkoutDate->day;

        $maximumRecurringDueDates = $this->buildMonthlyRecurringDueDatesUntilDeadline(
            checkoutDate: $checkoutDate,
            deadlineDate: $deadlineDate,
            billingDay: $billingDay,
        );

        $maximumInstallmentCount = min(
            1 + count($maximumRecurringDueDates),
            Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT,
        );

        if ($maximumInstallmentCount < Accommodation::MIN_INSTALLMENT_COUNT) {
            throw new DomainException('This booking does not have enough billing dates for installment checkout.');
        }

        $selectedInstallmentCount = $accommodation->installment_count_mode === Accommodation::INSTALLMENT_COUNT_MODE_FIXED
            ? $this->resolveFixedInstallmentCount($accommodation, $maximumInstallmentCount)
            : $this->resolveFlexibleInstallmentCount($installmentCount, $maximumInstallmentCount);

        $selectedRecurringDueDates = array_slice(
            $maximumRecurringDueDates,
            0,
            max(0, $selectedInstallmentCount - 1),
        );

        return $this->buildPlanPayload(
            booking: $booking,
            checkoutDate: $checkoutDate,
            billingDay: $billingDay,
            selectedInstallmentCount: $selectedInstallmentCount,
            maximumInstallmentCount: $maximumInstallmentCount,
            recurringDueDates: $selectedRecurringDueDates,
            availableRecurringDueDates: array_slice(
                $maximumRecurringDueDates,
                0,
                max(0, $maximumInstallmentCount - 1),
            ),
            deadlineDate: $deadlineDate,
            countMode: $accommodation->installment_count_mode,
        );
    }

    /**
     * @param  array<int, CarbonImmutable>  $recurringDueDates
     * @param  array<int, CarbonImmutable>  $availableRecurringDueDates
     * @return array<string, mixed>
     */
    private function buildPlanPayload(
        AccommodationBooking $booking,
        CarbonImmutable $checkoutDate,
        int $billingDay,
        int $selectedInstallmentCount,
        int $maximumInstallmentCount,
        array $recurringDueDates,
        array $availableRecurringDueDates,
        CarbonImmutable $deadlineDate,
        ?string $countMode,
    ): array {
        $finalDueAt = $recurringDueDates !== []
            ? end($recurringDueDates)
            : $checkoutDate;

        if (! $finalDueAt instanceof CarbonImmutable) {
            $finalDueAt = $checkoutDate;
        }

        $totalAmount = $this->normalizeAmount((float) $booking->total_amount);
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
            'currency_code' => (string) $booking->currency_code,
            'installment_count' => $selectedInstallmentCount,
            'maximum_installment_count' => $maximumInstallmentCount,
            'minimum_installment_count' => Accommodation::MIN_INSTALLMENT_COUNT,
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

    private function resolveDeadlineDate(AccommodationBooking $booking): CarbonImmutable
    {
        if ($booking->check_in_date === null) {
            throw new InvalidArgumentException('Booking check-in date is required.');
        }

        if ($booking->check_in_date instanceof CarbonInterface) {
            return CarbonImmutable::instance($booking->check_in_date)->startOfDay();
        }

        return CarbonImmutable::parse((string) $booking->check_in_date)->startOfDay();
    }

    private function resolveFlexibleInstallmentCount(
        ?int $selectedInstallmentCount,
        int $maximumInstallmentCount,
    ): int {
        if ($selectedInstallmentCount === null) {
            return $maximumInstallmentCount;
        }

        if ($selectedInstallmentCount < Accommodation::MIN_INSTALLMENT_COUNT) {
            throw new InvalidArgumentException('Installment count must be at least '.Accommodation::MIN_INSTALLMENT_COUNT.'.');
        }

        if ($selectedInstallmentCount > $maximumInstallmentCount) {
            throw new InvalidArgumentException('Installment count exceeds the maximum available installment count.');
        }

        return $selectedInstallmentCount;
    }

    /**
     * Accommodation's fixed mode clamps the configured count down to whatever
     * actually fits before check-in, rather than rejecting the booking outright
     * like Package's number-based fixed mode does.
     */
    private function resolveFixedInstallmentCount(
        Accommodation $accommodation,
        int $maximumInstallmentCount,
    ): int {
        $configuredCount = (int) $accommodation->installment_fixed_count;

        if ($configuredCount < Accommodation::MIN_INSTALLMENT_COUNT) {
            throw new InvalidArgumentException('Accommodation fixed installment count must be at least '.Accommodation::MIN_INSTALLMENT_COUNT.'.');
        }

        return min($configuredCount, $maximumInstallmentCount);
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

        while ($cursor->lessThanOrEqualTo($deadlineDate) && count($dates) < (Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT - 1)) {
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

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
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
