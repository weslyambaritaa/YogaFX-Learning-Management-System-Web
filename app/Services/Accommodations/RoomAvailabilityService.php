<?php

namespace App\Services\Accommodations;

use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RoomAvailabilityService
{
    public const DEFAULT_HOLD_MINUTES = 30;

    /**
     * Installment checkout goes through PayPal subscription approval instead
     * of a synchronous order capture, and confirmation only arrives later via
     * the BILLING.SUBSCRIPTION.ACTIVATED webhook — so it gets a longer hold
     * than the pay-full flow.
     */
    public const INSTALLMENT_HOLD_MINUTES = 60;

    /**
     * Count rooms currently occupying the given date range: confirmed
     * bookings plus pending_payment bookings whose hold has not expired yet.
     *
     * Overlap uses the standard hotel half-open convention [check_in, check_out) —
     * a checkout on day X does not collide with a check-in on day X.
     */
    public function bookedCount(
        AccommodationRoomType $roomType,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $excludeBookingId = null,
    ): int {
        return AccommodationBooking::query()
            ->where('accommodation_room_type_id', $roomType->id)
            ->where(function ($query): void {
                $query->where('status', AccommodationBooking::STATUS_CONFIRMED)
                    ->orWhere(function ($pendingQuery): void {
                        $pendingQuery->where('status', AccommodationBooking::STATUS_PENDING_PAYMENT)
                            ->where('hold_expires_at', '>', now());
                    });
            })
            ->whereDate('check_in_date', '<', $checkOut->toDateString())
            ->whereDate('check_out_date', '>', $checkIn->toDateString())
            ->when(
                $excludeBookingId !== null,
                fn ($query) => $query->where('id', '!=', $excludeBookingId),
            )
            ->count();
    }

    public function availableRooms(
        AccommodationRoomType $roomType,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $excludeBookingId = null,
    ): int {
        $booked = $this->bookedCount($roomType, $checkIn, $checkOut, $excludeBookingId);

        return max(0, $roomType->total_rooms - $booked);
    }

    /**
     * Validate availability and create a pending_payment booking with a hold,
     * inside a locked transaction so two concurrent requests can never both
     * take the last room.
     *
     * @param  array<string, mixed>  $bookingAttributes  Booking columns already
     *      resolved by the caller (guest_name, guest_email, guest_phone,
     *      price_per_night, total_amount, currency_code, booking_number, user_id).
     *      accommodation_id, accommodation_room_type_id, check_in_date,
     *      check_out_date, status, and hold_expires_at are set by this method.
     */
    public function reserveWithLock(
        AccommodationRoomType $roomType,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        array $bookingAttributes,
        int $holdMinutes = self::DEFAULT_HOLD_MINUTES,
    ): AccommodationBooking {
        return DB::transaction(function () use ($roomType, $checkIn, $checkOut, $bookingAttributes, $holdMinutes) {
            /** @var AccommodationRoomType $lockedRoomType */
            $lockedRoomType = AccommodationRoomType::query()
                ->lockForUpdate()
                ->findOrFail($roomType->id);

            if ($this->availableRooms($lockedRoomType, $checkIn, $checkOut) < 1) {
                throw new RuntimeException(
                    'No room is available for the selected dates.',
                );
            }

            return AccommodationBooking::query()->create(array_merge($bookingAttributes, [
                'accommodation_id' => $lockedRoomType->accommodation_id,
                'accommodation_room_type_id' => $lockedRoomType->id,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'status' => AccommodationBooking::STATUS_PENDING_PAYMENT,
                'hold_expires_at' => now()->addMinutes($holdMinutes),
            ]));
        });
    }
}
