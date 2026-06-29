<?php

namespace App\Console\Commands;

use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Services\Payments\InstallmentWebhookHandler;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Console\Command;

class RecoverInstallmentFirstPaymentCommand extends Command
{
    protected $signature = 'yogafx:installment-recover-first-payment
        {provider_subscription_id : PayPal provider subscription ID}
        {--force : Allow running in production}';

    protected $description = 'Recover a stuck first installment payment from a processed PayPal subscription activation event.';

    public function handle(
        InstallmentWebhookHandler $handler,
        PayPalSubscriptionService $provider,
    ): int {
        if (config('app.env') === 'production' && ! $this->option('force')) {
            $this->error('This recovery command is blocked in production unless --force is provided.');

            return self::INVALID;
        }

        $providerSubscriptionId = (string) $this->argument('provider_subscription_id');

        /** @var PaymentSubscription|null $subscription */
        $subscription = PaymentSubscription::query()
            ->with(['invoice', 'pendingRegistration.onboardingState'])
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->latest('id')
            ->first();

        if (! $subscription instanceof PaymentSubscription) {
            $this->error("Subscription {$providerSubscriptionId} was not found.");

            return self::FAILURE;
        }

        if ($subscription->status !== PaymentSubscription::STATUS_ACTIVE) {
            $this->error("Subscription {$providerSubscriptionId} is not active. Current status: {$subscription->status}.");

            return self::INVALID;
        }

        if ($this->firstPaymentAlreadyProcessed($subscription)) {
            $this->warn("First payment for {$providerSubscriptionId} has already been processed.");

            return self::INVALID;
        }

        /** @var PaymentSubscriptionEvent|null $activationEvent */
        $activationEvent = PaymentSubscriptionEvent::query()
            ->where('payment_subscription_id', $subscription->id)
            ->where('provider_event_type', 'BILLING.SUBSCRIPTION.ACTIVATED')
            ->latest('id')
            ->first();

        $payload = is_array($activationEvent?->payload) ? $activationEvent->payload : null;
        $providerEventId = $activationEvent?->provider_event_id;

        if (! $this->payloadHasActivationLastPayment($payload)) {
            $subscriptionDetail = $provider->getSubscription($providerSubscriptionId);
            $payload = [
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'resource' => $subscriptionDetail,
            ];
        }

        $result = $handler->recoverFirstPaymentFromActivationPayload(
            subscription: $subscription,
            payload: $payload,
            providerEventId: $providerEventId,
        );

        if ($result['status'] !== 'recovered') {
            $this->error(sprintf(
                'Recovery for %s did not complete. Status: %s.',
                $providerSubscriptionId,
                $result['status'],
            ));

            return self::INVALID;
        }

        $subscription->refresh();
        $subscription->loadMissing('invoice', 'pendingRegistration.onboardingState');

        $this->info(sprintf(
            'Recovered first payment for %s using reference %s.',
            $providerSubscriptionId,
            $result['reference'] ?? 'n/a',
        ));
        $this->line(sprintf('Subscription status: %s', $subscription->status));
        $this->line(sprintf('Installments paid count: %d', (int) $subscription->installments_paid_count));
        $this->line(sprintf(
            'First payment paid at: %s',
            optional($subscription->first_payment_paid_at)->toDateTimeString() ?? 'null',
        ));
        $this->line(sprintf(
            'Invoice status: %s | Balance due: %s',
            (string) ($subscription->invoice?->status ?? 'n/a'),
            (string) ($subscription->invoice?->balance_due ?? 'n/a'),
        ));
        $this->line(sprintf(
            'Pending registration status: %s',
            (string) ($subscription->pendingRegistration?->status ?? 'n/a'),
        ));
        $this->line(sprintf(
            'Onboarding state: %s',
            (string) ($subscription->pendingRegistration?->onboardingState?->status ?? 'not_created'),
        ));

        return self::SUCCESS;
    }

    private function firstPaymentAlreadyProcessed(PaymentSubscription $subscription): bool
    {
        return $subscription->first_payment_paid_at !== null
            || (int) $subscription->installments_paid_count >= 1
            || $subscription->invoice()
                ->whereHas('paymentActivities', fn ($query) => $query->where('status', 'success'))
                ->exists();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function payloadHasActivationLastPayment(?array $payload): bool
    {
        return is_array($payload)
            && is_array($payload['resource']['billing_info']['last_payment'] ?? null)
            && is_numeric($payload['resource']['billing_info']['last_payment']['amount']['value'] ?? null)
            && is_string($payload['resource']['billing_info']['last_payment']['amount']['currency_code'] ?? null)
            && is_string($payload['resource']['billing_info']['last_payment']['time'] ?? null);
    }
}
