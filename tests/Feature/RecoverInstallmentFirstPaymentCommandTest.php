<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecoverInstallmentFirstPaymentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_recovers_first_payment_and_opens_onboarding(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createStuckActivationFixture();

        $exitCode = Artisan::call('yogafx:installment-recover-first-payment', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installments_paid_count' => 1,
        ]);
        $this->assertNotNull($subscription->fresh()->first_payment_paid_at);
        $this->assertDatabaseHas('payment_activities', [
            'invoice_id' => $invoice->id,
            'status' => Payment::STATUS_SUCCESS,
            'amount_paid' => 48.00,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_INSTALLMENT,
            'balance_due' => 252.00,
        ]);
        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);
        $this->assertNotNull(OnboardingState::query()->where('pending_registration_id', $pendingRegistration->id)->first());
    }

    public function test_command_is_idempotent_and_does_not_duplicate_first_payment(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createStuckActivationFixture();

        $this->assertSame(0, Artisan::call('yogafx:installment-recover-first-payment', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ]));

        $secondExitCode = Artisan::call('yogafx:installment-recover-first-payment', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ]);

        $this->assertSame(2, $secondExitCode);
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, (int) $subscription->fresh()->installments_paid_count);
        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);
    }

    public function test_command_rejects_non_active_subscription(): void
    {
        [$subscription] = $this->createStuckActivationFixture([
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
        ]);

        $exitCode = Artisan::call('yogafx:installment-recover-first-payment', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ]);

        $this->assertSame(2, $exitCode);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_command_rejects_when_first_payment_has_already_been_processed(): void
    {
        [$subscription, $pendingRegistration, $invoice] = $this->createStuckActivationFixture([
            'installments_paid_count' => 1,
            'first_payment_paid_at' => now()->subMinute(),
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_INSTALLMENT,
            'payment_reference' => 'existing-first-payment',
            'amount_paid' => 48,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Payment::STATUS_SUCCESS,
            'notes' => 'Existing first payment.',
        ]);

        $exitCode = Artisan::call('yogafx:installment-recover-first-payment', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ]);

        $this->assertSame(2, $exitCode);
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
    }

    public function test_command_does_not_run_in_production_without_force(): void
    {
        config(['app.env' => 'production']);

        [$subscription] = $this->createStuckActivationFixture();

        $exitCode = Artisan::call('yogafx:installment-recover-first-payment', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ]);

        $this->assertSame(2, $exitCode);
        $this->assertSame(0, Payment::query()->count());
    }

    private function createStuckActivationFixture(array $subscriptionOverrides = []): array
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
            'first_name' => 'Moses',
            'last_name' => 'Simangunsong',
            'email' => 'moses@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
            'amount_snapshot' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'installment_billing_day' => 15,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-RECOVER-001',
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

        $subscription = PaymentSubscription::query()->create(array_merge([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-RECOVER-001',
            'provider_plan_id' => 'P-RECOVER-001',
            'provider_subscription_id' => 'I-RECOVER-001',
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installment_count' => 7,
            'installments_paid_count' => 0,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42,
            'first_payment_amount' => 48,
            'next_billing_amount' => 42,
            'started_at' => now(),
            'next_due_at' => '2026-08-15',
            'final_due_at' => '2027-01-15',
            'grace_deadline_at' => '2026-08-18',
            'metadata' => [
                'installment_plan' => [
                    'recurring_due_dates' => [
                        '2026-08-15',
                        '2026-09-15',
                        '2026-10-15',
                        '2026-11-15',
                        '2026-12-15',
                        '2027-01-15',
                    ],
                    'grace_deadlines' => [
                        '2026-08-18',
                        '2026-09-18',
                        '2026-10-18',
                        '2026-11-18',
                        '2026-12-18',
                        '2027-01-18',
                    ],
                ],
            ],
        ], $subscriptionOverrides));

        PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'payment_activity_id' => null,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-RECOVER-ACTIVATED-001',
            'provider_event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'occurred_at' => now(),
            'processed_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_PROCESSED,
            'payload' => [
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'resource' => [
                    'id' => $subscription->provider_subscription_id,
                    'status' => 'ACTIVE',
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
            ],
        ]);

        return [$subscription, $pendingRegistration, $invoice];
    }
}
