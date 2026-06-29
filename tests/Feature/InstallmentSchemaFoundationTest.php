<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentSchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_subscription_can_belong_to_invoice_package_pending_registration_user_and_access_tier(): void
    {
        $tier = AccessTier::factory()->create();
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'installment_enabled' => true,
        ]);
        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'package_id' => $package->id,
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava-subscription@example.com',
            'phone' => '+6281234567001',
            'country' => 'Indonesia',
            'amount_snapshot' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);
        $user = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-2026-9001',
            'pending_registration_id' => $pendingRegistration->id,
            'package_id' => $package->id,
            'user_id' => $user->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'total_amount' => 300,
            'balance_due' => 252,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Invoice::STATUS_INSTALLMENT,
            'issued_at' => now(),
        ]);

        $subscription = PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'access_tier_id' => $tier->id,
            'provider' => 'paypal',
            'status' => PaymentSubscription::STATUS_DRAFT,
            'installment_count' => 5,
            'installments_paid_count' => 1,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 63,
            'first_payment_amount' => 48,
            'next_billing_amount' => 63,
            'next_due_at' => now()->addMonth(),
            'final_due_at' => now()->addMonths(4),
        ]);

        $this->assertTrue($subscription->invoice->is($invoice));
        $this->assertTrue($subscription->package->is($package));
        $this->assertTrue($subscription->pendingRegistration->is($pendingRegistration));
        $this->assertTrue($subscription->user->is($user));
        $this->assertTrue($subscription->accessTier->is($tier));
    }

    public function test_payment_subscription_event_can_link_to_subscription_invoice_and_payment_activity(): void
    {
        $tier = AccessTier::factory()->create();
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-2026-9002',
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'total_amount' => 300,
            'balance_due' => 252,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Invoice::STATUS_INSTALLMENT,
            'issued_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_INSTALLMENT,
            'amount_paid' => 48,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Payment::STATUS_SUCCESS,
            'payment_reference' => 'PAYPAL-FIRST-PAYMENT-001',
        ]);
        $subscription = PaymentSubscription::query()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'provider' => 'paypal',
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installment_count' => 5,
            'installments_paid_count' => 1,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 63,
            'first_payment_amount' => 48,
        ]);

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'payment_activity_id' => $payment->id,
            'provider' => 'paypal',
            'provider_event_id' => 'WH-EVT-001',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => 'I-SUBSCRIPTION-001',
            'provider_capture_id' => 'CAPTURE-001',
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => ['id' => 'WH-EVT-001'],
        ]);

        $this->assertTrue($event->paymentSubscription->is($subscription));
        $this->assertTrue($event->invoice->is($invoice));
        $this->assertTrue($event->paymentActivity->is($payment));
    }
}
