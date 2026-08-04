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
        private readonly EmailNotificationService $emailNotifications,
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
            'amount_snapshot' => $package->checkoutBaseAmount(),
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
     * @param  array{
     *     payment_type: string,
     *     payment_method: string,
     *     billing_day?: int|null,
     *     installment_count?: int|null
     *     donation_amount?: float|null
     * }  $attributes
     *
     * @return array<string, mixed>
     */
    public function startInitialCheckout(PendingRegistration $pendingRegistration, array $attributes): array
    {
        $this->assertSupportedPaymentMethod($attributes['payment_method']);

        $pendingRegistration->loadMissing('package', 'accessTier');

        $normalizedBillingDay = null;
        $requestedInstallmentCount = isset($attributes['installment_count']) && $attributes['installment_count'] !== null
            ? (int) $attributes['installment_count']
            : null;
        $installmentCount = $requestedInstallmentCount;

        if ($attributes['payment_type'] === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            try {
                $normalizedBillingDay = $this->normalizeCheckoutBillingDay(
                    $pendingRegistration->package,
                    isset($attributes['billing_day']) ? (int) $attributes['billing_day'] : null,
                );
            } catch (\InvalidArgumentException) {
                abort(422, 'The selected billing day is not available for this package.');
            }

            $installmentCount = $this->resolveRequestedInstallmentCount(
                $pendingRegistration->package,
                $requestedInstallmentCount,
            );
        }

        $this->assertInitialCheckoutPaymentTypeSupported(
            $pendingRegistration,
            $attributes['payment_type'],
            $attributes['payment_method'],
            $normalizedBillingDay,
            $installmentCount,
        );

        if (
            ($attributes['payment_method'] ?? null) === Payment::METHOD_INTERNAL
            && ! ($pendingRegistration->package?->isFreePackage() ?? false)
        ) {
            abort(422, 'Internal checkout is only available for free packages.');
        }

        if ($attributes['payment_type'] === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return $this->paymentSubscriptionService->startInitialCheckout(
                $pendingRegistration,
                [
                    'return_url' => $this->checkoutSubscriptionReturnUrl($pendingRegistration),
                    'cancel_url' => $this->checkoutSubscriptionCancelUrl($pendingRegistration),
                ],
                $normalizedBillingDay,
                $installmentCount,
            );
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

        /** @var array{invoice: Invoice, payment_activity: Payment, is_new_invoice: bool} $created */
        $created = DB::transaction(function () use ($pendingRegistration, $attributes): array {
            $pendingRegistration->loadMissing('accessTier', 'package', 'onboardingState.user');

            $accessTier = $pendingRegistration->accessTier()->firstOrFail();
            $package = $pendingRegistration->package;
            $amount = $this->resolveInitialCheckoutAmount($pendingRegistration, $attributes);
            $currencyCode = (string) ($package?->currency_code ?? $accessTier->currency_code);
            $packagePaymentType = $package?->normalizedPaymentType() ?? Package::PAYMENT_TYPE_PAID;

            if ($pendingRegistration->status === PendingRegistration::STATUS_COMPLETED) {
                abort(409, 'This registration flow is already completed.');
            }

            // Reuse the still-unpaid invoice for this registration instead of creating a
            // new one on every checkout retry, so one abandoned attempt = one invoice.
            $existingInvoice = Invoice::query()
                ->where('pending_registration_id', $pendingRegistration->id)
                ->where('type', Invoice::TYPE_INITIAL)
                ->where('payment_type', $attributes['payment_type'])
                ->where('status', Invoice::STATUS_UNPAID)
                ->latest('id')
                ->first();

            $isNewInvoice = ! $existingInvoice instanceof Invoice;

            if ($existingInvoice instanceof Invoice) {
                $existingInvoice->forceFill([
                    'package_id' => $package?->id,
                    'access_tier_id' => $pendingRegistration->access_tier_id,
                    'package_payment_type' => $packagePaymentType,
                    'total_amount' => $amount,
                    'balance_due' => $amount,
                    'currency_code' => $currencyCode,
                ])->save();

                $invoice = $existingInvoice->fresh();
            } else {
                $invoice = Invoice::query()->create([
                    'invoice_number' => $this->invoiceNumbers->nextNumber(),
                    'pending_registration_id' => $pendingRegistration->id,
                    'package_id' => $package?->id,
                    'access_tier_id' => $pendingRegistration->access_tier_id,
                    'type' => Invoice::TYPE_INITIAL,
                    'payment_type' => $attributes['payment_type'],
                    'package_payment_type' => $packagePaymentType,
                    'total_amount' => $amount,
                    'balance_due' => $amount,
                    'currency_code' => $currencyCode,
                    'status' => Invoice::STATUS_UNPAID,
                    'issued_at' => now(),
                ]);
            }

            $paymentActivity = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_method' => $attributes['payment_method'],
                'payment_type' => $attributes['payment_type'],
                'package_payment_type' => $packagePaymentType,
                'amount_paid' => $this->initialPaymentAmount(
                    totalAmount: $amount,
                    paymentType: $attributes['payment_type'],
                ),
                'currency_code' => $currencyCode,
                'status' => Payment::STATUS_PENDING,
                'notes' => $this->initialPaymentNotes(
                    paymentMethod: (string) $attributes['payment_method'],
                    packagePaymentType: $packagePaymentType,
                    isUpgrade: false,
                ),
            ]);

            return [
                'invoice' => $invoice,
                'payment_activity' => $paymentActivity,
                'is_new_invoice' => $isNewInvoice,
            ];
        });

        if (
            in_array($attributes['payment_method'], [Payment::METHOD_MOCK, Payment::METHOD_INTERNAL], true)
            || (float) $created['invoice']->total_amount <= 0
        ) {
            $finalized = $this->paymentFinalizer->finalizeSuccessfulPayment(
                $created['payment_activity'],
                $attributes['payment_method'] === Payment::METHOD_MOCK
                    ? 'MOCK-'.$created['invoice']->id.'-'.$created['payment_activity']->id
                    : 'INTERNAL-'.$created['invoice']->id.'-'.$created['payment_activity']->id,
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

        if ($created['is_new_invoice']) {
            $this->emailNotifications->sendCheckoutPaymentLinkNotification(
                $pendingRegistration,
                $created['invoice'],
                $this->checkoutUrl($pendingRegistration),
            );
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
     * @param  array{
     *     payment_type: string,
     *     payment_method: string,
     *     billing_day?: int|null,
     *     installment_count?: int|null
     *     donation_amount?: float|null,
     *     package_id?: int|null
     * }  $attributes
     *
     * @return array{invoice: Invoice, payment_activity: Payment, redirect_url: string, amount_due: float}
     */
    public function startUpgradeCheckout(User $user, AccessTier $targetTier, array $attributes): array
    {
        $this->assertSupportedPaymentMethod($attributes['payment_method']);

        $targetPackage = $this->resolveUpgradePackage(
            $targetTier,
            isset($attributes['package_id']) ? (int) $attributes['package_id'] : null,
        );

        abort_unless($targetPackage instanceof Package, 422, 'This upgrade target package is unavailable.');

        $minimumAmountDue = $this->relevantUpgradeAmountDueForPackage($user, $targetTier, $targetPackage);
        $chargeAmount = $this->resolveUpgradeChargeAmount($user, $targetTier, $targetPackage, $attributes);

        $normalizedBillingDay = null;
        $requestedInstallmentCount = isset($attributes['installment_count']) && $attributes['installment_count'] !== null
            ? (int) $attributes['installment_count']
            : null;
        $installmentCount = $requestedInstallmentCount;

        if ($attributes['payment_type'] === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            try {
                $normalizedBillingDay = $this->normalizeCheckoutBillingDay(
                    $targetPackage,
                    isset($attributes['billing_day']) ? (int) $attributes['billing_day'] : null,
                );
            } catch (\InvalidArgumentException) {
                abort(422, 'The selected billing day is not available for this package.');
            }

            $installmentCount = $this->resolveRequestedInstallmentCount(
                $targetPackage,
                $requestedInstallmentCount,
            );
        }

        $this->assertUpgradePaymentTypeSupported(
            $targetPackage,
            $attributes['payment_type'],
            $attributes['payment_method'],
            $normalizedBillingDay,
            $chargeAmount,
            $installmentCount,
        );

        if (
            ($attributes['payment_method'] ?? null) === Payment::METHOD_INTERNAL
            && $chargeAmount > 0
        ) {
            abort(422, 'Internal upgrade checkout is only available when no additional payment is required.');
        }

        if ($attributes['payment_type'] === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return $this->paymentSubscriptionService->startUpgradeCheckout(
                $user,
                $targetTier,
                $targetPackage,
                $chargeAmount,
                [
                    'return_url' => $this->upgradeSubscriptionReturnUrl($targetTier),
                    'cancel_url' => $this->upgradeSubscriptionCancelUrl($targetTier),
                ],
                $normalizedBillingDay,
                $installmentCount,
            );
        }

        /** @var array{invoice: Invoice, payment_activity: Payment} $created */
        $created = DB::transaction(function () use ($user, $targetTier, $targetPackage, $attributes, $chargeAmount): array {
            $invoice = Invoice::query()->create([
                'invoice_number' => $this->invoiceNumbers->nextNumber(),
                'user_id' => $user->id,
                'package_id' => $targetPackage->id,
                'access_tier_id' => $targetTier->id,
                'type' => Invoice::TYPE_UPGRADE,
                'payment_type' => $attributes['payment_type'],
                'package_payment_type' => $targetPackage->normalizedPaymentType(),
                'total_amount' => $chargeAmount,
                'balance_due' => $chargeAmount,
                'currency_code' => $targetTier->currency_code,
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $paymentActivity = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_method' => $attributes['payment_method'],
                'payment_type' => $attributes['payment_type'],
                'package_payment_type' => $targetPackage->normalizedPaymentType(),
                'amount_paid' => $this->initialPaymentAmount(
                    totalAmount: $chargeAmount,
                    paymentType: $attributes['payment_type'],
                ),
                'currency_code' => $targetTier->currency_code,
                'status' => Payment::STATUS_PENDING,
                'notes' => $this->initialPaymentNotes(
                    paymentMethod: (string) $attributes['payment_method'],
                    packagePaymentType: $targetPackage->normalizedPaymentType(),
                    isUpgrade: true,
                ),
            ]);

            return [
                'invoice' => $invoice,
                'payment_activity' => $paymentActivity,
            ];
        });

        if (
            in_array($attributes['payment_method'], [Payment::METHOD_MOCK, Payment::METHOD_INTERNAL], true)
            || $chargeAmount <= 0
        ) {
            $finalized = $this->paymentFinalizer->finalizeSuccessfulPayment(
                $created['payment_activity'],
                $attributes['payment_method'] === Payment::METHOD_MOCK
                    ? 'MOCK-UPGRADE-'.$created['invoice']->id.'-'.$created['payment_activity']->id
                    : 'INTERNAL-UPGRADE-'.$created['invoice']->id.'-'.$created['payment_activity']->id,
            );

            return [
                'invoice' => $finalized['invoice'],
                'payment_activity' => $finalized['payment_activity'],
                'redirect_url' => $this->upgradePaymentSuccessUrl($finalized['invoice']),
                'amount_due' => $minimumAmountDue,
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
            'amount_due' => $minimumAmountDue,
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
        $amount = $package?->suggestedCheckoutAmount()
            ?? (float) $pendingRegistration->accessTier->price;
        $currencyCode = (string) ($package?->currency_code ?? $pendingRegistration->accessTier->currency_code);
        $installmentData = $this->availableInstallmentData($pendingRegistration);
        $installmentSummary = $this->normalizeInstallmentSummary(
            $installmentData['selected_summary'],
        );
        $installmentSummaries = $this->normalizeInstallmentSummaries(
            $installmentData['summaries'],
        );
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
                'payment_type' => $package->normalizedPaymentType(),
                'image_url' => null,
                'price' => (float) $package->price,
                'minimum_donation_amount' => $package->minimumDonationAmount(),
                'suggested_donation_amount' => $package->suggestedDonationAmount(),
                'currency_code' => $package->currency_code,
                'installment_enabled' => $package->supportsInstallments(),
                'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
                'installment_count_mode' => $package->normalizedInstallmentCountMode(),
                'installment_count' => $package->configuredInstallmentCount(),
                'installment_count_selectable' => $installmentSummary['installment_count_selectable'] ?? $package->installmentCountSelectable(),
                'configured_installment_count' => $installmentSummary['configured_installment_count'] ?? $package->configuredInstallmentCount(),
                'minimum_installment_count' => $installmentSummary['minimum_installment_count'] ?? $package->minimumInstallmentCount(),
                'fixed_installment_count' => $installmentSummary['fixed_installment_count'] ?? $package->fixedInstallmentCount(),
                'installment_deadline_date' => $package->installment_deadline_date?->toDateString(),
                'allowed_billing_days' => $package->checkoutBillingDayOptions(),
                'checkout_billing_day_options' => $checkoutBillingDayOptions,
                'installment_billing_day_options' => $checkoutBillingDayOptions,
                'installment_maximum_count' => $installmentSummary['installment_maximum_count'] ?? null,
                'maximum_installment_count' => $installmentSummary['maximum_installment_count'] ?? null,
            ] : null,
            'installment_summary' => $installmentSummary,
            'installment_summaries' => $installmentSummaries,
            'installment_allowed_billing_days' => $installmentData['allowed_billing_days'],
            'installment_selected_billing_day' => $installmentData['selected_billing_day'],
            'installment_accepts_billing_day' => $checkoutAcceptsBillingDay,
            'installment_requires_billing_day_choice' => $checkoutRequiresBillingDayChoice,
            'installment_billing_day_options' => $checkoutBillingDayOptions,
            'installment_billing_interval_unit' => 'MONTH',
            'installment_billing_interval_count' => 1,
            'installment_calculation_method' => $installmentSummary['installment_calculation_method'] ?? $package?->normalizedInstallmentCalculationMethod(),
            'installment_count_mode' => $installmentSummary['installment_count_mode'] ?? $package?->normalizedInstallmentCountMode(),
            'installment_count_selectable' => $installmentSummary['installment_count_selectable'] ?? $package?->installmentCountSelectable(),
            'configured_installment_count' => $installmentSummary['configured_installment_count'] ?? $package?->configuredInstallmentCount(),
            'minimum_installment_count' => $installmentSummary['minimum_installment_count'] ?? $package?->minimumInstallmentCount(),
            'fixed_installment_count' => $installmentSummary['fixed_installment_count'] ?? $package?->fixedInstallmentCount(),
            'installment_count' => $installmentSummary['installment_count'] ?? null,
            'total_amount' => $installmentSummary['total_amount'] ?? null,
            'first_payment_amount' => $installmentSummary['first_payment_amount'] ?? null,
            'first_payment_date' => $installmentSummary['first_payment_date'] ?? null,
            'recurring_payment_amount' => $installmentSummary['recurring_payment_amount'] ?? null,
            'monthly_base_amount' => $installmentSummary['monthly_base_amount'] ?? null,
            'billing_day' => $installmentSummary['billing_day'] ?? null,
            'recurring_due_dates' => $installmentSummary['recurring_due_dates'] ?? [],
            'available_recurring_due_dates' => $installmentSummary['available_recurring_due_dates'] ?? [],
            'schedule_breakdown' => $installmentSummary['schedule_breakdown'] ?? [],
            'final_due_at' => $installmentSummary['final_due_at'] ?? null,
            'installment_maximum_count' => $installmentSummary['installment_maximum_count'] ?? null,
            'maximum_installment_count' => $installmentSummary['maximum_installment_count'] ?? null,
            'installment_available_recurring_due_dates' => $installmentSummary['available_recurring_due_dates'] ?? [],
            'installment_deadline_date' => $installmentSummary['deadline_date'] ?? $package?->installment_deadline_date?->toDateString(),
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
                package: $package,
                totalAmount: $amount,
                currencyCode: $currencyCode,
                installmentSummary: $installmentSummary,
                allowedBillingDays: $installmentData['allowed_billing_days'],
            ),
            'payment_method_options' => $this->availablePaymentMethodOptions($package),
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

    public function enrollmentSuccessUrl(OnboardingState $onboardingState): string
{
    return URL::temporarySignedRoute(
        'onboarding.enrollment-success.show',
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
            /** @var OnboardingState $lockedOnboardingState */
            $lockedOnboardingState = OnboardingState::query()
                ->with(['user.accessTier', 'pendingRegistration'])
                ->lockForUpdate()
                ->findOrFail($onboardingState->id);

            abort_if($lockedOnboardingState->status !== OnboardingState::STATUS_AWAITING_SIGNUP, 409, 'Password creation is not available for this onboarding flow.');

            $user = $lockedOnboardingState->user;

            abort_unless($user instanceof User, 404);

            $user->forceFill([
                'is_active' => true,
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $lockedOnboardingState->forceFill([
                'status' => OnboardingState::STATUS_COMPLETED,
                'signup_completed_at' => now(),
            ])->save();

            $lockedOnboardingState->pendingRegistration->forceFill([
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

    public function upgradeSubscriptionReturnUrl(AccessTier $targetTier): string
    {
        return route('student.upgrades.installments.return', [
            'accessTier' => $targetTier,
        ]);
    }

    public function upgradeSubscriptionCancelUrl(AccessTier $targetTier): string
    {
        return route('student.upgrades.installments.cancel', [
            'accessTier' => $targetTier,
        ]);
    }

    public function upgradeSubscriptionStatusUrl(AccessTier $targetTier): string
    {
        return route('student.upgrades.installments.status', [
            'accessTier' => $targetTier,
        ]);
    }

    public function upgradeSubscriptionApproveUrl(AccessTier $targetTier): string
    {
        return route('student.upgrades.installments.approve', [
            'accessTier' => $targetTier,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function upgradePayload(User $user, AccessTier $targetTier, ?int $selectedPackageId = null): array
    {
        $currentTier = $user->accessTier;
        $packages = $this->availableUpgradePackages($targetTier);
        $selectedPackage = $this->resolveUpgradePackage($targetTier, $selectedPackageId)
            ?? ($packages[0] ?? null);
        abort_unless($selectedPackage instanceof Package, 422, 'This upgrade target package is unavailable.');

        $amountDue = $this->relevantUpgradeAmountDueForPackage($user, $targetTier, $selectedPackage);
        $totalPaid = $this->relevantUpgradePaidAmount($user, $targetTier);
        $installmentData = $this->availableUpgradeInstallmentData($selectedPackage, $amountDue);
        $installmentSummary = $this->normalizeInstallmentSummary(
            $installmentData['selected_summary'],
        );
        $installmentSummaries = $this->normalizeInstallmentSummaries(
            $installmentData['summaries'],
        );

        return [
            'submit_url' => route('student.upgrades.pay', $targetTier),
            'amount_due' => $amountDue,
            'total_paid' => $totalPaid,
            'current_tier' => $currentTier ? [
                'id' => $currentTier->id,
                'name' => $currentTier->name,
                'slug' => $currentTier->slug,
                'price' => (float) $currentTier->price,
                'currency_code' => $currentTier->currency_code,
                'level' => $currentTier->level,
            ] : null,
            'target_tier' => [
                'id' => $targetTier->id,
                'name' => $targetTier->name,
                'slug' => $targetTier->slug,
                'price' => (float) $targetTier->price,
                'currency_code' => $targetTier->currency_code,
                'level' => $targetTier->level,
            ],
            'payment_method_options' => $this->availablePaymentMethodOptions($selectedPackage),
            'payment_options' => $this->checkoutPaymentOptions(
                package: $selectedPackage,
                totalAmount: $amountDue,
                currencyCode: (string) $targetTier->currency_code,
                installmentSummary: $installmentSummary,
                allowedBillingDays: $installmentData['allowed_billing_days'],
            ),
            'installment_summary' => $installmentSummary,
            'installment_summaries' => $installmentSummaries,
            'installment_allowed_billing_days' => $installmentData['allowed_billing_days'],
            'installment_selected_billing_day' => $installmentData['selected_billing_day'],
            'installment_accepts_billing_day' => $installmentData['accepts_billing_day'],
            'installment_requires_billing_day_choice' => $installmentData['requires_billing_day_choice'],
            'installment_billing_day_options' => $installmentData['visible_billing_day_options'],
            'installment_billing_interval_unit' => 'MONTH',
            'installment_billing_interval_count' => 1,
            'installment_calculation_method' => $installmentSummary['installment_calculation_method'] ?? ($installmentData['package']['installment_calculation_method'] ?? null),
            'installment_count_mode' => $installmentSummary['installment_count_mode'] ?? ($installmentData['package']['installment_count_mode'] ?? null),
            'installment_count_selectable' => $installmentSummary['installment_count_selectable'] ?? ($installmentData['package']['installment_count_selectable'] ?? null),
            'configured_installment_count' => $installmentSummary['configured_installment_count'] ?? ($installmentData['package']['configured_installment_count'] ?? null),
            'minimum_installment_count' => $installmentSummary['minimum_installment_count'] ?? ($installmentData['package']['minimum_installment_count'] ?? null),
            'fixed_installment_count' => $installmentSummary['fixed_installment_count'] ?? ($installmentData['package']['fixed_installment_count'] ?? null),
            'installment_count' => $installmentSummary['installment_count'] ?? null,
            'total_amount' => $installmentSummary['total_amount'] ?? null,
            'first_payment_amount' => $installmentSummary['first_payment_amount'] ?? null,
            'first_payment_date' => $installmentSummary['first_payment_date'] ?? null,
            'recurring_payment_amount' => $installmentSummary['recurring_payment_amount'] ?? null,
            'monthly_base_amount' => $installmentSummary['monthly_base_amount'] ?? null,
            'billing_day' => $installmentSummary['billing_day'] ?? null,
            'recurring_due_dates' => $installmentSummary['recurring_due_dates'] ?? [],
            'available_recurring_due_dates' => $installmentSummary['available_recurring_due_dates'] ?? [],
            'schedule_breakdown' => $installmentSummary['schedule_breakdown'] ?? [],
            'final_due_at' => $installmentSummary['final_due_at'] ?? null,
            'installment_maximum_count' => $installmentSummary['installment_maximum_count'] ?? null,
            'maximum_installment_count' => $installmentSummary['maximum_installment_count'] ?? null,
            'installment_available_recurring_due_dates' => $installmentSummary['available_recurring_due_dates'] ?? [],
            'installment_deadline_date' => $installmentSummary['deadline_date'] ?? null,
            'installment_approve_url' => $this->upgradeSubscriptionApproveUrl($targetTier),
            'installment_status_url' => $this->upgradeSubscriptionStatusUrl($targetTier),
            'package' => $installmentData['package'],
            'packages' => collect($packages)->map(fn (Package $package) => [
                'id' => $package->id,
                'title' => $package->title,
                'slug' => $package->slug,
                'payment_type' => $package->normalizedPaymentType(),
                'price' => (float) $package->price,
                'minimum_donation_amount' => $package->minimumDonationAmount(),
                'suggested_donation_amount' => $package->suggestedDonationAmount(),
                'currency_code' => $package->currency_code,
                'installment_enabled' => $package->supportsInstallments(),
            ])->values()->all(),
            'paypal' => [
                'client_id' => $this->paypalService->clientId(),
                'currency_code' => (string) $targetTier->currency_code,
                'intent' => 'capture',
                'environment' => $this->paypalService->environment(),
            ],
        ];
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
    public function availablePaymentMethodOptions(?Package $package = null): array
    {
        if ($package instanceof Package && $package->isFreePackage()) {
            return [[
                'value' => Payment::METHOD_INTERNAL,
                'label' => 'Continue',
            ]];
        }

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

    /**
     * @return array<int, Package>
     */
    public function availableUpgradePackages(AccessTier $targetTier): array
    {
        return $targetTier->packages()
            ->where('is_active', true)
            ->orderBy('title')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    private function assertSupportedPaymentMethod(string $paymentMethod): void
    {
        if ($paymentMethod === Payment::METHOD_MOCK && app()->environment('production')) {
            abort(422, 'Mock payment is not available in production.');
        }

        if ($paymentMethod === Payment::METHOD_BANK_TRANSFER) {
            abort(422, 'Bank transfer is not available in the PayPal payment architecture phase.');
        }

        if (! in_array($paymentMethod, [
            Payment::METHOD_PAYPAL,
            Payment::METHOD_MOCK,
            Payment::METHOD_INTERNAL,
        ], true)) {
            abort(422, 'This payment method is not supported.');
        }
    }

    private function assertInitialCheckoutPaymentTypeSupported(
        PendingRegistration $pendingRegistration,
        string $paymentType,
        string $paymentMethod,
        ?int $billingDay = null,
        ?int $installmentCount = null,
    ): void {
        if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return;
        }

        $package = $pendingRegistration->package;

        if (! $package instanceof Package) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        if (! $package->isPaidPackage()) {
            abort(422, 'Installment checkout is only available for paid packages.');
        }

        if ($paymentMethod !== Payment::METHOD_PAYPAL) {
            abort(422, 'Installment checkout currently requires PayPal.');
        }

        if (! $this->installmentPlanCalculator->isEligible($package)) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        if ($billingDay === null) {
            abort(422, 'Billing day is required for this package checkout.');
        }

        if (! in_array((int) $billingDay, Package::CUSTOMER_BILLING_DAY_OPTIONS, true)) {
            abort(422, 'Billing day must be either the 1st or the 15th.');
        }

        if (! $package->checkoutAcceptsBillingDay()) {
            abort(422, 'Billing day is not available for this package.');
        }

        try {
            $resolvedBillingDay = $this->normalizeCheckoutBillingDay($package, $billingDay);
        } catch (\InvalidArgumentException) {
            abort(422, 'The selected billing day is not available for this package.');
        }

        if ($installmentCount === null && ! $package->usesFixedInstallmentCount()) {
            abort(422, 'Installment count is required for installment checkout.');
        }

        try {
            $this->installmentPlanCalculator->calculate(
                $package,
                $pendingRegistration->checkout_opened_at ?? now(),
                $resolvedBillingDay,
                $installmentCount,
            );
        } catch (\DomainException|\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage() ?: 'This package is not eligible for installment checkout.');
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

    private function assertUpgradePaymentTypeSupported(
        ?Package $package,
        string $paymentType,
        string $paymentMethod,
        ?int $billingDay,
        float $amountDue,
        ?int $installmentCount = null,
    ): void {
        if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return;
        }

        if (! $package instanceof Package) {
            abort(422, 'This upgrade target is not ready for installment checkout.');
        }

        if (! $package->isPaidPackage()) {
            abort(422, 'Installment upgrade checkout is only available for paid packages.');
        }

        if ($paymentMethod !== Payment::METHOD_PAYPAL) {
            abort(422, 'Installment checkout currently requires PayPal.');
        }

        if ($amountDue <= 0) {
            abort(422, 'No additional upgrade payment is required for this tier.');
        }

        if (! $this->installmentPlanCalculator->isEligible($package)) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        if ($billingDay === null) {
            abort(422, 'Billing day is required for this package checkout.');
        }

        if (! in_array((int) $billingDay, Package::CUSTOMER_BILLING_DAY_OPTIONS, true)) {
            abort(422, 'Billing day must be either the 1st or the 15th.');
        }

        if (! $package->checkoutAcceptsBillingDay()) {
            abort(422, 'Billing day is not available for this package.');
        }

        try {
            $resolvedBillingDay = $this->normalizeCheckoutBillingDay($package, $billingDay);
        } catch (\InvalidArgumentException) {
            abort(422, 'The selected billing day is not available for this package.');
        }

        if ($installmentCount === null && ! $package->usesFixedInstallmentCount()) {
            abort(422, 'Installment count is required for installment checkout.');
        }

        try {
            $this->installmentPlanCalculator->calculateForAmount(
                $package,
                $amountDue,
                now(),
                $resolvedBillingDay,
                $installmentCount,
            );
        } catch (\DomainException|\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage() ?: 'This package is not eligible for installment checkout.');
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
            return [
                'selected_summary' => null,
                'selected_billing_day' => null,
                'allowed_billing_days' => [],
                'summaries' => [],
            ];
        }

        $visibleBillingDayOptions = $package->checkoutBillingDayOptions();
        $calculationBillingDays = $visibleBillingDayOptions !== []
            ? $visibleBillingDayOptions
            : [$package->defaultInstallmentBillingDay()];

        $summaries = [];

        foreach ($calculationBillingDays as $billingDay) {
            try {
                $summary = $this->installmentPlanCalculator->calculate(
                    $package,
                    $pendingRegistration->checkout_opened_at ?? now(),
                    $billingDay,
                    null,
                );

                $summaries[(string) $billingDay] = $summary;
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
     * @return array{
     *     package: array<string, mixed>|null,
     *     selected_summary: array<string, mixed>|null,
     *     selected_billing_day: int|null,
     *     allowed_billing_days: array<int, int>,
     *     visible_billing_day_options: array<int, int>,
     *     accepts_billing_day: bool,
     *     requires_billing_day_choice: bool,
     *     billing_interval_unit: string|null,
     *     billing_interval_count: int|null,
     *     summaries: array<string, array<string, mixed>>
     * }
     */
    private function availableUpgradeInstallmentData(Package $package, float $amountDue): array
    {
        if (! $this->installmentPlanCalculator->isEligible($package) || $amountDue <= 0) {
            return [
                'package' => null,
                'selected_summary' => null,
                'selected_billing_day' => null,
                'allowed_billing_days' => [],
                'visible_billing_day_options' => [],
                'accepts_billing_day' => false,
                'requires_billing_day_choice' => false,
                'billing_interval_unit' => null,
                'billing_interval_count' => null,
                'summaries' => [],
            ];
        }

        $acceptsBillingDay = $package->checkoutAcceptsBillingDay();
        $visibleBillingDayOptions = $package->checkoutBillingDayOptions();

        if (! $acceptsBillingDay) {
            return [
                'package' => [
                    'id' => $package->id,
                    'title' => $package->title,
                    'slug' => $package->slug,
                    'installment_enabled' => (bool) $package->installment_enabled,
                    'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
                    'installment_count_mode' => $package->normalizedInstallmentCountMode(),
                    'installment_count' => $package->configuredInstallmentCount(),
                    'installment_count_selectable' => $package->installmentCountSelectable(),
                    'configured_installment_count' => $package->configuredInstallmentCount(),
                    'minimum_installment_count' => $package->minimumInstallmentCount(),
                    'fixed_installment_count' => $package->fixedInstallmentCount(),
                    'installment_deadline_date' => $package->installment_deadline_date?->toDateString(),
                    'allowed_billing_days' => [],
                    'checkout_billing_day_options' => [],
                    'installment_billing_day_options' => [],
                    'installment_maximum_count' => null,
                    'maximum_installment_count' => null,
                ],
                'selected_summary' => null,
                'selected_billing_day' => null,
                'allowed_billing_days' => [],
                'visible_billing_day_options' => [],
                'accepts_billing_day' => false,
                'requires_billing_day_choice' => false,
                'billing_interval_unit' => 'MONTH',
                'billing_interval_count' => 1,
                'summaries' => [],
            ];
        }

        $calculationBillingDays = $visibleBillingDayOptions !== []
            ? $visibleBillingDayOptions
            : [$package->defaultInstallmentBillingDay()];

        $summaries = [];

        foreach ($calculationBillingDays as $billingDay) {
            try {
                $summary = $this->installmentPlanCalculator->calculateForAmount(
                    $package,
                    $amountDue,
                    now(),
                    $billingDay,
                    null,
                );

                $summaries[(string) $billingDay] = $summary;
            } catch (\DomainException|\InvalidArgumentException) {
                continue;
            }
        }

        if ($summaries === []) {
            return [
                'package' => [
                    'id' => $package->id,
                    'title' => $package->title,
                    'slug' => $package->slug,
                    'installment_enabled' => (bool) $package->installment_enabled,
                    'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
                    'installment_count_mode' => $package->normalizedInstallmentCountMode(),
                    'installment_count' => $package->configuredInstallmentCount(),
                    'installment_count_selectable' => $package->installmentCountSelectable(),
                    'configured_installment_count' => $package->configuredInstallmentCount(),
                    'minimum_installment_count' => $package->minimumInstallmentCount(),
                    'fixed_installment_count' => $package->fixedInstallmentCount(),
                    'installment_deadline_date' => $package->installment_deadline_date?->toDateString(),
                    'allowed_billing_days' => $visibleBillingDayOptions,
                    'checkout_billing_day_options' => $visibleBillingDayOptions,
                    'installment_billing_day_options' => $visibleBillingDayOptions,
                    'installment_maximum_count' => null,
                    'maximum_installment_count' => null,
                ],
                'selected_summary' => null,
                'selected_billing_day' => null,
                'allowed_billing_days' => $visibleBillingDayOptions,
                'visible_billing_day_options' => $visibleBillingDayOptions,
                'accepts_billing_day' => $acceptsBillingDay,
                'requires_billing_day_choice' => $package->checkoutRequiresBillingDayChoice(),
                'billing_interval_unit' => 'MONTH',
                'billing_interval_count' => 1,
                'summaries' => [],
            ];
        }

        $selectedBillingDay = in_array(15, $visibleBillingDayOptions, true)
            ? 15
            : $package->defaultInstallmentBillingDay();

        $selectedSummaryKey = (string) $selectedBillingDay;

        if (! array_key_exists($selectedSummaryKey, $summaries)) {
            $selectedSummaryKey = (string) array_key_first($summaries);
            $selectedBillingDay = (int) $selectedSummaryKey;
        }

        return [
            'package' => [
                'id' => $package->id,
                'title' => $package->title,
                'slug' => $package->slug,
                'installment_enabled' => (bool) $package->installment_enabled,
                'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
                'installment_count_mode' => $package->normalizedInstallmentCountMode(),
                'installment_count' => $package->configuredInstallmentCount(),
                'installment_count_selectable' => $package->installmentCountSelectable(),
                'configured_installment_count' => $package->configuredInstallmentCount(),
                'minimum_installment_count' => $summaries[$selectedSummaryKey]['minimum_installment_count'] ?? $package->minimumInstallmentCount(),
                'fixed_installment_count' => $package->fixedInstallmentCount(),
                'installment_deadline_date' => $package->installment_deadline_date?->toDateString(),
                'allowed_billing_days' => $visibleBillingDayOptions,
                'checkout_billing_day_options' => $visibleBillingDayOptions,
                'installment_billing_day_options' => $visibleBillingDayOptions,
                'installment_maximum_count' => $summaries[$selectedSummaryKey]['installment_maximum_count'] ?? null,
                'maximum_installment_count' => $summaries[$selectedSummaryKey]['maximum_installment_count'] ?? null,
            ],
            'selected_summary' => $summaries[$selectedSummaryKey] ?? null,
            'selected_billing_day' => $selectedBillingDay,
            'allowed_billing_days' => $visibleBillingDayOptions,
            'visible_billing_day_options' => $visibleBillingDayOptions,
            'accepts_billing_day' => $acceptsBillingDay,
            'requires_billing_day_choice' => $package->checkoutRequiresBillingDayChoice(),
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'summaries' => $summaries,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $installmentSummary
     * @param  array<int, int>  $allowedBillingDays
     * @return array<int, array<string, mixed>>
     */
    private function checkoutPaymentOptions(
        ?Package $package,
        float $totalAmount,
        string $currencyCode,
        ?array $installmentSummary,
        array $allowedBillingDays,
    ): array {
        if ($package instanceof Package && $package->isFreePackage()) {
            return [[
                'type' => Invoice::PAYMENT_TYPE_FULL,
                'label' => 'Continue to Enrollment',
                'amount_due_today' => $this->formatMoney(0),
                'currency_code' => $currencyCode,
                'checkout_variant' => Package::PAYMENT_TYPE_FREE,
            ]];
        }

        if ($package instanceof Package && $package->isDonationPackage()) {
            return [[
                'type' => Invoice::PAYMENT_TYPE_FULL,
                'label' => 'Donate with PayPal',
                'amount_due_today' => $this->formatMoney($package->suggestedCheckoutAmount()),
                'currency_code' => $currencyCode,
                'checkout_variant' => Package::PAYMENT_TYPE_DONATION,
                'minimum_donation_amount' => $this->formatMoney($package->minimumDonationAmount()),
                'suggested_donation_amount' => $this->formatMoney($package->suggestedCheckoutAmount()),
            ]];
        }

        $options = [[
            'type' => Invoice::PAYMENT_TYPE_FULL,
            'label' => 'Pay in full',
            'amount_due_today' => $this->formatMoney($totalAmount),
            'currency_code' => $currencyCode,
            'checkout_variant' => Package::PAYMENT_TYPE_PAID,
        ]];

        if ($installmentSummary !== null) {
            $options[] = [
                'type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                'label' => 'Installment',
                'amount_due_today' => $installmentSummary['first_payment_amount'],
                'currency_code' => $installmentSummary['currency_code'],
                'installment_count' => $installmentSummary['installment_count'],
                'maximum_installment_count' => $installmentSummary['maximum_installment_count'] ?? $installmentSummary['installment_count'],
                'installment_maximum_count' => $installmentSummary['installment_maximum_count'] ?? $installmentSummary['maximum_installment_count'] ?? $installmentSummary['installment_count'],
                'minimum_installment_count' => $installmentSummary['minimum_installment_count'] ?? Package::MIN_INSTALLMENT_COUNT,
                'installment_count_selectable' => $installmentSummary['installment_count_selectable'] ?? true,
                'configured_installment_count' => $installmentSummary['configured_installment_count'] ?? null,
                'fixed_installment_count' => $installmentSummary['fixed_installment_count'] ?? null,
                'installment_calculation_method' => $installmentSummary['installment_calculation_method'] ?? Package::INSTALLMENT_CALCULATION_DATE,
                'installment_count_mode' => $installmentSummary['installment_count_mode'] ?? null,
                'total_amount' => $installmentSummary['total_amount'] ?? null,
                'first_payment_amount' => $installmentSummary['first_payment_amount'] ?? null,
                'recurring_amount' => $installmentSummary['recurring_payment_amount'],
                'recurring_payment_amount' => $installmentSummary['recurring_payment_amount'] ?? null,
                'monthly_base_amount' => $installmentSummary['monthly_base_amount'] ?? null,
                'billing_day' => $installmentSummary['billing_day'],
                'allowed_billing_days' => $allowedBillingDays,
                'deadline_date' => $installmentSummary['deadline_date'] ?? null,
                'final_due_at' => $installmentSummary['final_due_at'],
                'first_payment_date' => $installmentSummary['first_payment_date'] ?? null,
                'recurring_due_dates' => $installmentSummary['recurring_due_dates'] ?? [],
                'available_recurring_due_dates' => $installmentSummary['available_recurring_due_dates'] ?? [],
                'schedule_breakdown' => $installmentSummary['schedule_breakdown'] ?? [],
                'summary' => $installmentSummary,
                'checkout_variant' => Package::PAYMENT_TYPE_PAID,
            ];
        }

        return $options;
    }

    private function initialPaymentAmount(float $totalAmount, string $paymentType): float
    {
        if ($paymentType === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return round($totalAmount, 2);
        }

        return round($totalAmount, 2);
    }

    private function relevantUpgradeAmountDue(User $user, AccessTier $targetTier): float
    {
        $package = $this->resolveUpgradePackage($targetTier);

        abort_unless($package instanceof Package, 422, 'This upgrade target package is unavailable.');

        return $this->relevantUpgradeAmountDueForPackage($user, $targetTier, $package);
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

    private function resolveUpgradePackage(AccessTier $targetTier, ?int $packageId = null): ?Package
    {
        return $targetTier->packages()
            ->where('is_active', true)
            ->when($packageId, fn ($query) => $query->whereKey($packageId))
            ->latest('id')
            ->first();
    }

    private function formatMoney(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>|null  $installmentSummary
     * @return array<string, mixed>|null
     */
    private function normalizeInstallmentSummary(?array $installmentSummary): ?array
    {
        if ($installmentSummary === null) {
            return null;
        }

        $calculationMethod = $installmentSummary['installment_calculation_method'] ?? Package::INSTALLMENT_CALCULATION_DATE;
        $countMode = $installmentSummary['installment_count_mode'] ?? null;
        $configuredInstallmentCount = isset($installmentSummary['configured_installment_count'])
            ? (int) $installmentSummary['configured_installment_count']
            : null;
        $fixedInstallmentCount = isset($installmentSummary['fixed_installment_count'])
            ? (int) $installmentSummary['fixed_installment_count']
            : null;
        $installmentCountSelectable = $installmentSummary['installment_count_selectable'] ?? true;

        if (
            $calculationMethod === Package::INSTALLMENT_CALCULATION_NUMBER
            && $countMode === Package::INSTALLMENT_COUNT_MODE_FIXED
            && $fixedInstallmentCount !== null
            && $fixedInstallmentCount >= Package::MIN_INSTALLMENT_COUNT
        ) {
            $maximumInstallmentCount = $fixedInstallmentCount;
            $minimumInstallmentCount = $fixedInstallmentCount;
            $normalizedInstallmentCount = $fixedInstallmentCount;
            $installmentCountSelectable = false;
        } elseif (
            $calculationMethod === Package::INSTALLMENT_CALCULATION_NUMBER
            && $configuredInstallmentCount !== null
            && $configuredInstallmentCount >= Package::MIN_INSTALLMENT_COUNT
        ) {
            $maximumInstallmentCount = $configuredInstallmentCount;
            $minimumInstallmentCount = Package::MIN_INSTALLMENT_COUNT;
            $normalizedInstallmentCount = isset($installmentSummary['installment_count'])
                ? (int) $installmentSummary['installment_count']
                : $configuredInstallmentCount;
        } else {
            $maximumInstallmentCount = $installmentSummary['maximum_installment_count']
                ?? $installmentSummary['installment_maximum_count']
                ?? $installmentSummary['installment_count']
                ?? null;
            $minimumInstallmentCount = $installmentSummary['minimum_installment_count'] ?? Package::MIN_INSTALLMENT_COUNT;
            $normalizedInstallmentCount = $installmentSummary['installment_count'] ?? null;
        }

        return [
            ...$installmentSummary,
            'total_amount' => $installmentSummary['total_amount'] ?? null,
            'first_payment_amount' => $installmentSummary['first_payment_amount'] ?? null,
            'recurring_payment_amount' => $installmentSummary['recurring_payment_amount'] ?? null,
            'monthly_base_amount' => $installmentSummary['monthly_base_amount'] ?? null,
            'installment_count' => $normalizedInstallmentCount,
            'maximum_installment_count' => $maximumInstallmentCount,
            'installment_maximum_count' => $maximumInstallmentCount,
            'minimum_installment_count' => $minimumInstallmentCount,
            'installment_count_selectable' => $installmentCountSelectable,
            'configured_installment_count' => $configuredInstallmentCount,
            'fixed_installment_count' => $fixedInstallmentCount,
            'installment_calculation_method' => $calculationMethod,
            'installment_count_mode' => $countMode,
            'recurring_due_dates' => $installmentSummary['recurring_due_dates'] ?? [],
            'available_recurring_due_dates' => $installmentSummary['available_recurring_due_dates'] ?? [],
            'schedule_breakdown' => $installmentSummary['schedule_breakdown'] ?? [],
            'final_due_at' => $installmentSummary['final_due_at'] ?? null,
            'first_payment_date' => $installmentSummary['first_payment_date'] ?? null,
            'billing_day' => $installmentSummary['billing_day'] ?? null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $installmentSummaries
     * @return array<string, array<string, mixed>>
     */
    private function normalizeInstallmentSummaries(array $installmentSummaries): array
    {
        $normalized = [];

        foreach ($installmentSummaries as $billingDay => $installmentSummary) {
            $normalized[(string) $billingDay] =
                $this->normalizeInstallmentSummary($installmentSummary) ?? [];
        }

        return $normalized;
    }

    private function resolveRequestedInstallmentCount(?Package $package, ?int $requestedInstallmentCount): ?int
    {
        if (! $package instanceof Package) {
            return $requestedInstallmentCount;
        }

        if ($package->usesFixedInstallmentCount()) {
            return $package->configuredInstallmentCount();
        }

        return $requestedInstallmentCount;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveInitialCheckoutAmount(PendingRegistration $pendingRegistration, array $attributes): float
    {
        $package = $pendingRegistration->package;

        if (! $package instanceof Package) {
            return round((float) $pendingRegistration->accessTier->price, 2);
        }

        if ($package->isDonationPackage()) {
            return round(max(
                $package->minimumDonationAmount(),
                (float) ($attributes['donation_amount'] ?? 0)
            ), 2);
        }

        return $package->checkoutBaseAmount();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveUpgradeChargeAmount(User $user, AccessTier $targetTier, Package $targetPackage, array $attributes): float
    {
        $credit = $this->relevantUpgradePaidAmount($user, $targetTier);

        if ($targetPackage->isFreePackage()) {
            return 0.0;
        }

        if ($targetPackage->isDonationPackage()) {
            $enteredDonationAmount = round(max(
                $targetPackage->minimumDonationAmount(),
                (float) ($attributes['donation_amount'] ?? $targetPackage->minimumDonationAmount())
            ), 2);

            return max(0, round($enteredDonationAmount - $credit, 2));
        }

        return max(0, round($targetPackage->checkoutBaseAmount() - $credit, 2));
    }

    private function relevantUpgradeAmountDueForPackage(User $user, AccessTier $targetTier, Package $targetPackage): float
    {
        $currentTier = $user->accessTier;

        abort_if(! $targetTier->is_active, 422, 'This upgrade target is not active.');
        abort_if(! $currentTier || ! $currentTier->is_active, 422, 'Your current tier is not available for upgrade.');
        abort_if($targetTier->level <= $currentTier->level, 422, 'Only higher tiers can be selected for upgrade.');

        return max(0, round($targetPackage->checkoutBaseAmount() - $this->relevantUpgradePaidAmount($user, $targetTier), 2));
    }

    private function initialPaymentNotes(string $paymentMethod, string $packagePaymentType, bool $isUpgrade): string
    {
        if ($paymentMethod === Payment::METHOD_INTERNAL) {
            return $isUpgrade
                ? 'Internal upgrade finalized without PayPal.'
                : 'Internal checkout finalized without PayPal.';
        }

        if ($paymentMethod === Payment::METHOD_MOCK) {
            return $isUpgrade
                ? 'Mock upgrade checkout initialized.'
                : 'Mock checkout initialized.';
        }

        if ($packagePaymentType === Package::PAYMENT_TYPE_DONATION) {
            return $isUpgrade
                ? 'PayPal donation upgrade checkout initialized.'
                : 'PayPal donation checkout initialized.';
        }

        return $isUpgrade
            ? 'PayPal upgrade checkout initialized.'
            : 'PayPal checkout initialized.';
    }
}
