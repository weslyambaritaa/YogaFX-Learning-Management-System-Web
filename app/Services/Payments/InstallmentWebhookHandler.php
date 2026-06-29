<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\PaymentFinalizerService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InstallmentWebhookHandler
{
    public function __construct(
        private readonly PaymentFinalizerService $paymentFinalizer,
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    public function handle(PaymentSubscriptionEvent $event): PaymentSubscriptionEvent
    {
        return DB::transaction(function () use ($event): PaymentSubscriptionEvent {
            /** @var PaymentSubscriptionEvent $event */
            $event = PaymentSubscriptionEvent::query()
                ->with([
                    'paymentSubscription.invoice.pendingRegistration',
                    'paymentSubscription.user',
                    'paymentSubscription.package',
                    'paymentSubscription.accessTier',
                ])
                ->lockForUpdate()
                ->findOrFail($event->id);

            if ($event->status === PaymentSubscriptionEvent::STATUS_PROCESSED) {
                return $event;
            }

            $subscription = $event->paymentSubscription;
            if (! $subscription instanceof PaymentSubscription) {
                $event->forceFill([
                    'status' => PaymentSubscriptionEvent::STATUS_IGNORED,
                    'processed_at' => now(),
                    'notes' => 'No matching payment subscription was found for this event.',
                ])->save();

                return $event->fresh();
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, event: ?PaymentSubscriptionEvent, reference: ?string}
     */
    public function recoverFirstPaymentFromActivationPayload(
        PaymentSubscription $subscription,
        array $payload,
        ?string $providerEventId = null,
    ): array {
        return DB::transaction(function () use ($subscription, $payload, $providerEventId): array {
            /** @var PaymentSubscription $subscription */
            $subscription = PaymentSubscription::query()
                ->with(['invoice', 'pendingRegistration'])
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if ($subscription->status !== PaymentSubscription::STATUS_ACTIVE) {
                return [
                    'status' => 'subscription_not_active',
                    'event' => null,
                    'reference' => null,
                ];
            }

            if ($this->hasProcessedFirstPayment($subscription)) {
                return [
                    'status' => 'already_processed',
                    'event' => null,
                    'reference' => null,
                ];
            }

            $lastPayment = $this->extractActivationLastPayment($payload, $subscription);

            if ($lastPayment === null) {
                return [
                    'status' => 'missing_last_payment',
                    'event' => null,
                    'reference' => null,
                ];
            }

            $reference = $this->activationPaymentReference($subscription, $lastPayment['paid_at']);
            $event = null;

            if (is_string($providerEventId) && $providerEventId !== '') {
                $event = PaymentSubscriptionEvent::query()
                    ->where('payment_subscription_id', $subscription->id)
                    ->where('provider_event_id', $providerEventId)
                    ->first();
            }

            if (! $event instanceof PaymentSubscriptionEvent) {
                $event = PaymentSubscriptionEvent::query()->create([
                    'payment_subscription_id' => $subscription->id,
                    'invoice_id' => $subscription->invoice_id,
                    'payment_activity_id' => null,
                    'provider' => PaymentSubscription::PROVIDER_PAYPAL,
                    'provider_event_id' => $providerEventId ?: sprintf(
                        'RECOVERY-FIRST-PAYMENT:%s:%s',
                        $subscription->provider_subscription_id ?: $subscription->id,
                        $lastPayment['paid_at']->utc()->format('YmdHis'),
                    ),
                    'provider_event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                    'provider_subscription_id' => $subscription->provider_subscription_id,
                    'provider_order_id' => null,
                    'provider_capture_id' => null,
                    'occurred_at' => $lastPayment['paid_at'],
                    'processed_at' => null,
                    'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
                    'payload' => $payload,
                    'notes' => 'Recovered first payment from activation payload.',
                ]);
            }

            $processedEvent = $this->processSuccessfulPaymentEvent(
                event: $event,
                subscription: $subscription,
                reference: $reference,
                amount: $lastPayment['amount'],
                currencyCode: $lastPayment['currency_code'],
                paidAt: $lastPayment['paid_at'],
            );

            return [
                'status' => 'recovered',
                'event' => $processedEvent,
                'reference' => $reference,
            ];
        });
    }

    private function handleCreated(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    private function handleActivated(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'started_at' => $subscription->started_at ?? ($event->occurred_at ?? now()),
            'last_synced_at' => now(),
        ])->save();

        $lastPayment = $this->extractActivationLastPayment($event->payload, $subscription);

        if ($lastPayment !== null) {
            return $this->processSuccessfulPaymentEvent(
                event: $event,
                subscription: $subscription,
                reference: $this->activationPaymentReference($subscription, $lastPayment['paid_at']),
                amount: $lastPayment['amount'],
                currencyCode: $lastPayment['currency_code'],
                paidAt: $lastPayment['paid_at'],
            );
        }

        return $this->markProcessed($event);
    }

    private function handleCancelled(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => PaymentSubscription::STATUS_CANCELLED,
            'cancelled_at' => $event->occurred_at ?? now(),
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    private function handleSuspended(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => PaymentSubscription::STATUS_SUSPENDED,
            'suspended_at' => $event->occurred_at ?? now(),
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    private function handleExpired(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $subscription->forceFill([
            'status' => PaymentSubscription::STATUS_COMPLETED,
            'completed_at' => $event->occurred_at ?? now(),
            'next_due_at' => null,
            'grace_deadline_at' => null,
            'last_synced_at' => now(),
        ])->save();

        return $this->markProcessed($event);
    }

    private function handlePaymentFailed(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $dueAt = $subscription->next_due_at ?? $event->occurred_at ?? now();

        $subscription->forceFill([
            'status' => PaymentSubscription::STATUS_PAST_DUE,
            'last_payment_failed_at' => $event->occurred_at ?? now(),
            'grace_deadline_at' => $dueAt instanceof Carbon ? $dueAt->copy()->addDays(3) : Carbon::parse($dueAt)->addDays(3),
            'last_synced_at' => now(),
        ])->save();

        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_FAILED,
            $this->notificationPayload(
                subscription: $subscription,
                event: $event,
                paymentAmount: (float) ($subscription->next_billing_amount ?: $subscription->monthly_base_amount),
            ),
            OverdueInstallmentService::REFERENCE_TYPE_PAYMENT_SUBSCRIPTION,
            $subscription->id,
        );

        return $this->markProcessed($event);
    }

    private function handlePaymentCompleted(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
    ): PaymentSubscriptionEvent {
        $invoice = $subscription->invoice()->lockForUpdate()->first();
        if (! $invoice instanceof Invoice) {
            return $this->markIgnored($event, 'No invoice is attached to this payment subscription.');
        }

        $reference = $event->provider_capture_id ?: $event->provider_order_id ?: $event->provider_event_id;
        $amount = $this->extractAmountFromPayload($event->payload, $subscription);
        $currencyCode = $this->extractCurrencyCodeFromPayload($event->payload, $subscription);

        if ($this->isDuplicateFirstPaymentSaleCompleted($subscription, $invoice, $amount)) {
            $existingFirstPayment = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', Payment::STATUS_SUCCESS)
                ->orderBy('id')
                ->first();

            if ($existingFirstPayment instanceof Payment) {
                $event->forceFill([
                    'payment_activity_id' => $existingFirstPayment->id,
                ])->save();
            }

            return $this->markProcessed($event);
        }

        return $this->processSuccessfulPaymentEvent(
            event: $event,
            subscription: $subscription,
            reference: $reference,
            amount: $amount,
            currencyCode: $currencyCode,
            paidAt: $event->occurred_at ?? now(),
        );
    }

    private function processSuccessfulPaymentEvent(
        PaymentSubscriptionEvent $event,
        PaymentSubscription $subscription,
        string $reference,
        float $amount,
        string $currencyCode,
        Carbon $paidAt,
    ): PaymentSubscriptionEvent {
        $currentStatus = $subscription->status;
        $invoice = $subscription->invoice()->lockForUpdate()->first();
        if (! $invoice instanceof Invoice) {
            return $this->markIgnored($event, 'No invoice is attached to this payment subscription.');
        }

        $paymentActivity = $this->upsertInstallmentPaymentActivity(
            invoice: $invoice,
            reference: $reference,
            amount: $amount,
            currencyCode: $currencyCode,
        );

        $alreadySuccessful = $paymentActivity->status === Payment::STATUS_SUCCESS;

        $result = $this->paymentFinalizer->finalizeSuccessfulPayment($paymentActivity, $reference);

        $paidCount = (int) $subscription->installments_paid_count;
        if (! $alreadySuccessful && ! ($result['skipped'] ?? false)) {
            $paidCount++;
        }

        $nextSchedule = $this->nextScheduleAfterPaidCount($subscription, $paidCount);
        $isCompleted = ((float) $result['invoice']->balance_due) <= 0 || $paidCount >= (int) $subscription->installment_count;

        $user = $result['user'] instanceof User ? $result['user'] : $subscription->user;
        $reactivationBlocked = in_array($currentStatus, [
            PaymentSubscription::STATUS_CANCELLED,
            PaymentSubscription::STATUS_SUSPENDED,
        ], true);
        $reactivated = false;

        if ($paidCount > 1 && $user instanceof User && ! $user->is_active && ! $reactivationBlocked) {
            $user->forceFill([
                'is_active' => true,
            ])->save();

            $reactivated = true;
        }

        $metadata = $subscription->metadata ?? [];
        if ($reactivated) {
            $metadata['reactivated_at'] = now()->toIso8601String();
            $metadata['reactivated_by_event_id'] = $event->provider_event_id;
        }

        if ($reactivationBlocked && $user instanceof User && ! $user->is_active) {
            $metadata['reactivation_blocked_at'] = now()->toIso8601String();
            $metadata['reactivation_blocked_reason'] = 'subscription_'.$currentStatus;
            $metadata['reactivation_blocked_by_event_id'] = $event->provider_event_id;
        }

        if ($event->provider_event_type === 'BILLING.SUBSCRIPTION.ACTIVATED') {
            $metadata['activation_last_payment_processed_at'] = $paidAt->toIso8601String();
            $metadata['activation_last_payment_reference'] = $reference;
        }

        $subscription->forceFill([
            'user_id' => $user?->id,
            'status' => $reactivationBlocked
                ? $currentStatus
                : ($isCompleted ? PaymentSubscription::STATUS_COMPLETED : PaymentSubscription::STATUS_ACTIVE),
            'installments_paid_count' => $paidCount,
            'first_payment_paid_at' => $subscription->first_payment_paid_at ?? $paidAt,
            'started_at' => $subscription->started_at ?? $paidAt,
            'completed_at' => (! $reactivationBlocked && $isCompleted) ? $paidAt : null,
            'next_due_at' => (! $reactivationBlocked && $isCompleted) ? null : $nextSchedule['due_at'],
            'grace_deadline_at' => (! $reactivationBlocked && $isCompleted) ? null : $nextSchedule['grace_deadline'],
            'last_payment_failed_at' => null,
            'last_synced_at' => now(),
            'metadata' => $metadata,
        ])->save();

        $event->forceFill([
            'payment_activity_id' => $paymentActivity->id,
        ])->save();

        if (! $alreadySuccessful && ! ($result['skipped'] ?? false)) {
            $this->emailNotificationService->sendAutomated(
                EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS,
                $this->notificationPayload(
                    subscription: $subscription,
                    invoice: $result['invoice'] instanceof Invoice ? $result['invoice'] : $invoice,
                    user: $user,
                    event: $event,
                    paymentAmount: $amount,
                    installmentsPaidCount: $paidCount,
                ),
                OverdueInstallmentService::REFERENCE_TYPE_PAYMENT_SUBSCRIPTION,
                $subscription->id,
            );
        }

        if ($isCompleted && ! $reactivationBlocked && ! $alreadySuccessful && ! ($result['skipped'] ?? false)) {
            $this->emailNotificationService->sendAutomated(
                EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_COMPLETED,
                $this->notificationPayload(
                    subscription: $subscription->fresh(['invoice', 'package', 'user', 'accessTier']),
                    invoice: $result['invoice'] instanceof Invoice ? $result['invoice'] : $invoice,
                    user: $user,
                    event: $event,
                    paymentAmount: $amount,
                    installmentsPaidCount: $paidCount,
                    paymentCompletedAt: $paidAt,
                ),
                OverdueInstallmentService::REFERENCE_TYPE_PAYMENT_SUBSCRIPTION,
                $subscription->id,
            );
        }

        return $this->markProcessed($event);
    }

    private function notificationPayload(
        PaymentSubscription $subscription,
        ?Invoice $invoice = null,
        ?User $user = null,
        ?PaymentSubscriptionEvent $event = null,
        ?float $paymentAmount = null,
        ?int $installmentsPaidCount = null,
        ?Carbon $paymentCompletedAt = null,
    ): array {
        $invoice ??= $subscription->invoice;
        $user ??= $subscription->user ?? $invoice?->user;

        return [
            'notification_type' => $event?->provider_event_type ?? '',
            'user_name' => $user?->name ?? ($subscription->pendingRegistration?->first_name ?? 'Student'),
            'user_email' => $user?->email ?? ($subscription->pendingRegistration?->email ?? ''),
            'admin_email' => config('mail.from.address'),
            'package_title' => $subscription->package?->title ?? '',
            'tier_name' => $subscription->accessTier?->name ?? '',
            'invoice_number' => (string) ($invoice?->invoice_number ?? ''),
            'payment_amount' => $paymentAmount !== null ? number_format($paymentAmount, 2, '.', '') : '',
            'currency_code' => (string) $subscription->currency_code,
            'installment_count' => (string) $subscription->installment_count,
            'installments_paid_count' => (string) ($installmentsPaidCount ?? $subscription->installments_paid_count),
            'balance_due' => (string) ($invoice?->balance_due ?? ''),
            'next_due_at' => optional($subscription->next_due_at)->toDateString() ?? '',
            'grace_deadline_at' => optional($subscription->grace_deadline_at)->toDateString() ?? '',
            'payment_completed_at' => optional($paymentCompletedAt)->toDateTimeString() ?? '',
        ];
    }

    private function markProcessed(PaymentSubscriptionEvent $event): PaymentSubscriptionEvent
    {
        $event->forceFill([
            'status' => PaymentSubscriptionEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    private function markIgnored(PaymentSubscriptionEvent $event, string $notes): PaymentSubscriptionEvent
    {
        $event->forceFill([
            'status' => PaymentSubscriptionEvent::STATUS_IGNORED,
            'processed_at' => now(),
            'notes' => $notes,
        ])->save();

        return $event->fresh();
    }

    private function extractAmountFromPayload(array $payload, PaymentSubscription $subscription): float
    {
        $amount = $payload['resource']['amount']['total'] ?? null;

        if (is_numeric($amount)) {
            return round((float) $amount, 2);
        }

        return (int) $subscription->installments_paid_count === 0
            ? (float) $subscription->first_payment_amount
            : (float) ($subscription->next_billing_amount ?: $subscription->monthly_base_amount);
    }

    private function extractCurrencyCodeFromPayload(array $payload, PaymentSubscription $subscription): string
    {
        $currency = $payload['resource']['amount']['currency'] ?? null;

        return is_string($currency) && $currency !== ''
            ? $currency
            : (string) $subscription->currency_code;
    }

    private function upsertInstallmentPaymentActivity(
        Invoice $invoice,
        string $reference,
        float $amount,
        string $currencyCode,
    ): Payment {
        /** @var Payment|null $existing */
        $existing = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_reference', $reference)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof Payment) {
            $existing->forceFill([
                'payment_method' => Payment::METHOD_PAYPAL,
                'payment_type' => Payment::TYPE_INSTALLMENT,
                'amount_paid' => $amount,
                'currency_code' => $currencyCode,
                'notes' => 'PayPal subscription payment received via webhook.',
            ])->save();

            return $existing->fresh();
        }

        /** @var Payment $created */
        $created = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_INSTALLMENT,
            'payment_reference' => $reference,
            'amount_paid' => $amount,
            'currency_code' => $currencyCode,
            'status' => Payment::STATUS_PENDING,
            'notes' => 'PayPal subscription payment received via webhook.',
        ]);

        return $created;
    }

    /**
     * @return array{due_at: Carbon|null, grace_deadline: Carbon|null}
     */
    private function nextScheduleAfterPaidCount(PaymentSubscription $subscription, int $paidCount): array
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array{amount: float, currency_code: string, paid_at: Carbon}|null
     */
    private function extractActivationLastPayment(array $payload, PaymentSubscription $subscription): ?array
    {
        $lastPayment = $payload['resource']['billing_info']['last_payment'] ?? null;
        if (! is_array($lastPayment)) {
            return null;
        }

        $value = $lastPayment['amount']['value'] ?? null;
        $currencyCode = $lastPayment['amount']['currency_code'] ?? null;
        $time = $lastPayment['time'] ?? null;

        if (! is_numeric($value) || ! is_string($currencyCode) || $currencyCode === '' || ! is_string($time) || $time === '') {
            return null;
        }

        if ($subscription->first_payment_paid_at !== null) {
            return null;
        }

        return [
            'amount' => round((float) $value, 2),
            'currency_code' => $currencyCode,
            'paid_at' => Carbon::parse($time),
        ];
    }

    private function activationPaymentReference(PaymentSubscription $subscription, Carbon $paidAt): string
    {
        return sprintf(
            'subscription_activation:%s:%s',
            $subscription->provider_subscription_id ?: $subscription->id,
            $paidAt->utc()->format('YmdHis'),
        );
    }

    private function isDuplicateFirstPaymentSaleCompleted(
        PaymentSubscription $subscription,
        Invoice $invoice,
        float $amount,
    ): bool {
        $expectedRemainingAfterFirstPayment = round(
            (float) $subscription->total_amount - (float) $subscription->first_payment_amount,
            2,
        );

        return $subscription->first_payment_paid_at !== null
            && (int) $subscription->installments_paid_count >= 1
            && round((float) $invoice->balance_due, 2) === $expectedRemainingAfterFirstPayment
            && round($amount, 2) === round((float) $subscription->first_payment_amount, 2)
            && Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', Payment::STATUS_SUCCESS)
                ->exists();
    }

    private function hasProcessedFirstPayment(PaymentSubscription $subscription): bool
    {
        return $subscription->first_payment_paid_at !== null
            || (int) $subscription->installments_paid_count >= 1
            || Payment::query()
                ->where('invoice_id', $subscription->invoice_id)
                ->where('status', Payment::STATUS_SUCCESS)
                ->exists();
    }
}
