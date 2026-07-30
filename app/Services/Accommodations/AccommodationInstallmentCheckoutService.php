<?php

namespace App\Services\Accommodations;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationRoomType;
use App\Services\Payments\PayPalSubscriptionService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class AccommodationInstallmentCheckoutService
{
    private const PAYMENT_FAILURE_THRESHOLD = 5;

    public function __construct(
        private readonly RoomAvailabilityService $availabilityService,
        private readonly BookingNumberService $bookingNumbers,
        private readonly AccommodationInstallmentPlanCalculator $installmentPlanCalculator,
        private readonly PayPalSubscriptionService $paypalSubscriptionService,
    ) {}

    /**
     * Create the booking hold and the PayPal Product/Plan for an installment
     * checkout. This does NOT create the PayPal Subscription itself or
     * confirm the booking — guest approval is attached separately via
     * attachApprovedSubscription(), and booking confirmation only happens
     * once the BILLING.SUBSCRIPTION.ACTIVATED webhook is processed.
     *
     * @param  array{user_id: ?int, guest_name: string, guest_email: string, guest_phone: string, guest_country: string}  $guestData
     * @return array{booking: AccommodationBooking, payment_subscription: AccommodationPaymentSubscription, installment_plan: array<string, mixed>}
     */
    public function createSubscription(
        Accommodation $accommodation,
        AccommodationRoomType $roomType,
        array $guestData,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $installmentCount = null,
    ): array {
        $nights = $checkIn->diffInDays($checkOut);
        $pricePerNight = (float) $roomType->price;
        $totalAmount = round($pricePerNight * $nights, 2);

        $booking = DB::transaction(function () use ($roomType, $guestData, $checkIn, $checkOut, $nights, $pricePerNight, $totalAmount, $accommodation) {
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
                RoomAvailabilityService::INSTALLMENT_HOLD_MINUTES,
            );
        });

        $booking->setRelation('accommodation', $accommodation);
        $booking->setRelation('roomType', $roomType);

        try {
            $installmentPlan = $this->installmentPlanCalculator->calculate($booking, now(), $installmentCount);
        } catch (DomainException|InvalidArgumentException $exception) {
            // Release the hold immediately instead of leaving the room
            // reserved until the hold naturally expires.
            $booking->update([
                'status' => AccommodationBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            throw $exception;
        }

        $paymentSubscription = AccommodationPaymentSubscription::query()->create([
            'accommodation_booking_id' => $booking->id,
            'provider' => AccommodationPaymentSubscription::PROVIDER_PAYPAL,
            'status' => AccommodationPaymentSubscription::STATUS_DRAFT,
            'installment_count' => $installmentPlan['installment_count'],
            'installments_paid_count' => 0,
            'currency_code' => $installmentPlan['currency_code'],
            'total_amount' => (float) $installmentPlan['total_amount'],
            'monthly_amount' => (float) $installmentPlan['monthly_base_amount'],
            'first_payment_amount' => (float) $installmentPlan['first_payment_amount'],
            'billing_day' => $installmentPlan['billing_day'],
            'next_due_at' => $installmentPlan['recurring_due_dates'][0] ?? null,
            'final_due_at' => $installmentPlan['final_due_at'],
            'grace_deadline_at' => $installmentPlan['grace_deadlines'][0] ?? null,
            'metadata' => [
                'installment_plan' => $installmentPlan,
            ],
        ]);

        try {
            $productId = $this->paypalSubscriptionService->createProductFromReference(
                $this->productName($accommodation),
                $this->productDescription($accommodation, $booking),
            )['id'];

            $planId = $this->paypalSubscriptionService->createPlanFromReference(
                productId: $productId,
                name: $this->planName($accommodation, $booking, $installmentPlan),
                description: $this->planDescription($installmentPlan),
                currencyCode: (string) $installmentPlan['currency_code'],
                installmentCount: (int) $installmentPlan['installment_count'],
                intervalUnit: (string) $installmentPlan['billing_interval_unit'],
                intervalCount: (int) $installmentPlan['billing_interval_count'],
                recurringAmount: $installmentPlan['recurring_payment_amount'],
                firstPaymentAmount: $installmentPlan['first_payment_amount'],
                paymentFailureThreshold: self::PAYMENT_FAILURE_THRESHOLD,
            )['id'];
        } catch (Throwable $throwable) {
            $booking->update([
                'status' => AccommodationBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $paymentSubscription->forceFill([
                'status' => AccommodationPaymentSubscription::STATUS_FAILED,
            ])->save();

            throw $throwable;
        }

        $paymentSubscription->forceFill([
            'provider_product_id' => $productId,
            'provider_plan_id' => $planId,
        ])->save();

        return [
            'booking' => $booking,
            'payment_subscription' => $paymentSubscription->fresh(),
            'installment_plan' => $installmentPlan,
        ];
    }

    /**
     * Records the PayPal subscription ID the guest approved client-side.
     * This never touches the booking's status — confirmation only happens
     * once the webhook handler (Fase 4) processes BILLING.SUBSCRIPTION.ACTIVATED.
     */
    public function attachApprovedSubscription(
        AccommodationBooking $booking,
        AccommodationPaymentSubscription $paymentSubscription,
        string $providerSubscriptionId,
    ): AccommodationPaymentSubscription {
        abort_unless($paymentSubscription->accommodation_booking_id === $booking->id, 404);
        abort_if(
            ! is_string($paymentSubscription->provider_plan_id) || $paymentSubscription->provider_plan_id === '',
            422,
            'PayPal subscription plan is not ready yet.',
        );

        if (
            is_string($paymentSubscription->provider_subscription_id)
            && $paymentSubscription->provider_subscription_id !== ''
            && $paymentSubscription->provider_subscription_id !== $providerSubscriptionId
        ) {
            abort(409, 'A different PayPal subscription approval is already attached to this booking.');
        }

        $paymentSubscription->forceFill([
            'provider_subscription_id' => $providerSubscriptionId,
            'status' => AccommodationPaymentSubscription::STATUS_APPROVAL_PENDING,
            'last_synced_at' => now(),
        ])->save();

        return $paymentSubscription->fresh();
    }

    /**
     * Guest abandoned the PayPal approval popup (or closed the checkout
     * page) before approving. Cancels the PayPal subscription if one was
     * ever created, and releases the room hold — there is no payment to
     * wait for anymore.
     */
    public function cancelSubscription(
        AccommodationBooking $booking,
        AccommodationPaymentSubscription $paymentSubscription,
    ): void {
        abort_unless($paymentSubscription->accommodation_booking_id === $booking->id, 404);

        if (
            is_string($paymentSubscription->provider_subscription_id)
            && $paymentSubscription->provider_subscription_id !== ''
        ) {
            try {
                $this->paypalSubscriptionService->cancelSubscription(
                    $paymentSubscription->provider_subscription_id,
                    'Guest cancelled installment checkout before approval.',
                );
            } catch (Throwable $throwable) {
                report($throwable);
            }
        }

        $paymentSubscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        if ($booking->status === AccommodationBooking::STATUS_PENDING_PAYMENT) {
            $booking->update([
                'status' => AccommodationBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }
    }

    private function productName(Accommodation $accommodation): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $accommodation->title) ?: '');

        return $name !== '' ? mb_substr($name, 0, 127) : 'YogaFX Accommodation';
    }

    private function productDescription(Accommodation $accommodation, AccommodationBooking $booking): string
    {
        return mb_substr(sprintf(
            '%s booking %s billed in installments.',
            $this->productName($accommodation),
            $booking->booking_number,
        ), 0, 256);
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function planName(Accommodation $accommodation, AccommodationBooking $booking, array $installmentPlan): string
    {
        return mb_substr(sprintf(
            '%s Installment %dx - %s',
            $this->productName($accommodation),
            (int) $installmentPlan['installment_count'],
            $booking->booking_number,
        ), 0, 127);
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function planDescription(array $installmentPlan): string
    {
        return mb_substr(sprintf(
            '%d installments until %s.',
            (int) $installmentPlan['installment_count'],
            (string) $installmentPlan['final_due_at'],
        ), 0, 127);
    }
}
