<?php

namespace App\Services;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Installments\InstallmentPlanCalculator;
use App\Services\Payments\PaymentSubscriptionService;
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
        private readonly InstallmentPlanCalculator $installmentPlanCalculator,
        private readonly PaymentSubscriptionService $paymentSubscriptionService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPendingRegistration(array $attributes): PendingRegistration
    {
        /** @var Package $package */
        $package = Package::query()
            ->with('accessTier')
            ->findOrFail($attributes['package_id']);
        $accessTier = $package->accessTier;
        abort_unless($accessTier instanceof AccessTier, 422, 'This package is currently unavailable for checkout.');

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $accessTier->id,
            'package_id' => $package->id,
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'],
            'email' => Str::lower((string) $attributes['email']),
            'phone' => $attributes['phone'],
            'country' => $attributes['country'],
            'amount_snapshot' => $package->price,
            'currency_code' => $package->currency_code,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $pendingRegistration->setRelation('accessTier', $accessTier);
        $pendingRegistration->setRelation('package', $package);

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

        return $pendingRegistration->fresh(['accessTier', 'package', 'onboardingState']);
    }

    /**
     * @param  array{payment_type: string, payment_method: string, billing_day?: int|null}  $attributes
     * @return array<string, mixed>
     */
    public function startInitialCheckout(PendingRegistration $pendingRegistration, array $attributes): array
    {
        $this->assertSupportedPaymentMethod($attributes['payment_method']);
        $pendingRegistration->loadMissing('package', 'accessTier');
        $normalizedBillingDay = $this->normalizeCheckoutBillingDay(
            $pendingRegistration->package,
            isset($attributes['billing_day']) ? (int) $attributes['billing_day'] : null,
        );
        $this->assertInitialCheckoutPaymentTypeSupported(
            $pendingRegistration,
            $attributes['payment_type'],
            $attributes['payment_method'],
            $normalizedBillingDay,
        );

        if ($attributes['payment_type'] === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            /** @var Package $package */
            $package = $pendingRegistration->package;

            return $this->paymentSubscriptionService->startInitialCheckout($pendingRegistration, [
                'return_url' => $this->checkoutSubscriptionReturnUrl($pendingRegistration),
                'cancel_url' => $this->checkoutSubscriptionCancelUrl($pendingRegistration),
            ], $normalizedBillingDay);
        }

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
            $pendingRegistration->loadMissing('accessTier', 'package', 'onboardingState.user');
            $accessTier = $pendingRegistration->accessTier()->firstOrFail();
            $package = $pendingRegistration->package;
            $amount = (float) ($package?->price ?? $accessTier->price);
            $currencyCode = (string) ($package?->currency_code ?? $accessTier->currency_code);

            if ($pendingRegistration->status === PendingRegistration::STATUS_COMPLETED) {
                abort(409, 'This registration flow is already completed.');
            }

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->invoiceNumbers->nextNumber(),
                'pending_registration_id' => $pendingRegistration->id,
                'package_id' => $package?->id,
                'access_tier_id' => $pendingRegistration->access_tier_id,
                'type' => Invoice::TYPE_INITIAL,
                'payment_type' => $attributes['payment_type'],
                'total_amount' => $amount,
                'balance_due' => $amount,
                'currency_code' => $currencyCode,
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $paymentActivity = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_method' => $attributes['payment_method'],
                'payment_type' => $attributes['payment_type'],
                'amount_paid' => $this->initialPaymentAmount(
                    totalAmount: $amount,
                    paymentType: $attributes['payment_type'],
                ),
                'currency_code' => $currencyCode,
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
            $created['invoice']->fresh(['pendingRegistration', 'package', 'accessTier']),
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
        $this->assertUpgradePaymentTypeSupported($attributes['payment_type']);

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

    public function checkoutSubscriptionReturnUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.installments.return',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    public function checkoutSubscriptionCancelUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.installments.cancel',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    public function checkoutSubscriptionApproveUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.installments.approve',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    public function checkoutSubscriptionStatusUrl(PendingRegistration $pendingRegistration): string
    {
        return URL::temporarySignedRoute(
            'checkout.installments.status',
            now()->addDays(7),
            [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => $pendingRegistration->accessTier->slug,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutPayload(PendingRegistration $pendingRegistration): array
    {
        $pendingRegistration->loadMissing('accessTier', 'package', 'onboardingState');
        $package = $pendingRegistration->package;
        $amount = (float) ($package?->price ?? $pendingRegistration->accessTier->price);
        $currencyCode = (string) ($package?->currency_code ?? $pendingRegistration->accessTier->currency_code);
        $installmentData = $this->availableInstallmentData($pendingRegistration);
        $installmentSummary = $installmentData['selected_summary'];
        $checkoutBillingDayOptions = $package?->checkoutBillingDayOptions() ?? [];
        $checkoutRequiresBillingDayChoice = $package?->checkoutRequiresBillingDayChoice() ?? false;
        $checkoutAcceptsBillingDay = $package?->checkoutAcceptsBillingDay() ?? false;

        return [
            'id' => $pendingRegistration->id,
            'first_name' => $pendingRegistration->first_name,
            'last_name' => $pendingRegistration->last_name,
            'email' => $pendingRegistration->email,
            'phone' => $pendingRegistration->phone,
            'country' => $pendingRegistration->country,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'status' => $pendingRegistration->status,
            'package' => $package ? [
                'id' => $package->id,
                'title' => $package->title,
                'slug' => $package->slug,
                'description' => $package->description,
                'image_url' => null,
                'price' => (float) $package->price,
                'currency_code' => $package->currency_code,
                'installment_enabled' => (bool) $package->installment_enabled,
            ] : null,
            'installment_summary' => $installmentSummary,
            'installment_summaries' => $installmentData['summaries'],
            'installment_allowed_billing_days' => $installmentData['allowed_billing_days'],
            'installment_selected_billing_day' => $installmentData['selected_billing_day'],
            'installment_accepts_billing_day' => $checkoutAcceptsBillingDay,
            'installment_requires_billing_day_choice' => $checkoutRequiresBillingDayChoice,
            'installment_billing_day_options' => $checkoutBillingDayOptions,
            'installment_billing_interval_unit' => $package?->normalizedBillingIntervalUnit(),
            'installment_billing_interval_count' => $package?->billing_interval_count,
            'access_tier' => [
                'id' => $pendingRegistration->accessTier->id,
                'name' => $pendingRegistration->accessTier->name,
                'slug' => $pendingRegistration->accessTier->slug,
                'price' => (float) $pendingRegistration->accessTier->price,
                'currency_code' => $pendingRegistration->accessTier->currency_code,
            ],
            'pay_url' => $this->checkoutPayUrl($pendingRegistration),
            'create_order_url' => $this->checkoutOrderCreateUrl($pendingRegistration),
            'payment_options' => $this->checkoutPaymentOptions(
                totalAmount: $amount,
                currencyCode: $currencyCode,
                installmentSummary: $installmentSummary,
                allowedBillingDays: $installmentData['allowed_billing_days'],
            ),
            'payment_method_options' => $this->availablePaymentMethodOptions(),
            'installment_approve_url' => $this->checkoutSubscriptionApproveUrl($pendingRegistration),
            'installment_status_url' => $this->checkoutSubscriptionStatusUrl($pendingRegistration),
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

    private function assertInitialCheckoutPaymentTypeSupported(
        PendingRegistration $pendingRegistration,
        string $paymentType,
        string $paymentMethod,
        ?int $billingDay = null,
    ): void {
        if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return;
        }

        $package = $pendingRegistration->package;

        if (! $package instanceof Package) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        if ($paymentMethod !== Payment::METHOD_PAYPAL) {
            abort(422, 'Installment checkout currently requires PayPal.');
        }

        if (! $this->installmentPlanCalculator->isEligible($package)) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        $requestedBillingDay = (int) ($billingDay ?? 0);

        if ($billingDay !== null && ! in_array($requestedBillingDay, Package::CUSTOMER_BILLING_DAY_OPTIONS, true)) {
            abort(422, 'Billing day must be either the 1st or the 15th.');
        }

        if ($billingDay !== null && ! $package->checkoutAcceptsBillingDay()) {
            abort(422, 'Billing day is not available for this package.');
        }

        if ($billingDay === null && $package->checkoutRequiresBillingDayChoice()) {
            abort(422, 'Billing day is required for this package checkout.');
        }

        try {
            $resolvedBillingDay = $this->normalizeCheckoutBillingDay($package, $billingDay);
        } catch (\InvalidArgumentException) {
            abort(422, 'The selected billing day is not available for this package.');
        }

        try {
            $this->installmentPlanCalculator->calculate(
                $package,
                $pendingRegistration->checkout_opened_at ?? now(),
                $resolvedBillingDay,
            );
        } catch (\DomainException|\InvalidArgumentException) {
            abort(422, 'This package is not eligible for installment checkout.');
        }
    }

    private function normalizeCheckoutBillingDay(?Package $package, ?int $billingDay): ?int
    {
        if (! $package instanceof Package) {
            return $billingDay;
        }

        if (! $package->checkoutAcceptsBillingDay()) {
            return null;
        }

        return $package->resolveInstallmentBillingDay($billingDay);
    }

    private function assertUpgradePaymentTypeSupported(string $paymentType): void
    {
        if ($paymentType === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            abort(422, 'Installment upgrade checkout is not available yet.');
        }
    }

    /**
     * @return array{
     *     selected_summary: array<string, mixed>|null,
     *     selected_billing_day: int|null,
     *     allowed_billing_days: array<int, int>,
     *     summaries: array<string, array<string, mixed>>
     * }
     */
    private function availableInstallmentData(PendingRegistration $pendingRegistration): array
    {
        $package = $pendingRegistration->package;

        if (! $package instanceof Package || ! $this->installmentPlanCalculator->isEligible($package)) {
            return [
                'selected_summary' => null,
                'selected_billing_day' => null,
                'allowed_billing_days' => [],
                'summaries' => [],
            ];
        }

        if (! $package->checkoutAcceptsBillingDay()) {
            try {
                $summary = $this->installmentPlanCalculator->calculate(
                    $package,
                    $pendingRegistration->checkout_opened_at ?? now(),
                    null,
                );
            } catch (\DomainException|\InvalidArgumentException) {
                return [
                    'selected_summary' => null,
                    'selected_billing_day' => null,
                    'allowed_billing_days' => [],
                    'summaries' => [],
                ];
            }

            return [
                'selected_summary' => $summary,
                'selected_billing_day' => null,
                'allowed_billing_days' => [],
                'summaries' => [
                    'default' => $summary,
                ],
            ];
        }

        $visibleBillingDayOptions = $package->checkoutBillingDayOptions();
        $calculationBillingDays = $visibleBillingDayOptions !== []
            ? $visibleBillingDayOptions
            : [$package->defaultInstallmentBillingDay()];
        $summaries = [];

        foreach ($calculationBillingDays as $billingDay) {
            try {
                $summaries[(string) $billingDay] = $this->installmentPlanCalculator->calculate(
                    $package,
                    $pendingRegistration->checkout_opened_at ?? now(),
                    $billingDay,
                );
            } catch (\DomainException|\InvalidArgumentException) {
                continue;
            }
        }

        if ($summaries === []) {
            return [
                'selected_summary' => null,
                'selected_billing_day' => null,
                'allowed_billing_days' => $visibleBillingDayOptions,
                'summaries' => [],
            ];
        }

        $selectedBillingDay = $pendingRegistration->installment_billing_day !== null
            ? (int) $pendingRegistration->installment_billing_day
            : (in_array(15, $calculationBillingDays, true)
                ? 15
                : $package->defaultInstallmentBillingDay());

        if (! array_key_exists((string) $selectedBillingDay, $summaries)) {
            $selectedBillingDay = (int) array_key_first($summaries);
        }

        return [
            'selected_summary' => $summaries[(string) $selectedBillingDay] ?? null,
            'selected_billing_day' => $selectedBillingDay,
            'allowed_billing_days' => $visibleBillingDayOptions,
            'summaries' => $summaries,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $installmentSummary
     * @param  array<int, int>  $allowedBillingDays
     * @return array<int, array<string, mixed>>
     */
    private function checkoutPaymentOptions(
        float $totalAmount,
        string $currencyCode,
        ?array $installmentSummary,
        array $allowedBillingDays,
    ): array {
        $options = [[
            'type' => Invoice::PAYMENT_TYPE_FULL,
            'label' => 'Pay in full',
            'amount_due_today' => $this->formatMoney($totalAmount),
            'currency_code' => $currencyCode,
        ]];

        if ($installmentSummary !== null) {
            $options[] = [
                'type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                'label' => 'Installment',
                'amount_due_today' => $installmentSummary['first_payment_amount'],
                'currency_code' => $installmentSummary['currency_code'],
                'installment_count' => $installmentSummary['installment_count'],
                'recurring_amount' => $installmentSummary['recurring_payment_amount'],
                'billing_day' => $installmentSummary['billing_day'],
                'allowed_billing_days' => $allowedBillingDays,
                'final_due_at' => $installmentSummary['final_due_at'],
                'summary' => $installmentSummary,
            ];
        }

        return $options;
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

    private function formatMoney(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
