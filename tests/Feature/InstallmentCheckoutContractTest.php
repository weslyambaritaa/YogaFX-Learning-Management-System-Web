<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentSubscription;
use App\Models\PendingRegistration;
use App\Services\PayPalService;
use App\Services\PaymentCheckoutService;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstallmentCheckoutContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_checkout_payload_includes_payment_options_and_installment_summary_for_eligible_package(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'currency_code' => AccessTier::CURRENCY_USD,
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
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-001');
            $mock->shouldReceive('environment')
                ->once()
                ->andReturn('sandbox');
        });

        $this->get(URL::temporarySignedRoute('checkout.show', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Checkout')
                ->where('checkout.installment_summary.installment_count', 7)
                ->where('checkout.installment_summary.first_payment_amount', '48.00')
                ->where('checkout.payment_options.0.type', 'pay_full')
                ->where('checkout.payment_options.0.amount_due_today', '300.00')
                ->where('checkout.payment_options.1.type', 'installment')
                ->where('checkout.payment_options.1.amount_due_today', '48.00')
                ->where('checkout.payment_options.1.installment_count', 7)
                ->where('checkout.payment_options.1.recurring_amount', '42.00')
                ->where('checkout.payment_options.1.billing_day', 15)
                ->where('checkout.installment_allowed_billing_days.0', 1)
                ->where('checkout.installment_allowed_billing_days.1', 15)
                ->where('checkout.installment_accepts_billing_day', true)
                ->where('checkout.installment_requires_billing_day_choice', true)
                ->where('checkout.payment_options.1.final_due_at', '2027-01-15')
                ->where('checkout.paypal.client_id', 'PAYPAL-CLIENT-ID-001')
                ->where('checkout.paypal.client_token', null)
                ->where('checkout.paypal.environment', 'sandbox'));
    }

    public function test_checkout_payload_keeps_full_payment_only_for_ineligible_package(): void
    {
        Carbon::setTestNow('2026-09-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'online-standard',
            'price' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'installment_enabled' => false,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'package_id' => $package->id,
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone' => '+6281234567000',
            'country' => 'Indonesia',
            'amount_snapshot' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-002');
            $mock->shouldReceive('environment')
                ->once()
                ->andReturn('sandbox');
        });

        $this->get(URL::temporarySignedRoute('checkout.show', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Checkout')
                ->where('checkout.installment_summary', null)
                ->has('checkout.payment_options', 1)
                ->where('checkout.payment_options.0.type', 'pay_full')
                ->where('checkout.payment_options.0.amount_due_today', '299.00')
                ->where('checkout.paypal.environment', 'sandbox'));
    }

    public function test_backend_rejects_installment_checkout_when_package_is_not_eligible(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'online-standard',
            'price' => 299,
            'installment_enabled' => false,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'package_id' => $package->id,
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone' => '+6281234567000',
            'country' => 'Indonesia',
            'amount_snapshot' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
            'checkout_opened_at' => now(),
        ]);

        try {
            app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
                'payment_type' => 'installment',
                'payment_method' => 'paypal',
                'billing_day' => 15,
            ]);

            $this->fail('Ineligible installment checkout should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('This package is not eligible for installment checkout.', $exception->getMessage());
        }
    }

    public function test_backend_allows_installment_checkout_once_subscription_orchestration_is_available(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
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

        $result = app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'billing_day' => 15,
        ]);

        $this->assertSame('P-001', $result['provider_plan_id']);
        $this->assertSame(
            PaymentSubscription::STATUS_DRAFT,
            $result['payment_subscription']->status,
        );
        $this->assertNull($result['payment_subscription']->provider_subscription_id);
    }

    public function test_backend_requires_billing_day_for_installment_checkout(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard',
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
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
            'checkout_opened_at' => now(),
        ]);

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'terms_accepted' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['billing_day']);
    }

    public function test_backend_allows_installment_checkout_without_billing_day_when_package_has_fixed_monthly_day(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-fixed-fifteenth',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'allowed_billing_days' => [15],
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
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
                ->andReturn(['id' => 'PROD-ONE-DAY', 'status' => 'ACTIVE']);
            $mock->shouldReceive('createPlan')
                ->once()
                ->andReturn(['id' => 'P-ONE-DAY', 'status' => 'ACTIVE']);
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'prepared')
            ->assertJsonPath('billing_day', 15);
    }

    public function test_backend_rejects_installment_checkout_when_billing_day_is_not_allowed_for_package(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'allowed_billing_days' => [15],
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
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

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 1,
            'terms_accepted' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected billing day is not available for this package.');
    }

    public function test_backend_rejects_billing_day_for_pay_full_checkout(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-full-only',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => 15,
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
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
            'checkout_opened_at' => now(),
        ]);

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'terms_accepted' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['billing_day']);
    }

    public function test_checkout_payload_hides_billing_day_options_for_non_monthly_installment_package(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Daily Test Plan',
            'slug' => 'masterclass-daily-test-plan',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'DAY',
            'billing_interval_count' => 1,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
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
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-DAILY');
            $mock->shouldReceive('environment')
                ->once()
                ->andReturn('sandbox');
        });

        $this->get(URL::temporarySignedRoute('checkout.show', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Checkout')
                ->where('checkout.installment_accepts_billing_day', false)
                ->where('checkout.installment_requires_billing_day_choice', false)
                ->where('checkout.installment_billing_interval_unit', 'DAY')
                ->where('checkout.installment_allowed_billing_days', []));
    }

    public function test_backend_ignores_billing_day_for_non_monthly_installment_package(): void
    {
        Carbon::setTestNow('2026-12-29 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-daily-test-plan',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'DAY',
            'billing_interval_count' => 1,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 1,
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
                ->andReturn(['id' => 'PROD-DAILY-IGNORED', 'status' => 'ACTIVE']);
            $mock->shouldReceive('createPlan')
                ->once()
                ->andReturn(['id' => 'P-DAILY-IGNORED', 'status' => 'ACTIVE']);
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'billing_day' => 15,
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'prepared')
            ->assertJsonPath('provider_plan_id', 'P-DAILY-IGNORED');

        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'installment_billing_day' => null,
        ]);

        $this->assertDatabaseHas('payment_subscriptions', [
            'pending_registration_id' => $pendingRegistration->id,
            'billing_day' => null,
            'provider_plan_id' => 'P-DAILY-IGNORED',
        ]);
    }

    public function test_backend_allows_daily_installment_checkout_without_billing_day(): void
    {
        Carbon::setTestNow('2026-12-29 09:00:00');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard-test-daily-plan',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => true,
            'billing_interval_unit' => 'DAY',
            'billing_interval_count' => 1,
            'allowed_billing_days' => [1, 15],
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 1,
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
                ->andReturn(['id' => 'PROD-DAILY-001', 'status' => 'ACTIVE']);
            $mock->shouldReceive('createPlan')
                ->once()
                ->andReturn(['id' => 'P-DAILY-001', 'status' => 'ACTIVE']);
        });

        $this->postJson(URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $tier->slug,
        ]), [
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'payment_method' => 'paypal',
            'checkout_mode' => 'paypal',
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'prepared')
            ->assertJsonPath('flow', 'subscription')
            ->assertJsonPath('provider_plan_id', 'P-DAILY-001');
    }
}
