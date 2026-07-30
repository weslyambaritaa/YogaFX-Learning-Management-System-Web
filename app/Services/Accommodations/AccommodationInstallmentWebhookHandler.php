<?php

namespace App\Services\Accommodations;

use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationPaymentSubscriptionEvent;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccommodationInstallmentWebhookHandler
{
    public function __construct(
        private readonly RoomAvailabilityService $availabilityService,
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    public function handle(AccommodationPaymentSubscriptionEvent $event): AccommodationPaymentSubscriptionEvent
    {
        return DB::transaction(function () use ($event): AccommodationPaymentSubscriptionEvent {
            /** @var AccommodationPaymentSubscriptionEvent $event */
            $event = AccommodationPaymentSubscriptionEvent::query()
                ->with(['accommodationPaymentSubscription.accommodationBooking.accommodation', 'accommodationPaymentSubscription.accommodationBooking.roomType'])
                ->lockForUpdate()
                ->findOrFail($event->id);

            if ($event->status === AccommodationPaymentSubscriptionEvent::STATUS_PROCESSED) {
                return $event;
            }

            $subscription = $event->accommodationPaymentSubscription;
            if (! $subscription instanceof AccommodationPaymentSubscription) {
                return $this->markIgnored($event, 'No matching accommodation payment subscription was found for this event.');
            }

            return match ($event->provider_event_type) {
                'BILLING.SUBSCRIPTION.CREATED' => $this->handleCreated($event, $subscription),
                'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleActivated($event, $subscription),
                'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleCancelled($event, $subscription),
                'BILLING.SUBSCRIPTION.SUSPENDED' => $this->handleSuspended($event, $subscription),
                'BILLING.SUBSCRIPTION.EXPIRED' => $this->handleExpired($event, $subscription),
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'PAYMENT.SALE.DENIED' => $this->handlePaymentFailed($event, $subscription),
                'PAYMENT.SALE.COMPLETED' => $this->handlePaymentCompleted($event, $subscription),
                default => $this->markIgnored($event, 'Unsupported subscription event type.'),
            };
        });
    }

    private function handleCreated(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_APPROVAL_PENDING,
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    /**
     * BILLING.SUBSCRIPTION.ACTIVATED confirms the setup_fee (first payment)
     * was charged. The actual booking-confirmation + duplicate-suppression
     * logic is shared with handlePaymentCompleted() via processSuccessfulPayment()
     * since PayPal can deliver either event first for the same first charge.
     */
    private function handleActivated(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_ACTIVE,
            'started_at' => $subscription->started_at ?? ($event->occurred_at ?? now()),
            'last_synced_at' => now(),
        ])->save();

        $lastPayment = $this->extractActivationLastPayment($event->payload);

        if ($lastPayment === null) {
            return $this->markProcessed($event);
        }

        return $this->processSuccessfulPayment($event, $subscription, $lastPayment['paid_at']);
    }

    private function handleCancelled(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_CANCELLED,
            'cancelled_at' => $event->occurred_at ?? now(),
            'last_synced_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Booking is intentionally NOT touched here
        |--------------------------------------------------------------------------
        |
        | Per spec §3.1/§7: a cancelled subscription never auto-cancels the
        | booking, no matter the reason PayPal reports it as cancelled. The
        | only path where an admin cancel also cancels the subscription is
        | Admin\AccommodationBookingController::cancel(), which cancels the
        | subscription directly rather than reacting to this webhook.
        */
        return $this->markProcessed($event);
    }

    private function handleSuspended(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_SUSPENDED,
            'suspended_at' => $event->occurred_at ?? now(),
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    private function handleExpired(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_COMPLETED,
            'completed_at' => $event->occurred_at ?? now(),
            'next_due_at' => null,
            'grace_deadline_at' => null,
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    private function handlePaymentFailed(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        $dueAt = $subscription->next_due_at ?? $event->occurred_at ?? now();
        $dueAtCarbon = $dueAt instanceof Carbon ? $dueAt : Carbon::parse($dueAt);

        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_PAST_DUE,
            'last_payment_failed_at' => $event->occurred_at ?? now(),
            'grace_deadline_at' => $dueAtCarbon->copy()->addDays(3),
            'last_synced_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Booking is intentionally NOT touched here
        |--------------------------------------------------------------------------
        |
        | Per spec §3.1: the booking stays confirmed no matter how many
        | installment attempts fail. Only the subscription's own status
        | reflects the failure.
        */
        $booking = $subscription->accommodationBooking;

        if ($booking instanceof AccommodationBooking) {
            $booking->loadMissing('accommodation', 'roomType');

            $this->emailNotificationService->sendAutomated(
                EmailNotificationTypeRegistry::ACCOMMODATION_INSTALLMENT_PAYMENT_FAILED,
                $this->paymentFailedNotificationPayload($subscription, $booking),
                'accommodation_payment_subscription',
                $subscription->id,
            );
        }

        return $this->markProcessed($event);
    }

    private function handlePaymentCompleted(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): AccommodationPaymentSubscriptionEvent {
        return $this->processSuccessfulPayment($event, $subscription, $event->occurred_at ?? now());
    }

    /**
     * Shared by handleActivated() and handlePaymentCompleted() — PayPal can
     * deliver either BILLING.SUBSCRIPTION.ACTIVATED or PAYMENT.SALE.COMPLETED
     * first for the same first charge, so both funnel through the same
     * state transition with the same duplicate guard.
     */
    private function processSuccessfulPayment(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
        Carbon $paidAt,
    ): AccommodationPaymentSubscriptionEvent {
        if ($this->isDuplicateSuccessfulPaymentEvent($event, $subscription)) {
            return $this->markProcessed($event);
        }

        $paidCount = (int) $subscription->installments_paid_count + 1;
        $isFirstPayment = $paidCount === 1;
        $nextSchedule = $this->nextScheduleAfterPaidCount($subscription, $paidCount);
        $isCompleted = $paidCount >= (int) $subscription->installment_count;

        $subscription->forceFill([
            'status' => $isCompleted ? AccommodationPaymentSubscription::STATUS_COMPLETED : AccommodationPaymentSubscription::STATUS_ACTIVE,
            'installments_paid_count' => $paidCount,
            'first_payment_paid_at' => $subscription->first_payment_paid_at ?? ($isFirstPayment ? $paidAt : null),
            'started_at' => $subscription->started_at ?? $paidAt,
            'completed_at' => $isCompleted ? $paidAt : null,
            'next_due_at' => $isCompleted ? null : $nextSchedule['due_at'],
            'grace_deadline_at' => $isCompleted ? null : $nextSchedule['grace_deadline'],
            'last_payment_failed_at' => null,
            'last_synced_at' => now(),
        ])->save();

        if ($isFirstPayment) {
            return $this->confirmBookingIfPossible($event, $subscription, $paidAt);
        }

        return $this->markProcessed($event);
    }

    /**
     * The two-layer confirmation guard below reuses the exact same checks as
     * AccommodationCheckoutService::captureOrder() for the pay-full flow:
     * hold not expired, then a fresh availableRooms() re-check excluding
     * this booking's own hold. If either fails, the payment was still
     * charged by PayPal but we do NOT confirm, cancel, or refund — logged
     * for manual admin follow-up, per explicit decision.
     */
    private function confirmBookingIfPossible(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
        Carbon $paidAt,
    ): AccommodationPaymentSubscriptionEvent {
        $booking = $subscription->accommodationBooking;

        if (! $booking instanceof AccommodationBooking) {
            return $this->markIgnored($event, 'No matching accommodation booking was found for this subscription.');
        }

        if ($booking->status !== AccommodationBooking::STATUS_PENDING_PAYMENT) {
            return $this->markProcessed($event);
        }

        if ($booking->hold_expires_at === null || $booking->hold_expires_at->isPast()) {
            Log::error('Accommodation installment first payment was charged by PayPal but the booking hold had already expired. Manual refund/support follow-up required.', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'accommodation_payment_subscription_id' => $subscription->id,
                'provider_subscription_id' => $subscription->provider_subscription_id,
            ]);

            return $this->markProcessed($event);
        }

        $stillAvailable = $this->availabilityService->availableRooms(
            $booking->roomType,
            $booking->check_in_date,
            $booking->check_out_date,
            excludeBookingId: $booking->id,
        ) > 0;

        if (! $stillAvailable) {
            Log::error('Accommodation installment first payment was charged by PayPal but the room became unavailable before confirmation. Manual refund/support follow-up required.', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'accommodation_payment_subscription_id' => $subscription->id,
                'provider_subscription_id' => $subscription->provider_subscription_id,
            ]);

            return $this->markProcessed($event);
        }

        $booking->update([
            'status' => AccommodationBooking::STATUS_CONFIRMED,
            'paid_at' => $paidAt,
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

        return $this->markProcessed($event);
    }

    /**
     * @return array<string, string>
     */
    private function paymentFailedNotificationPayload(
        AccommodationPaymentSubscription $subscription,
        AccommodationBooking $booking,
    ): array {
        $failedInstallmentNumber = (int) $subscription->installments_paid_count + 1;
        $failedAmount = (int) $subscription->installments_paid_count === 0
            ? $subscription->first_payment_amount
            : $subscription->monthly_amount;

        return [
            'user_name' => $booking->guest_name,
            'user_email' => $booking->guest_email,
            'admin_email' => (string) config('mail.from.address'),
            'hotel_name' => $booking->accommodation?->title ?? '',
            'room_type' => $booking->roomType?->title ?? '',
            'booking_number' => $booking->booking_number,
            'installment_count' => (string) $subscription->installment_count,
            'failed_installment_number' => (string) $failedInstallmentNumber,
            'failed_amount' => number_format((float) $failedAmount, 2, '.', ''),
            'currency_code' => (string) $subscription->currency_code,
            'next_due_at' => optional($subscription->next_due_at)->toDateString() ?? '',
        ];
    }

    private function markProcessed(AccommodationPaymentSubscriptionEvent $event): AccommodationPaymentSubscriptionEvent
    {
        $event->forceFill([
            'status' => AccommodationPaymentSubscriptionEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    private function markIgnored(AccommodationPaymentSubscriptionEvent $event, string $notes): AccommodationPaymentSubscriptionEvent
    {
        $event->forceFill([
            'status' => AccommodationPaymentSubscriptionEvent::STATUS_IGNORED,
            'processed_at' => now(),
            'notes' => $notes,
        ])->save();

        return $event->fresh();
    }

    /**
     * PayPal can send BILLING.SUBSCRIPTION.ACTIVATED and PAYMENT.SALE.COMPLETED
     * for the same first charge. There is no separate Payment/Invoice ledger
     * here (unlike Package) to cross-check against, so amount comparison
     * isn't reliable either — first_payment_amount and monthly_amount are
     * frequently identical (whenever total_amount divides evenly), which
     * would misclassify a genuine next cycle as a duplicate. Instead, treat
     * this event as the redundant twin only if ANOTHER already-processed
     * successful-payment event for the same subscription occurred within a
     * tight time window — real billing cycles are ~a month apart, while
     * ACTIVATED/PAYMENT.SALE.COMPLETED pairs for the same charge arrive
     * within seconds of each other.
     */
    private function isDuplicateSuccessfulPaymentEvent(
        AccommodationPaymentSubscriptionEvent $event,
        AccommodationPaymentSubscription $subscription,
    ): bool {
        $occurredAt = $event->occurred_at;

        if (! $occurredAt instanceof Carbon) {
            return false;
        }

        return AccommodationPaymentSubscriptionEvent::query()
            ->where('accommodation_payment_subscription_id', $subscription->id)
            ->where('id', '!=', $event->id)
            ->whereIn('provider_event_type', ['BILLING.SUBSCRIPTION.ACTIVATED', 'PAYMENT.SALE.COMPLETED'])
            ->where('status', AccommodationPaymentSubscriptionEvent::STATUS_PROCESSED)
            ->whereBetween('occurred_at', [
                $occurredAt->copy()->subMinutes(10),
                $occurredAt->copy()->addMinutes(10),
            ])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{paid_at: Carbon}|null
     */
    private function extractActivationLastPayment(array $payload): ?array
    {
        $lastPayment = $payload['resource']['billing_info']['last_payment'] ?? null;
        if (! is_array($lastPayment)) {
            return null;
        }

        $value = $lastPayment['amount']['value'] ?? null;
        $time = $lastPayment['time'] ?? null;

        if (! is_numeric($value) || ! is_string($time) || $time === '') {
            return null;
        }

        return [
            'paid_at' => Carbon::parse($time),
        ];
    }

    /**
     * @return array{due_at: Carbon|null, grace_deadline: Carbon|null}
     */
    private function nextScheduleAfterPaidCount(AccommodationPaymentSubscription $subscription, int $paidCount): array
    {
        $plan = $subscription->metadata['installment_plan'] ?? [];
        $recurringDueDates = $plan['recurring_due_dates'] ?? [];
        $graceDeadlines = $plan['grace_deadlines'] ?? [];

        $index = max(0, $paidCount - 1);

        $dueAt = isset($recurringDueDates[$index]) && is_string($recurringDueDates[$index])
            ? Carbon::parse($recurringDueDates[$index])->startOfDay()
            : null;
        $graceDeadline = isset($graceDeadlines[$index]) && is_string($graceDeadlines[$index])
            ? Carbon::parse($graceDeadlines[$index])->startOfDay()
            : null;

        return [
            'due_at' => $dueAt,
            'grace_deadline' => $graceDeadline,
        ];
    }
}
