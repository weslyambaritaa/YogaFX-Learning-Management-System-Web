<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationPaymentSubscriptionEvent;
use App\Models\AccommodationRoomType;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccommodationInstallmentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_activated_webhook_confirms_booking_and_records_first_payment(): void
    {
        [$booking, $subscription] = $this->createPendingBookingWithSubscription();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $this->postJson(route('paypal.webhook'), $this->activatedPayload('WH-ACC-001', $subscription, '100.00'), $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_CONFIRMED, $booking->status);
        $this->assertNotNull($booking->paid_at);

        $subscription->refresh();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame(1, $subscription->installments_paid_count);
        $this->assertNotNull($subscription->first_payment_paid_at);
        $this->assertNotNull($subscription->next_due_at);

        $this->assertDatabaseHas('email_logs', [
            'notification_type' => 'accommodation_booking_confirmed',
            'reference_type' => 'accommodation_booking',
            'reference_id' => $booking->id,
            'recipient_type' => 'user',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('accommodation_payment_subscription_events', [
            'provider_event_id' => 'WH-ACC-001',
            'accommodation_payment_subscription_id' => $subscription->id,
            'status' => AccommodationPaymentSubscriptionEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_activated_webhook_does_not_confirm_booking_when_hold_has_expired(): void
    {
        [$booking, $subscription] = $this->createPendingBookingWithSubscription([
            'hold_expires_at' => now()->subMinutes(5),
        ]);

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $this->postJson(route('paypal.webhook'), $this->activatedPayload('WH-ACC-002', $subscription, '100.00'), $this->webhookHeaders())
            ->assertOk();

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertNull($booking->paid_at);

        // The payment is still recorded on the subscription — PayPal did
        // charge it — just the booking itself is left for manual follow-up.
        $subscription->refresh();
        $this->assertSame(1, $subscription->installments_paid_count);

        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => 'accommodation_booking_confirmed',
            'reference_id' => $booking->id,
        ]);
    }

    public function test_activated_webhook_does_not_confirm_booking_when_room_is_oversold(): void
    {
        [$booking, $subscription] = $this->createPendingBookingWithSubscription();

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $booking->accommodation_id,
                'accommodation_room_type_id' => $booking->accommodation_room_type_id,
                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,
            ]);

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $this->postJson(route('paypal.webhook'), $this->activatedPayload('WH-ACC-003', $subscription, '100.00'), $this->webhookHeaders())
            ->assertOk();

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
    }

    public function test_payment_failed_webhook_sets_past_due_and_sends_email_without_touching_booking(): void
    {
        [$booking, $subscription] = $this->createActiveSubscriptionOnConfirmedBooking();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $payload = [
            'id' => 'WH-ACC-004',
            'event_type' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
            'create_time' => '2026-09-08T09:00:00Z',
            'resource' => [
                'id' => $subscription->provider_subscription_id,
            ],
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $subscription->refresh();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertNotNull($subscription->grace_deadline_at);
        $this->assertNotNull($subscription->last_payment_failed_at);

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_CONFIRMED, $booking->status);

        $this->assertDatabaseHas('email_logs', [
            'notification_type' => 'accommodation_installment_payment_failed',
            'reference_type' => 'accommodation_payment_subscription',
            'reference_id' => $subscription->id,
            'status' => 'sent',
        ]);
    }

    public function test_payment_completed_webhook_increments_paid_count_for_second_cycle(): void
    {
        [$booking, $subscription] = $this->createActiveSubscriptionOnConfirmedBooking();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $payload = [
            'id' => 'WH-ACC-005',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'create_time' => '2026-09-08T09:00:00Z',
            'resource' => [
                'id' => 'CAPTURE-002',
                'billing_agreement_id' => $subscription->provider_subscription_id,
                'amount' => ['total' => '100.00'],
            ],
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $subscription->refresh();
        $this->assertSame(2, $subscription->installments_paid_count);
        $this->assertSame(AccommodationPaymentSubscription::STATUS_ACTIVE, $subscription->status);
    }

    public function test_payment_completed_webhook_marks_subscription_completed_on_final_cycle(): void
    {
        [$booking, $subscription] = $this->createActiveSubscriptionOnConfirmedBooking([
            'installment_count' => 2,
            'installments_paid_count' => 1,
        ]);

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $payload = [
            'id' => 'WH-ACC-006',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'create_time' => '2026-09-08T09:00:00Z',
            'resource' => [
                'id' => 'CAPTURE-003',
                'billing_agreement_id' => $subscription->provider_subscription_id,
                'amount' => ['total' => '100.00'],
            ],
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())->assertOk();

        $subscription->refresh();
        $this->assertSame(2, $subscription->installments_paid_count);
        $this->assertSame(AccommodationPaymentSubscription::STATUS_COMPLETED, $subscription->status);
        $this->assertNotNull($subscription->completed_at);
        $this->assertNull($subscription->next_due_at);
    }

    public function test_subscription_cancelled_webhook_does_not_cancel_the_booking(): void
    {
        [$booking, $subscription] = $this->createActiveSubscriptionOnConfirmedBooking();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $payload = [
            'id' => 'WH-ACC-007',
            'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
            'create_time' => '2026-09-08T09:00:00Z',
            'resource' => [
                'id' => $subscription->provider_subscription_id,
            ],
        ];

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())->assertOk();

        $subscription->refresh();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_CANCELLED, $subscription->status);

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_CONFIRMED, $booking->status);
    }

    public function test_duplicate_webhook_event_is_not_reprocessed(): void
    {
        [$booking, $subscription] = $this->createPendingBookingWithSubscription();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue());

        $payload = $this->activatedPayload('WH-ACC-008', $subscription, '100.00');

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertJsonPath('status', 'processed');

        $this->postJson(route('paypal.webhook'), $payload, $this->webhookHeaders())
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, AccommodationPaymentSubscriptionEvent::query()->count());

        $subscription->refresh();
        $this->assertSame(1, $subscription->installments_paid_count);
    }

    public function test_activated_and_payment_completed_for_the_same_first_charge_do_not_double_count(): void
    {
        [$booking, $subscription] = $this->createPendingBookingWithSubscription();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue());

        // ACTIVATED arrives first and confirms the booking + first payment.
        $this->postJson(
            route('paypal.webhook'),
            $this->activatedPayload('WH-ACC-010', $subscription, '100.00'),
            $this->webhookHeaders(),
        )->assertOk();

        // PayPal also sends PAYMENT.SALE.COMPLETED for the very same charge,
        // a few seconds later, with a DIFFERENT event id (this is the
        // scenario the amount-based heuristic used to get wrong when
        // first_payment_amount equals monthly_amount).
        $twinPayload = [
            'id' => 'WH-ACC-011',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'create_time' => '2026-09-08T09:00:05Z',
            'resource' => [
                'id' => 'CAPTURE-FIRST-001',
                'billing_agreement_id' => $subscription->provider_subscription_id,
                'amount' => ['total' => '100.00'],
            ],
        ];

        $this->postJson(route('paypal.webhook'), $twinPayload, $this->webhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $subscription->refresh();
        $this->assertSame(1, $subscription->installments_paid_count);

        $this->assertSame(2, AccommodationPaymentSubscriptionEvent::query()->count());
    }

    public function test_webhook_for_accommodation_subscription_does_not_touch_package_subscription_tables(): void
    {
        [$booking, $subscription] = $this->createPendingBookingWithSubscription();

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue());

        $this->postJson(route('paypal.webhook'), $this->activatedPayload('WH-ACC-009', $subscription, '100.00'), $this->webhookHeaders())
            ->assertOk();

        $this->assertSame(0, \App\Models\PaymentSubscriptionEvent::query()->count());
        $this->assertSame(1, AccommodationPaymentSubscriptionEvent::query()->count());
    }

    /**
     * @return array<int, string>
     */
    private function webhookHeaders(): array
    {
        return [
            'paypal-auth-algo' => 'SHA256withRSA',
            'paypal-cert-url' => 'https://api-m.sandbox.paypal.com/certs/test',
            'paypal-transmission-id' => 'transmission-id',
            'paypal-transmission-sig' => 'signature',
            'paypal-transmission-time' => '2026-09-08T09:00:00Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activatedPayload(string $eventId, AccommodationPaymentSubscription $subscription, string $amount): array
    {
        return [
            'id' => $eventId,
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'create_time' => '2026-09-08T09:00:00Z',
            'resource' => [
                'id' => $subscription->provider_subscription_id,
                'plan_id' => $subscription->provider_plan_id,
                'billing_info' => [
                    'last_payment' => [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => $amount,
                        ],
                        'time' => '2026-09-08T09:00:00Z',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $bookingOverrides
     * @return array{0: AccommodationBooking, 1: AccommodationPaymentSubscription}
     */
    private function createPendingBookingWithSubscription(array $bookingOverrides = []): array
    {
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->pendingPayment()
            ->create(array_merge([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'total_amount' => 300,
                'currency_code' => 'USD',
                'hold_expires_at' => now()->addMinutes(60),
            ], $bookingOverrides));

        $subscription = AccommodationPaymentSubscription::query()->create([
            'accommodation_booking_id' => $booking->id,
            'provider' => AccommodationPaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-WH-001',
            'provider_plan_id' => 'PLAN-WH-001',
            'provider_subscription_id' => 'I-WEBHOOK-'.$booking->id,
            'status' => AccommodationPaymentSubscription::STATUS_APPROVAL_PENDING,
            'installment_count' => 3,
            'installments_paid_count' => 0,
            'currency_code' => 'USD',
            'total_amount' => 300,
            'monthly_amount' => 100,
            'first_payment_amount' => 100,
            'metadata' => [
                'installment_plan' => [
                    'recurring_due_dates' => ['2026-10-08', '2026-11-08'],
                    'grace_deadlines' => ['2026-10-11', '2026-11-11'],
                ],
            ],
        ]);

        return [$booking, $subscription];
    }

    /**
     * @param  array<string, mixed>  $subscriptionOverrides
     * @return array{0: AccommodationBooking, 1: AccommodationPaymentSubscription}
     */
    private function createActiveSubscriptionOnConfirmedBooking(array $subscriptionOverrides = []): array
    {
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'total_amount' => 300,
                'currency_code' => 'USD',
            ]);

        $subscription = AccommodationPaymentSubscription::query()->create(array_merge([
            'accommodation_booking_id' => $booking->id,
            'provider' => AccommodationPaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-WH-002',
            'provider_plan_id' => 'PLAN-WH-002',
            'provider_subscription_id' => 'I-WEBHOOK-ACTIVE-'.$booking->id,
            'status' => AccommodationPaymentSubscription::STATUS_ACTIVE,
            'installment_count' => 3,
            'installments_paid_count' => 1,
            'currency_code' => 'USD',
            'total_amount' => 300,
            'monthly_amount' => 100,
            'first_payment_amount' => 100,
            'first_payment_paid_at' => now()->subDays(30),
            'next_due_at' => now()->addDay(),
            'metadata' => [
                'installment_plan' => [
                    'recurring_due_dates' => ['2026-10-08', '2026-11-08'],
                    'grace_deadlines' => ['2026-10-11', '2026-11-11'],
                ],
            ],
        ], $subscriptionOverrides));

        return [$booking, $subscription];
    }

    private function createRoomType(): AccommodationRoomType
    {
        $accommodation = Accommodation::factory()->create([
            'is_active' => true,
            'currency_code' => 'USD',
        ]);

        return AccommodationRoomType::factory()->create([
            'accommodation_id' => $accommodation->id,
            'is_active' => true,
            'total_rooms' => 1,
            'price' => 150,
        ]);
    }
}
