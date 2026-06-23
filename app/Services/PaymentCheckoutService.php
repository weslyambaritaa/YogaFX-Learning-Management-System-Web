<?php

namespace App\Services;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PaymentCheckoutService
{
    public function __construct(
        private readonly InvoiceNumberService $invoiceNumbers,
        private readonly PayPalService $paypalService,
        private readonly PaymentFinalizerService $paymentFinalizer,
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
     * @return array{invoice: Invoice, payment_activity: Payment, redirect_url: string}
     */
    public function startInitialCheckout(PendingRegistration $pendingRegistration, array $attributes): array
    {
        $this->assertSupportedPaymentMethod($attributes['payment_method']);

        $pendingRegistration->loadMissing('onboardingState');

        if ($pendingRegistration->status === PendingRegistration::STATUS_PAYMENT_SUCCESS && $pendingRegistration->onboardingState) {
            $invoice = $pendingRegistration->invoices()->latest('id')->firstOrFail();
            $paymentActivity = $invoice->paymentActivities()->latest('id')->firstOrFail();

            return [
                'invoice' => $invoice,
                'payment_activity' => $paymentActivity,
                'redirect_url' => $this->paymentSuccessUrl($pendingRegistration->onboardingState),
            ];
        }

        /** @var array{invoice: Invoice, payment_activity: Payment} $created */
        $created = DB::transaction(function () use ($pendingRegistration, $attributes): array {
            $pendingRegistration->loadMissing('accessTier', 'onboardingState.user');
            $accessTier = $pendingRegistration->accessTier()->firstOrFail();

            if ($pendingRegistration->status === PendingRegistration::STATUS_COMPLETED) {
                abort(409, 'This registration flow is already completed.');
            }

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->invoiceNumbers->nextNumber(),
                'pending_registration_id' => $pendingRegistration->id,
                'access_tier_id' => $pendingRegistration->access_tier_id,
                'type' => Invoice::TYPE_INITIAL,
                'payment_type' => $attributes['payment_type'],
                'total_amount' => $accessTier->price,
                'balance_due' => $accessTier->price,
                'currency_code' => $accessTier->currency_code,
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $paymentActivity = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_method' => $attributes['payment_method'],
                'payment_type' => $attributes['payment_type'],
                'amount_paid' => $this->initialPaymentAmount(
                    totalAmount: (float) $accessTier->price,
                    paymentType: $attributes['payment_type'],
                ),
                'currency_code' => $accessTier->currency_code,
                'status' => Payment::STATUS_PENDING,
                'notes' => $attributes['payment_method'] === Payment::METHOD_MOCK
                    ? 'Mock checkout initialized.'
                    : 'PayPal checkout initialized.',
            ]);

            return [
                'invoice' => $invoice,
                'payment_activity' => $paymentActivity,
            ];
        });

        if ($attributes['payment_method'] === Payment::METHOD_MOCK) {
            $finalized = $this->paymentFinalizer->finalizeSuccessfulPayment(
                $created['payment_activity'],
                'MOCK-'.$created['invoice']->id.'-'.$created['payment_activity']->id,
            );

            /** @var OnboardingState $onboardingState */
            $onboardingState = $finalized['onboarding_state'];
            abort_unless($onboardingState !== null, 409, 'Onboarding continuation is not available for this payment.');

            return [
                'invoice' => $finalized['invoice'],
                'payment_activity' => $finalized['payment_activity'],
                'redirect_url' => $this->paymentSuccessUrl($onboardingState),
            ];
        }

        $approval = $this->paypalService->createOrder(
            $created['invoice']->fresh(['pendingRegistration', 'accessTier']),
            $created['payment_activity']->fresh(),
            $this->paypalSuccessUrl($created['invoice']),
            $this->paypalCancelUrl($created['invoice']),
        );

        $created['payment_activity']->forceFill([
            'payment_reference' => $approval['order_id'],
            'notes' => 'PayPal order created and awaiting approval.',
        ])->save();

