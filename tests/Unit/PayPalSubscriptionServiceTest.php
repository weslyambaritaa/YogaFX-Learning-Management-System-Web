<?php

namespace Tests\Unit;

use App\Models\AccessTier;
use App\Models\Package;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayPalSubscriptionServiceTest extends TestCase
{
    public function test_it_creates_paypal_product_from_package_context(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response([
                'id' => 'PROD-123',
            ], 201),
        ]);

        $result = $this->service()->createProduct(
            $this->package(),
            $this->installmentPlan(),
        );

        $this->assertSame([
            'id' => 'PROD-123',
            'status' => 'CREATED',
        ], $result);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v1/catalogs/products') {
                return false;
            }

            return $request['name'] === 'Masterclass Standard'
                && $request['description'] === 'Premium masterclass package.'
                && $request['type'] === 'SERVICE'
                && $request['category'] === 'SOFTWARE'
                && ! array_key_exists('image_url', $request->data())
                && ! array_key_exists('home_url', $request->data());
        });
    }

    public function test_it_sanitizes_paypal_product_payload_and_omits_localhost_urls(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response([
                'id' => 'PROD-LOCAL-001',
            ], 201),
        ]);

        $package = new Package([
            'title' => "  Masterclass   Standard  \n",
            'description' => '',
            'image' => 'http://127.0.0.1:8000/storage/packages/masterclass.png',
        ]);

        $this->service()->createProduct($package, $this->installmentPlan());

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v1/catalogs/products') {
                return false;
            }

            $data = $request->data();

            return $data['name'] === 'Masterclass Standard'
                && str_contains($data['description'], 'YogaFX Masterclass Standard package billed in installments')
                && $data['type'] === 'SERVICE'
                && $data['category'] === 'SOFTWARE'
                && ! array_key_exists('image_url', $data)
                && ! array_key_exists('home_url', $data);
        });
    }

    public function test_it_creates_paypal_plan_using_installment_mapping(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/plans' => Http::response([
                'id' => 'P-123',
                'status' => 'ACTIVE',
            ], 201),
        ]);

        $result = $this->service()->createPlan(
            $this->package(),
            $this->installmentPlan(),
            'PROD-123',
        );

        $this->assertSame([
            'id' => 'P-123',
            'status' => 'ACTIVE',
        ], $result);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v1/billing/plans') {
                return false;
            }

            $data = $request->data();

            return $data['product_id'] === 'PROD-123'
                && $data['billing_cycles'][0]['frequency']['interval_unit'] === 'MONTH'
                && $data['billing_cycles'][0]['frequency']['interval_count'] === 1
                && $data['billing_cycles'][0]['total_cycles'] === 6
                && $data['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] === '42.00'
                && $data['payment_preferences']['setup_fee']['value'] === '48.00'
                && $data['payment_preferences']['setup_fee']['currency_code'] === 'USD';
        });
    }

    public function test_it_creates_paypal_subscription_and_returns_approval_url(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'I-SUBSCRIPTION-001',
                'status' => 'APPROVAL_PENDING',
                'links' => [
                    [
                        'rel' => 'approve',
                        'href' => 'https://www.paypal.com/webapps/billing/subscriptions?ba_token=I-SUBSCRIPTION-001',
                    ],
                ],
            ], 201),
        ]);

        $result = $this->service()->createSubscription([
            'plan_id' => 'P-123',
            'return_url' => 'https://example.com/paypal/subscriptions/return',
            'cancel_url' => 'https://example.com/paypal/subscriptions/cancel',
            'custom_id' => 'INV-001',
            'start_time' => '2026-07-10T09:00:00Z',
            'subscriber' => [
                'email_address' => 'ava@example.com',
                'name' => [
                    'given_name' => 'Ava',
                    'surname' => 'Stone',
                ],
            ],
        ]);

        $this->assertSame('I-SUBSCRIPTION-001', $result['id']);
        $this->assertSame('APPROVAL_PENDING', $result['status']);
        $this->assertSame(
            'https://www.paypal.com/webapps/billing/subscriptions?ba_token=I-SUBSCRIPTION-001',
            $result['approval_url'],
        );

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions') {
                return false;
            }

            $data = $request->data();

            return $data['plan_id'] === 'P-123'
                && $data['custom_id'] === 'INV-001'
                && $data['application_context']['return_url'] === 'https://example.com/paypal/subscriptions/return'
                && $data['subscriber']['email_address'] === 'ava@example.com';
        });
    }

    public function test_it_creates_paypal_daily_plan_using_non_zero_recurring_amount(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/plans' => Http::response([
                'id' => 'P-DAILY-123',
                'status' => 'ACTIVE',
            ], 201),
        ]);

        $package = new Package([
            'access_tier_id' => 1,
            'title' => 'Masterclass Standard Daily',
            'slug' => 'masterclass-standard-test-daily-plan',
            'description' => 'Daily sandbox plan.',
            'price' => 29.99,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'DAY',
            'billing_interval_count' => 1,
            'installment_deadline_month' => 5,
            'installment_deadline_day' => 15,
        ]);

        $result = $this->service()->createPlan(
            $package,
            [
                'total_amount' => '29.99',
                'currency_code' => 'USD',
                'installment_count' => 319,
                'first_payment_amount' => '1.37',
                'monthly_base_amount' => '0.09',
                'recurring_payment_amount' => '0.09',
                'billing_interval_unit' => 'DAY',
                'billing_interval_count' => 1,
                'final_due_at' => '2027-05-15',
            ],
            'PROD-DAILY-123',
        );

        $this->assertSame([
            'id' => 'P-DAILY-123',
            'status' => 'ACTIVE',
        ], $result);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v1/billing/plans') {
                return false;
            }

            $data = $request->data();

            return $data['product_id'] === 'PROD-DAILY-123'
                && $data['billing_cycles'][0]['frequency']['interval_unit'] === 'DAY'
                && $data['billing_cycles'][0]['frequency']['interval_count'] === 1
                && $data['billing_cycles'][0]['total_cycles'] === 318
                && $data['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] === '0.09'
                && $data['payment_preferences']['setup_fee']['value'] === '1.37';
        });
    }

    public function test_it_can_get_cancel_suspend_and_activate_subscription(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001' => Http::response([
                'id' => 'I-SUBSCRIPTION-001',
                'status' => 'ACTIVE',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001/cancel' => Http::response([], 204),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001/suspend' => Http::response([], 204),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001/activate' => Http::response([], 204),
        ]);

        $subscription = $this->service()->getSubscription('I-SUBSCRIPTION-001');
        $this->service()->cancelSubscription('I-SUBSCRIPTION-001', 'Customer requested cancellation.');
        $this->service()->suspendSubscription('I-SUBSCRIPTION-001', 'Payment failed.');
        $this->service()->activateSubscription('I-SUBSCRIPTION-001', 'Recovered payment.');

        $this->assertSame('I-SUBSCRIPTION-001', $subscription['id']);
        $this->assertSame('ACTIVE', $subscription['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001/cancel'
                && $request['reason'] === 'Customer requested cancellation.';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001/suspend'
                && $request['reason'] === 'Payment failed.';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-SUBSCRIPTION-001/activate'
                && $request['reason'] === 'Recovered payment.';
        });
    }

    public function test_it_logs_paypal_create_product_failure_context_and_throws_validation_exception(): void
    {
        Log::spy();

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'message' => 'Category is invalid.',
                'debug_id' => 'debug-product-422',
                'details' => [
                    ['field' => '/category', 'issue' => 'INVALID_PARAMETER_VALUE'],
                ],
            ], 422),
        ]);

        try {
            $this->service()->createProduct($this->package(), $this->installmentPlan());
            $this->fail('Expected PayPal product creation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'PayPal product request failed. Debug ID: debug-product-422.',
                $exception->errors()['payment_method'][0] ?? null,
            );
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'PayPal subscription API request failed.'
                    && $context['endpoint'] === '/v1/catalogs/products'
                    && $context['status_code'] === 422
                    && $context['debug_id'] === 'debug-product-422'
                    && $context['payload']['name'] === 'Masterclass Standard'
                    && $context['payload']['type'] === 'SERVICE'
                    && $context['payload']['category'] === 'SOFTWARE';
            });
    }

    public function test_it_extracts_subscription_webhook_references_from_subscription_event(): void
    {
        $result = $this->service()->extractWebhookReferences([
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'I-SUBSCRIPTION-001',
                'plan_id' => 'P-123',
            ],
        ]);

        $this->assertSame('BILLING.SUBSCRIPTION.ACTIVATED', $result['event_type']);
        $this->assertSame('I-SUBSCRIPTION-001', $result['subscription_id']);
        $this->assertSame('P-123', $result['plan_id']);
        $this->assertNull($result['order_id']);
        $this->assertSame('I-SUBSCRIPTION-001', $result['resource_id']);
    }

    public function test_it_extracts_subscription_webhook_references_from_payment_event(): void
    {
        $result = $this->service()->extractWebhookReferences([
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'resource' => [
                'id' => '8AB12345CD6789012',
                'billing_agreement_id' => 'I-SUBSCRIPTION-001',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => '5O190127TN364715T',
                    ],
                ],
            ],
        ]);

        $this->assertSame('PAYMENT.SALE.COMPLETED', $result['event_type']);
        $this->assertSame('I-SUBSCRIPTION-001', $result['subscription_id']);
        $this->assertSame('5O190127TN364715T', $result['order_id']);
        $this->assertSame('8AB12345CD6789012', $result['capture_id']);
        $this->assertSame('8AB12345CD6789012', $result['resource_id']);
    }

    private function service(): PayPalSubscriptionService
    {
        return app(PayPalSubscriptionService::class);
    }

    private function package(): Package
    {
        return new Package([
            'access_tier_id' => 1,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'description' => 'Premium masterclass package.',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function installmentPlan(): array
    {
        return [
            'total_amount' => '300.00',
            'currency_code' => 'USD',
            'installment_count' => 7,
            'first_payment_amount' => '48.00',
            'monthly_base_amount' => '42.00',
            'recurring_payment_amount' => '42.00',
            'first_payment_date' => '2026-07-10',
            'recurring_due_dates' => [
                '2026-08-15',
                '2026-09-15',
                '2026-10-15',
                '2026-11-15',
                '2026-12-15',
                '2027-01-15',
            ],
            'final_due_at' => '2027-01-15',
            'grace_deadlines' => [
                '2026-08-18',
                '2026-09-18',
                '2026-10-18',
                '2026-11-18',
                '2026-12-18',
                '2027-01-18',
            ],
            'schedule_breakdown' => [],
        ];
    }
}
