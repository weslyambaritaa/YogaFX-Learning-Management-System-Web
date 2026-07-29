<?php

namespace Tests\Unit\Services\Accommodations;

use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use App\Services\Accommodations\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RoomAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_rooms_returns_full_count_when_no_bookings_exist(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 3]);

        $available = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-01'),
            $this->date('2026-08-05'),
        );

        $this->assertSame(3, $available);
    }

    public function test_confirmed_booking_reduces_availability(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-01',
                'check_out_date' => '2026-08-05',
            ]);

        $available = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-02'),
            $this->date('2026-08-03'),
        );

        $this->assertSame(0, $available);
    }

    public function test_pending_payment_booking_with_active_hold_reduces_availability(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        $this->travelTo($this->date('2026-08-01 10:00:00'));

        AccommodationBooking::factory()
            ->pendingPayment(holdExpiresAt: $this->date('2026-08-01 10:30:00'))
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-10',
                'check_out_date' => '2026-08-12',
            ]);

        $available = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
        );

        $this->assertSame(0, $available);
    }

    public function test_pending_payment_booking_with_expired_hold_does_not_reduce_availability(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        AccommodationBooking::factory()
            ->pendingPayment(holdExpiresAt: $this->date('2026-08-01 10:00:00'))
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-10',
                'check_out_date' => '2026-08-12',
            ]);

        // Current time is after hold_expires_at, so this booking must not count.
        $this->travelTo($this->date('2026-08-01 10:30:00'));

        $available = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
        );

        $this->assertSame(1, $available);
    }

    public function test_cancelled_booking_does_not_reduce_availability(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        AccommodationBooking::factory()
            ->cancelled()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-10',
                'check_out_date' => '2026-08-12',
            ]);

        $available = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
        );

        $this->assertSame(1, $available);
    }

    public function test_expired_status_booking_does_not_reduce_availability(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        AccommodationBooking::factory()
            ->expired()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-10',
                'check_out_date' => '2026-08-12',
            ]);

        $available = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
        );

        $this->assertSame(1, $available);
    }

    /**
     * Explicit half-open boundary test: a checkout on day X must never collide
     * with a check-in on day X for the same room. Both bookings must be able
     * to exist and both ranges must independently read as fully booked.
     */
    public function test_checkout_date_equal_to_next_checkin_date_does_not_overlap(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        $bookingA = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-01',
                'check_out_date' => '2026-08-05',
            ]);

        // Booking B check-in is exactly booking A's checkout date.
        $bookingB = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-05',
                'check_out_date' => '2026-08-08',
            ]);

        $this->assertNotNull($bookingA->id);
        $this->assertNotNull($bookingB->id);

        // Requesting the exact boundary date range (Aug 5) must still show 0
        // available, because it collides with booking B only, not booking A.
        $availableOnBoundary = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-05'),
            $this->date('2026-08-06'),
        );
        $this->assertSame(0, $availableOnBoundary);

        // A third booking that starts the moment booking A ends and finishes
        // the moment booking B starts must be fully available (no overlap
        // with either existing booking).
        $availableBetween = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-05'),
            $this->date('2026-08-05'),
        );
        // check_in == check_out is not a real stay, but confirms the query
        // itself treats the touching boundary as zero-width, non-overlapping.
        $this->assertSame(1, $availableBetween);
    }

    public function test_reserve_with_lock_creates_pending_payment_booking_with_hold(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        $this->travelTo($this->date('2026-08-01 09:00:00'));

        $booking = $this->service()->reserveWithLock(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
            $this->bookingAttributes(),
        );

        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertSame($roomType->accommodation_id, $booking->accommodation_id);
        $this->assertSame($roomType->id, $booking->accommodation_room_type_id);
        $this->assertNotNull($booking->hold_expires_at);
        $this->assertTrue(
            $booking->hold_expires_at->equalTo($this->date('2026-08-01 09:30:00')),
        );
    }

    public function test_reserve_with_lock_throws_when_no_room_is_available(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-10',
                'check_out_date' => '2026-08-12',
            ]);

        $this->expectException(RuntimeException::class);

        $this->service()->reserveWithLock(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
            $this->bookingAttributes(),
        );
    }

    /**
     * Sequential attempts on the last remaining room: the second attempt must
     * fail because reserveWithLock recomputes availability from inside the
     * locked transaction rather than trusting a stale outside count. Genuine
     * concurrent-thread contention cannot be exercised in a single-process
     * PHPUnit run against SQLite, but this proves the recomputation itself is
     * correct — the same code path guarded by lockForUpdate() is what
     * prevents two simultaneous Postgres requests from both succeeding.
     */
    public function test_second_sequential_reservation_for_the_last_room_fails(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        $firstBooking = $this->service()->reserveWithLock(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
            $this->bookingAttributes(bookingNumber: 'BOOK-2026-000001'),
        );

        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $firstBooking->status);

        $this->expectException(RuntimeException::class);

        $this->service()->reserveWithLock(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
            $this->bookingAttributes(bookingNumber: 'BOOK-2026-000002'),
        );
    }

    public function test_exclude_booking_id_ignores_the_booking_itself_when_revalidating(): void
    {
        $roomType = AccommodationRoomType::factory()->create(['total_rooms' => 1]);

        $booking = AccommodationBooking::factory()
            ->pendingPayment(holdExpiresAt: now()->addMinutes(30))
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => '2026-08-10',
                'check_out_date' => '2026-08-12',
            ]);

        $availableIncludingSelf = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
        );
        $this->assertSame(0, $availableIncludingSelf);

        $availableExcludingSelf = $this->service()->availableRooms(
            $roomType,
            $this->date('2026-08-10'),
            $this->date('2026-08-12'),
            excludeBookingId: $booking->id,
        );
        $this->assertSame(1, $availableExcludingSelf);
    }

    private function service(): RoomAvailabilityService
    {
        return new RoomAvailabilityService();
    }

    private function date(string $value): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingAttributes(string $bookingNumber = 'BOOK-2026-000001'): array
    {
        return [
            'booking_number' => $bookingNumber,
            'user_id' => null,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '+62812345678',
            'nights' => 2,
            'price_per_night' => 100,
            'total_amount' => 200,
            'currency_code' => 'USD',
        ];
    }
}
