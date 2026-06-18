<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\SimulatedPaymentFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentAndModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_payment_uses_current_tier_price_and_currency_snapshots(): void
    {
        $tier = AccessTier::factory()->create([
            'price' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
            'amount_snapshot' => 199.00,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $this->mock(EmailNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendAutomated')->once();
        });

        $result = app(SimulatedPaymentFlowService::class)->processInitialPayment($pendingRegistration, [
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_method' => Payment::METHOD_PAYPAL,
        ]);

        /** @var Invoice $invoice */
        $invoice = $result['invoice'];
        /** @var Payment $payment */
        $payment = $result['payment'];

        $this->assertSame(Invoice::TYPE_INITIAL, $invoice->type);
        $this->assertSame(AccessTier::CURRENCY_GBP, $invoice->currency_code);
        $this->assertSame('499.00', $invoice->total_amount);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(AccessTier::CURRENCY_GBP, $payment->currency_code);
        $this->assertSame(Payment::METHOD_PAYPAL, $payment->payment_method);
        $this->assertSame(Payment::TYPE_PAY_FULL, $payment->payment_type);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame('499.00', $payment->amount_paid);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'pending_registration_id' => $pendingRegistration->id,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'type' => Invoice::TYPE_INITIAL,
            'status' => Invoice::STATUS_PAID_FULL,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'invoice_id' => $invoice->id,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    public function test_assignment_submission_marks_assignment_module_complete_for_sequential_access(): void
    {
        $tier = AccessTier::factory()->create();
        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $assignmentModule = Module::factory()->create([
            'title' => 'Assignment Module',
            'url_slug' => 'assignment-module',
            'sort_order' => 1,
            'certificate_enabled' => false,
            'ebook_enabled' => false,
            'video_lecturer_enabled' => false,
        ]);
        $assignmentModule->accessTiers()->attach($tier);

        $nextModule = Module::factory()->create([
            'title' => 'Resource Module',
            'url_slug' => 'resource-module',
            'sort_order' => 2,
            'certificate_enabled' => false,
            'ebook_enabled' => true,
            'video_lecturer_enabled' => false,
        ]);
        $nextModule->accessTiers()->attach($tier);

        $assignment = Assignment::query()->create([
            'module_id' => $assignmentModule->id,
            'title' => 'Standing Assignment',
            'description' => 'Upload your standing practice.',
            'sort_order' => 1,
            'status' => Assignment::STATUS_LIVE,
            'is_required' => true,
        ]);

        AssignmentSubmission::query()->create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'assignment_type' => 'standing_assignment',
            'assignment_video' => 'assignments/videos/standing.mp4',
            'assignment_status' => AssignmentSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Modules/Index')
                ->where('modules.0.title', 'Assignment Module')
                ->where('modules.0.status', 'completed')
                ->where('modules.1.title', 'Resource Module')
                ->where('modules.1.status', 'available'));
    }
}
