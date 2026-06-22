<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PayPalService
{
    public function clientId(): string
    {
        return (string) config('services.paypal.client_id');
    }

    public function generateClientToken(): string
    {
        $response = $this->authenticatedHttp()
            ->post('/v1/identity/generate-token', (object) [])
            ->throw()
            ->json();

        $token = $response['client_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'PayPal client token could not be generated.',
            ]);
        }

        return $token;
    }

    /**
     * @return array{order_id: string, approval_url: string}
     */
    public function createOrder(
        Invoice $invoice,
        Payment $paymentActivity,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $response = $this->authenticatedHttp()
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $invoice->invoice_number,
                    'custom_id' => (string) $paymentActivity->id,
                    'invoice_id' => $invoice->invoice_number,
                    'amount' => [
                        'currency_code' => $invoice->currency_code,
                        'value' => number_format((float) $paymentActivity->amount_paid, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ])
            ->throw()
            ->json();

        $approvalUrl = collect($response['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! is_string($approvalUrl) || $approvalUrl === '' || ! is_string($response['id'] ?? null)) {
            throw ValidationException::withMessages([
                'payment_method' => 'PayPal approval link could not be created.',
            ]);
        }

        return [
            'order_id' => $response['id'],
            'approval_url' => $approvalUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        return $this->authenticatedHttp()
            ->post('/v2/checkout/orders/'.$orderId.'/capture', (object) [])
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $headers
     */
    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        $webhookId = (string) config('services.paypal.webhook_id');

        if ($webhookId === '') {
            return false;
        }

        $response = $this->authenticatedHttp()
            ->post('/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ])
            ->throw()
            ->json();

        return ($response['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * @return array{event_type: ?string, order_id: ?string}
     */
    public function extractWebhookOrderReference(array $payload): array
    {
        $eventType = $payload['event_type'] ?? null;
        $resource = $payload['resource'] ?? [];

        $orderId = null;

        if (($eventType === 'CHECKOUT.ORDER.APPROVED' || $eventType === 'CHECKOUT.ORDER.COMPLETED') && is_string($resource['id'] ?? null)) {
            $orderId = $resource['id'];
        }

        if (! $orderId && is_string($resource['supplementary_data']['related_ids']['order_id'] ?? null)) {
            $orderId = $resource['supplementary_data']['related_ids']['order_id'];
        }

        return [
            'event_type' => is_string($eventType) ? $eventType : null,
            'order_id' => $orderId,
        ];
    }

    private function authenticatedHttp(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson();
    }

    private function accessToken(): string
    {
        $response = Http::baseUrl($this->baseUrl())
            ->asForm()
            ->withBasicAuth(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.secret'),
            )
            ->post('/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ])
            ->throw()
            ->json();

        $token = $response['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'PayPal access token could not be retrieved.',
            ]);
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paypal.base_url'), '/');
    }
}
