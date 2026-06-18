<?php

namespace App\Services;

use App\Mail\TemplatedNotificationMail;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class SimulatedPaymentFlowService
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPendingRegistration(array $attributes): PendingRegistration
    {
        /** @var AccessTier $accessTier */
        $accessTier = AccessTier::query()->findOrFail($attributes['access_tier_id']);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $accessTier->id,
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'],
            'email' => Str::lower((string) $attributes['email']),
            'phone' => $attributes['phone'],
            'country' => $attributes['country'],
            'amount_snapshot' => $accessTier->price,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $pendingRegistration->setRelation('accessTier', $accessTier);
        $this->sendCheckoutLinkEmail($pendingRegistration);

        return $pendingRegistration;
    }

    public function markCheckoutOpened(PendingRegistration $pendingRegistration): PendingRegistration
    {
        if ($pendingRegistration->status === PendingRegistration::STATUS_CREATED) {
            $pendingRegistration->forceFill([
                'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
                'checkout_opened_at' => $pendingRegistration->checkout_opened_at ?? now(),
            ])->save();
        }

        return $pendingRegistration->fresh(['accessTier', 'onboardingState']);
    }

    /**
     * @param  array{payment_type: string, payment_method: string}  $attributes
     * @return array{invoice: Invoice, payment: Payment, onboarding_state: OnboardingState}
     */
    public function processInitialPayment(PendingRegistration $pendingRegistration, array $attributes): array
    {
        return DB::transaction(function () use ($pendingRegistration, $attributes): array {
            $pendingRegistration->loadMissing('accessTier', 'onboardingState.user');
            $accessTier = $pendingRegistration->accessTier()->firstOrFail();
            $currentTierAmount = (float) $accessTier->price;
            $currencyCode = $accessTier->currency_code;

            if ($pendingRegistration->status === PendingRegistration::STATUS_COMPLETED) {
                abort(409, 'This registration flow is already completed.');
            }

            if ($pendingRegistration->status === PendingRegistration::STATUS_PAYMENT_SUCCESS && $pendingRegistration->onboardingState) {
                $invoice = $pendingRegistration->invoices()->latest('id')->firstOrFail();

                return [
                    'invoice' => $invoice,
                    'payment' => $invoice->payments()->latest('id')->firstOrFail(),
                    'onboarding_state' => $pendingRegistration->onboardingState,
                ];
            }

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'pending_registration_id' => $pendingRegistration->id,
                'access_tier_id' => $pendingRegistration->access_tier_id,
                'type' => Invoice::TYPE_INITIAL,
                'payment_type' => $attributes['payment_type'],
                'total_amount' => $currentTierAmount,
                'balance_due' => $currentTierAmount,
                'currency_code' => $currencyCode,
                'status' => Invoice::STATUS_PENDING,
                'issued_at' => now(),
            ]);

            $paymentAmount = $this->initialPaymentAmount(
                totalAmount: $currentTierAmount,
                paymentType: $attributes['payment_type'],
            );

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_reference' => $this->nextPaymentReference(),
                'payment_type' => $attributes['payment_type'],
                'payment_method' => $attributes['payment_method'],
                'amount_paid' => $paymentAmount,
                'currency_code' => $currencyCode,
                'status' => Payment::STATUS_PENDING,
                'notes' => 'Simulated payment flow.',
            ]);

            $payment->forceFill([
                'status' => Payment::STATUS_SUCCESS,
                'notes' => 'Simulated payment flow succeeded.',
            ])->save();

            $remainingBalance = max(0, round($currentTierAmount - $paymentAmount, 2));

            $invoice->forceFill([
                'balance_due' => $remainingBalance,
                'status' => $remainingBalance <= 0
                    ? Invoice::STATUS_PAID_FULL
                    : Invoice::STATUS_INSTALLMENT,
                'paid_at' => now(),
            ])->save();

            $user = User::query()->create([
                'name' => $pendingRegistration->fullName(),
                'role' => User::ROLE_STUDENT,
                'is_active' => true,
                'access_tier_id' => $pendingRegistration->access_tier_id,
                'email' => $pendingRegistration->email,
                'password' => Hash::make(Str::random(40)),
                'first_name' => $pendingRegistration->first_name,
                'last_name' => $pendingRegistration->last_name,
                'whatsapp' => $pendingRegistration->phone,
                'country' => $pendingRegistration->country,
            ]);

            $invoice->forceFill([
                'user_id' => $user->id,
            ])->save();

            $onboardingState = OnboardingState::query()->create([
                'pending_registration_id' => $pendingRegistration->id,
                'user_id' => $user->id,
                'status' => OnboardingState::STATUS_AWAITING_ENROLLMENT,
                'continuation_sent_at' => now(),
            ]);

            $pendingRegistration->forceFill([
                'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
                'payment_succeeded_at' => now(),
            ])->save();

            $this->sendOnboardingContinuation($user, $onboardingState);

            return [
                'invoice' => $invoice->fresh(),
                'payment' => $payment->fresh(),
                'onboarding_state' => $onboardingState->fresh(['user', 'pendingRegistration.accessTier']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function completeEnrollment(OnboardingState $onboardingState, array $attributes): OnboardingState
    {
        return DB::transaction(function () use ($onboardingState, $attributes): OnboardingState {
            $onboardingState->loadMissing('user', 'pendingRegistration');

            abort_if($onboardingState->status !== OnboardingState::STATUS_AWAITING_ENROLLMENT, 409, 'Enrollment is no longer available for this onboarding flow.');

            $user = $onboardingState->user;
            $user->fill($attributes);
            $user->syncDisplayName();
            $user->save();

            $onboardingState->forceFill([
                'status' => OnboardingState::STATUS_AWAITING_SIGNUP,
                'enrollment_completed_at' => now(),
            ])->save();

            return $onboardingState->fresh(['user', 'pendingRegistration.accessTier']);
        });
    }

    public function completeSignup(OnboardingState $onboardingState, string $password): User
    {
        return DB::transaction(function () use ($onboardingState, $password): User {
            $onboardingState->loadMissing('user', 'pendingRegistration');

            abort_if($onboardingState->status !== OnboardingState::STATUS_AWAITING_SIGNUP, 409, 'Password creation is not available for this onboarding flow.');

            $user = $onboardingState->user;
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $onboardingState->forceFill([
                'status' => OnboardingState::STATUS_COMPLETED,
                'signup_completed_at' => now(),
            ])->save();

            $onboardingState->pendingRegistration->forceFill([
                'status' => PendingRegistration::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            return $user->fresh(['accessTier']);
        });
    }

    /**
     * @param  array{payment_type: string, payment_method: string}  $attributes
     * @return array{invoice: Invoice, payment: Payment, amount_due: float}
     */
    public function processUpgrade(User $user, AccessTier $targetTier, array $attributes): array
    {
        return DB::transaction(function () use ($user, $targetTier, $attributes): array {
            $currentPrice = (float) ($user->accessTier?->price ?? 0);
            $targetPrice = (float) $targetTier->price;

            abort_if($targetPrice <= $currentPrice, 422, 'Only higher tiers can be selected for upgrade.');

            $totalPaid = (float) Payment::query()
                ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
                ->where('status', Payment::STATUS_SUCCESS)
                ->sum('amount_paid');

            $amountDue = max(0, round($targetPrice - $totalPaid, 2));
            abort_if($amountDue <= 0, 422, 'No additional upgrade payment is required for this tier.');

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'user_id' => $user->id,
                'access_tier_id' => $targetTier->id,
                'type' => Invoice::TYPE_UPGRADE,
                'payment_type' => $attributes['payment_type'],
                'total_amount' => $amountDue,
                'balance_due' => $amountDue,
                'currency_code' => $targetTier->currency_code,
                'status' => Invoice::STATUS_PENDING,
                'issued_at' => now(),
            ]);

            $paymentAmount = $this->initialPaymentAmount(
                totalAmount: $amountDue,
                paymentType: $attributes['payment_type'],
            );

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_reference' => $this->nextPaymentReference(),
                'payment_type' => $attributes['payment_type'],
                'payment_method' => $attributes['payment_method'],
                'amount_paid' => $paymentAmount,
                'currency_code' => $targetTier->currency_code,
                'status' => Payment::STATUS_PENDING,
                'notes' => 'Simulated upgrade payment.',
            ]);

            $payment->forceFill([
                'status' => Payment::STATUS_SUCCESS,
                'notes' => 'Simulated upgrade payment succeeded.',
            ])->save();

            $remainingBalance = max(0, round($amountDue - $paymentAmount, 2));

            $invoice->forceFill([
                'balance_due' => $remainingBalance,
                'status' => $remainingBalance <= 0
                    ? Invoice::STATUS_PAID_FULL
                    : Invoice::STATUS_INSTALLMENT,
                'paid_at' => now(),
            ])->save();

            $user->forceFill([
                'access_tier_id' => $targetTier->id,
            ])->save();

            return [
                'invoice' => $invoice->fresh(),
                'payment' => $payment->fresh(),
                'amount_due' => $amountDue,
            ];
        });
    }

    public function checkoutUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.show',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    public function checkoutPayUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.pay',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    public function enrollmentUrl(OnboardingState $onboardingState): string
    {
        return URL::temporarySignedRoute(
            'onboarding.enrollment.show',
            now()->addDays(7),
            ['onboardingState' => $onboardingState->id],
        );
    }

    public function paymentSuccessUrl(OnboardingState $onboardingState): string
    {
        return URL::temporarySignedRoute(
            'onboarding.payment-success.show',
            now()->addDays(7),
            ['onboardingState' => $onboardingState->id],
        );
    }

    public function enrollmentSubmitUrl(OnboardingState $onboardingState): string
    {
        return URL::temporarySignedRoute(
            'onboarding.enrollment.store',
            now()->addDays(7),
            ['onboardingState' => $onboardingState->id],
        );
    }

    public function signupUrl(OnboardingState $onboardingState): string
    {
        return URL::temporarySignedRoute(
            'onboarding.signup.show',
            now()->addDays(7),
            ['onboardingState' => $onboardingState->id],
        );
    }

    public function signupSubmitUrl(OnboardingState $onboardingState): string
    {
        return URL::temporarySignedRoute(
            'onboarding.signup.store',
            now()->addDays(7),
            ['onboardingState' => $onboardingState->id],
        );
    }

    private function initialPaymentAmount(float $totalAmount, string $paymentType): float
    {
        if ($paymentType === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return round($totalAmount / 4, 2);
        }

        return round($totalAmount, 2);
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    private function nextPaymentReference(): string
    {
        return 'PAY-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    private function sendOnboardingContinuation(User $user, OnboardingState $onboardingState): void
    {
        $this->emailNotificationService->sendAutomated(
            EmailNotificationTypeRegistry::SIGNUP,
            [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'admin_email' => config('mail.from.address'),
                'access_tier' => $user->accessTier?->slug,
                'access_tier_label' => $user->accessTier?->name,
                'registration_date' => optional($user->created_at)->toDateString() ?? now()->toDateString(),
                'dashboard_url' => route('login'),
                'login_url' => route('login'),
                'continuation_url' => $this->enrollmentUrl($onboardingState),
            ],
            'onboarding_state',
            $onboardingState->id,
        );
    }

    private function sendCheckoutLinkEmail(PendingRegistration $pendingRegistration): void
    {
        $checkoutUrl = $this->checkoutUrl($pendingRegistration);
        $subject = 'Your YogaFX checkout link is ready';
        $body = implode('', [
            '<p>Hi '.e($pendingRegistration->fullName()).',</p>',
            '<p>Thank you for starting your YogaFX journey.</p>',
            '<p>Your selected tier: <strong>'.e($pendingRegistration->accessTier->name).'</strong></p>',
            '<p>Your current amount: <strong>'.e($pendingRegistration->accessTier->currency_code.' '.number_format((float) $pendingRegistration->accessTier->price, 2)).'</strong></p>',
            '<p>Continue to your signed checkout here: <a href="'.e($checkoutUrl).'">'.e($checkoutUrl).'</a></p>',
        ]);

        try {
            Mail::to($pendingRegistration->email)->send(
                new TemplatedNotificationMail($subject, $body, 'Checkout Link'),
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
