<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Payments\InstallmentWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_student_is_redirected_away_from_student_dashboard(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => false,
            ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.inactive'));
    }

    public function test_recurring_payment_does_not_reactivate_user_when_subscription_is_cancelled(): void
    {
        [$subscription, $user, $invoice] = $this->createCancelledSubscriptionFixture();

        $event = PaymentSubscriptionEvent::query()->create([
            'payment_subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_event_id' => 'WH-CANCELLED-RECOVERY-001',
            'provider_event_type' => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_capture_id' => 'CAPTURE-CANCELLED-001',
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

        $this->assertFalse((bool) $user->fresh()->is_active);

        $subscription->refresh();
        $this->assertSame(PaymentSubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertSame(6, $subscription->installments_paid_count);
        $this->assertSame('42.00', $invoice->fresh()->balance_due);
        $this->assertSame('subscription_cancelled', $subscription->metadata['reactivation_blocked_reason'] ?? null);
        $this->assertArrayNotHasKey('reactivated_at', $subscription->metadata ?? []);
    }

    private function createCancelledSubscriptionFixture(): array
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $user = User::factory()->student()->create([
            'email' => 'cancelled@example.com',
            'access_tier_id' => $tier->id,
            'is_active' => false,
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
            'first_name' => 'Cancelled',
            'last_name' => 'Student',
            'email' => $user->email,
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
            'amount_snapshot' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-CANCELLED-001',
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'total_amount' => 300,
            'balance_due' => 84,
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
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-001',
            'provider_plan_id' => 'P-001',
            'provider_subscription_id' => 'I-SUBSCRIPTION-CANCELLED-001',
            'status' => PaymentSubscription::STATUS_CANCELLED,
            'installment_count' => 7,
            'installments_paid_count' => 5,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42,
            'first_payment_amount' => 48,
            'next_billing_amount' => 42,
            'first_payment_paid_at' => now()->subMonths(5),
            'next_due_at' => '2026-12-15',
            'final_due_at' => '2027-01-15',
            'grace_deadline_at' => '2026-12-18',
            'cancelled_at' => now()->subDay(),
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
        ]);

        return [$subscription, $user, $invoice];
    }
}
