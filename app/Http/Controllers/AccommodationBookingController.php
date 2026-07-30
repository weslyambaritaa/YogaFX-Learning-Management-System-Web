<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Requests\AccommodationAvailabilityRequest;
use App\Http\Requests\AccommodationInstallmentOrderRequest;
use App\Http\Requests\AccommodationOrderRequest;
use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationRoomType;
use App\Models\User;
use App\Services\Accommodations\AccommodationCheckoutService;
use App\Services\Accommodations\AccommodationInstallmentCheckoutService;
use App\Services\Accommodations\AccommodationInstallmentPlanCalculator;
use App\Services\Accommodations\RoomAvailabilityService;
use App\Services\PayPalService;
use App\Support\CountryDirectory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class AccommodationBookingController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly RoomAvailabilityService $availabilityService,
        private readonly AccommodationCheckoutService $checkoutService,
        private readonly AccommodationInstallmentCheckoutService $installmentCheckoutService,
        private readonly AccommodationInstallmentPlanCalculator $installmentPlanCalculator,
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
                'installment_enabled' => $accommodation->installment_enabled,
            ],
            'roomTypes' => $roomTypes->map(fn (AccommodationRoomType $roomType) => [
                'id' => $roomType->id,
                'title' => $roomType->title,
                'price' => (float) $roomType->price,
            ])->values(),
            'prefill' => $this->prefillForCurrentUser(),
            'availabilityUrl' => route('stay.availability', $accommodation),
            'ordersUrl' => route('stay.orders.store', $accommodation),
            'installmentsUrl' => route('stay.installments.store', $accommodation),
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
            'installment' => $availableRooms > 0
                ? $this->installmentPreview(
                    $accommodation,
                    $checkIn,
                    $totalAmount,
                    isset($validated['installment_count']) ? (int) $validated['installment_count'] : null,
                )
                : null,
        ]);
    }

    /**
     * Read-only preview of the installment plan for a prospective booking —
     * no AccommodationBooking/AccommodationPaymentSubscription row is
     * created here. Returns null whenever the option should not be shown at
     * all (per spec §10: "tidak ditampilkan disabled, langsung tidak ada").
     *
     * @return array<string, mixed>|null
     */
    private function installmentPreview(
        Accommodation $accommodation,
        CarbonInterface $checkIn,
        float $totalAmount,
        ?int $installmentCount = null,
    ): ?array {
        if (! $accommodation->installment_enabled) {
            return null;
        }

        $previewBooking = new AccommodationBooking([
            'check_in_date' => $checkIn->toDateString(),
            'total_amount' => $totalAmount,
            'currency_code' => $accommodation->currency_code,
        ]);
        $previewBooking->setRelation('accommodation', $accommodation);

        if (! $this->installmentPlanCalculator->isEligibleForBooking($previewBooking)) {
            return null;
        }

        try {
            $plan = $this->installmentPlanCalculator->calculate($previewBooking, installmentCount: $installmentCount);
        } catch (DomainException|InvalidArgumentException) {
            // Selected count out of range (e.g. stale selection after dates
            // changed) — fall back to the default (maximum) plan instead of
            // erroring the whole availability response.
            $plan = $this->installmentPlanCalculator->calculate($previewBooking);
        }

        return [
            'minimum_installment_count' => $plan['minimum_installment_count'],
            'maximum_installment_count' => $plan['maximum_installment_count'],
            'count_mode' => $accommodation->installment_count_mode,
            'installment_count' => $plan['installment_count'],
            'first_payment_amount' => $plan['first_payment_amount'],
            'monthly_base_amount' => $plan['monthly_base_amount'],
            'schedule_breakdown' => $plan['schedule_breakdown'],
            'final_due_at' => $plan['final_due_at'],
            'currency_code' => $plan['currency_code'],
        ];
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
                    'guest_country' => $validated['country'],
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

    /**
     * Creates the booking hold plus the PayPal Product/Plan for an
     * installment checkout. Returns the plan id so the frontend can create
     * the PayPal Subscription client-side and redirect the guest to
     * approval — this endpoint never creates the Subscription itself.
     */
    public function createInstallmentSubscription(AccommodationInstallmentOrderRequest $request, Accommodation $accommodation): JsonResponse
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
            $result = $this->installmentCheckoutService->createSubscription(
                $accommodation,
                $roomType,
                [
                    'user_id' => $user instanceof User && $user->isStudent() ? $user->id : null,
                    'guest_name' => $validated['guest_name'],
                    'guest_email' => $validated['guest_email'],
                    'guest_phone' => $validated['guest_phone'],
                    'guest_country' => $validated['country'],
                ],
                $checkIn,
                $checkOut,
                isset($validated['installment_count']) ? (int) $validated['installment_count'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'unavailable',
                'message' => $exception->getMessage(),
            ], 409);
        } catch (DomainException|InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'not_eligible',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $booking = $result['booking'];
        $paymentSubscription = $result['payment_subscription'];

        return response()->json([
            'status' => 'created',
            'booking_id' => $booking->id,
            'payment_subscription_id' => $paymentSubscription->id,
            'provider_plan_id' => $paymentSubscription->provider_plan_id,
            'installment_plan' => $result['installment_plan'],
            'paypal_client_id' => $this->paypalService->clientId(),
            'environment' => $this->paypalService->environment(),
            'approve_url' => URL::temporarySignedRoute('stay.installments.approve', now()->addMinutes(65), [
                'accommodation' => $accommodation,
                'booking' => $booking->id,
            ]),
            'cancel_url' => URL::temporarySignedRoute('stay.installments.cancel', now()->addMinutes(65), [
                'accommodation' => $accommodation,
                'booking' => $booking->id,
            ]),
            'status_url' => URL::temporarySignedRoute('stay.installments.status', now()->addDays(1), [
                'accommodation' => $accommodation,
                'booking' => $booking->id,
            ]),
            'paypal_subscription_start_time' => $this->paypalSubscriptionStartTime($paymentSubscription),
        ]);
    }

    /**
     * Records the PayPal subscription id the guest approved client-side.
     * Does NOT confirm the booking — that only happens once the
     * BILLING.SUBSCRIPTION.ACTIVATED webhook is processed (Fase 4).
     */
    public function approveInstallmentSubscription(Request $request, Accommodation $accommodation, AccommodationBooking $booking): JsonResponse
    {
        abort_unless($booking->accommodation_id === $accommodation->id, 404);

        $validated = $request->validate([
            'provider_subscription_id' => ['required', 'string', 'max:255'],
        ]);

        $paymentSubscription = $this->latestPaymentSubscriptionOrFail($booking);

        $paymentSubscription = $this->installmentCheckoutService->attachApprovedSubscription(
            $booking,
            $paymentSubscription,
            (string) $validated['provider_subscription_id'],
        );

        return response()->json([
            'status' => 'approval_attached',
            'payment_subscription_id' => $paymentSubscription->id,
            'provider_subscription_id' => $paymentSubscription->provider_subscription_id,
            'awaiting_webhook' => true,
        ]);
    }

    public function cancelInstallmentSubscription(Accommodation $accommodation, AccommodationBooking $booking): JsonResponse
    {
        abort_unless($booking->accommodation_id === $accommodation->id, 404);

        $paymentSubscription = $this->latestPaymentSubscriptionOrFail($booking);

        $this->installmentCheckoutService->cancelSubscription($booking, $paymentSubscription);

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * Lets the frontend poll for the outcome of BILLING.SUBSCRIPTION.ACTIVATED
     * (Fase 4's webhook handler) instead of leaving the guest on a dead-end
     * "processing" screen after approving. Read-only — never mutates state.
     */
    public function installmentStatus(Accommodation $accommodation, AccommodationBooking $booking): JsonResponse
    {
        abort_unless($booking->accommodation_id === $accommodation->id, 404);

        if ($booking->status === AccommodationBooking::STATUS_CONFIRMED) {
            return response()->json([
                'status' => 'confirmed',
                'redirect_url' => URL::temporarySignedRoute('stay.bookings.success', now()->addDays(7), [
                    'accommodation' => $accommodation,
                    'booking' => $booking->id,
                ]),
            ]);
        }

        $paymentSubscription = $booking->paymentSubscriptions()->latest('id')->first();

        return response()->json([
            'status' => $paymentSubscription?->status ?? 'pending',
            'redirect_url' => null,
        ]);
    }

    /**
     * Without an explicit start_time, PayPal schedules the first REGULAR
     * billing cycle almost immediately after the setup_fee (first payment)
     * is charged on approval — instead of one full interval later — so the
     * guest gets double-billed within minutes. Package avoids this the same
     * way (CheckoutController::paypalSubscriptionStartTime()): tell PayPal
     * explicitly when the first recurring cycle should start, using the
     * date already computed by the installment plan.
     */
    private function paypalSubscriptionStartTime(AccommodationPaymentSubscription $paymentSubscription): ?string
    {
        if (! $paymentSubscription->next_due_at) {
            return null;
        }

        return $paymentSubscription->next_due_at->copy()->utc()->startOfDay()->format('Y-m-d\TH:i:s\Z');
    }

    private function latestPaymentSubscriptionOrFail(AccommodationBooking $booking): AccommodationPaymentSubscription
    {
        /** @var AccommodationPaymentSubscription|null $paymentSubscription */
        $paymentSubscription = $booking->paymentSubscriptions()->latest('id')->first();

        abort_if($paymentSubscription === null, 404);

        return $paymentSubscription;
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

        // User only stores a single `name` field — best-effort split into
        // first/last (first word vs. the rest) since there is no reliable
        // separator guarantee. Same convenience-only rationale as the phone
        // split below; the guest can always correct it before submitting.
        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);
        $phoneSplit = CountryDirectory::splitPhoneNumber($user->whatsapp, $user->country);

        return [
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'email' => $user->email,
            'phone_country_code' => $phoneSplit['country_code'],
            'phone_number' => $phoneSplit['local_number'],
            'country' => $user->country,
        ];
    }
}
