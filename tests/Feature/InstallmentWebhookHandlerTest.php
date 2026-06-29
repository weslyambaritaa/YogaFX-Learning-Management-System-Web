<?php

namespace Tests\Feature;

use App\Jobs\SendOnboardingContinuationEmailJob;
use App\Models\AccessTier;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Payments\InstallmentWebhookHandler;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InstallmentWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_event_with_last_payment_opens_onboarding_and_records_first_payment(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture([
            'provider_subscription_id' => 'I-ACTIVATION-001',
            'first_payment_amount' => 48,
            'monthly_base_amount' => 42,
            'next_billing_amount' => 42,
        ]);

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-ACTIVATED-001',
            'provider_event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
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

        app(InstallmentWebhookHandler::class)->handle($event);

        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $onboardingState = OnboardingState::query()->where('pending_registration_id', $pendingRegistration->id)->firstOrFail();

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame('48.00', $payment->amount_paid);
        $this->assertStringStartsWith('subscription_activation:I-ACTIVATION-001:', (string) $payment->payment_reference);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installments_paid_count' => 1,
        ]);

        $this->assertSame('2026-07-10 09:00:00', optional($subscription->fresh()->first_payment_paid_at)->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_INSTALLMENT,
            'balance_due' => 252.00,
        ]);
        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);
        $this->assertSame(OnboardingState::STATUS_AWAITING_ENROLLMENT, $onboardingState->status);

        Queue::assertPushed(SendOnboardingContinuationEmailJob::class);
    }

    public function test_activation_event_without_last_payment_only_marks_subscription_active(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture();

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-ACTIVATED-NO-PAYMENT-001',
            'provider_event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
                'resource' => [
                    'id' => $subscription->provider_subscription_id,
                    'status' => 'ACTIVE',
                ],
            ],
        ]);

        app(InstallmentWebhookHandler::class)->handle($event);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installments_paid_count' => 0,
        ]);

        $this->assertNull($subscription->fresh()->first_payment_paid_at);
        $this->assertSame(0, Payment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_UNPAID,
            'balance_due' => 300.00,
        ]);
        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
        $this->assertNull(OnboardingState::query()->where('pending_registration_id', $pendingRegistration->id)->first());

        Queue::assertNothingPushed();
    }

    public function test_sale_completed_after_activation_first_payment_does_not_double_count(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture([
            'provider_subscription_id' => 'I-ACTIVATION-DUP-001',
            'first_payment_amount' => 48,
            'monthly_base_amount' => 42,
            'next_billing_amount' => 42,
        ]);

        $activationEvent = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-ACTIVATED-DUP-001',
            'provider_event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
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

        app(InstallmentWebhookHandler::class)->handle($activationEvent);

        $saleCompletedEvent = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-SALE-DUP-001',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_capture_id' => 'CAPTURE-SALE-DUP-001',
            'occurred_at' => now()->addMinute(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
                'resource' => [
                    'amount' => [
                        'total' => '48.00',
                        'currency' => AccessTier::CURRENCY_USD,
                    ],
                ],
            ],
        ]);

        app(InstallmentWebhookHandler::class)->handle($saleCompletedEvent);

        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'installments_paid_count' => 1,
            'status' => PaymentSubscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_INSTALLMENT,
            'balance_due' => 252.00,
        ]);
        $this->assertDatabaseHas('payment_subscription_events', [
            'id' => $saleCompletedEvent->id,
            'status' => PaymentSubscriptionEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_first_payment_success_creates_ledger_and_opens_onboarding(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture();

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-FIRST-PAYMENT-001',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_order_id' => 'ORDER-001',
            'provider_capture_id' => 'CAPTURE-001',
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
                'resource' => [
                    'amount' => [
                        'total' => '48.00',
                        'currency' => AccessTier::CURRENCY_USD,
                    ],
                ],
            ],
        ]);

        app(InstallmentWebhookHandler::class)->handle($event);

        $payment = Payment::query()->where('payment_reference', 'CAPTURE-001')->firstOrFail();
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($pendingRegistration->email)])->firstOrFail();
        $onboardingState = OnboardingState::query()->where('pending_registration_id', $pendingRegistration->id)->firstOrFail();

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame('48.00', $payment->amount_paid);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_INSTALLMENT,
            'balance_due' => 252.00,
        ]);

        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);

        $this->assertSame($user->id, $invoice->fresh()->user_id);
        $this->assertSame(OnboardingState::STATUS_AWAITING_ENROLLMENT, $onboardingState->status);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installments_paid_count' => 1,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('payment_subscription_events', [
            'id' => $event->id,
            'payment_activity_id' => $payment->id,
            'status' => PaymentSubscriptionEvent::STATUS_PROCESSED,
        ]);

        Queue::assertPushed(SendOnboardingContinuationEmailJob::class);

        $response = $this->getJson(URL::temporarySignedRoute('checkout.installments.status', now()->addDay(), [
            'pendingRegistration' => $pendingRegistration->id,
            'accessTierSlug' => $pendingRegistration->accessTier->slug,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'onboarding_ready')
            ->assertJsonPath('onboarding_ready', true);

        $this->assertIsString($response->json('onboarding_url'));
        $this->assertStringContainsString('/onboarding/', $response->json('onboarding_url'));
    }

    public function test_recurring_payment_success_reactivates_inactive_user_and_can_complete_invoice(): void
    {
        Queue::fake();
        Mail::fake();

        EmailTemplate::query()->create([
            'notification_type' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS,
            'notification_name' => 'Installment Payment Success',
            'is_enabled' => true,
            'admin_recipients' => 'ops-installment@yogafx.test',
            'subject_admin' => 'Installment success {{ user_email }}',
            'body_admin' => '{{ payment_amount }} paid',
        ]);

        EmailTemplate::query()->create([
            'notification_type' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_COMPLETED,
            'notification_name' => 'Installment Payment Completed',
            'is_enabled' => true,
            'admin_recipients' => '',
            'subject_user' => 'Installment completed',
            'body_user' => '{{ package_title }} completed at {{ payment_completed_at }}',
        ]);

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture([
            'installments_paid_count' => 6,
            'status' => PaymentSubscription::STATUS_PAST_DUE,
            'user_id' => null,
            'first_payment_paid_at' => now()->subMonths(5),
            'last_payment_failed_at' => now()->subDay(),
        ]);

        $user = User::factory()->student()->create([
            'email' => $pendingRegistration->email,
            'access_tier_id' => $subscription->access_tier_id,
            'is_active' => false,
        ]);

        $invoice->forceFill([
            'user_id' => $user->id,
            'balance_due' => 42.00,
            'status' => Invoice::STATUS_INSTALLMENT,
        ])->save();

        $subscription->forceFill([
            'user_id' => $user->id,
        ])->save();

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-RECURRING-001',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_capture_id' => 'CAPTURE-RECURRING-001',
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
                'resource' => [
                    'amount' => [
                        'total' => '42.00',
                        'currency' => AccessTier::CURRENCY_USD,
                    ],
                ],
            ],
        ]);

        app(InstallmentWebhookHandler::class)->handle($event);

        $this->assertDatabaseHas('payment_activities', [
            'invoice_id' => $invoice->id,
            'payment_reference' => 'CAPTURE-RECURRING-001',
            'status' => Payment::STATUS_SUCCESS,
            'amount_paid' => 42.00,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PAID_FULL,
            'balance_due' => 0.00,
        ]);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_COMPLETED,
            'installments_paid_count' => 7,
        ]);

        $this->assertTrue((bool) $user->fresh()->is_active);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS,
            'reference_type' => 'payment_subscription',
            'reference_id' => $subscription->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'ops-installment@yogafx.test',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_COMPLETED,
            'reference_type' => 'payment_subscription',
            'reference_id' => $subscription->id,
            'recipient_type' => 'user',
            'recipient_email' => $user->email,
            'status' => 'sent',
        ]);
    }

    public function test_failed_payment_marks_subscription_past_due_and_sets_grace_deadline(): void
    {
        Mail::fake();

        EmailTemplate::query()->create([
            'notification_type' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_FAILED,
            'notification_name' => 'Installment Payment Failed',
            'is_enabled' => true,
            'admin_recipients' => 'ops-failed@yogafx.test',
            'subject_admin' => 'Installment failed {{ user_email }}',
            'body_admin' => 'Grace until {{ grace_deadline_at }}',
        ]);

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture();

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-FAILED-001',
            'provider_event_type' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [],
        ]);

        app(InstallmentWebhookHandler::class)->handle($event);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_PAST_DUE,
        ]);

        $fresh = $subscription->fresh();
        $this->assertSame('2026-08-18', optional($fresh->grace_deadline_at)->format('Y-m-d'));
        $this->assertNotNull($fresh->last_payment_failed_at);
        $this->assertTrue($invoice->fresh()->status === Invoice::STATUS_UNPAID);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_FAILED,
            'reference_type' => 'payment_subscription',
            'reference_id' => $subscription->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'ops-failed@yogafx.test',
            'status' => 'sent',
        ]);
    }

    public function test_duplicate_provider_capture_does_not_create_second_ledger_row_or_double_reduce_invoice_balance(): void
    {
        Queue::fake();

        [$subscription, $pendingRegistration, $invoice] = $this->createSubscriptionFixture([
            'installments_paid_count' => 1,
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'first_payment_paid_at' => now()->subMonth(),
            'next_due_at' => '2026-09-15',
            'grace_deadline_at' => '2026-09-18',
        ]);

        $user = User::factory()->student()->create([
            'email' => $pendingRegistration->email,
            'access_tier_id' => $subscription->access_tier_id,
            'is_active' => true,
        ]);

        $invoice->forceFill([
            'user_id' => $user->id,
            'balance_due' => 252.00,
            'status' => Invoice::STATUS_INSTALLMENT,
        ])->save();

        $subscription->forceFill([
            'user_id' => $user->id,
        ])->save();

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_INSTALLMENT,
            'payment_reference' => 'CAPTURE-DUPLICATE-001',
            'amount_paid' => 42.00,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Payment::STATUS_SUCCESS,
            'notes' => 'Previous webhook success.',
        ]);

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-DUPLICATE-001',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_capture_id' => 'CAPTURE-DUPLICATE-001',
            'occurred_at' => now(),
            'status' => PaymentSubscriptionEvent::STATUS_RECEIVED,
            'payload' => [
                'resource' => [
                    'amount' => [
                        'total' => '42.00',
                        'currency' => AccessTier::CURRENCY_USD,
                    ],
                ],
            ],
        ]);

        app(InstallmentWebhookHandler::class)->handle($event);

        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());

        $payment = Payment::query()->where('payment_reference', 'CAPTURE-DUPLICATE-001')->firstOrFail();
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame('42.00', $payment->amount_paid);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_INSTALLMENT,
            'balance_due' => 252.00,
        ]);

        $this->assertDatabaseHas('payment_subscriptions', [
            'id' => $subscription->id,
            'status' => PaymentSubscription::STATUS_ACTIVE,
            'installments_paid_count' => 1,
        ]);

        $this->assertDatabaseHas('payment_subscription_events', [
            'id' => $event->id,
            'payment_activity_id' => $payment->id,
            'status' => PaymentSubscriptionEvent::STATUS_PROCESSED,
        ]);
    }

    private function createSubscriptionFixture(array $subscriptionOverrides = []): array
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
            'invoice_number' => 'INV-INSTALLMENT-001',
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

        return [$subscription, $pendingRegistration, $invoice];
    }
}
