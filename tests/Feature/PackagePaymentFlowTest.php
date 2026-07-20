<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackagePaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_package_checkout_skips_paypal_and_creates_zero_amount_audit_records(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'level' => 1,
        ]);

        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'payment_type' => Package::PAYMENT_TYPE_FREE,
            'price' => 0,
            'installment_enabled' => false,
        ]);

        $pendingRegistration = app(PaymentCheckoutService::class)->createPendingRegistration([
            'package_id' => $package->id,
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
        ]);

        $result = app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_INTERNAL,
        ]);

        $invoice = $result['invoice']->fresh();
        $paymentActivity = $result['payment_activity']->fresh();

        $this->assertSame(Package::PAYMENT_TYPE_FREE, $invoice->package_payment_type);
        $this->assertSame(0.0, (float) $invoice->total_amount);
        $this->assertSame(Invoice::STATUS_PAID_FULL, $invoice->status);
        $this->assertSame(Package::PAYMENT_TYPE_FREE, $paymentActivity->package_payment_type);
        $this->assertSame(Payment::STATUS_SUCCESS, $paymentActivity->status);
        $this->assertSame(0.0, (float) $paymentActivity->amount_paid);
        $this->assertDatabaseHas('onboarding_states', [
            'pending_registration_id' => $pendingRegistration->id,
        ]);
    }

    public function test_donation_package_checkout_uses_actual_donation_amount(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'level' => 2,
        ]);

        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'payment_type' => Package::PAYMENT_TYPE_DONATION,
            'price' => 0,
            'minimum_donation_amount' => 25,
            'suggested_donation_amount' => 100,
            'installment_enabled' => false,
        ]);

        $pendingRegistration = app(PaymentCheckoutService::class)->createPendingRegistration([
            'package_id' => $package->id,
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
        ]);

        $result = app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_MOCK,
            'donation_amount' => 1500,
        ]);

        $invoice = $result['invoice']->fresh();
        $paymentActivity = $result['payment_activity']->fresh();

        $this->assertSame(Package::PAYMENT_TYPE_DONATION, $invoice->package_payment_type);
        $this->assertSame(1500.0, (float) $invoice->total_amount);
        $this->assertSame(Package::PAYMENT_TYPE_DONATION, $paymentActivity->package_payment_type);
        $this->assertSame(1500.0, (float) $paymentActivity->amount_paid);
    }

    public function test_upgrade_uses_actual_successful_payment_credit_and_can_finalize_without_paypal_when_due_is_zero(): void
    {
        $onlineTier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'level' => 2,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);
        $masterTier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'level' => 3,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);

        $user = User::factory()->student()->create([
            'access_tier_id' => $onlineTier->id,
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'invoice_number' => 'INV-CREDIT-001',
            'user_id' => $user->id,
            'access_tier_id' => $onlineTier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'package_payment_type' => Package::PAYMENT_TYPE_DONATION,
            'total_amount' => 3000,
            'balance_due' => 0,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Invoice::STATUS_PAID_FULL,
            'issued_at' => now(),
            'paid_at' => now(),
        ])->payments()->create([
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'package_payment_type' => Package::PAYMENT_TYPE_DONATION,
            'amount_paid' => 3000,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Payment::STATUS_SUCCESS,
            'payment_reference' => 'PAY-CREDIT-001',
            'notes' => 'Historical donation payment.',
        ]);

        $targetPackage = Package::factory()->create([
            'access_tier_id' => $masterTier->id,
            'payment_type' => Package::PAYMENT_TYPE_PAID,
            'price' => 2000,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => false,
        ]);

        $result = app(PaymentCheckoutService::class)->startUpgradeCheckout($user, $masterTier, [
            'package_id' => $targetPackage->id,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_INTERNAL,
        ]);

        $invoice = $result['invoice']->fresh();
        $paymentActivity = $result['payment_activity']->fresh();

        $this->assertSame(0.0, (float) $invoice->total_amount);
        $this->assertSame(Invoice::STATUS_PAID_FULL, $invoice->status);
        $this->assertSame(Payment::STATUS_SUCCESS, $paymentActivity->status);
        $this->assertSame($masterTier->id, $user->fresh()->access_tier_id);
    }

    public function test_upgrade_credit_counts_only_successful_installment_payments(): void
    {
        $onlineTier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'level' => 2,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);
        $masterTier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'level' => 3,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);

        $user = User::factory()->student()->create([
            'access_tier_id' => $onlineTier->id,
            'is_active' => true,
        ]);

        $basisInvoice = Invoice::query()->create([
            'invoice_number' => 'INV-INSTALLMENT-001',
            'user_id' => $user->id,
            'access_tier_id' => $onlineTier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'package_payment_type' => Package::PAYMENT_TYPE_PAID,
            'total_amount' => 1000,
            'balance_due' => 750,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Invoice::STATUS_INSTALLMENT,
            'issued_at' => now(),
            'paid_at' => now(),
        ]);

        $basisInvoice->payments()->createMany([
            [
                'payment_method' => Payment::METHOD_PAYPAL,
                'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                'package_payment_type' => Package::PAYMENT_TYPE_PAID,
                'amount_paid' => 250,
                'currency_code' => AccessTier::CURRENCY_USD,
                'status' => Payment::STATUS_SUCCESS,
                'payment_reference' => 'PAY-INSTALLMENT-001',
                'notes' => 'Successful installment.',
            ],
            [
                'payment_method' => Payment::METHOD_PAYPAL,
                'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                'package_payment_type' => Package::PAYMENT_TYPE_PAID,
                'amount_paid' => 750,
                'currency_code' => AccessTier::CURRENCY_USD,
                'status' => Payment::STATUS_PENDING,
                'payment_reference' => 'PAY-INSTALLMENT-002',
                'notes' => 'Pending installment should not count.',
            ],
        ]);

        $targetPackage = Package::factory()->create([
            'access_tier_id' => $masterTier->id,
            'payment_type' => Package::PAYMENT_TYPE_PAID,
            'price' => 2000,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_enabled' => false,
        ]);

        $this->assertSame(
            250.0,
            app(PaymentCheckoutService::class)->relevantUpgradePaidAmount($user, $masterTier),
        );

        $this->assertSame(
            1750.0,
            app(PaymentCheckoutService::class)->upgradePayload($user, $masterTier, $targetPackage->id)['amount_due'],
        );
    }
}
