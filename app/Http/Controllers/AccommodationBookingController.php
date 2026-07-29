<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Requests\AccommodationAvailabilityRequest;
use App\Http\Requests\AccommodationOrderRequest;
use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use App\Models\User;
use App\Services\Accommodations\AccommodationCheckoutService;
use App\Services\Accommodations\RoomAvailabilityService;
use App\Services\PayPalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AccommodationBookingController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly RoomAvailabilityService $availabilityService,
        private readonly AccommodationCheckoutService $checkoutService,
        private readonly PayPalService $paypalService,
    ) {}

    public function show(Accommodation $accommodation): Response
    {
        abort_unless($accommodation->is_active, 404);

        $roomTypes = $accommodation->roomTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return Inertia::render('Public/AccommodationBooking', [
            'accommodation' => [
                'id' => $accommodation->id,
                'title' => $accommodation->title,
                'slug' => $accommodation->slug,
                'description' => $accommodation->description,
                'image_url' => $this->protectedMediaUrl(
                    'accommodation',
                    $accommodation->id,
                    'image',
                    $accommodation->image,
                    versionSeed: $accommodation->updated_at,
                ),
                'currency_code' => $accommodation->currency_code,
            ],
            'roomTypes' => $roomTypes->map(fn (AccommodationRoomType $roomType) => [
                'id' => $roomType->id,
                'title' => $roomType->title,
                'price' => (float) $roomType->price,
            ])->values(),
            'prefill' => $this->prefillForCurrentUser(),
            'availabilityUrl' => route('stay.availability', $accommodation),
            'ordersUrl' => route('stay.orders.store', $accommodation),
            'paypal' => [
                'client_id' => $this->paypalService->clientId(),
                'currency_code' => $accommodation->currency_code,
                'intent' => 'capture',
                'environment' => $this->paypalService->environment(),
            ],
        ]);
    }

    public function availability(AccommodationAvailabilityRequest $request, Accommodation $accommodation): JsonResponse
    {
        abort_unless($accommodation->is_active, 404);

        $validated = $request->validated();

        /** @var AccommodationRoomType $roomType */
        $roomType = AccommodationRoomType::query()
            ->where('accommodation_id', $accommodation->id)
            ->findOrFail($validated['room_type_id']);

        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);
        $nights = $checkIn->diffInDays($checkOut);

        /*
        |--------------------------------------------------------------------------
        | Server-side price calculation
        |--------------------------------------------------------------------------
        |
        | The frontend only displays this response — nights and total_amount are
        | always computed here, never trusted from the request payload.
        */
        $availableRooms = $this->availabilityService->availableRooms($roomType, $checkIn, $checkOut);
        $pricePerNight = (float) $roomType->price;
        $totalAmount = round($pricePerNight * $nights, 2);

        return response()->json([
            'available' => $availableRooms > 0,
            'available_rooms' => $availableRooms,
            'nights' => $nights,
            'price_per_night' => $pricePerNight,
            'total_amount' => $totalAmount,
            'currency_code' => $accommodation->currency_code,
        ]);
    }

    public function createOrder(AccommodationOrderRequest $request, Accommodation $accommodation): JsonResponse
    {
        abort_unless($accommodation->is_active, 404);

        $validated = $request->validated();

        /** @var AccommodationRoomType $roomType */
        $roomType = AccommodationRoomType::query()
            ->where('accommodation_id', $accommodation->id)
            ->findOrFail($validated['room_type_id']);

        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);
        $user = auth()->user();

        try {
            $result = $this->checkoutService->createOrder(
                $accommodation,
                $roomType,
                [
                    'user_id' => $user instanceof User && $user->isStudent() ? $user->id : null,
                    'guest_name' => $validated['guest_name'],
                    'guest_email' => $validated['guest_email'],
                    'guest_phone' => $validated['guest_phone'],
                ],
                $checkIn,
                $checkOut,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'unavailable',
                'message' => $exception->getMessage(),
            ], 409);
        }

        $booking = $result['booking'];

        return response()->json([
            'status' => 'created',
            'order_id' => $result['order_id'],
            'booking_id' => $booking->id,
            'capture_url' => URL::temporarySignedRoute('stay.orders.capture', now()->addMinutes(35), [
                'accommodation' => $accommodation,
                'booking' => $booking->id,
            ]),
            'cancel_url' => URL::temporarySignedRoute('stay.orders.cancel', now()->addMinutes(35), [
                'accommodation' => $accommodation,
                'booking' => $booking->id,
            ]),
        ]);
    }

    public function captureOrder(Request $request, Accommodation $accommodation, AccommodationBooking $booking): JsonResponse
    {
        abort_unless($booking->accommodation_id === $accommodation->id, 404);

        $validated = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $result = $this->checkoutService->captureOrder($booking, $validated['order_id']);

        return match ($result['status']) {
            'success' => response()->json([
                'status' => 'success',
                'redirect_url' => URL::temporarySignedRoute('stay.bookings.success', now()->addDays(7), [
                    'accommodation' => $accommodation,
                    'booking' => $result['booking']->id,
                ]),
            ]),
            'pending' => response()->json([
                'status' => 'pending',
                'message' => 'PayPal is still processing this payment. Please wait a moment and try again.',
            ], 202),
            'hold_expired' => response()->json([
                'status' => 'failed',
                'message' => 'Your room hold has expired. Please start a new booking.',
            ], 422),
            'already_processed' => response()->json([
                'status' => 'failed',
                'message' => 'This booking has already been processed.',
            ], 409),
            'oversold' => response()->json([
                'status' => 'failed',
                'message' => 'This room is no longer available for the selected dates. Your payment may have been charged — please contact support.',
            ], 409),
            default => response()->json([
                'status' => 'failed',
                'message' => 'This payment did not complete. Please try again.',
            ], 422),
        };
    }

    public function cancelOrder(Accommodation $accommodation, AccommodationBooking $booking): JsonResponse
    {
        abort_unless($booking->accommodation_id === $accommodation->id, 404);

        $this->checkoutService->cancelOrder($booking);

        return response()->json(['status' => 'cancelled']);
    }

    public function success(Accommodation $accommodation, AccommodationBooking $booking): Response
    {
        abort_unless($booking->accommodation_id === $accommodation->id, 404);
        abort_unless($booking->status === AccommodationBooking::STATUS_CONFIRMED, 404);

        $booking->loadMissing('roomType');

        return Inertia::render('Public/AccommodationBookingSuccess', [
            'accommodation' => [
                'title' => $accommodation->title,
            ],
            'booking' => [
                'booking_number' => $booking->booking_number,
                'room_type_title' => $booking->roomType->title,
                'guest_name' => $booking->guest_name,
                'check_in_date' => $booking->check_in_date->toDateString(),
                'check_out_date' => $booking->check_out_date->toDateString(),
                'nights' => $booking->nights,
                'total_amount' => (float) $booking->total_amount,
                'currency_code' => $booking->currency_code,
                'paid_at' => $booking->paid_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * @return array{name: string, email: string, phone: ?string}|null
     */
    private function prefillForCurrentUser(): ?array
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isStudent()) {
            return null;
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->whatsapp,
        ];
    }
}
