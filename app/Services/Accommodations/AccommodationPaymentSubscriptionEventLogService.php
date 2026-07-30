<?php

namespace App\Services\Accommodations;

use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationPaymentSubscriptionEvent;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Support\Carbon;

class AccommodationPaymentSubscriptionEventLogService
{
    public function __construct(
        private readonly PayPalSubscriptionService $provider,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: AccommodationPaymentSubscriptionEvent, duplicate: bool}
     */
    public function logPayPalWebhookEvent(array $payload): array
    {
        $providerEventId = $this->extractProviderEventId($payload);

        if ($providerEventId !== null) {
            $existing = AccommodationPaymentSubscriptionEvent::query()
                ->where('provider', AccommodationPaymentSubscription::PROVIDER_PAYPAL)
                ->where('provider_event_id', $providerEventId)
                ->first();

            if ($existing instanceof AccommodationPaymentSubscriptionEvent) {
                return [
                    'event' => $existing,
                    'duplicate' => true,
                ];
            }
        }

        $references = $this->provider->extractWebhookReferences($payload);
        $subscription = null;

        if (is_string($references['subscription_id'] ?? null) && $references['subscription_id'] !== '') {
            $subscription = AccommodationPaymentSubscription::query()
                ->where('provider', AccommodationPaymentSubscription::PROVIDER_PAYPAL)
                ->where('provider_subscription_id', $references['subscription_id'])
                ->latest('id')
                ->first();
        }

        $event = AccommodationPaymentSubscriptionEvent::query()->create([
            'accommodation_payment_subscription_id' => $subscription?->id,
            'provider' => AccommodationPaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => $providerEventId,
            'provider_event_type' => $references['event_type'],
            'provider_subscription_id' => $references['subscription_id'],
            'provider_order_id' => $references['order_id'],
            'provider_capture_id' => $references['capture_id'],
            'occurred_at' => $this->extractOccurredAt($payload),
            'processed_at' => null,
            'status' => AccommodationPaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => $payload,
        ]);

        return [
            'event' => $event,
            'duplicate' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderEventId(array $payload): ?string
    {
        $eventId = $payload['id'] ?? null;

        return is_string($eventId) && $eventId !== '' ? $eventId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOccurredAt(array $payload): ?Carbon
    {
        $candidates = [
            $payload['create_time'] ?? null,
            $payload['resource']['create_time'] ?? null,
            $payload['resource']['update_time'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return Carbon::parse($candidate);
            }
        }

        return null;
    }
}
