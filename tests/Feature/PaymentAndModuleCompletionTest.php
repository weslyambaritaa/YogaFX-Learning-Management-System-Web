<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Jobs\SendOnboardingContinuationEmailJob;
use App\Jobs\SendUpgradeWelcomeEmailJob;
use App\Services\PayPalService;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
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

        $this->assertDatabaseHas('users', [
            'email' => 'ava@example.com',
            'is_active' => false,
        ]);

        Queue::assertPushed(SendOnboardingContinuationEmailJob::class);
    }

    public function test_public_checkout_page_includes_paypal_client_configuration_for_onsite_components(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'price' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Sora',
            'last_name' => 'Blake',
            'email' => 'sora@example.com',
            'phone' => '+6281234567111',
            'country' => 'Indonesia',
            'amount_snapshot' => 499.00,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('generateClientToken')
                ->once()
                ->andReturn('PAYPAL-CLIENT-TOKEN-001');
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-001');
        });

        $this->get(
            URL::temporarySignedRoute('checkout.show', now()->addDay(), [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $tier->slug,
            ]),
        )
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Checkout')
                ->where('checkout.paypal.client_id', 'PAYPAL-CLIENT-ID-001')
                ->where('checkout.paypal.client_token', 'PAYPAL-CLIENT-TOKEN-001')
                ->where('checkout.access_tier.slug', $tier->slug));
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

    public function test_public_checkout_creates_paypal_order_for_onsite_flow_without_redirecting_the_browser(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'price' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Maya',
            'last_name' => 'Cole',
            'email' => 'maya@example.com',
            'phone' => '+6281234567888',
            'country' => 'Indonesia',
            'amount_snapshot' => 499.00,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')
                ->once()
                ->andReturn([
                    'order_id' => 'PAYPAL-ONSITE-ORDER-001',
                    'approval_url' => 'https://www.paypal.com/checkoutnow?token=PAYPAL-ONSITE-ORDER-001',
                ]);
            $mock->shouldReceive('clientId')->andReturn('test-client-id');
        });

        $response = $this->postJson(
            URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $tier->slug,
            ]),
            [
                'payment_type' => Payment::TYPE_PAY_FULL,
                'payment_method' => Payment::METHOD_PAYPAL,
                'checkout_mode' => 'card',
                'first_name' => 'Maya',
                'last_name' => 'Cole',
                'billing_postcode' => '90123',
                'billing_country' => 'Indonesia',
                'terms_accepted' => true,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('order_id', 'PAYPAL-ONSITE-ORDER-001')
            ->assertJsonStructure([
                'capture_url',
                'cancel_url',
                'invoice_id',
            ]);

        $this->assertDatabaseHas('payment_activities', [
            'payment_reference' => 'PAYPAL-ONSITE-ORDER-001',
            'payment_method' => Payment::METHOD_PAYPAL,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    public function test_public_paypal_create_order_only_requires_terms_from_checkout_request(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'price' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Mika',
            'last_name' => 'Dunn',
            'email' => 'mika@example.com',
            'phone' => '+6281234567222',
            'country' => 'Indonesia',
            'amount_snapshot' => 499.00,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')
                ->once()
                ->andReturn([
                    'order_id' => 'PAYPAL-ONSITE-ORDER-TERMS-ONLY-001',
                    'approval_url' => 'https://www.paypal.com/checkoutnow?token=PAYPAL-ONSITE-ORDER-TERMS-ONLY-001',
                ]);
            $mock->shouldReceive('clientId')->andReturn('test-client-id');
        });

        $this->postJson(
            URL::temporarySignedRoute('checkout.orders.store', now()->addDay(), [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $tier->slug,
            ]),
            [
                'payment_type' => Payment::TYPE_PAY_FULL,
                'payment_method' => Payment::METHOD_PAYPAL,
                'checkout_mode' => 'paypal',
                'terms_accepted' => true,
            ],
        )
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('order_id', 'PAYPAL-ONSITE-ORDER-TERMS-ONLY-001');
    }

    public function test_public_checkout_capture_success_marks_invoice_paid_and_returns_success_redirect(): void
    {
        Queue::fake();

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'price' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Luna',
            'last_name' => 'Reed',
            'email' => 'luna@example.com',
            'phone' => '+6281234567666',
            'country' => 'Indonesia',
            'amount_snapshot' => 499.00,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ONSITE-0001',
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'total_amount' => 499.00,
            'balance_due' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_reference' => 'PAYPAL-CAPTURE-SUCCESS-001',
            'amount_paid' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with('PAYPAL-CAPTURE-SUCCESS-001')
                ->andReturn([
                    'status' => 'COMPLETED',
                ]);
            $mock->shouldReceive('clientId')->andReturn('test-client-id');
        });

        $response = $this->postJson(
            URL::temporarySignedRoute('checkout.orders.capture', now()->addDay(), [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $tier->slug,
                'invoice' => $invoice->id,
            ]),
            [
                'order_id' => 'PAYPAL-CAPTURE-SUCCESS-001',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PAID_FULL,
            'balance_due' => 0,
        ]);

        $this->assertDatabaseHas('payment_activities', [
            'invoice_id' => $invoice->id,
            'payment_reference' => 'PAYPAL-CAPTURE-SUCCESS-001',
            'status' => Payment::STATUS_SUCCESS,
        ]);

        Queue::assertPushed(SendOnboardingContinuationEmailJob::class);
    }

    public function test_signup_completion_activates_student_account_and_completes_onboarding(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'name' => 'Online',
        ]);

        $user = User::factory()->student()->create([
            'first_name' => 'Signup',
            'last_name' => 'Student',
            'email' => 'signup@example.com',
            'is_active' => false,
            'access_tier_id' => $tier->id,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => '+6281234567999',
            'country' => 'Indonesia',
            'amount_snapshot' => 499.00,
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
            'payment_succeeded_at' => now(),
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_SIGNUP,
            'enrollment_completed_at' => now(),
        ]);

        $response = $this->post(
            URL::temporarySignedRoute('onboarding.signup.store', now()->addDay(), [
                'onboardingState' => $onboardingState->id,
            ]),
            [
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ],
        );

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your YogaFX account is now active. Please sign in with your new password.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('onboarding_states', [
            'id' => $onboardingState->id,
            'status' => OnboardingState::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_COMPLETED,
        ]);
    }

    public function test_public_checkout_capture_pending_keeps_payment_pending_and_returns_pending_status_page(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'price' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $tier->id,
            'first_name' => 'Ari',
            'last_name' => 'Snow',
            'email' => 'ari@example.com',
            'phone' => '+6281234567555',
            'country' => 'Indonesia',
            'amount_snapshot' => 499.00,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ONSITE-0002',
            'pending_registration_id' => $pendingRegistration->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'total_amount' => 499.00,
            'balance_due' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'payment_reference' => 'PAYPAL-CAPTURE-PENDING-001',
            'amount_paid' => 499.00,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with('PAYPAL-CAPTURE-PENDING-001')
                ->andReturn([
                    'status' => 'PENDING',
                ]);
            $mock->shouldReceive('clientId')->andReturn('test-client-id');
        });

        $response = $this->postJson(
            URL::temporarySignedRoute('checkout.orders.capture', now()->addDay(), [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $tier->slug,
                'invoice' => $invoice->id,
            ]),
            [
                'order_id' => 'PAYPAL-CAPTURE-PENDING-001',
            ],
        );

        $response
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('redirect_url', URL::temporarySignedRoute('checkout.status', now()->addDays(7), [
                'invoice' => $invoice->id,
            ]));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_UNPAID,
            'balance_due' => 499,
        ]);

        $this->assertDatabaseHas('payment_activities', [
            'invoice_id' => $invoice->id,
            'payment_reference' => 'PAYPAL-CAPTURE-PENDING-001',
            'status' => Payment::STATUS_PENDING,
        ]);
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
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'name' => 'Online',
        ]);
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

    public function test_starter_kit_skips_assignment_only_module_for_progression_and_certificate_unlock(): void
    {
        config()->set('certificates.tiers.starter_kit', ['bikram_yoga_certificate']);

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'name' => 'Starter Kit',
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $lessonModule = Module::factory()->create([
            'title' => 'Starter Lesson Module',
            'url_slug' => 'starter-lesson-module',
            'sort_order' => 1,
        ]);
        $lessonModule->accessTiers()->attach($tier);

        $lesson = \App\Models\Lesson::factory()->create([
            'module_id' => $lessonModule->id,
            'title' => 'Starter Lesson',
        ]);
        $lesson->accessTiers()->attach($tier);

        \App\Models\LessonProgress::query()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => now(),
            'video_completed_at' => now(),
        ]);

        $assignmentModule = Module::factory()->create([
            'title' => 'Starter Assignment Module',
            'url_slug' => 'starter-assignment-module',
            'sort_order' => 2,
        ]);
        $assignmentModule->accessTiers()->attach($tier);

        Assignment::query()->create([
            'module_id' => $assignmentModule->id,
            'title' => 'Starter Assignment',
            'description' => 'Should be skipped for starter path.',
            'sort_order' => 1,
            'status' => Assignment::STATUS_LIVE,
            'is_required' => true,
        ]);

        $certificateModule = Module::factory()->create([
            'title' => 'Starter Certificate Module',
            'url_slug' => 'starter-certificate-module',
            'sort_order' => 3,
            'certificate_enabled' => true,
        ]);
        $certificateModule->accessTiers()->attach($tier);

        $this->actingAs($student)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Modules/Index')
                ->has('modules', 2)
                ->where('modules.0.title', 'Starter Lesson Module')
                ->where('modules.0.status', 'completed')
                ->where('modules.1.title', 'Starter Certificate Module')
                ->where('modules.1.status', 'available'));

        $this->actingAs($student)
            ->get(route('modules.show', $certificateModule->url_slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Certificates/Show')
                ->where('module.title', 'Starter Certificate Module')
                ->where('certificate.state', 'ready'));

        $summary = app(CertificateEligibilityService::class)->summaryForStudent($student->fresh());

        $this->assertTrue($summary['learning_eligible']);
    }

    public function test_master_class_skips_assignment_only_module_when_certificate_is_next_accessible_module(): void
    {
        config()->set('certificates.tiers.master_class', ['bikram_yoga_certificate']);

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'name' => 'Master Class',
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $assignmentModule = Module::factory()->create([
            'title' => 'Master Assignment Module',
            'url_slug' => 'master-assignment-module',
            'sort_order' => 1,
        ]);
        $assignmentModule->accessTiers()->attach($tier);

        Assignment::query()->create([
            'module_id' => $assignmentModule->id,
            'title' => 'Master Assignment',
            'description' => 'Should be skipped for master class path.',
            'sort_order' => 1,
            'status' => Assignment::STATUS_LIVE,
            'is_required' => true,
        ]);

        $certificateModule = Module::factory()->create([
            'title' => 'Master Certificate Module',
            'url_slug' => 'master-certificate-module',
            'sort_order' => 2,
            'certificate_enabled' => true,
        ]);
        $certificateModule->accessTiers()->attach($tier);

        $this->actingAs($student)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Modules/Index')
                ->has('modules', 1)
                ->where('modules.0.title', 'Master Certificate Module')
                ->where('modules.0.status', 'available'));

        $this->actingAs($student)
            ->get(route('modules.show', $certificateModule->url_slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Certificates/Show')
                ->where('module.title', 'Master Certificate Module')
                ->where('certificate.state', 'ready'));

        $summary = app(CertificateEligibilityService::class)->summaryForStudent($student->fresh());

        $this->assertTrue($summary['learning_eligible']);
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
