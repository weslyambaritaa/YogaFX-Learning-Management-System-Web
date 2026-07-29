<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayPalService
{
    public function clientId(): string
    {
        return (string) config('services.paypal.client_id');
    }

    public function environment(): string
    {
        $baseUrl = rtrim((string) config('services.paypal.base_url'), '/');

        return str_contains($baseUrl, 'sandbox')
            ? 'sandbox'
            : 'live';
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
        return $this->createOrderFromReference(
            referenceId: $invoice->invoice_number,
            customId: (string) $paymentActivity->id,
            invoiceId: $invoice->invoice_number,
            currencyCode: $invoice->currency_code,
            amount: (float) $paymentActivity->amount_paid,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
        );
    }

    /**
     * Generic PayPal order creation for domains that are not backed by an
     * Invoice/Payment pair (e.g. accommodation bookings). createOrder() above
     * keeps its exact original behavior and now just delegates here.
     *
     * @return array{order_id: string, approval_url: string}
     */
    public function createOrderFromReference(
        string $referenceId,
        string $customId,
        string $invoiceId,
        string $currencyCode,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $response = $this->authenticatedHttp()
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $referenceId,
                    'custom_id' => $customId,
                    'invoice_id' => $invoiceId,
                    'amount' => [
                        'currency_code' => $currencyCode,
                        'value' => number_format($amount, 2, '.', ''),
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
            $this->logWebhookVerificationFailure('missing_webhook_id', $payload, $headers);

            return false;
        }

        $requestPayload = [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => $payload,
        ];

        try {
            $response = $this->authenticatedHttp()
                ->post('/v1/notifications/verify-webhook-signature', $requestPayload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $this->logWebhookVerificationFailure(
                reason: 'request_exception',
                payload: $payload,
                headers: $headers,
                extra: [
                    'status_code' => $exception->response?->status(),
                    'debug_id' => $exception->response?->json('debug_id'),
                    'response_body' => $exception->response?->json() ?? $exception->response?->body(),
                ],
            );

            throw $exception;
        }

        $verified = ($response['verification_status'] ?? null) === 'SUCCESS';

        if (! $verified) {
            $this->logWebhookVerificationFailure(
                reason: 'verification_failed',
                payload: $payload,
                headers: $headers,
                extra: [
                    'verification_status' => $response['verification_status'] ?? null,
                    'response_body' => $response,
                ],
            );
        }

        return $verified;
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $headers
     * @param  array<string, mixed>  $extra
     */
    private function logWebhookVerificationFailure(
        string $reason,
        array $payload,
        array $headers,
        array $extra = [],
    ): void {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        Log::warning('PayPal webhook signature verification failed.', array_merge([
            'reason' => $reason,
            'paypal_base_url' => $this->baseUrl(),
            'webhook_id_prefix' => substr((string) config('services.paypal.webhook_id'), 0, 4),
            'webhook_id_length' => strlen((string) config('services.paypal.webhook_id')),
            'event_id' => is_string($payload['id'] ?? null) ? $payload['id'] : null,
            'event_type' => is_string($payload['event_type'] ?? null) ? $payload['event_type'] : null,
            'resource_id' => is_string($payload['resource']['id'] ?? null) ? $payload['resource']['id'] : null,
            'billing_agreement_id' => is_string($payload['resource']['billing_agreement_id'] ?? null)
                ? $payload['resource']['billing_agreement_id']
                : null,
            'headers_present' => [
                'paypal-auth-algo' => filled($headers['paypal-auth-algo'] ?? null),
                'paypal-cert-url' => filled($headers['paypal-cert-url'] ?? null),
                'paypal-transmission-id' => filled($headers['paypal-transmission-id'] ?? null),
                'paypal-transmission-sig' => filled($headers['paypal-transmission-sig'] ?? null),
                'paypal-transmission-time' => filled($headers['paypal-transmission-time'] ?? null),
            ],
        ], $extra));
    }
}
