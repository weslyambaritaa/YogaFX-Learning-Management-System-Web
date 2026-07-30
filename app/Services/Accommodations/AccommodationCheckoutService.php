<?php

namespace App\Services\Accommodations;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use App\Services\EmailNotificationService;
use App\Services\PayPalService;
use App\Support\EmailNotificationTypeRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccommodationCheckoutService
{
    public function __construct(
        private readonly RoomAvailabilityService $availabilityService,
        private readonly PayPalService $paypalService,
        private readonly BookingNumberService $bookingNumbers,
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * @param  array{user_id: ?int, guest_name: string, guest_email: string, guest_phone: string, guest_country: string}  $guestData
     * @return array{booking: AccommodationBooking, order_id: string}
     */
    public function createOrder(
        Accommodation $accommodation,
        AccommodationRoomType $roomType,
        array $guestData,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
    ): array {
        $nights = $checkIn->diffInDays($checkOut);
        $pricePerNight = (float) $roomType->price;
        $totalAmount = round($pricePerNight * $nights, 2);

        /*
        |--------------------------------------------------------------------------
        | DB-only transaction
        |--------------------------------------------------------------------------
        |
        | Booking number generation + the locked availability check/insert stay
        | inside one short transaction. The PayPal API call happens afterward,
        | outside any transaction, so the room-type row lock is never held
        | during a slow external HTTP request.
        */
        $booking = DB::transaction(function () use ($accommodation, $roomType, $guestData, $checkIn, $checkOut, $nights, $pricePerNight, $totalAmount) {
            $bookingNumber = $this->bookingNumbers->nextNumber();

            return $this->availabilityService->reserveWithLock(
                $roomType,
                $checkIn,
                $checkOut,
                [
                    'booking_number' => $bookingNumber,
                    'user_id' => $guestData['user_id'] ?? null,
                    'guest_name' => $guestData['guest_name'],
                    'guest_email' => $guestData['guest_email'],
                    'guest_phone' => $guestData['guest_phone'],
                    'guest_country' => $guestData['guest_country'],
                    'nights' => $nights,
                    'price_per_night' => $pricePerNight,
                    'total_amount' => $totalAmount,
                    'currency_code' => $accommodation->currency_code,
                ],
            );
        });

        try {
            $order = $this->paypalService->createOrderFromReference(
                referenceId: $booking->booking_number,
                customId: $booking->booking_number,
                invoiceId: $booking->booking_number,
                currencyCode: $accommodation->currency_code,
                amount: $totalAmount,
                successUrl: route('stay.show', $accommodation),
                cancelUrl: route('stay.show', $accommodation),
            );
        } catch (Throwable $exception) {
            // Release the hold immediately instead of leaving the room
            // reserved until the 30-minute hold naturally expires.
            $booking->update([
                'status' => AccommodationBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            throw $exception;
        }

        $booking->update(['paypal_order_id' => $order['order_id']]);

        return [
            'booking' => $booking,
            'order_id' => $order['order_id'],
        ];
    }

    /**
     * @return array{status: string, booking: AccommodationBooking}
     */
    public function captureOrder(AccommodationBooking $booking, string $orderId): array
    {
        if ($booking->paypal_order_id !== $orderId) {
            return ['status' => 'order_mismatch', 'booking' => $booking];
        }

        if ($booking->status !== AccommodationBooking::STATUS_PENDING_PAYMENT) {
            return ['status' => 'already_processed', 'booking' => $booking];
        }

        if ($booking->hold_expires_at === null || $booking->hold_expires_at->isPast()) {
            $booking->update(['status' => AccommodationBooking::STATUS_EXPIRED]);

            return ['status' => 'hold_expired', 'booking' => $booking];
        }

        $capture = $this->paypalService->captureOrder($orderId);
        $providerStatus = strtoupper((string) ($capture['status'] ?? ''));

        if ($providerStatus === 'PENDING') {
            return ['status' => 'pending', 'booking' => $booking];
        }

        if ($providerStatus !== 'COMPLETED') {
            return ['status' => 'failed', 'booking' => $booking];
        }

        /*
        |--------------------------------------------------------------------------
        | Second-layer availability re-check
        |--------------------------------------------------------------------------
        |
        | excludeBookingId lets this booking's own hold be ignored so we're
        | asking "aside from myself, is there still a free room?" — the same
        | RoomAvailabilityService query used everywhere else, no duplicated
        | overlap logic.
        */
        $booking->loadMissing('roomType');

        $stillAvailable = $this->availabilityService->availableRooms(
            $booking->roomType,
            $booking->check_in_date,
            $booking->check_out_date,
            excludeBookingId: $booking->id,
        ) > 0;

        if (! $stillAvailable) {
            Log::error('Accommodation booking captured by PayPal but the room became unavailable before confirmation. Payment was charged — manual refund/support follow-up required.', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'paypal_order_id' => $orderId,
            ]);

            return ['status' => 'oversold', 'booking' => $booking];
        }

        $booking->update([
            'status' => AccommodationBooking::STATUS_CONFIRMED,
            'paid_at' => now(),
        ]);

        $booking = $booking->fresh(['accommodation', 'roomType']);

        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::ACCOMMODATION_BOOKING_CONFIRMED,
            [
                'user_name' => $booking->guest_name,
                'user_email' => $booking->guest_email,
                'hotel_name' => $booking->accommodation->title,
                'room_type' => $booking->roomType->title,
                'check_in_date' => $booking->check_in_date->toDateString(),
                'check_out_date' => $booking->check_out_date->toDateString(),
                'nights' => (string) $booking->nights,
                'total_amount' => number_format((float) $booking->total_amount, 2, '.', ''),
                'currency_code' => $booking->currency_code,
                'booking_number' => $booking->booking_number,
            ],
            'accommodation_booking',
            $booking->id,
        );

        return ['status' => 'success', 'booking' => $booking];
    }

    public function cancelOrder(AccommodationBooking $booking): void
    {
        if ($booking->status !== AccommodationBooking::STATUS_PENDING_PAYMENT) {
            return;
        }

        $booking->update([
            'status' => AccommodationBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
