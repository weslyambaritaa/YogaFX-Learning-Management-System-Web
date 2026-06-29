<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentSubscription;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Payments\OverdueInstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SyncOverdueInstallmentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deactivates_overdue_student_and_sends_admin_notification_once(): void
    {
        Mail::fake();

        EmailTemplate::query()->create([
            'notification_type' => OverdueInstallmentService::NOTIFICATION_TYPE_OVERDUE_INACTIVE,
            'notification_name' => 'Installment Overdue Inactive',
            'is_enabled' => true,
            'admin_recipients' => 'ops@yogafx.test',
            'subject_admin' => 'Installment overdue for {{ user_email }}',
            'body_admin' => 'Subscription {{ provider_subscription_id }} is overdue and student is now {{ student_status }}.',
        ]);

        [$subscription, $user] = $this->createPastDueSubscription([
            'grace_deadline_at' => now()->subDay(),
        ]);

        Artisan::call('installments:sync-overdue-status');

        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->assertDatabaseHas('email_logs', [
            'notification_type' => OverdueInstallmentService::NOTIFICATION_TYPE_OVERDUE_INACTIVE,
            'reference_type' => OverdueInstallmentService::REFERENCE_TYPE_PAYMENT_SUBSCRIPTION,
            'reference_id' => $subscription->id,
            'recipient_type' => 'admin',
            'recipient_email' => 'ops@yogafx.test',
            'status' => 'sent',
        ]);

        $subscription->refresh();
        $this->assertArrayHasKey('overdue_deactivated_at', $subscription->metadata ?? []);
        $this->assertArrayHasKey('overdue_notification_sent_at', $subscription->metadata ?? []);

        Artisan::call('installments:sync-overdue-status');

        $this->assertSame(1, EmailLog::query()
            ->where('notification_type', OverdueInstallmentService::NOTIFICATION_TYPE_OVERDUE_INACTIVE)
            ->where('reference_id', $subscription->id)
            ->count());
    }

    public function test_command_does_not_deactivate_before_grace_deadline(): void
    {
        Mail::fake();

        EmailTemplate::query()->create([
            'notification_type' => OverdueInstallmentService::NOTIFICATION_TYPE_OVERDUE_INACTIVE,
            'notification_name' => 'Installment Overdue Inactive',
            'is_enabled' => true,
            'admin_recipients' => 'ops@yogafx.test',
            'subject_admin' => 'Installment overdue',
            'body_admin' => 'Student inactive.',
        ]);

        [$subscription, $user] = $this->createPastDueSubscription([
            'grace_deadline_at' => now()->addDay(),
        ]);

        Artisan::call('installments:sync-overdue-status');

        $this->assertTrue((bool) $user->fresh()->is_active);
        $this->assertSame(0, EmailLog::query()
            ->where('notification_type', OverdueInstallmentService::NOTIFICATION_TYPE_OVERDUE_INACTIVE)
            ->where('reference_id', $subscription->id)
            ->count());
    }

    public function test_schedule_registers_daily_overdue_sync_command(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $event = $events->first(fn ($scheduled) => str_contains($scheduled->command, 'installments:sync-overdue-status'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    private function createPastDueSubscription(array $subscriptionOverrides = []): array
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'name' => 'Master Class',
        ]);
        $user = User::factory()->student()->create([
            'email' => 'overdue@example.com',
            'access_tier_id' => $tier->id,
            'is_active' => true,
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
            'first_name' => 'Overdue',
            'last_name' => 'Student',
            'email' => $user->email,
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
            'amount_snapshot' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-OVERDUE-001',
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
            'total_amount' => 300,
            'balance_due' => 126,
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => Invoice::STATUS_INSTALLMENT,
            'issued_at' => now(),
        ]);

        $subscription = PaymentSubscription::query()->create(array_merge([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'access_tier_id' => $tier->id,
            'provider' => PaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-001',
            'provider_plan_id' => 'P-001',
            'provider_subscription_id' => 'I-SUBSCRIPTION-OVERDUE-001',
            'status' => PaymentSubscription::STATUS_PAST_DUE,
            'installment_count' => 7,
            'installments_paid_count' => 4,
            'currency_code' => AccessTier::CURRENCY_USD,
            'total_amount' => 300,
            'monthly_base_amount' => 42,
            'first_payment_amount' => 48,
            'next_billing_amount' => 42,
            'next_due_at' => now()->subDays(4),
            'final_due_at' => now()->addMonths(2),
            'grace_deadline_at' => now()->subDay(),
            'metadata' => [],
        ], $subscriptionOverrides));

        return [$subscription, $user];
    }
}
