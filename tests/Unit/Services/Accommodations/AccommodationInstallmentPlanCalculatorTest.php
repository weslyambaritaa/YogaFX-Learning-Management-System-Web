<?php

namespace Tests\Unit\Services\Accommodations;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Services\Accommodations\AccommodationInstallmentPlanCalculator;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

class AccommodationInstallmentPlanCalculatorTest extends TestCase
{
    public function test_flexible_mode_builds_schedule_from_checkout_until_check_in(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligibleBooking(bookingOverrides: [
                'total_amount' => 300,
                'check_in_date' => '2026-12-08',
            ]),
            '2026-07-08',
        );

        $this->assertSame(2, $plan['minimum_installment_count']);
        $this->assertSame(6, $plan['maximum_installment_count']);
        $this->assertSame(6, $plan['installment_count']);
        $this->assertSame('2026-07-08', $plan['first_payment_date']);
        $this->assertSame('2026-08-08', $plan['recurring_due_dates'][0]);
        $this->assertSame('2026-12-08', $plan['final_due_at']);
        $this->assertSame('2026-12-08', $plan['deadline_date']);
    }

    public function test_flexible_mode_accepts_selected_count_below_maximum(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligibleBooking(bookingOverrides: [
                'check_in_date' => '2026-12-08',
            ]),
            '2026-07-08',
            3,
        );

        $this->assertSame(3, $plan['installment_count']);
        $this->assertCount(2, $plan['recurring_due_dates']);
        $this->assertSame('2026-09-08', $plan['final_due_at']);
    }

    public function test_flexible_mode_rejects_selected_count_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->calculate(
            $this->eligibleBooking(bookingOverrides: [
                'check_in_date' => '2026-12-08',
            ]),
            '2026-07-08',
            7,
        );
    }

    public function test_caps_maximum_installment_count_to_provider_limit(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligibleBooking(bookingOverrides: [
                'check_in_date' => '2029-01-08',
            ]),
            '2026-07-08',
        );

        $this->assertSame(Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT, $plan['maximum_installment_count']);
        $this->assertSame(Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT, $plan['installment_count']);
        $this->assertCount(Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT - 1, $plan['available_recurring_due_dates']);
    }

    public function test_exact_boundary_of_two_installments_is_eligible(): void
    {
        $booking = $this->eligibleBooking(bookingOverrides: [
            'check_in_date' => '2026-08-08',
        ]);

        $this->assertTrue($this->calculator()->isEligibleForBooking($booking, '2026-07-08'));
        $this->assertSame(2, $this->calculator()->maximumInstallmentCount($booking, '2026-07-08'));

        $plan = $this->calculator()->calculate($booking, '2026-07-08');

        $this->assertSame(2, $plan['installment_count']);
    }

    public function test_less_than_one_month_until_check_in_is_not_eligible(): void
    {
        $booking = $this->eligibleBooking(bookingOverrides: [
            'check_in_date' => '2026-08-07',
        ]);

        $this->assertFalse($this->calculator()->isEligibleForBooking($booking, '2026-07-08'));
        $this->assertSame(1, $this->calculator()->maximumInstallmentCount($booking, '2026-07-08'));

        $this->expectException(DomainException::class);

        $this->calculator()->calculate($booking, '2026-07-08');
    }

    public function test_rejects_checkout_on_or_after_check_in_date(): void
    {
        $booking = $this->eligibleBooking(bookingOverrides: [
            'check_in_date' => '2026-07-08',
        ]);

        $this->assertFalse($this->calculator()->isEligibleForBooking($booking, '2026-07-08'));

        $this->expectException(DomainException::class);

        $this->calculator()->calculate($booking, '2026-07-08');
    }

    public function test_disabled_accommodation_is_never_eligible(): void
    {
        $booking = $this->eligibleBooking(
            accommodationOverrides: ['installment_enabled' => false],
            bookingOverrides: ['check_in_date' => '2026-12-08'],
        );

        $this->assertFalse($this->calculator()->isEligibleForBooking($booking, '2026-07-08'));

        $this->expectException(DomainException::class);

        $this->calculator()->calculate($booking, '2026-07-08');
    }

    public function test_fixed_mode_clamps_configured_count_down_to_what_fits(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligibleBooking(
                accommodationOverrides: [
                    'installment_count_mode' => Accommodation::INSTALLMENT_COUNT_MODE_FIXED,
                    'installment_fixed_count' => 10,
                ],
                bookingOverrides: [
                    'check_in_date' => '2026-09-08',
                ],
            ),
            '2026-07-08',
        );

        $this->assertSame(3, $plan['maximum_installment_count']);
        $this->assertSame(3, $plan['installment_count']);
        $this->assertCount(2, $plan['recurring_due_dates']);
    }

    public function test_fixed_mode_uses_configured_count_when_it_fits(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligibleBooking(
                accommodationOverrides: [
                    'installment_count_mode' => Accommodation::INSTALLMENT_COUNT_MODE_FIXED,
                    'installment_fixed_count' => 2,
                ],
                bookingOverrides: [
                    'check_in_date' => '2026-09-08',
                ],
            ),
            '2026-07-08',
        );

        $this->assertSame(2, $plan['installment_count']);
        $this->assertCount(1, $plan['recurring_due_dates']);
    }

    public function test_uneven_total_amount_puts_the_remainder_on_the_first_payment(): void
    {
        $plan = $this->calculator()->calculate(
            $this->eligibleBooking(bookingOverrides: [
                'total_amount' => 301,
                'check_in_date' => '2026-09-08',
            ]),
            '2026-07-08',
        );

        $this->assertSame(3, $plan['installment_count']);
        $this->assertSame('100.34', $plan['first_payment_amount']);
        $this->assertSame('100.33', $plan['monthly_base_amount']);
        $this->assertSame('301.00', $plan['total_amount']);

        $sum = (float) $plan['first_payment_amount']
            + array_sum(array_map(
                fn (array $cycle) => (float) $cycle['amount'],
                array_slice($plan['schedule_breakdown'], 1),
            ));

        $this->assertSame(301.0, round($sum, 2));
    }

    private function calculator(): AccommodationInstallmentPlanCalculator
    {
        return new AccommodationInstallmentPlanCalculator();
    }

    private function eligibleBooking(array $accommodationOverrides = [], array $bookingOverrides = []): AccommodationBooking
    {
        $accommodation = new Accommodation(array_merge([
            'title' => 'Ubud Retreat Villa',
            'slug' => 'ubud-retreat-villa',
            'currency_code' => 'USD',
            'is_active' => true,
            'installment_enabled' => true,
            'installment_count_mode' => Accommodation::INSTALLMENT_COUNT_MODE_FLEX,
            'installment_fixed_count' => null,
        ], $accommodationOverrides));

        $booking = new AccommodationBooking(array_merge([
            'booking_number' => 'ACC-000001',
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '+62800000000',
            'check_in_date' => '2026-12-08',
            'check_out_date' => '2026-12-10',
            'nights' => 2,
            'price_per_night' => 150,
            'total_amount' => 300,
            'currency_code' => 'USD',
            'status' => AccommodationBooking::STATUS_PENDING_PAYMENT,
        ], $bookingOverrides));

        $booking->setRelation('accommodation', $accommodation);

        return $booking;
    }
}