        return [
            'invoice' => $created['invoice']->fresh(),
            'payment_activity' => $created['payment_activity']->fresh(),
            'redirect_url' => $approval['approval_url'],
        ];
    }

    /**
     * @param  array{payment_type: string, payment_method: string}  $attributes
     * @return array{invoice: Invoice, payment_activity: Payment, redirect_url: string, amount_due: float}
     */
    public function startUpgradeCheckout(User $user, AccessTier $targetTier, array $attributes): array
    {
        $this->assertSupportedPaymentMethod($attributes['payment_method']);

        $amountDue = $this->relevantUpgradeAmountDue($user, $targetTier);
        abort_if($amountDue <= 0, 422, 'No additional upgrade payment is required for this tier.');

        /** @var array{invoice: Invoice, payment_activity: Payment} $created */
        $created = DB::transaction(function () use ($user, $targetTier, $attributes, $amountDue): array {
            $invoice = Invoice::query()->create([
                'invoice_number' => $this->invoiceNumbers->nextNumber(),
                'user_id' => $user->id,
                'access_tier_id' => $targetTier->id,
                'type' => Invoice::TYPE_UPGRADE,
                'payment_type' => $attributes['payment_type'],
                'total_amount' => $amountDue,
                'balance_due' => $amountDue,
                'currency_code' => $targetTier->currency_code,
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $paymentActivity = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_method' => $attributes['payment_method'],
                'payment_type' => $attributes['payment_type'],
                'amount_paid' => $this->initialPaymentAmount(
                    totalAmount: $amountDue,
                    paymentType: $attributes['payment_type'],
                ),
                'currency_code' => $targetTier->currency_code,
                'status' => Payment::STATUS_PENDING,
                'notes' => $attributes['payment_method'] === Payment::METHOD_MOCK
                    ? 'Mock upgrade checkout initialized.'
                    : 'PayPal upgrade checkout initialized.',
            ]);

            return [
                'invoice' => $invoice,
                'payment_activity' => $paymentActivity,
            ];
        });

        if ($attributes['payment_method'] === Payment::METHOD_MOCK) {
            $finalized = $this->paymentFinalizer->finalizeSuccessfulPayment(
                $created['payment_activity'],
                'MOCK-UPGRADE-'.$created['invoice']->id.'-'.$created['payment_activity']->id,
            );

            return [
                'invoice' => $finalized['invoice'],
                'payment_activity' => $finalized['payment_activity'],
                'redirect_url' => $this->upgradePaymentSuccessUrl($finalized['invoice']),
                'amount_due' => $amountDue,
            ];
        }

        $approval = $this->paypalService->createOrder(
            $created['invoice']->fresh(['user', 'accessTier']),
            $created['payment_activity']->fresh(),
            $this->paypalSuccessUrl($created['invoice']),
            $this->paypalCancelUrl($created['invoice']),
        );

        $created['payment_activity']->forceFill([
            'payment_reference' => $approval['order_id'],
            'notes' => 'PayPal upgrade order created and awaiting approval.',
        ])->save();

        return [
            'invoice' => $created['invoice']->fresh(),
            'payment_activity' => $created['payment_activity']->fresh(),
            'redirect_url' => $approval['approval_url'],
            'amount_due' => $amountDue,
        ];
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

    public function checkoutOrderCreateUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.orders.store',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    public function checkoutOrderCaptureUrl(PendingRegistration $pendingRegistration, Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'checkout.orders.capture',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
                'invoice' => $invoice->id,
            ],
        );
    }

    public function checkoutOrderCancelUrl(PendingRegistration $pendingRegistration, Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'checkout.orders.cancel',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
                'invoice' => $invoice->id,
            ],
        );
    }

    public function checkoutStatusUrl(Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'checkout.status',
            now()->addDays(7),
            ['invoice' => $invoice->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutPayload(PendingRegistration $pendingRegistration): array
    {
        $pendingRegistration->loadMissing('accessTier', 'onboardingState');

        return [
            'id' => $pendingRegistration->id,
            'first_name' => $pendingRegistration->first_name,
            'last_name' => $pendingRegistration->last_name,
            'email' => $pendingRegistration->email,
            'phone' => $pendingRegistration->phone,
            'country' => $pendingRegistration->country,
            'amount' => (float) $pendingRegistration->accessTier->price,
            'currency_code' => $pendingRegistration->accessTier->currency_code,
            'status' => $pendingRegistration->status,
            'access_tier' => [
                'id' => $pendingRegistration->accessTier->id,
                'name' => $pendingRegistration->accessTier->name,
                'slug' => $pendingRegistration->accessTier->slug,
                'price' => (float) $pendingRegistration->accessTier->price,
                'currency_code' => $pendingRegistration->accessTier->currency_code,
            ],
            'pay_url' => $this->checkoutPayUrl($pendingRegistration),
            'create_order_url' => $this->checkoutOrderCreateUrl($pendingRegistration),
            'payment_method_options' => $this->availablePaymentMethodOptions(),
        ];
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function completeEnrollment(OnboardingState $onboardingState, array $attributes): OnboardingState
    {
        return DB::transaction(function () use ($onboardingState, $attributes): OnboardingState {
            $onboardingState->loadMissing('user', 'pendingRegistration');

            abort_if($onboardingState->status !== OnboardingState::STATUS_AWAITING_ENROLLMENT, 409, 'Enrollment is no longer available for this onboarding flow.');

            $user = $onboardingState->user;
            abort_unless($user instanceof User, 404);

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
            abort_unless($user instanceof User, 404);

            $user->forceFill([
                'is_active' => true,
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

    public function paypalSuccessUrl(Invoice $invoice): string
    {
        return route('paypal.success', ['invoice' => $invoice]);
    }

    public function paypalCancelUrl(Invoice $invoice): string
    {
        return route('paypal.cancel', ['invoice' => $invoice]);
    }

    public function upgradePaymentSuccessUrl(Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'student.upgrades.success',
            now()->addDays(7),
            ['invoice' => $invoice->id],
        );
    }

    public function relevantUpgradePaidAmount(User $user, AccessTier $targetTier): float
    {
        $basisInvoice = $this->relevantUpgradeBasisInvoice($user, $targetTier);

        if (! $basisInvoice) {
            return 0.0;
        }

        return (float) $basisInvoice->paymentActivities()
            ->where('status', Payment::STATUS_SUCCESS)
            ->sum('amount_paid');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function availablePaymentMethodOptions(): array
    {
        $options = [
            [
                'value' => Payment::METHOD_PAYPAL,
                'label' => 'PayPal',
            ],
        ];

        if (! app()->environment('production') && (bool) config('app.enable_mock_payment_ui', false)) {
            $options[] = [
                'value' => Payment::METHOD_MOCK,
                'label' => 'Mock',
            ];
        }

        return $options;
    }

    private function assertSupportedPaymentMethod(string $paymentMethod): void
    {
        if ($paymentMethod === Payment::METHOD_MOCK && app()->environment('production')) {
            abort(422, 'Mock payment is not available in production.');
        }

        if ($paymentMethod === Payment::METHOD_BANK_TRANSFER) {
            abort(422, 'Bank transfer is not available in the PayPal payment architecture phase.');
        }
    }

    private function initialPaymentAmount(float $totalAmount, string $paymentType): float
    {
        if ($paymentType === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return round($totalAmount / 4, 2);
        }

        return round($totalAmount, 2);
    }

    private function relevantUpgradeAmountDue(User $user, AccessTier $targetTier): float
    {
        $currentTier = $user->accessTier;

        abort_if(! $targetTier->is_active, 422, 'This upgrade target is not active.');
        abort_if(! $currentTier || ! $currentTier->is_active, 422, 'Your current tier is not available for upgrade.');
        abort_if($targetTier->level <= $currentTier->level, 422, 'Only higher tiers can be selected for upgrade.');

        return max(0, round((float) $targetTier->price - $this->relevantUpgradePaidAmount($user, $targetTier), 2));
    }

    private function relevantUpgradeBasisInvoice(User $user, AccessTier $targetTier): ?Invoice
    {
        $currentTier = $user->accessTier;

        $query = Invoice::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Invoice::STATUS_PAID_FULL,
                Invoice::STATUS_INSTALLMENT,
            ]);

        if ($currentTier) {
            $query->where('access_tier_id', $currentTier->id);
        } else {
            $query->whereHas('accessTier', fn ($tierQuery) => $tierQuery->where('level', '<', $targetTier->level));
        }

        return $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();
    }
}
