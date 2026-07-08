<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentSubscription;
use App\Models\PendingRegistration;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InstallmentCheckoutOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_backend_can_create_installment_checkout_session_with_invoice_and_payment_subscription(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'description' => 'Premium installment package.',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_date' => '2027-01-15',
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'paypal_product_id' => null,
            'paypal_plan_id' => null,
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
            'checkout_opened_at' => now(),
        ]);

        $this->mock(PayPalSubscriptionService::class, function ($mock): void {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn(['id' => 'PROD-001', 'status' => 'ACTIVE']);
            $mock->shouldReceive('createPlan')
                ->once()
                ->andReturn(['id' => 'P-001', 'status' => 'ACTIVE']);
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'installment_count' => 7,
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'prepared')
            ->assertJsonPath('flow', 'subscription')
            ->assertJsonPath('payment_subscription_id', 1)
            ->assertJsonPath('provider_plan_id', 'P-001')
            ->assertJsonPath('paypal_subscription_start_time', '2026-08-15T00:00:00Z')
            ->assertJsonPath('provider_subscription_id', null);

        $invoice = Invoice::query()->latest('id')->firstOrFail();
        $subscription = PaymentSubscription::query()->latest('id')->firstOrFail();

        $this->assertSame(Invoice::PAYMENT_TYPE_INSTALLMENT, $invoice->payment_type);
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertSame('300.00', $invoice->total_amount);
        $this->assertSame($package->id, $invoice->package_id);

        $this->assertSame(PaymentSubscription::PROVIDER_PAYPAL, $subscription->provider);
        $this->assertSame(PaymentSubscription::STATUS_DRAFT, $subscription->status);
        $this->assertSame('PROD-001', $subscription->provider_product_id);
        $this->assertSame('P-001', $subscription->provider_plan_id);
        $this->assertNull($subscription->provider_subscription_id);
        $this->assertSame(15, $subscription->billing_day);
        $this->assertSame(7, $subscription->installment_count);
        $this->assertSame(0, $subscription->installments_paid_count);
        $this->assertSame('42.90', $subscription->first_payment_amount);
        $this->assertSame('42.85', $subscription->monthly_base_amount);
        $this->assertSame('42.85', $subscription->next_billing_amount);
        $this->assertSame('2026-08-15 00:00:00', optional($subscription->next_due_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-15 00:00:00', optional($subscription->final_due_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-18 00:00:00', optional($subscription->grace_deadline_at)->format('Y-m-d H:i:s'));
        $this->assertSame('plan_ready', $subscription->metadata['provider_prepare_stage'] ?? null);
        $this->assertSame('v2', $subscription->metadata['provider_plan_cache_version'] ?? null);
        $this->assertSame(
            sprintf('v2_initial_pkg%s_day15_7x_USD_total30000_first4290_rec4285_month1', $package->id),
            $subscription->metadata['provider_plan_fingerprint'] ?? null,
        );

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'paypal_product_id' => 'PROD-001',
            'paypal_plan_id' => 'P-001',
        ]);
    }

    public function test_backend_reuses_existing_prepared_subscription_session_for_same_pending_registration(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_date' => '2027-01-15',
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'paypal_product_id' => 'PROD-001',
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
            'checkout_opened_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-REUSE-001',
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

        PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-001',
            'provider_plan_id' => 'P-001',
            'provider_subscription_id' => null,
            'status' => PaymentSubscription::STATUS_DRAFT,
            'billing_day' => 15,
            'installment_count' => 7,
            'installments_paid_count' => 0,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42.85,
            'first_payment_amount' => 42.90,
            'next_billing_amount' => 42.85,
            'next_due_at' => '2026-08-15',
            'final_due_at' => '2027-01-15',
            'grace_deadline_at' => '2026-08-18',
            'metadata' => [
                'provider_prepare_stage' => 'plan_ready',
                'context' => 'initial',
                'provider_plan_cache_version' => 'v2',
                'provider_plan_fingerprint' => sprintf(
                    'v2_initial_pkg%s_day15_7x_USD_total30000_first4290_rec4285_month1',
                    $package->id,
                ),
            ],
        ]);

        $package->forceFill([
            'metadata' => [
                'paypal_plan_ids_v2' => [
                    sprintf('v2_initial_pkg%s_day15_7x_USD_total30000_first4290_rec4285_month1', $package->id) => 'P-001',
                ],
            ],
        ])->save();

        $this->mock(PayPalSubscriptionService::class, function ($mock): void {
            $mock->shouldNotReceive('createProduct');
            $mock->shouldNotReceive('createPlan');
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'installment_count' => 7,
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'prepared')
            ->assertJsonPath('payment_subscription_id', 1)
            ->assertJsonPath('provider_plan_id', 'P-001')
            ->assertJsonPath('paypal_subscription_start_time', '2026-08-15T00:00:00Z')
            ->assertJsonPath('provider_subscription_id', null);

        $this->assertSame(1, PaymentSubscription::query()->count());
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_backend_does_not_reuse_legacy_plan_cache_for_new_installment_checkout(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_date' => '2027-01-15',
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'paypal_product_id' => 'PROD-LEGACY-001',
            'paypal_plan_id' => 'P-LEGACY-ROOT',
            'metadata' => [
                'paypal_plan_ids' => [
                    '15' => 'P-LEGACY-BY-DAY',
                    '15_7x' => 'P-LEGACY-BY-COUNT',
                ],
            ],
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
            'checkout_opened_at' => now(),
        ]);

        $this->mock(PayPalSubscriptionService::class, function ($mock): void {
            $mock->shouldNotReceive('createProduct');
            $mock->shouldReceive('createPlan')
                ->once()
                ->andReturn(['id' => 'P-V2-001', 'status' => 'ACTIVE']);
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'installment_count' => 7,
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('provider_plan_id', 'P-V2-001');

        $subscription = PaymentSubscription::query()->latest('id')->firstOrFail();

        $this->assertSame('P-V2-001', $subscription->provider_plan_id);
        $this->assertSame('v2', $subscription->metadata['provider_plan_cache_version'] ?? null);
        $this->assertSame(
            sprintf('v2_initial_pkg%s_day15_7x_USD_total30000_first4290_rec4285_month1', $package->id),
            $subscription->metadata['provider_plan_fingerprint'] ?? null,
        );
    }

    public function test_backend_rebuilds_legacy_draft_subscription_instead_of_reusing_old_provider_plan_id(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_date' => '2027-01-15',
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'paypal_product_id' => 'PROD-001',
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
            'checkout_opened_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-LEGACY-DRAFT-001',
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

        PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-001',
            'provider_plan_id' => 'P-LEGACY-DRAFT',
            'provider_subscription_id' => null,
            'status' => PaymentSubscription::STATUS_DRAFT,
            'billing_day' => 15,
            'installment_count' => 7,
            'installments_paid_count' => 0,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42.85,
            'first_payment_amount' => 42.90,
            'next_billing_amount' => 42.85,
            'next_due_at' => '2026-08-15',
            'final_due_at' => '2027-01-15',
            'grace_deadline_at' => '2026-08-18',
            'metadata' => [
                'provider_prepare_stage' => 'plan_ready',
            ],
        ]);

        $this->mock(PayPalSubscriptionService::class, function ($mock): void {
            $mock->shouldNotReceive('createProduct');
            $mock->shouldReceive('createPlan')
                ->once()
                ->andReturn(['id' => 'P-V2-REBUILT', 'status' => 'ACTIVE']);
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'installment_count' => 7,
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('payment_subscription_id', 1)
            ->assertJsonPath('provider_plan_id', 'P-V2-REBUILT');

        $subscription = PaymentSubscription::query()->findOrFail(1);

        $this->assertSame('P-V2-REBUILT', $subscription->provider_plan_id);
        $this->assertSame('v2', $subscription->metadata['provider_plan_cache_version'] ?? null);
        $this->assertSame(
            sprintf('v2_initial_pkg%s_day15_7x_USD_total30000_first4290_rec4285_month1', $package->id),
            $subscription->metadata['provider_plan_fingerprint'] ?? null,
        );
    }

    public function test_backend_marks_local_subscription_failed_when_provider_product_creation_fails(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_date' => '2027-01-15',
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'paypal_product_id' => null,
            'paypal_plan_id' => null,
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
            'checkout_opened_at' => now(),
        ]);

        $this->mock(PayPalSubscriptionService::class, function ($mock): void {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andThrow(\Illuminate\Validation\ValidationException::withMessages([
                    'payment_method' => 'PayPal product request failed. Debug ID: debug-product-422.',
                ]));
            $mock->shouldNotReceive('createPlan');
            $mock->shouldNotReceive('createSubscription');
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'installment_count' => 7,
            'terms_accepted' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'PayPal product request failed. Debug ID: debug-product-422.');

        $subscription = PaymentSubscription::query()->latest('id')->firstOrFail();

        $this->assertSame(PaymentSubscription::STATUS_FAILED, $subscription->status);
        $this->assertSame(
            'PayPal product request failed. Debug ID: debug-product-422.',
            $subscription->metadata['provider_error'] ?? null,
        );
    }

    public function test_backend_can_attach_paypal_subscription_approval_without_finalizing_onboarding(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'paypal_product_id' => 'PROD-001',
            'paypal_plan_id' => 'P-001',
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
            'checkout_opened_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-APPROVE-001',
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

        $paymentSubscription = PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-001',
            'provider_plan_id' => 'P-001',
            'provider_subscription_id' => null,
            'status' => PaymentSubscription::STATUS_DRAFT,
            'billing_day' => 15,
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

        $this->postJson(URL::temporarySignedRoute('checkout.installments.approve', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_subscription_id' => $paymentSubscription->id,
            'provider_subscription_id' => 'I-SUBSCRIPTION-001',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'approval_attached')
            ->assertJsonPath('provider_subscription_id', 'I-SUBSCRIPTION-001')
            ->assertJsonPath('awaiting_webhook', true)
            ->assertJsonPath('onboarding_ready', false);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $paymentSubscription->id,
            'provider_subscription_id' => 'I-SUBSCRIPTION-001',
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
        ]);

        $this->assertDatabaseMissing('onboarding_states', [
            'pending_registration_id' => $pendingRegistration->id,
        ]);
    }

    public function test_installment_status_waits_for_first_payment_until_onboarding_is_ready(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'allowed_billing_days' => [1, 15],
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
            'installment_billing_day' => 15,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
            'checkout_opened_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-STATUS-001',
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

        PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_plan_id' => 'P-001',
            'provider_subscription_id' => 'I-SUBSCRIPTION-001',
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
            'billing_day' => 15,
            'installment_count' => 7,
            'installments_paid_count' => 0,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42,
            'first_payment_amount' => 48,
            'next_billing_amount' => 42,
            'metadata' => [],
        ]);

        $this->getJson(URL::temporarySignedRoute('checkout.installments.status', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]))
            ->assertOk()
            ->assertJsonPath('status', 'waiting_for_first_payment')
            ->assertJsonPath('onboarding_ready', false)
            ->assertJsonPath('onboarding_url', null);
    }
}
