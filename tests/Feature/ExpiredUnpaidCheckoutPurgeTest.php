<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Payments\ExpiredUnpaidCheckoutPurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredUnpaidCheckoutPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingRegistration(AccessTier $tier): PendingRegistration
    {
        return PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
            'amount_snapshot' => 500,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
            'checkout_opened_at' => now(),
        ]);
    }

    private function makeInitialInvoice(
        PendingRegistration $pendingRegistration,
        AccessTier $tier,
        string $paymentType,
        string $status,
        $issuedAt,
        ?int $userId = null,
    ): Invoice {
        return Invoice::query()->create([
            'invoice_number' => 'INV-'.uniqid('', true),
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $userId,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => $paymentType,
            'total_amount' => 500,
            'balance_due' => 500,
            'currency_code' => 'USD',
            'status' => $status,
            'issued_at' => $issuedAt,
        ]);
    }

    public function test_deletes_unpaid_initial_invoice_older_than_72_hours_with_its_pending_registration(): void
    {
        $tier = AccessTier::factory()->create();
        $pendingRegistration = $this->makePendingRegistration($tier);
        $invoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_FULL,
            Invoice::STATUS_UNPAID,
            now()->subHours(73),
        );
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'amount_paid' => 500,
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'payment_reference' => 'ORDER-EXPIRED-1',
        ]);

        $result = app(ExpiredUnpaidCheckoutPurgeService::class)->purge();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['invoices_deleted']);
        $this->assertSame(1, $result['pending_registrations_deleted']);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payment_activities', ['id' => $payment->id]);
        $this->assertDatabaseMissing('pending_registrations', ['id' => $pendingRegistration->id]);
    }

    public function test_does_not_delete_unpaid_invoice_younger_than_72_hours(): void
    {
        $tier = AccessTier::factory()->create();
        $pendingRegistration = $this->makePendingRegistration($tier);
        $invoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_FULL,
            Invoice::STATUS_UNPAID,
            now()->subHours(10),
        );

        $result = app(ExpiredUnpaidCheckoutPurgeService::class)->purge();

        $this->assertSame(0, $result['invoices_deleted']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('pending_registrations', ['id' => $pendingRegistration->id]);
    }

    public function test_does_not_delete_paid_invoice_even_if_old(): void
    {
        $tier = AccessTier::factory()->create();
        $pendingRegistration = $this->makePendingRegistration($tier);
        $invoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_FULL,
            Invoice::STATUS_PAID_FULL,
            now()->subHours(200),
        );

        $result = app(ExpiredUnpaidCheckoutPurgeService::class)->purge();

        $this->assertSame(0, $result['invoices_deleted']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_does_not_delete_expired_upgrade_invoice(): void
    {
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create(['access_tier_id' => $tier->id]);

        $upgradeInvoice = Invoice::query()->create([
            'invoice_number' => 'INV-UPGRADE-1',
            'user_id' => $student->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_UPGRADE,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'total_amount' => 200,
            'balance_due' => 200,
            'currency_code' => 'USD',
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now()->subHours(200),
        ]);

        $result = app(ExpiredUnpaidCheckoutPurgeService::class)->purge();

        $this->assertSame(0, $result['invoices_deleted']);
        $this->assertDatabaseHas('invoices', ['id' => $upgradeInvoice->id]);
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_keeps_pending_registration_when_another_invoice_still_references_it(): void
    {
        $tier = AccessTier::factory()->create();
        $pendingRegistration = $this->makePendingRegistration($tier);

        $expiredInvoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_FULL,
            Invoice::STATUS_UNPAID,
            now()->subHours(100),
        );

        // A second, still-fresh invoice for the same registration (e.g. they retried
        // with a different payment type) must keep the pending registration alive.
        $freshInvoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_INSTALLMENT,
            Invoice::STATUS_UNPAID,
            now()->subHours(1),
        );

        $result = app(ExpiredUnpaidCheckoutPurgeService::class)->purge();

        $this->assertSame(1, $result['invoices_deleted']);
        $this->assertSame(0, $result['pending_registrations_deleted']);
        $this->assertDatabaseMissing('invoices', ['id' => $expiredInvoice->id]);
        $this->assertDatabaseHas('invoices', ['id' => $freshInvoice->id]);
        $this->assertDatabaseHas('pending_registrations', ['id' => $pendingRegistration->id]);
    }

    public function test_deletes_expired_unpaid_installment_invoice_with_its_payment_subscription(): void
    {
        $tier = AccessTier::factory()->create();
        $pendingRegistration = $this->makePendingRegistration($tier);
        $invoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_INSTALLMENT,
            Invoice::STATUS_UNPAID,
            now()->subHours(80),
        );

        $package = Package::factory()->create(['access_tier_id' => $tier->id]);

        $subscription = PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'status' => PaymentSubscription::STATUS_DRAFT,
            'installment_count' => 7,
            'installments_paid_count' => 0,
            'currency_code' => 'USD',
            'total_amount' => 500,
            'monthly_base_amount' => 70,
            'first_payment_amount' => 70,
        ]);

        $result = app(ExpiredUnpaidCheckoutPurgeService::class)->purge();

        $this->assertSame(1, $result['invoices_deleted']);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payment_subscriptions', ['id' => $subscription->id]);
    }

    public function test_artisan_command_runs_the_purge(): void
    {
        $tier = AccessTier::factory()->create();
        $pendingRegistration = $this->makePendingRegistration($tier);
        $invoice = $this->makeInitialInvoice(
            $pendingRegistration,
            $tier,
            Invoice::PAYMENT_TYPE_FULL,
            Invoice::STATUS_UNPAID,
            now()->subHours(100),
        );

        $this->artisan('checkouts:purge-expired-unpaid')->assertSuccessful();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }
}
