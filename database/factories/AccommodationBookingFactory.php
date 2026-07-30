<?php

namespace Database\Factories;

use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccommodationBooking>
 */
class AccommodationBookingFactory extends Factory
{
    protected $model = AccommodationBooking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Eagerly created (not passed as a lazy factory reference) so both FK
        // columns below can be read from the same resolved room type.
        $roomType = AccommodationRoomType::factory()->create();
        $checkIn = fake()->dateTimeBetween('+1 day', '+10 days');
        $nights = fake()->numberBetween(1, 5);
        $checkOut = (clone $checkIn)->modify("+{$nights} days");
        $pricePerNight = fake()->randomFloat(2, 30, 300);

        return [
            'booking_number' => 'BOOK-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'accommodation_id' => $roomType->accommodation_id,
            'accommodation_room_type_id' => $roomType->id,
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'guest_country' => fake()->country(),
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'nights' => $nights,
            'price_per_night' => $pricePerNight,
            'total_amount' => round($pricePerNight * $nights, 2),
            'currency_code' => 'USD',
            'status' => AccommodationBooking::STATUS_CONFIRMED,
            'paypal_order_id' => null,
            'hold_expires_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pendingPayment(?\DateTimeInterface $holdExpiresAt = null): static
    {
        return $this->state(fn () => [
            'status' => AccommodationBooking::STATUS_PENDING_PAYMENT,
            'hold_expires_at' => $holdExpiresAt ?? now()->addMinutes(30),
            'paid_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => AccommodationBooking::STATUS_CONFIRMED,
            'hold_expires_at' => null,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => AccommodationBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => AccommodationBooking::STATUS_EXPIRED,
            'hold_expires_at' => now()->subMinutes(5),
        ]);
    }
}
