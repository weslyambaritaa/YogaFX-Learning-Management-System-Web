<?php

namespace App\Services\Payments;

use App\Models\Package;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayPalSubscriptionService
{
    private const PRODUCT_NAME_MAX_LENGTH = 127;
    private const PRODUCT_DESCRIPTION_MAX_LENGTH = 256;
    private const PLAN_NAME_MAX_LENGTH = 127;
    private const PLAN_DESCRIPTION_MAX_LENGTH = 127;
    private const PRODUCT_TYPE = 'SERVICE';
    private const PRODUCT_CATEGORY = 'SOFTWARE';

    public const WEBHOOK_EVENT_TYPES = [
        'BILLING.SUBSCRIPTION.CREATED',
        'BILLING.SUBSCRIPTION.ACTIVATED',
        'BILLING.SUBSCRIPTION.CANCELLED',
        'BILLING.SUBSCRIPTION.SUSPENDED',
        'BILLING.SUBSCRIPTION.EXPIRED',
        'PAYMENT.SALE.COMPLETED',
        'PAYMENT.SALE.DENIED',
        'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
    ];

    /**
     * @param  array<string, mixed>  $installmentPlan
     * @return array{id: string, status: string}
     */
    public function createProduct(Package $package, array $installmentPlan): array
    {
        return $this->createProductFromReference(
            $this->productName($package),
            $this->productDescription($package, $installmentPlan),
        );
    }

    /**
     * Generic PayPal catalog product creation for domains that are not
     * backed by a Package (e.g. accommodation installment subscriptions).
     * createProduct() above keeps its exact original behavior and now just
     * delegates here.
     *
     * @return array{id: string, status: string}
     */
    public function createProductFromReference(string $name, string $description): array
    {
        $payload = [
            'name' => mb_substr($name, 0, self::PRODUCT_NAME_MAX_LENGTH),
            'description' => mb_substr($description, 0, self::PRODUCT_DESCRIPTION_MAX_LENGTH),
            'type' => self::PRODUCT_TYPE,
            'category' => self::PRODUCT_CATEGORY,
        ];

        try {
            $response = $this->authenticatedHttp()
                ->post('/v1/catalogs/products', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $this->logProviderFailure('/v1/catalogs/products', $payload, $exception);

            throw ValidationException::withMessages([
                'payment_method' => $this->providerFailureMessage('product', $exception),
            ]);
        }

        if (! is_string($response['id'] ?? null) || $response['id'] === '') {
            Log::warning('PayPal product response was missing the required identifier.', [
                'endpoint' => '/v1/catalogs/products',
                'response_body' => $response,
                'payload' => $payload,
            ]);

            throw ValidationException::withMessages([
                'payment_method' => 'PayPal product response was incomplete.',
            ]);
        }

        return [
            'id' => $response['id'],
            'status' => is_string($response['status'] ?? null) && $response['status'] !== ''
                ? $response['status']
                : 'CREATED',
        ];
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     * @return array{id: string, status: string}
     */
    public function createPlan(
        Package $package,
        array $installmentPlan,
        string $productId,
    ): array {
        $installmentCount = min(
            Package::MAX_PROVIDER_INSTALLMENT_COUNT,
            max(Package::MIN_INSTALLMENT_COUNT, (int) ($installmentPlan['installment_count'] ?? Package::MIN_INSTALLMENT_COUNT)),
        );

        /*
        |--------------------------------------------------------------------------
        | New installment flow
        |--------------------------------------------------------------------------
        |
        | Pembayaran pertama diperlakukan sebagai setup_fee.
        | Sisa cicilan diperlakukan sebagai REGULAR billing cycle.
        |
        | Contoh:
        | installment_count = 4
        | - 1 pembayaran pertama saat approval/activation
        | - 3 recurring billing bulanan setelahnya
        |
        */
        return $this->createPlanFromReference(
            productId: $productId,
            name: $this->planName($package, $installmentPlan),
            description: $this->planDescription($installmentPlan),
            currencyCode: (string) $installmentPlan['currency_code'],
            installmentCount: $installmentCount,
            intervalUnit: (string) ($installmentPlan['billing_interval_unit'] ?? 'MONTH'),
            intervalCount: (int) ($installmentPlan['billing_interval_count'] ?? 1),
            recurringAmount: $installmentPlan['recurring_payment_amount'],
firstPaymentAmount: $installmentPlan['first_payment_amount'],
paymentFailureThreshold: 1,
firstRecurringAmount:
    $installmentPlan['first_recurring_payment_amount']
        ?? $installmentPlan['recurring_payment_amount'],
        );
    }

    /**
     * Generic PayPal billing plan creation for domains that are not backed
     * by a Package (e.g. accommodation installment subscriptions).
     * createPlan() above keeps its exact original behavior (including the
     * payment_failure_threshold=1 Package uses) and now just delegates here.
     *
     * @return array{id: string, status: string}
     */
    public function createPlanFromReference(
    string $productId,
    string $name,
    string $description,
    string $currencyCode,
    int $installmentCount,
    string $intervalUnit,
    int $intervalCount,
    float|int|string $recurringAmount,
    float|int|string $firstPaymentAmount,
    int $paymentFailureThreshold,
    float|int|string|null $firstRecurringAmount = null,
): array {
        $recurringCycles = max(1, $installmentCount - 1);

$frequency = [
    'interval_unit' => strtoupper($intervalUnit),
    'interval_count' => max(1, $intervalCount),
];

$regularAmount = $this->paypalAmount($recurringAmount);
$adjustedFirstRecurringAmount = $this->paypalAmount(
    $firstRecurringAmount ?? $recurringAmount,
);

$billingCycles = [];

if (
    $recurringCycles > 1
    && $adjustedFirstRecurringAmount !== $regularAmount
) {
    /*
    |--------------------------------------------------------------------------
    | Paid adjustment cycle
    |--------------------------------------------------------------------------
    |
    | PayPal hanya mengizinkan satu REGULAR cycle. Satu TRIAL cycle berbayar
    | dipakai untuk menyerap selisih pembulatan recurring payment pertama.
    |
    */
    $billingCycles[] = [
        'frequency' => $frequency,
        'tenure_type' => 'TRIAL',
        'sequence' => 1,
        'total_cycles' => 1,
        'pricing_scheme' => [
            'fixed_price' => [
                'currency_code' => $currencyCode,
                'value' => $adjustedFirstRecurringAmount,
            ],
        ],
    ];

    $billingCycles[] = [
        'frequency' => $frequency,
        'tenure_type' => 'REGULAR',
        'sequence' => 2,
        'total_cycles' => $recurringCycles - 1,
        'pricing_scheme' => [
            'fixed_price' => [
                'currency_code' => $currencyCode,
                'value' => $regularAmount,
            ],
        ],
    ];
} else {
    $billingCycles[] = [
        'frequency' => $frequency,
        'tenure_type' => 'REGULAR',
        'sequence' => 1,
        'total_cycles' => $recurringCycles,
        'pricing_scheme' => [
            'fixed_price' => [
                'currency_code' => $currencyCode,
                'value' => $recurringCycles === 1
                    ? $adjustedFirstRecurringAmount
                    : $regularAmount,
            ],
        ],
    ];
}

$payload = [
    'product_id' => $productId,
    'name' => mb_substr($name, 0, self::PLAN_NAME_MAX_LENGTH),
    'description' => mb_substr(
        $description,
        0,
        self::PLAN_DESCRIPTION_MAX_LENGTH,
    ),
    'status' => 'ACTIVE',
    'billing_cycles' => $billingCycles,
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'currency_code' => $currencyCode,
                    'value' => $this->paypalAmount($firstPaymentAmount),
                ],
                'setup_fee_failure_action' => 'CANCEL',
                'payment_failure_threshold' => $paymentFailureThreshold,
            ],
        ];

        try {
            $response = $this->authenticatedHttp()
                ->post('/v1/billing/plans', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $this->logProviderFailure('/v1/billing/plans', $payload, $exception);

            throw ValidationException::withMessages([
                'payment_method' => $this->providerFailureMessage('subscription plan', $exception),
            ]);
        }

        if (! is_string($response['id'] ?? null) || ! is_string($response['status'] ?? null)) {
            Log::warning('PayPal subscription plan response was incomplete.', [
                'endpoint' => '/v1/billing/plans',
                'response_body' => $response,
                'payload' => $payload,
            ]);

            throw ValidationException::withMessages([
                'payment_method' => 'PayPal subscription plan could not be created.',
            ]);
        }

        return [
            'id' => $response['id'],
            'status' => $response['status'],
        ];
    }

    /**
     * @param  array{
     *     plan_id: string,
     *     return_url: string,
     *     cancel_url: string,
     *     custom_id?: string|null,
     *     start_time?: string|null,
     *     subscriber?: array<string, mixed>|null
     * }  $attributes
     * @return array{id: string, status: string, approval_url: string|null}
     */
    public function createSubscription(array $attributes): array
    {
        $payload = [
            'plan_id' => $attributes['plan_id'],
            'application_context' => [
                'return_url' => $attributes['return_url'],
                'cancel_url' => $attributes['cancel_url'],
            ],
        ];

        if (! empty($attributes['custom_id'])) {
            $payload['custom_id'] = $attributes['custom_id'];
        }

        if (! empty($attributes['start_time'])) {
            $payload['start_time'] = $attributes['start_time'];
        }

        if (! empty($attributes['subscriber']) && is_array($attributes['subscriber'])) {
            $payload['subscriber'] = $attributes['subscriber'];
        }

        try {
            $response = $this->authenticatedHttp()
                ->post('/v1/billing/subscriptions', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $this->logProviderFailure('/v1/billing/subscriptions', $payload, $exception);

            throw ValidationException::withMessages([
                'payment_method' => $this->providerFailureMessage('subscription', $exception),
            ]);
        }

        if (! is_string($response['id'] ?? null) || ! is_string($response['status'] ?? null)) {
            Log::warning('PayPal subscription response was incomplete.', [
                'endpoint' => '/v1/billing/subscriptions',
                'response_body' => $response,
                'payload' => $payload,
            ]);

            throw ValidationException::withMessages([
                'payment_method' => 'PayPal subscription could not be created.',
            ]);
        }

        $approvalUrl = collect($response['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'id' => $response['id'],
            'status' => $response['status'],
            'approval_url' => is_string($approvalUrl) && $approvalUrl !== '' ? $approvalUrl : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->authenticatedHttp()
            ->get('/v1/billing/subscriptions/'.$subscriptionId)
            ->throw()
            ->json();
    }

    public function cancelSubscription(string $subscriptionId, ?string $reason = null): void
    {
        $this->authenticatedHttp()
            ->post('/v1/billing/subscriptions/'.$subscriptionId.'/cancel', [
                'reason' => $reason ?: 'Cancelled by YogaFX LMS.',
            ])
            ->throw();
    }

    public function suspendSubscription(string $subscriptionId, ?string $reason = null): void
    {
        $this->authenticatedHttp()
            ->post('/v1/billing/subscriptions/'.$subscriptionId.'/suspend', [
                'reason' => $reason ?: 'Suspended by YogaFX LMS.',
            ])
            ->throw();
    }

    public function activateSubscription(string $subscriptionId, ?string $reason = null): void
    {
        $this->authenticatedHttp()
            ->post('/v1/billing/subscriptions/'.$subscriptionId.'/activate', [
                'reason' => $reason ?: 'Activated by YogaFX LMS.',
            ])
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     event_type: ?string,
     *     subscription_id: ?string,
     *     plan_id: ?string,
     *     order_id: ?string,
     *     capture_id: ?string,
     *     resource_id: ?string
     * }
     */
    public function extractWebhookReferences(array $payload): array
    {
        $eventType = is_string($payload['event_type'] ?? null) ? $payload['event_type'] : null;
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $supplementary = is_array($resource['supplementary_data']['related_ids'] ?? null)
            ? $resource['supplementary_data']['related_ids']
            : [];

        $subscriptionId = $this->firstString([
            $resource['id'] ?? null,
            $resource['billing_agreement_id'] ?? null,
            $supplementary['subscription_id'] ?? null,
        ]);

        if ($eventType !== null && str_contains($eventType, 'PAYMENT')) {
            $subscriptionId = $this->firstString([
                $resource['billing_agreement_id'] ?? null,
                $supplementary['subscription_id'] ?? null,
                $subscriptionId,
            ]);
        }

        return [
            'event_type' => $eventType,
            'subscription_id' => $subscriptionId,
            'plan_id' => $this->firstString([
                $resource['plan_id'] ?? null,
                $supplementary['plan_id'] ?? null,
            ]),
            'order_id' => $this->firstString([
                $supplementary['order_id'] ?? null,
                $resource['order_id'] ?? null,
            ]),
            'capture_id' => $this->firstString([
                $supplementary['capture_id'] ?? null,
                $resource['sale_id'] ?? null,
                $resource['id'] ?? null,
            ]),
            'resource_id' => $this->firstString([
                $resource['id'] ?? null,
            ]),
        ];
    }

    public function isSubscriptionWebhookEvent(?string $eventType): bool
    {
        return is_string($eventType) && in_array($eventType, self::WEBHOOK_EVENT_TYPES, true);
    }

    private function authenticatedHttp(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->asJson();
    }

    private function accessToken(): string
    {
        $payload = [
            'grant_type' => 'client_credentials',
        ];

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->connectTimeout(10)
                ->timeout(30)
                ->asForm()
                ->withBasicAuth(
                    (string) config('services.paypal.client_id'),
                    (string) config('services.paypal.secret'),
                )
                ->post('/v1/oauth2/token', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $this->logProviderFailure('/v1/oauth2/token', $payload, $exception);

            throw ValidationException::withMessages([
                'payment_method' => $this->providerFailureMessage('access token', $exception),
            ]);
        }

        $token = $response['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            Log::warning('PayPal access token response was incomplete.', [
                'endpoint' => '/v1/oauth2/token',
                'response_body' => $response,
            ]);

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

    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function productName(Package $package): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $package->title) ?: '');

        if ($name === '') {
            $name = 'YogaFX Package';
        }

        return mb_substr($name, 0, self::PRODUCT_NAME_MAX_LENGTH);
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function productDescription(Package $package, array $installmentPlan): string
    {
        $description = trim(preg_replace('/\s+/', ' ', (string) $package->description) ?: '');

        if ($description === '') {
            $description = sprintf(
                'YogaFX %s package billed in installments for %s %s.',
                $this->productName($package),
                (string) ($installmentPlan['currency_code'] ?? $package->currency_code),
                $this->paypalAmount($installmentPlan['total_amount'] ?? $package->price),
            );
        }

        return mb_substr($description, 0, self::PRODUCT_DESCRIPTION_MAX_LENGTH);
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function planName(Package $package, array $installmentPlan): string
    {
        $billingDay = $installmentPlan['billing_day'] ?? null;
        $installmentCount = (int) ($installmentPlan['installment_count'] ?? 0);

        $suffix = $billingDay !== null
            ? sprintf('%dx - Day %s', $installmentCount, $billingDay)
            : sprintf('%dx', $installmentCount);

        $name = sprintf('%s Installment %s', $this->productName($package), $suffix);

        return mb_substr($name, 0, self::PLAN_NAME_MAX_LENGTH);
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function planDescription(array $installmentPlan): string
    {
        $installmentCount = (int) ($installmentPlan['installment_count'] ?? 0);
        $billingDay = $installmentPlan['billing_day'] ?? null;
        $finalDueAt = (string) ($installmentPlan['final_due_at'] ?? '');

        $description = sprintf(
            '%d installments%s%s.',
            $installmentCount,
            $billingDay !== null ? sprintf(' on day %s', $billingDay) : '',
            $finalDueAt !== '' ? sprintf(' until %s', $finalDueAt) : '',
        );

        return mb_substr($description, 0, self::PLAN_DESCRIPTION_MAX_LENGTH);
    }

    private function paypalAmount(float|int|string|null $amount): string
    {
        return number_format(round((float) ($amount ?? 0), 2), 2, '.', '');
    }

    private function providerFailureMessage(string $resource, RequestException $exception): string
    {
        $debugId = $exception->response?->json('debug_id');
        $message = sprintf('PayPal %s request failed.', $resource);

        if (is_string($debugId) && $debugId !== '') {
            $message .= sprintf(' Debug ID: %s.', $debugId);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logProviderFailure(string $endpoint, array $payload, RequestException $exception): void
    {
        Log::error('PayPal subscription API request failed.', [
            'endpoint' => $endpoint,
            'status_code' => $exception->response?->status(),
            'debug_id' => $exception->response?->json('debug_id'),
            'response_body' => $exception->response?->json() ?? $exception->response?->body(),
            'payload' => $payload,
            'base_url' => $this->baseUrl(),
        ]);
    }
}
