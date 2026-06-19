<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Jobs\SendOnboardingContinuationEmailJob;
use App\Jobs\SendUpgradeWelcomeEmailJob;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        Queue::fake();

        $result = app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_method' => Payment::METHOD_MOCK,
        ]);

        /** @var Invoice $invoice */
        $invoice = $result['invoice'];
        /** @var Payment $payment */
        $payment = $result['payment_activity'];

        $this->assertSame(Invoice::TYPE_INITIAL, $invoice->type);
        $this->assertSame(AccessTier::CURRENCY_GBP, $invoice->currency_code);
        $this->assertSame('499.00', $invoice->total_amount);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(AccessTier::CURRENCY_GBP, $payment->currency_code);
        $this->assertSame(Payment::METHOD_MOCK, $payment->payment_method);
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

        $this->assertDatabaseHas('payment_activities', [
            'id' => $payment->id,
            'invoice_id' => $invoice->id,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'payment_method' => Payment::METHOD_MOCK,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'status' => Payment::STATUS_SUCCESS,
        ]);

        Queue::assertPushed(SendOnboardingContinuationEmailJob::class);
    }

    public function test_mock_payment_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $tier = AccessTier::factory()->create([
            'price' => 249.00,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Nina',
            'last_name' => 'Hart',
            'email' => 'nina@example.com',
            'phone' => '+6281234567000',
            'country' => 'Indonesia',
            'amount_snapshot' => 249.00,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        try {
            app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
                'payment_type' => Payment::TYPE_PAY_FULL,
                'payment_method' => Payment::METHOD_MOCK,
            ]);

            $this->fail('Mock payment should be blocked in production.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_upgrade_payment_marks_previous_invoice_upgraded_and_dispatches_upgrade_email(): void
    {
        $starterTier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'name' => 'Starter Kit',
            'level' => 1,
            'price' => 200.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $masterTier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'name' => 'Master Class',
            'level' => 3,
            'price' => 500.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $student = User::factory()->student()->create([
            'access_tier_id' => $starterTier->id,
            'is_active' => true,
        ]);

        $initialInvoice = Invoice::query()->create([
            'invoice_number' => 'INV-TEST-INITIAL-001',
            'user_id' => $student->id,
            'access_tier_id' => $starterTier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'total_amount' => 200.00,
            'balance_due' => 0.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Invoice::STATUS_PAID_FULL,
            'issued_at' => now()->subDays(2),
            'paid_at' => now()->subDay(),
        ]);

        Payment::query()->create([
            'invoice_id' => $initialInvoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_reference' => 'PAYPAL-INITIAL-001',
            'amount_paid' => 200.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Payment::STATUS_SUCCESS,
        ]);

        Queue::fake();

        $result = app(PaymentCheckoutService::class)->startUpgradeCheckout($student->fresh(['accessTier']), $masterTier, [
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_method' => Payment::METHOD_MOCK,
        ]);

        /** @var Invoice $upgradeInvoice */
        $upgradeInvoice = $result['invoice'];
        /** @var Payment $upgradePayment */
        $upgradePayment = $result['payment_activity'];

        $this->assertSame(Invoice::TYPE_UPGRADE, $upgradeInvoice->type);
        $this->assertSame('300.00', $upgradeInvoice->total_amount);
        $this->assertSame(Invoice::STATUS_PAID_FULL, $upgradeInvoice->status);
        $this->assertSame(Payment::STATUS_SUCCESS, $upgradePayment->status);

        $this->assertDatabaseHas('invoices', [
            'id' => $initialInvoice->id,
            'status' => Invoice::STATUS_UPGRADED,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $upgradeInvoice->id,
            'user_id' => $student->id,
            'access_tier_id' => $masterTier->id,
            'type' => Invoice::TYPE_UPGRADE,
            'status' => Invoice::STATUS_PAID_FULL,
            'total_amount' => 300.00,
            'balance_due' => 0.00,
        ]);

        $this->assertDatabaseHas('payment_activities', [
            'id' => $upgradePayment->id,
            'invoice_id' => $upgradeInvoice->id,
            'payment_method' => Payment::METHOD_MOCK,
            'status' => Payment::STATUS_SUCCESS,
            'amount_paid' => 300.00,
        ]);

        $this->assertSame($masterTier->id, $student->fresh()->access_tier_id);
        Queue::assertPushed(SendUpgradeWelcomeEmailJob::class, 1);
    }

    public function test_assignment_module_requires_approval_before_next_module_unlocks(): void
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
                ->where('modules.0.status', 'available')
                ->where('modules.1.title', 'Resource Module')
                ->where('modules.1.status', 'locked'));

        AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->where('assignment_id', $assignment->id)
            ->update([
                'assignment_status' => AssignmentSubmission::STATUS_APPROVED,
                'reviewed_at' => now(),
                'graded_at' => now(),
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

    public function test_certificate_unlock_requires_assignment_approval(): void
    {
        config()->set('certificates.tiers.online', ['bikram_yoga_certificate']);

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $assignmentModule = Module::factory()->create();
        $assignmentModule->accessTiers()->attach($tier);

        $assignment = Assignment::query()->create([
            'module_id' => $assignmentModule->id,
            'title' => 'Final Standing Assignment',
            'description' => 'Upload your standing assignment.',
            'sort_order' => 1,
            'status' => Assignment::STATUS_LIVE,
            'is_required' => true,
        ]);

        AssignmentSubmission::query()->create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'assignment_type' => 'final_standing_assignment',
            'assignment_video' => 'assignments/videos/final-standing.mp4',
            'assignment_status' => AssignmentSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $summaryBeforeApproval = app(CertificateEligibilityService::class)->summaryForStudent($student);
        $this->assertFalse($summaryBeforeApproval['learning_eligible']);

        AssignmentSubmission::query()->where('user_id', $student->id)->update([
            'assignment_status' => AssignmentSubmission::STATUS_APPROVED,
            'reviewed_at' => now(),
            'graded_at' => now(),
        ]);

        $summaryAfterApproval = app(CertificateEligibilityService::class)->summaryForStudent($student->fresh());
        $this->assertTrue($summaryAfterApproval['learning_eligible']);
    }

    public function test_student_can_open_video_lecturer_in_dedicated_player_page(): void
    {
        $tier = AccessTier::factory()->create();
        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $course = Course::factory()->create([
            'title' => 'Lecturer Focus Session',
            'url_slug' => 'lecturer-focus-session',
            'access_tier_id' => $tier->id,
            'video' => 'not-a-valid-bunny-id',
        ]);
        $course->accessTiers()->attach($tier);

        $this->actingAs($student)
            ->get(route('courses.show', $course->url_slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Courses/Show')
                ->where('course.title', 'Lecturer Focus Session')
                ->where('course.url_slug', 'lecturer-focus-session'));
    }
}
