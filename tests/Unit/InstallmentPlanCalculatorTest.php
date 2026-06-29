<?php

namespace Tests\Unit;

use App\Models\AccessTier;
use App\Models\Package;
use App\Services\Installments\InstallmentPlanCalculator;
use DomainException;
use PHPUnit\Framework\TestCase;

class InstallmentPlanCalculatorTest extends TestCase
{
    public function test_checkout_in_september_builds_schedule_until_next_january(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 250),
            '2026-09-10',
            15,
        );

        $this->assertSame('250.00', $plan['total_amount']);
        $this->assertSame(AccessTier::CURRENCY_USD, $plan['currency_code']);
        $this->assertSame(5, $plan['installment_count']);
        $this->assertSame('50.00', $plan['first_payment_amount']);
        $this->assertSame('50.00', $plan['monthly_base_amount']);
        $this->assertSame('50.00', $plan['recurring_payment_amount']);
        $this->assertSame('2026-09-10', $plan['first_payment_date']);
        $this->assertSame([
            '2026-10-15',
            '2026-11-15',
            '2026-12-15',
            '2027-01-15',
        ], $plan['recurring_due_dates']);
        $this->assertSame('2027-01-15', $plan['final_due_at']);
        $this->assertSame([
            '2026-10-18',
            '2026-11-18',
            '2026-12-18',
            '2027-01-18',
        ], $plan['grace_deadlines']);
    }

    public function test_three_hundred_total_with_seven_cycles_produces_expected_split(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-07-10',
            15,
        );

        $this->assertSame(7, $plan['installment_count']);
        $this->assertSame('48.00', $plan['first_payment_amount']);
        $this->assertSame('42.00', $plan['monthly_base_amount']);
        $this->assertSame('42.00', $plan['recurring_payment_amount']);
        $this->assertSame([
            '2026-08-15',
            '2026-09-15',
            '2026-10-15',
            '2026-11-15',
            '2026-12-15',
            '2027-01-15',
        ], $plan['recurring_due_dates']);
    }

    public function test_checkout_before_the_15th_counts_current_month_as_first_cycle(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-09-14',
            15,
        );

        $this->assertSame('2026-09-14', $plan['first_payment_date']);
        $this->assertSame(5, $plan['installment_count']);
        $this->assertSame('2026-10-15', $plan['recurring_due_dates'][0]);
    }

    public function test_checkout_on_the_15th_keeps_first_payment_on_checkout_date(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-09-15',
            15,
        );

        $this->assertSame('2026-09-15', $plan['first_payment_date']);
        $this->assertSame(5, $plan['installment_count']);
        $this->assertSame('2026-10-15', $plan['recurring_due_dates'][0]);
    }

    public function test_checkout_after_the_15th_starts_recurring_on_next_month(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-09-16',
            15,
        );

        $this->assertSame('2026-09-16', $plan['first_payment_date']);
        $this->assertSame(5, $plan['installment_count']);
        $this->assertSame([
            '2026-10-15',
            '2026-11-15',
            '2026-12-15',
            '2027-01-15',
        ], $plan['recurring_due_dates']);
    }

    public function test_checkout_in_december_only_has_january_recurring_cycle(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-12-05',
            15,
        );

        $this->assertSame(2, $plan['installment_count']);
        $this->assertSame([
            '2027-01-15',
        ], $plan['recurring_due_dates']);
        $this->assertSame('150.00', $plan['first_payment_amount']);
        $this->assertSame('150.00', $plan['recurring_payment_amount']);
    }

    public function test_checkout_in_january_before_deadline_has_single_payment_cycle(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2027-01-10',
            15,
        );

        $this->assertSame(1, $plan['installment_count']);
        $this->assertSame('300.00', $plan['first_payment_amount']);
        $this->assertSame('300.00', $plan['monthly_base_amount']);
        $this->assertSame([], $plan['recurring_due_dates']);
        $this->assertSame([], $plan['grace_deadlines']);
        $this->assertSame('2027-01-15', $plan['final_due_at']);
    }

    public function test_package_with_installment_disabled_is_not_eligible(): void
    {
        $package = $this->eligiblePackage(price: 300)->forceFill([
            'installment_enabled' => false,
        ]);

        $this->assertFalse($this->calculator()->isEligible($package));
    }

    public function test_package_without_assigned_tier_is_not_eligible(): void
    {
        $package = $this->eligiblePackage(price: 300)->forceFill([
            'access_tier_id' => null,
        ]);

        $this->assertFalse($this->calculator()->isEligible($package));
    }

    public function test_grace_deadline_is_always_plus_three_days_from_due_date(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-12-05',
            15,
        );

        $this->assertSame('2027-01-18', $plan['grace_deadlines'][0]);
        $this->assertSame('2027-01-18', $plan['schedule_breakdown'][1]['grace_deadline']);
    }

    public function test_calculate_throws_when_installment_window_has_closed(): void
    {
        $this->expectException(DomainException::class);

        $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2027-01-16',
            15,
        );
    }

    public function test_checkout_in_september_can_build_schedule_for_billing_day_1(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-09-10',
            1,
        );

        $this->assertSame(5, $plan['installment_count']);
        $this->assertSame(1, $plan['billing_day']);
        $this->assertSame([
            '2026-10-01',
            '2026-11-01',
            '2026-12-01',
            '2027-01-01',
        ], $plan['recurring_due_dates']);
        $this->assertSame('2027-01-01', $plan['final_due_at']);
    }

    public function test_calculate_rejects_billing_day_outside_allowed_choices(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator()->calculate(
            $this->eligiblePackage(price: 300),
            '2026-09-10',
            7,
        );
    }

    public function test_calculate_rejects_billing_day_not_allowed_by_package(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator()->calculate(
            $this->eligiblePackage(price: 300, allowedBillingDays: [15]),
            '2026-09-10',
            1,
        );
    }

    private function calculator(): InstallmentPlanCalculator
    {
        return new InstallmentPlanCalculator();
    }

    private function eligiblePackage(int $price, array $allowedBillingDays = [1, 15]): Package
    {
        return new Package([
            'access_tier_id' => 1,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'description' => 'Standard package.',
            'price' => $price,
            'currency_code' => AccessTier::CURRENCY_USD,
            'is_active' => true,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => $allowedBillingDays,
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
        ]);
    }
}
