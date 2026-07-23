<?php

namespace Tests\Unit;

use App\Models\AccessTier;
use App\Models\Package;
use App\Services\Installments\InstallmentPlanCalculator;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

class InstallmentPlanCalculatorTest extends TestCase
{
    public function test_date_strategy_builds_schedule_from_checkout_until_deadline(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(overrides: [
                'price' => 300,
                'installment_deadline_date' => '2027-03-15',
            ]),
            '2026-07-08',
            15,
        );

        $this->assertSame('date', $plan['installment_calculation_method']);
        $this->assertNull($plan['installment_count_mode']);
        $this->assertTrue($plan['installment_count_selectable']);
        $this->assertSame(2, $plan['minimum_installment_count']);
        $this->assertSame(9, $plan['maximum_installment_count']);
        $this->assertSame(9, $plan['installment_count']);
        $this->assertSame('2026-07-08', $plan['first_payment_date']);
        $this->assertSame('2026-08-15', $plan['recurring_due_dates'][0]);
        $this->assertSame('2027-03-15', $plan['final_due_at']);
    }

    public function test_date_strategy_accepts_selected_count_below_maximum(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(overrides: [
                'installment_deadline_date' => '2027-03-15',
            ]),
            '2026-07-08',
            15,
            5,
        );

        $this->assertSame(5, $plan['installment_count']);
        $this->assertCount(4, $plan['recurring_due_dates']);
        $this->assertSame('2026-11-15', $plan['final_due_at']);
    }

    public function test_date_strategy_rejects_selected_count_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->calculate(
            $this->eligiblePackage(overrides: [
                'installment_deadline_date' => '2027-03-15',
            ]),
            '2026-07-08',
            15,
            10,
        );
    }

    public function test_date_strategy_rejects_past_deadline(): void
    {
        $this->expectException(DomainException::class);

        $this->calculator()->calculate(
            $this->eligiblePackage(overrides: [
                'installment_deadline_date' => '2026-07-01',
            ]),
            '2026-07-08',
            15,
        );
    }

    public function test_date_strategy_caps_maximum_installment_count_to_provider_limit(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligiblePackage(overrides: [
                'installment_deadline_date' => '2028-12-15',
            ]),
            '2026-07-08',
            15,
        );

        $this->assertSame(Package::MAX_PROVIDER_INSTALLMENT_COUNT, $plan['maximum_installment_count']);
        $this->assertSame(Package::MAX_PROVIDER_INSTALLMENT_COUNT, $plan['installment_count']);
        $this->assertCount(Package::MAX_PROVIDER_INSTALLMENT_COUNT - 1, $plan['available_recurring_due_dates']);
    }

    public function test_number_flex_accepts_selected_values_within_configured_limit(): void
    {
        $package = $this->eligiblePackage(overrides: [
            'installment_calculation_method' => Package::INSTALLMENT_CALCULATION_NUMBER,
            'installment_count_mode' => Package::INSTALLMENT_COUNT_MODE_FLEX,
            'installment_count' => 9,
            'installment_deadline_date' => null,
        ]);

        $planTwo = $this->calculator()->calculate($package, '2026-07-08', 15, 2);
        $planFive = $this->calculator()->calculate($package, '2026-07-08', 15, 5);
        $planNine = $this->calculator()->calculate($package, '2026-07-08', 15, 9);

        $this->assertSame('number', $planFive['installment_calculation_method']);
        $this->assertSame('flex', $planFive['installment_count_mode']);
        $this->assertTrue($planFive['installment_count_selectable']);
        $this->assertSame(2, $planFive['minimum_installment_count']);
        $this->assertSame(9, $planFive['maximum_installment_count']);
        $this->assertSame(2, $planTwo['installment_count']);
        $this->assertSame(5, $planFive['installment_count']);
        $this->assertSame(9, $planNine['installment_count']);
        $this->assertNull($planFive['deadline_date']);
    }

    public function test_number_flex_rejects_selected_value_above_configured_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->calculate(
            $this->eligiblePackage(overrides: [
                'installment_calculation_method' => Package::INSTALLMENT_CALCULATION_NUMBER,
                'installment_count_mode' => Package::INSTALLMENT_COUNT_MODE_FLEX,
                'installment_count' => 9,
                'installment_deadline_date' => null,
            ]),
            '2026-07-08',
            15,
            10,
        );
    }

    public function test_number_fixed_defaults_to_configured_count_and_disables_selection(): void
    {
        $package = $this->eligiblePackage(overrides: [
            'installment_calculation_method' => Package::INSTALLMENT_CALCULATION_NUMBER,
            'installment_count_mode' => Package::INSTALLMENT_COUNT_MODE_FIXED,
            'installment_count' => 9,
            'installment_deadline_date' => null,
        ]);

        $plan = $this->calculator()->calculate($package, '2026-07-08', 15, null);

        $this->assertSame('number', $plan['installment_calculation_method']);
        $this->assertSame('fixed', $plan['installment_count_mode']);
        $this->assertFalse($plan['installment_count_selectable']);
        $this->assertSame(9, $plan['configured_installment_count']);
        $this->assertSame(9, $plan['fixed_installment_count']);
        $this->assertSame(9, $plan['minimum_installment_count']);
        $this->assertSame(9, $plan['maximum_installment_count']);
        $this->assertSame(9, $plan['installment_count']);
        $this->assertCount(8, $plan['recurring_due_dates']);
        $this->assertSame('2026-08-15', $plan['recurring_due_dates'][0]);
    }

    public function test_number_fixed_accepts_exact_count_and_rejects_different_count(): void
    {
        $package = $this->eligiblePackage(overrides: [
            'installment_calculation_method' => Package::INSTALLMENT_CALCULATION_NUMBER,
            'installment_count_mode' => Package::INSTALLMENT_COUNT_MODE_FIXED,
            'installment_count' => 9,
            'installment_deadline_date' => null,
        ]);

        $acceptedPlan = $this->calculator()->calculate($package, '2026-07-08', 15, 9);
        $this->assertSame(9, $acceptedPlan['installment_count']);

        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->calculate($package, '2026-07-08', 15, 5);
    }

    private function calculator(): InstallmentPlanCalculator
    {
        return new InstallmentPlanCalculator();
    }

    private function eligiblePackage(array $overrides = []): Package
    {
        return new Package(array_merge([
            'access_tier_id' => 1,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'description' => 'Standard package.',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'is_active' => true,
            'installment_enabled' => true,
            'installment_calculation_method' => Package::INSTALLMENT_CALCULATION_DATE,
            'installment_count_mode' => null,
            'installment_count' => null,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_date' => '2027-01-15',
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
        ], $overrides));
    }
}
