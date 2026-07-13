<?php

namespace App\Services\Payments;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentSubscription;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Installments\InstallmentPlanCalculator;
use App\Services\InvoiceNumberService;
use DomainException;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSubscriptionService
{
    private const PLAN_CACHE_VERSION = 'v3';

    private const CONTEXT_INITIAL = 'initial';

    private const CONTEXT_UPGRADE = 'upgrade';

    public function __construct(
        private readonly InvoiceNumberService $invoiceNumbers,
        private readonly InstallmentPlanCalculator $installmentPlanCalculator,
        private readonly PayPalSubscriptionService $provider,
    ) {}

    /**
     * @param  array{return_url: string, cancel_url: string}  $urls
     * @return array{invoice: Invoice, payment_subscription: PaymentSubscription, provider_plan_id: string}
     */
    public function startInitialCheckout(
        PendingRegistration $pendingRegistration,
        array $urls,
        ?int $billingDay,
        ?int $installmentCount = null,
    ): array {
        $pendingRegistration->loadMissing('package', 'accessTier');

        /** @var Package|null $package */
        $package = $pendingRegistration->package;

        if (! $package instanceof Package || ! $this->installmentPlanCalculator->isEligible($package)) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        $billingDay = $package->checkoutAcceptsBillingDay()
            ? $package->resolveInstallmentBillingDay($billingDay)
            : null;

        if ($installmentCount !== null) {
            $installmentCount = (int) $installmentCount;
        }

        $existingPreparedSubscription = PaymentSubscription::query()
            ->with('invoice')
            ->where('pending_registration_id', $pendingRegistration->id)
            ->whereIn('status', [
                PaymentSubscription::STATUS_DRAFT,
                PaymentSubscription::STATUS_APPROVAL_PENDING,
                PaymentSubscription::STATUS_ACTIVE,
            ])
            ->latest('id')
            ->first();

        try {
            $installmentPlan = $this->installmentPlanCalculator->calculate(
                package: $package,
                checkoutAt: $pendingRegistration->checkout_opened_at ?? now(),
                billingDay: $billingDay,
                installmentCount: $installmentCount,
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            abort(422, $exception->getMessage() ?: 'This package is not eligible for installment checkout.');
        }

        $planFingerprint = $this->providerPlanFingerprint(
            context: self::CONTEXT_INITIAL,
            package: $package,
            installmentPlan: $installmentPlan,
        );

        $existingConfigMatches = $existingPreparedSubscription instanceof PaymentSubscription
            && $this->preparedSubscriptionConfigMatches(
                paymentSubscription: $existingPreparedSubscription,
                installmentPlan: $installmentPlan,
                billingDay: $billingDay,
            );

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && $existingPreparedSubscription->invoice instanceof Invoice
            && $existingConfigMatches
            && $this->preparedSubscriptionCanReuse(
                paymentSubscription: $existingPreparedSubscription,
                context: self::CONTEXT_INITIAL,
                planFingerprint: $planFingerprint,
                installmentPlan: $installmentPlan,
                billingDay: $billingDay,
            )
        ) {
            return [
                'invoice' => $existingPreparedSubscription->invoice,
                'payment_subscription' => $existingPreparedSubscription,
                'provider_plan_id' => $existingPreparedSubscription->provider_plan_id,
            ];
        }

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && ! $existingConfigMatches
            && $this->paypalApprovalHasStarted($existingPreparedSubscription)
        ) {
            abort(409, 'Installment billing configuration cannot be changed after PayPal approval has started.');
        }

        /** @var array{invoice: Invoice, payment_subscription: PaymentSubscription} $created */
        $created = DB::transaction(function () use (
            $pendingRegistration,
            $package,
            $installmentPlan,
            $billingDay,
            $existingPreparedSubscription,
            $planFingerprint
        ): array {
            $pendingRegistration->forceFill([
                'installment_billing_day' => $billingDay,
            ])->save();

            if (
                $existingPreparedSubscription instanceof PaymentSubscription
                && $existingPreparedSubscription->invoice instanceof Invoice
                && $existingPreparedSubscription->status === PaymentSubscription::STATUS_DRAFT
                && ! $this->paypalApprovalHasStarted($existingPreparedSubscription)
            ) {
                $invoice = $existingPreparedSubscription->invoice;

                $invoice->forceFill([
                    'package_id' => $package->id,
                    'access_tier_id' => $pendingRegistration->access_tier_id,
                    'total_amount' => (float) $installmentPlan['total_amount'],
                    'balance_due' => (float) $installmentPlan['total_amount'],
                    'currency_code' => $installmentPlan['currency_code'],
                    'status' => Invoice::STATUS_UNPAID,
                    'issued_at' => $invoice->issued_at ?? now(),
                ])->save();

                $existingPreparedSubscription->forceFill([
                    'package_id' => $package->id,
                    'access_tier_id' => $pendingRegistration->access_tier_id,
                    'provider_product_id' => $package->paypal_product_id,
                    'provider_plan_id' => null,
                    'status' => PaymentSubscription::STATUS_DRAFT,
                    'installment_count' => $installmentPlan['installment_count'],
                    'installments_paid_count' => 0,
                    'currency_code' => $installmentPlan['currency_code'],
                    'total_amount' => (float) $installmentPlan['total_amount'],
                    'monthly_base_amount' => (float) $installmentPlan['monthly_base_amount'],
                    'first_payment_amount' => (float) $installmentPlan['first_payment_amount'],
                    'next_billing_amount' => (float) $installmentPlan['recurring_payment_amount'],
                    'billing_day' => $billingDay,
                    'started_at' => null,
                    'first_payment_paid_at' => null,
                    'next_due_at' => $installmentPlan['recurring_due_dates'][0] ?? null,
                    'final_due_at' => $installmentPlan['final_due_at'],
                    'grace_deadline_at' => $installmentPlan['grace_deadlines'][0] ?? null,
                    'completed_at' => null,
                    'suspended_at' => null,
                    'cancelled_at' => null,
                    'last_payment_failed_at' => null,
                    'last_synced_at' => null,
                    'metadata' => [
                        'context' => self::CONTEXT_INITIAL,
                        'installment_plan' => $installmentPlan,
                        'selected_billing_day' => $billingDay,
                        'installment_calculation_method' => $installmentPlan['installment_calculation_method'] ?? $package->normalizedInstallmentCalculationMethod(),
                        'installment_count_mode' => $installmentPlan['installment_count_mode'] ?? $package->normalizedInstallmentCountMode(),
                        'configured_installment_count' => $installmentPlan['configured_installment_count'] ?? $package->configuredInstallmentCount(),
                        'installment_count_selectable' => $installmentPlan['installment_count_selectable'] ?? $package->installmentCountSelectable(),
                        'selected_installment_count' => $installmentPlan['installment_count'],
                        'provider_plan_fingerprint' => $planFingerprint,
                    ],
                ])->save();

                return [
                    'invoice' => $invoice->fresh(),
                    'payment_subscription' => $existingPreparedSubscription->fresh(),
                ];
            }

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->invoiceNumbers->nextNumber(),
                'pending_registration_id' => $pendingRegistration->id,
                'package_id' => $package->id,
                'access_tier_id' => $pendingRegistration->access_tier_id,
                'type' => Invoice::TYPE_INITIAL,
                'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                'total_amount' => (float) $installmentPlan['total_amount'],
                'balance_due' => (float) $installmentPlan['total_amount'],
                'currency_code' => $installmentPlan['currency_code'],
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $paymentSubscription = PaymentSubscription::query()->create([
                'invoice_id' => $invoice->id,
                'package_id' => $package->id,
                'pending_registration_id' => $pendingRegistration->id,
                'access_tier_id' => $pendingRegistration->access_tier_id,
                'provider' => PaymentSubscription::PROVIDER_PAYPAL,
                'provider_product_id' => $package->paypal_product_id,
                'provider_plan_id' => null,
                'status' => PaymentSubscription::STATUS_DRAFT,
                'installment_count' => $installmentPlan['installment_count'],
                'installments_paid_count' => 0,
                'currency_code' => $installmentPlan['currency_code'],
                'total_amount' => (float) $installmentPlan['total_amount'],
                'monthly_base_amount' => (float) $installmentPlan['monthly_base_amount'],
                'first_payment_amount' => (float) $installmentPlan['first_payment_amount'],
                'next_billing_amount' => (float) $installmentPlan['recurring_payment_amount'],
                'billing_day' => $billingDay,
                'next_due_at' => $installmentPlan['recurring_due_dates'][0] ?? null,
                'final_due_at' => $installmentPlan['final_due_at'],
                'grace_deadline_at' => $installmentPlan['grace_deadlines'][0] ?? null,
                'metadata' => [
                    'context' => self::CONTEXT_INITIAL,
                    'installment_plan' => $installmentPlan,
                    'selected_billing_day' => $billingDay,
                    'installment_calculation_method' => $installmentPlan['installment_calculation_method'] ?? $package->normalizedInstallmentCalculationMethod(),
                    'installment_count_mode' => $installmentPlan['installment_count_mode'] ?? $package->normalizedInstallmentCountMode(),
                    'configured_installment_count' => $installmentPlan['configured_installment_count'] ?? $package->configuredInstallmentCount(),
                    'installment_count_selectable' => $installmentPlan['installment_count_selectable'] ?? $package->installmentCountSelectable(),
                    'selected_installment_count' => $installmentPlan['installment_count'],
                    'provider_plan_fingerprint' => $planFingerprint,
                ],
            ]);

            return [
                'invoice' => $invoice,
                'payment_subscription' => $paymentSubscription,
            ];
        });

        return $this->preparePlanForCheckout(
            package: $package,
            created: $created,
            urls: $urls,
            billingDay: $billingDay,
            installmentPlan: $installmentPlan,
            context: self::CONTEXT_INITIAL,
            planFingerprint: $planFingerprint,
        );
    }

    /**
     * @param  array{return_url: string, cancel_url: string}  $urls
     * @return array{invoice: Invoice, payment_subscription: PaymentSubscription, provider_plan_id: string}
     */
    public function startUpgradeCheckout(
        User $user,
        AccessTier $targetTier,
        Package $package,
        float $amountDue,
        array $urls,
        ?int $billingDay,
        ?int $installmentCount = null,
    ): array {
        if (! $this->installmentPlanCalculator->isEligible($package)) {
            abort(422, 'This package is not eligible for installment checkout.');
        }

        $billingDay = $package->checkoutAcceptsBillingDay()
            ? $package->resolveInstallmentBillingDay($billingDay)
            : null;

        if ($installmentCount !== null) {
            $installmentCount = (int) $installmentCount;
        }

        $existingPreparedSubscription = PaymentSubscription::query()
            ->with('invoice')
            ->where('user_id', $user->id)
            ->where('access_tier_id', $targetTier->id)
            ->whereHas('invoice', fn ($query) => $query->where('type', Invoice::TYPE_UPGRADE))
            ->whereIn('status', [
                PaymentSubscription::STATUS_DRAFT,
                PaymentSubscription::STATUS_APPROVAL_PENDING,
                PaymentSubscription::STATUS_ACTIVE,
            ])
            ->latest('id')
            ->first();

        try {
            $installmentPlan = $this->installmentPlanCalculator->calculateForAmount(
                package: $package,
                totalAmountOverride: $amountDue,
                checkoutAt: now(),
                billingDay: $billingDay,
                installmentCount: $installmentCount,
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            abort(422, $exception->getMessage() ?: 'This package is not eligible for installment checkout.');
        }

        $planFingerprint = $this->providerPlanFingerprint(
            context: self::CONTEXT_UPGRADE,
            package: $package,
            installmentPlan: $installmentPlan,
        );

        $existingConfigMatches = $existingPreparedSubscription instanceof PaymentSubscription
            && $this->preparedSubscriptionConfigMatches(
                paymentSubscription: $existingPreparedSubscription,
                installmentPlan: $installmentPlan,
                billingDay: $billingDay,
            );

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && $existingPreparedSubscription->invoice instanceof Invoice
            && $existingConfigMatches
            && $this->preparedSubscriptionCanReuse(
                paymentSubscription: $existingPreparedSubscription,
                context: self::CONTEXT_UPGRADE,
                planFingerprint: $planFingerprint,
                installmentPlan: $installmentPlan,
                billingDay: $billingDay,
            )
        ) {
            return [
                'invoice' => $existingPreparedSubscription->invoice,
                'payment_subscription' => $existingPreparedSubscription,
                'provider_plan_id' => $existingPreparedSubscription->provider_plan_id,
            ];
        }

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && ! $existingConfigMatches
            && $this->paypalApprovalHasStarted($existingPreparedSubscription)
        ) {
            abort(409, 'Installment billing configuration cannot be changed after PayPal approval has started.');
        }

        /** @var array{invoice: Invoice, payment_subscription: PaymentSubscription} $created */
        $created = DB::transaction(function () use (
            $user,
            $targetTier,
            $package,
            $installmentPlan,
            $billingDay,
            $existingPreparedSubscription,
            $planFingerprint
        ): array {
            if (
                $existingPreparedSubscription instanceof PaymentSubscription
                && $existingPreparedSubscription->invoice instanceof Invoice
                && $existingPreparedSubscription->status === PaymentSubscription::STATUS_DRAFT
                && ! $this->paypalApprovalHasStarted($existingPreparedSubscription)
            ) {
                $invoice = $existingPreparedSubscription->invoice;

                $invoice->forceFill([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'access_tier_id' => $targetTier->id,
                    'type' => Invoice::TYPE_UPGRADE,
                    'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                    'total_amount' => (float) $installmentPlan['total_amount'],
                    'balance_due' => (float) $installmentPlan['total_amount'],
                    'currency_code' => $installmentPlan['currency_code'],
                    'status' => Invoice::STATUS_UNPAID,
                    'issued_at' => $invoice->issued_at ?? now(),
                ])->save();

                $existingPreparedSubscription->forceFill([
                    'invoice_id' => $invoice->id,
                    'package_id' => $package->id,
                    'user_id' => $user->id,
                    'access_tier_id' => $targetTier->id,
                    'provider_product_id' => $package->paypal_product_id,
                    'provider_plan_id' => null,
                    'status' => PaymentSubscription::STATUS_DRAFT,
                    'installment_count' => $installmentPlan['installment_count'],
                    'installments_paid_count' => 0,
                    'currency_code' => $installmentPlan['currency_code'],
                    'total_amount' => (float) $installmentPlan['total_amount'],
                    'monthly_base_amount' => (float) $installmentPlan['monthly_base_amount'],
                    'first_payment_amount' => (float) $installmentPlan['first_payment_amount'],
                    'next_billing_amount' => (float) $installmentPlan['recurring_payment_amount'],
                    'billing_day' => $billingDay,
                    'started_at' => null,
                    'first_payment_paid_at' => null,
                    'next_due_at' => $installmentPlan['recurring_due_dates'][0] ?? null,
                    'final_due_at' => $installmentPlan['final_due_at'],
                    'grace_deadline_at' => $installmentPlan['grace_deadlines'][0] ?? null,
                    'completed_at' => null,
                    'suspended_at' => null,
                    'cancelled_at' => null,
                    'last_payment_failed_at' => null,
                    'last_synced_at' => null,
                    'metadata' => [
                        'context' => self::CONTEXT_UPGRADE,
                        'installment_plan' => $installmentPlan,
                        'selected_billing_day' => $billingDay,
                        'installment_calculation_method' => $installmentPlan['installment_calculation_method'] ?? $package->normalizedInstallmentCalculationMethod(),
                        'installment_count_mode' => $installmentPlan['installment_count_mode'] ?? $package->normalizedInstallmentCountMode(),
                        'configured_installment_count' => $installmentPlan['configured_installment_count'] ?? $package->configuredInstallmentCount(),
                        'installment_count_selectable' => $installmentPlan['installment_count_selectable'] ?? $package->installmentCountSelectable(),
                        'selected_installment_count' => $installmentPlan['installment_count'],
                        'provider_plan_fingerprint' => $planFingerprint,
                    ],
                ])->save();

                return [
                    'invoice' => $invoice->fresh(),
                    'payment_subscription' => $existingPreparedSubscription->fresh(),
                ];
            }

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->invoiceNumbers->nextNumber(),
                'user_id' => $user->id,
                'package_id' => $package->id,
                'access_tier_id' => $targetTier->id,
                'type' => Invoice::TYPE_UPGRADE,
                'payment_type' => Invoice::PAYMENT_TYPE_INSTALLMENT,
                'total_amount' => (float) $installmentPlan['total_amount'],
                'balance_due' => (float) $installmentPlan['total_amount'],
                'currency_code' => $installmentPlan['currency_code'],
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $paymentSubscription = PaymentSubscription::query()->create([
                'invoice_id' => $invoice->id,
                'package_id' => $package->id,
                'user_id' => $user->id,
                'access_tier_id' => $targetTier->id,
                'provider' => PaymentSubscription::PROVIDER_PAYPAL,
                'provider_product_id' => $package->paypal_product_id,
                'provider_plan_id' => null,
                'status' => PaymentSubscription::STATUS_DRAFT,
                'installment_count' => $installmentPlan['installment_count'],
                'installments_paid_count' => 0,
                'currency_code' => $installmentPlan['currency_code'],
                'total_amount' => (float) $installmentPlan['total_amount'],
                'monthly_base_amount' => (float) $installmentPlan['monthly_base_amount'],
                'first_payment_amount' => (float) $installmentPlan['first_payment_amount'],
                'next_billing_amount' => (float) $installmentPlan['recurring_payment_amount'],
                'billing_day' => $billingDay,
                'next_due_at' => $installmentPlan['recurring_due_dates'][0] ?? null,
                'final_due_at' => $installmentPlan['final_due_at'],
                'grace_deadline_at' => $installmentPlan['grace_deadlines'][0] ?? null,
                'metadata' => [
                    'context' => self::CONTEXT_UPGRADE,
                    'installment_plan' => $installmentPlan,
                    'selected_billing_day' => $billingDay,
                    'installment_calculation_method' => $installmentPlan['installment_calculation_method'] ?? $package->normalizedInstallmentCalculationMethod(),
                    'installment_count_mode' => $installmentPlan['installment_count_mode'] ?? $package->normalizedInstallmentCountMode(),
                    'configured_installment_count' => $installmentPlan['configured_installment_count'] ?? $package->configuredInstallmentCount(),
                    'installment_count_selectable' => $installmentPlan['installment_count_selectable'] ?? $package->installmentCountSelectable(),
                    'selected_installment_count' => $installmentPlan['installment_count'],
                    'provider_plan_fingerprint' => $planFingerprint,
                ],
            ]);

            return [
                'invoice' => $invoice,
                'payment_subscription' => $paymentSubscription,
            ];
        });

        return $this->preparePlanForCheckout(
            package: $package,
            created: $created,
            urls: $urls,
            billingDay: $billingDay,
            installmentPlan: $installmentPlan,
            context: self::CONTEXT_UPGRADE,
            planFingerprint: $planFingerprint,
        );
    }

    public function attachApprovedSubscription(
        PendingRegistration $pendingRegistration,
        PaymentSubscription $paymentSubscription,
        string $providerSubscriptionId,
    ): PaymentSubscription {
        abort_unless($paymentSubscription->pending_registration_id === $pendingRegistration->id, 404);
        abort_unless($paymentSubscription->provider === PaymentSubscription::PROVIDER_PAYPAL, 422, 'This subscription provider is not supported.');
        abort_if(! is_string($paymentSubscription->provider_plan_id) || $paymentSubscription->provider_plan_id === '', 422, 'PayPal subscription plan is not ready yet.');

        if (
            is_string($paymentSubscription->provider_subscription_id)
            && $paymentSubscription->provider_subscription_id !== ''
            && $paymentSubscription->provider_subscription_id !== $providerSubscriptionId
        ) {
            abort(409, 'A different PayPal subscription approval is already attached to this checkout.');
        }

        $paymentSubscription->forceFill([
            'provider_subscription_id' => $providerSubscriptionId,
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
            'last_synced_at' => now(),
            'metadata' => array_merge($paymentSubscription->metadata ?? [], [
                'approval_attached_at' => now()->toIso8601String(),
                'provider_status' => 'APPROVAL_PENDING',
            ]),
        ])->save();

        return $paymentSubscription->fresh(['invoice']);
    }

    public function attachApprovedUpgradeSubscription(
        User $user,
        PaymentSubscription $paymentSubscription,
        string $providerSubscriptionId,
    ): PaymentSubscription {
        abort_unless($paymentSubscription->user_id === $user->id, 404);
        abort_unless($paymentSubscription->provider === PaymentSubscription::PROVIDER_PAYPAL, 422, 'This subscription provider is not supported.');
        abort_if(! is_string($paymentSubscription->provider_plan_id) || $paymentSubscription->provider_plan_id === '', 422, 'PayPal subscription plan is not ready yet.');

        if (
            is_string($paymentSubscription->provider_subscription_id)
            && $paymentSubscription->provider_subscription_id !== ''
            && $paymentSubscription->provider_subscription_id !== $providerSubscriptionId
        ) {
            abort(409, 'A different PayPal subscription approval is already attached to this checkout.');
        }

        $paymentSubscription->forceFill([
            'provider_subscription_id' => $providerSubscriptionId,
            'status' => PaymentSubscription::STATUS_APPROVAL_PENDING,
            'last_synced_at' => now(),
            'metadata' => array_merge($paymentSubscription->metadata ?? [], [
                'approval_attached_at' => now()->toIso8601String(),
                'provider_status' => 'APPROVAL_PENDING',
                'context' => self::CONTEXT_UPGRADE,
            ]),
        ])->save();

        return $paymentSubscription->fresh(['invoice']);
    }

    public function markApprovalCancelled(PaymentSubscription $paymentSubscription): PaymentSubscription
    {
        $paymentSubscription->forceFill([
            'status' => PaymentSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        return $paymentSubscription->fresh();
    }

    /**
     * @param  array{invoice: Invoice, payment_subscription: PaymentSubscription}  $created
     * @param  array{return_url: string, cancel_url: string}  $urls
     * @param  array<string, mixed>  $installmentPlan
     * @return array{invoice: Invoice, payment_subscription: PaymentSubscription, provider_plan_id: string}
     */
    private function preparePlanForCheckout(
        Package $package,
        array $created,
        array $urls,
        ?int $billingDay,
        array $installmentPlan,
        string $context,
        string $planFingerprint,
    ): array {
        try {
            $productId = $package->paypal_product_id;

            if (! is_string($productId) || $productId === '') {
                $productId = $this->provider->createProduct($package, $installmentPlan)['id'];
            }

            $planId = $this->providerPlanIdForInstallmentFingerprint($package, $planFingerprint);

            if (! is_string($planId) || $planId === '') {
                $planId = $this->provider->createPlan($package, $installmentPlan, $productId)['id'];
            }
        } catch (\Throwable $throwable) {
            DB::transaction(function () use ($created, $throwable): void {
                $errorDetails = $throwable instanceof ValidationException
                    ? $throwable->errors()
                    : null;

                $created['payment_subscription']->forceFill([
                    'status' => PaymentSubscription::STATUS_FAILED,
                    'metadata' => array_merge($created['payment_subscription']->metadata ?? [], [
                        'provider_error' => $throwable->getMessage(),
                        'provider_error_details' => $errorDetails,
                    ]),
                ])->save();
            });

            throw $throwable;
        }

        DB::transaction(function () use (
            $package,
            $created,
            $productId,
            $planId,
            $urls,
            $billingDay,
            $installmentPlan,
            $context,
            $planFingerprint
        ): void {
            $metadata = is_array($package->metadata) ? $package->metadata : [];
            $paypalPlanIdsV2 = is_array($metadata['paypal_plan_ids_v2'] ?? null)
                ? $metadata['paypal_plan_ids_v2']
                : [];

            $paypalPlanIdsV2[$planFingerprint] = $planId;
            $metadata['paypal_plan_ids_v2'] = $paypalPlanIdsV2;

            if ($package->paypal_product_id !== $productId || $package->paypal_plan_id !== $planId || $package->metadata !== $metadata) {
                $package->forceFill([
                    'paypal_product_id' => $productId,
                    'paypal_plan_id' => $planId,
                    'metadata' => $metadata,
                ])->save();
            }

            $created['payment_subscription']->forceFill([
                'provider_product_id' => $productId,
                'provider_plan_id' => $planId,
                'status' => PaymentSubscription::STATUS_DRAFT,
                'metadata' => array_merge($created['payment_subscription']->metadata ?? [], [
                    'context' => $context,
                    'provider_prepare_stage' => 'plan_ready',
                    'provider_return_url' => $urls['return_url'],
                    'provider_cancel_url' => $urls['cancel_url'],
                    'selected_billing_day' => $billingDay,
                    'installment_calculation_method' => $installmentPlan['installment_calculation_method'] ?? $package->normalizedInstallmentCalculationMethod(),
                    'installment_count_mode' => $installmentPlan['installment_count_mode'] ?? $package->normalizedInstallmentCountMode(),
                    'configured_installment_count' => $installmentPlan['configured_installment_count'] ?? $package->configuredInstallmentCount(),
                    'installment_count_selectable' => $installmentPlan['installment_count_selectable'] ?? $package->installmentCountSelectable(),
                    'selected_installment_count' => (int) $installmentPlan['installment_count'],
                    'provider_plan_key' => $this->providerPlanKeyForInstallmentConfig(
                        billingDay: $billingDay,
                        installmentCount: (int) $installmentPlan['installment_count'],
                    ),
                    'provider_plan_fingerprint' => $planFingerprint,
                    'provider_plan_cache_version' => self::PLAN_CACHE_VERSION,
                ]),
            ])->save();
        });

        return [
            'invoice' => $created['invoice']->fresh(),
            'payment_subscription' => $created['payment_subscription']->fresh(),
            'provider_plan_id' => $planId,
        ];
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function preparedSubscriptionConfigMatches(
        PaymentSubscription $paymentSubscription,
        array $installmentPlan,
        ?int $billingDay,
    ): bool {
        return (int) ($paymentSubscription->billing_day ?? 0) === (int) ($billingDay ?? 0)
            && (int) ($paymentSubscription->installment_count ?? 0) === (int) ($installmentPlan['installment_count'] ?? 0)
            && (string) $paymentSubscription->currency_code === (string) ($installmentPlan['currency_code'] ?? '')
            && $this->amountToCents($paymentSubscription->total_amount) === $this->amountToCents($installmentPlan['total_amount'] ?? 0)
            && $this->amountToCents($paymentSubscription->first_payment_amount) === $this->amountToCents($installmentPlan['first_payment_amount'] ?? 0)
            && $this->amountToCents($paymentSubscription->next_billing_amount) === $this->amountToCents($installmentPlan['recurring_payment_amount'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function preparedSubscriptionCanReuse(
        PaymentSubscription $paymentSubscription,
        string $context,
        string $planFingerprint,
        array $installmentPlan,
        ?int $billingDay,
    ): bool {
        if (! is_string($paymentSubscription->provider_plan_id) || $paymentSubscription->provider_plan_id === '') {
            return false;
        }

        $metadata = is_array($paymentSubscription->metadata) ? $paymentSubscription->metadata : [];

        return ($metadata['provider_plan_cache_version'] ?? null) === self::PLAN_CACHE_VERSION
            && ($metadata['provider_plan_fingerprint'] ?? null) === $planFingerprint
            && ($metadata['context'] ?? self::CONTEXT_INITIAL) === $context
            && $this->preparedSubscriptionConfigMatches($paymentSubscription, $installmentPlan, $billingDay);
    }

    private function paypalApprovalHasStarted(PaymentSubscription $paymentSubscription): bool
    {
        return (is_string($paymentSubscription->provider_subscription_id) && $paymentSubscription->provider_subscription_id !== '')
            || $paymentSubscription->status !== PaymentSubscription::STATUS_DRAFT;
    }

    /**
     * @param  array<string, mixed>  $installmentPlan
     */
    private function providerPlanFingerprint(
        string $context,
        Package $package,
        array $installmentPlan,
    ): string {
        $billingDay = $installmentPlan['billing_day'] ?? null;
        $billingDayKey = $billingDay === null ? 'default' : (string) $billingDay;
        $intervalUnit = strtolower((string) ($installmentPlan['billing_interval_unit'] ?? 'month'));
        $intervalCount = max(1, (int) ($installmentPlan['billing_interval_count'] ?? 1));

        return sprintf(
            '%s_%s_pkg%s_day%s_%sx_%s_total%s_first%s_rec%s_%s%s',
            self::PLAN_CACHE_VERSION,
            strtolower($context),
            (string) $package->getKey(),
            $billingDayKey,
            (int) ($installmentPlan['installment_count'] ?? 0),
            strtoupper((string) ($installmentPlan['currency_code'] ?? '')),
            $this->amountToCents($installmentPlan['total_amount'] ?? 0),
            $this->amountToCents($installmentPlan['first_payment_amount'] ?? 0),
            $this->amountToCents($installmentPlan['recurring_payment_amount'] ?? 0),
            $intervalUnit,
            $intervalCount,
        );
    }

    private function providerPlanIdForInstallmentFingerprint(
        Package $package,
        string $planFingerprint,
    ): ?string {
        $metadata = is_array($package->metadata) ? $package->metadata : [];
        $paypalPlanIdsV2 = is_array($metadata['paypal_plan_ids_v2'] ?? null)
            ? $metadata['paypal_plan_ids_v2']
            : [];

        $planId = $paypalPlanIdsV2[$planFingerprint] ?? null;

        return is_string($planId) && $planId !== '' ? $planId : null;
    }

    private function providerPlanKeyForInstallmentConfig(?int $billingDay, int $installmentCount): string
    {
        $billingDayKey = $billingDay === null ? 'default' : (string) $billingDay;

        return $billingDayKey.'_'.$installmentCount.'x';
    }

    private function amountToCents(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }
}
