<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\PendingRegistration;
use App\Services\PayPalService;
use App\Services\PaymentFinalizerService;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayPalWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_route_bypasses_csrf_but_still_requires_valid_signature(): void
    {
        $this->withMiddleware();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andReturnFalse();
        });

        $this->post(route('paypal.webhook'), [
            'id' => 'WH-CSRF-001',
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'I-CSRF-001',
            ],
        ], $this->webhookHeaders())
            ->assertStatus(401);
    }

    public function test_subscription_webhook_event_is_logged_and_duplicate_is_ignored(): void
    {
        $subscription = $this->createPaymentSubscription();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhookSignature')
                ->twice()
                ->andReturnTrue();
        });

        $payload = [
            'id' => 'WH-EVT-001',
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'create_time' => '2026-07-10T09:00:00Z',
            'resource' => [
                'id' => $subscription->provider_subscription_id,
                'plan_id' => $subscription->provider_plan_id,
                'billing_info' => [
                    'last_payment' => [
                        'amount' => [
                            'currency_code' => AccessTier::CURRENCY_USD,
                            'value' => '48.0',
                        ],
                        'time' => '2026-07-10T09:00:00Z',
                    ],
                ],
            ],
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertDatabaseHas('payment_subscription_events', [
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-EVT-001',
            'provider_event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'invoice_id' => $subscription->invoice_id,
            'payment_subscription_id' => $subscription->id,
            'status' => PaymentSubscriptionEvent::STATUS_PROCESSED,
        ]);

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, PaymentSubscriptionEvent::query()->count());
        $this->assertSame(1, Payment::query()->where('invoice_id', $subscription->invoice_id)->count());
    }

    public function test_subscription_payment_webhook_logs_order_and_capture_references(): void
    {
        $subscription = $this->createPaymentSubscription();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andReturnTrue();
        });

        $payload = [
            'id' => 'WH-EVT-002',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'create_time' => '2026-08-15T09:00:00Z',
            'resource' => [
                'id' => 'CAPTURE-001',
                'billing_agreement_id' => $subscription->provider_subscription_id,
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'ORDER-001',
                    ],
                ],
            ],
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertDatabaseHas('payment_subscription_events', [
            'provider_event_id' => 'WH-EVT-002',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_order_id' => 'ORDER-001',
            'provider_capture_id' => 'CAPTURE-001',
            'payment_subscription_id' => $subscription->id,
        ]);

        $event = PaymentSubscriptionEvent::query()
            ->where('provider_event_id', 'WH-EVT-002')
            ->firstOrFail();

        $this->assertSame('CAPTURE-001', $event->payload['resource']['id'] ?? null);
        $this->assertSame(
            'ORDER-001',
            $event->payload['resource']['supplementary_data']['related_ids']['order_id'] ?? null,
        );
    }

    public function test_unknown_webhook_event_is_ignored_safely_when_no_order_reference_exists(): void
    {
        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andReturnTrue();
            $mock->shouldReceive('extractWebhookOrderReference')
                ->once()
                ->andReturn([
                    'event_type' => 'PAYMENT.SALE.UNKNOWN',
                    'order_id' => null,
                ]);
        });

        $payload = [
            'id' => 'WH-EVT-UNKNOWN-001',
            'event_type' => 'PAYMENT.SALE.UNKNOWN',
            'create_time' => '2026-08-16T09:00:00Z',
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertStatus(202)
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(0, PaymentSubscriptionEvent::query()->count());
    }

    public function test_existing_order_webhook_flow_still_works_for_one_time_checkout(): void
    {
        $payment = $this->createOneTimePayment();

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andReturnTrue();
            $mock->shouldReceive('extractWebhookOrderReference')
                ->once()
                ->andReturn([
                    'event_type' => 'CHECKOUT.ORDER.APPROVED',
                    'order_id' => $payment->payment_reference,
                ]);
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with($payment->payment_reference)
                ->andReturn(['status' => 'COMPLETED']);
        });

        $this->mock(PaymentFinalizerService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('finalizeSuccessfulPayment')
                ->once()
                ->withArgs(fn ($paymentActivity, $reference) => $paymentActivity->is($payment) && $reference === $payment->payment_reference)
                ->andReturn([
                    'invoice' => $payment->invoice,
                    'payment_activity' => $payment,
                    'user' => null,
                    'onboarding_state' => null,
                    'skipped' => false,
                ]);
        });

        $this->postJson(route('paypal.webhook'), [
            'id' => 'WH-ORDER-001',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
        ], $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertSame(0, PaymentSubscriptionEvent::query()->count());
    }

    /**
     * @return array<string, string>
     */
    private function webhookHeaders(): array
    {
        return [
            'paypal-auth-algo' => 'SHA256withRSA',
            'paypal-cert-url' => 'https://api-m.sandbox.paypal.com/certs/test',
            'paypal-transmission-id' => 'transmission-id',
            'paypal-transmission-sig' => 'signature',
            'paypal-transmission-time' => '2026-07-10T09:00:00Z',
        ];
    }

    private function createPaymentSubscription(): PaymentSubscription
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);
        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'package_id' => $package->id,
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
            'amount_snapshot' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-SUB-001',
            'pending_registration_id' => $pendingRegistration->id,
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'total_amount' => 300,
            'balance_due' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
        ]);

        return PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-001',
            'provider_plan_id' => 'P-001',
            'provider_subscription_id' => 'I-SUBSCRIPTION-001',
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
            'installment_count' => 7,
            'installments_paid_count' => 0,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42,
            'first_payment_amount' => 48,
            'next_billing_amount' => 42,
            'next_due_at' => '2026-08-15',
            'final_due_at' => '2027-01-15',
            'grace_deadline_at' => '2026-08-18',
            'metadata' => [],
        ]);
    }

    private function createOneTimePayment(): Payment
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone' => '+6281234567000',
            'country' => 'Indonesia',
            'amount_snapshot' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ORDER-001',
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'total_amount' => 299,
            'balance_due' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
        ]);

        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_reference' => 'ORDER-ONE-TIME-001',
            'amount_paid' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Payment::STATUS_PENDING,
        ]);
    }
}
