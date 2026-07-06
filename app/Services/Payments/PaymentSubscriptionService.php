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

        $existingBillingDayMatches = $existingPreparedSubscription instanceof PaymentSubscription
            && (int) ($existingPreparedSubscription->billing_day ?? 0) === (int) ($billingDay ?? 0);

        $existingInstallmentCountMatches = $existingPreparedSubscription instanceof PaymentSubscription
            && (int) ($existingPreparedSubscription->installment_count ?? 0) === (int) ($installmentCount ?? 0);

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && $existingPreparedSubscription->invoice instanceof Invoice
            && $existingBillingDayMatches
            && $existingInstallmentCountMatches
            && is_string($existingPreparedSubscription->provider_plan_id)
            && $existingPreparedSubscription->provider_plan_id !== ''
        ) {
            return [
                'invoice' => $existingPreparedSubscription->invoice,
                'payment_subscription' => $existingPreparedSubscription,
                'provider_plan_id' => $existingPreparedSubscription->provider_plan_id,
            ];
        }

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && (
                ! $existingBillingDayMatches
                || ! $existingInstallmentCountMatches
            )
            && (
                (is_string($existingPreparedSubscription->provider_subscription_id) && $existingPreparedSubscription->provider_subscription_id !== '')
                || $existingPreparedSubscription->status !== PaymentSubscription::STATUS_DRAFT
            )
        ) {
            abort(409, 'Installment billing configuration cannot be changed after PayPal approval has started.');
        }

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

        /** @var array{invoice: Invoice, payment_subscription: PaymentSubscription} $created */
        $created = DB::transaction(function () use ($pendingRegistration, $package, $installmentPlan, $billingDay, $existingPreparedSubscription): array {
            $pendingRegistration->forceFill([
                'installment_billing_day' => $billingDay,
            ])->save();

            if (
                $existingPreparedSubscription instanceof PaymentSubscription
                && $existingPreparedSubscription->invoice instanceof Invoice
                && $existingPreparedSubscription->status === PaymentSubscription::STATUS_DRAFT
                && ! is_string($existingPreparedSubscription->provider_subscription_id)
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
                        'installment_plan' => $installmentPlan,
                        'selected_billing_day' => $billingDay,
                        'selected_installment_count' => $installmentPlan['installment_count'],
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
                    'installment_plan' => $installmentPlan,
                    'selected_billing_day' => $billingDay,
                    'selected_installment_count' => $installmentPlan['installment_count'],
                ],
            ]);

            return [
                'invoice' => $invoice,
                'payment_subscription' => $paymentSubscription,
            ];
        });

        try {
            $productId = $package->paypal_product_id;

            if (! is_string($productId) || $productId === '') {
                $productId = $this->provider->createProduct($package, $installmentPlan)['id'];
            }

            $planId = $this->providerPlanIdForInstallmentConfig(
                package: $package,
                billingDay: $billingDay,
                installmentCount: (int) $installmentPlan['installment_count'],
            );

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

        DB::transaction(function () use ($package, $created, $productId, $planId, $urls, $billingDay, $installmentPlan): void {
            $metadata = is_array($package->metadata) ? $package->metadata : [];

            $paypalPlanIds = is_array($metadata['paypal_plan_ids'] ?? null)
                ? $metadata['paypal_plan_ids']
                : [];

            $paypalPlanKey = $this->providerPlanKeyForInstallmentConfig(
                billingDay: $billingDay,
                installmentCount: (int) $installmentPlan['installment_count'],
            );

            $paypalPlanIds[$paypalPlanKey] = $planId;
            $metadata['paypal_plan_ids'] = $paypalPlanIds;

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
                    'provider_prepare_stage' => 'plan_ready',
                    'provider_return_url' => $urls['return_url'],
                    'provider_cancel_url' => $urls['cancel_url'],
                    'selected_billing_day' => $billingDay,
                    'selected_installment_count' => (int) $installmentPlan['installment_count'],
                    'provider_plan_key' => $paypalPlanKey,
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

        $existingBillingDayMatches = $existingPreparedSubscription instanceof PaymentSubscription
            && (int) ($existingPreparedSubscription->billing_day ?? 0) === (int) ($billingDay ?? 0);

        $existingInstallmentCountMatches = $existingPreparedSubscription instanceof PaymentSubscription
            && (int) ($existingPreparedSubscription->installment_count ?? 0) === (int) ($installmentCount ?? 0);

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && $existingPreparedSubscription->invoice instanceof Invoice
            && $existingBillingDayMatches
            && $existingInstallmentCountMatches
            && is_string($existingPreparedSubscription->provider_plan_id)
            && $existingPreparedSubscription->provider_plan_id !== ''
        ) {
            return [
                'invoice' => $existingPreparedSubscription->invoice,
                'payment_subscription' => $existingPreparedSubscription,
                'provider_plan_id' => $existingPreparedSubscription->provider_plan_id,
            ];
        }

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && (
                ! $existingBillingDayMatches
                || ! $existingInstallmentCountMatches
            )
            && (
                (is_string($existingPreparedSubscription->provider_subscription_id) && $existingPreparedSubscription->provider_subscription_id !== '')
                || $existingPreparedSubscription->status !== PaymentSubscription::STATUS_DRAFT
            )
        ) {
            abort(409, 'Installment billing configuration cannot be changed after PayPal approval has started.');
        }

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

        /** @var array{invoice: Invoice, payment_subscription: PaymentSubscription} $created */
        $created = DB::transaction(function () use (
            $user,
            $targetTier,
            $package,
            $installmentPlan,
            $billingDay,
            $existingPreparedSubscription
        ): array {
            if (
                $existingPreparedSubscription instanceof PaymentSubscription
                && $existingPreparedSubscription->invoice instanceof Invoice
                && $existingPreparedSubscription->status === PaymentSubscription::STATUS_DRAFT
                && ! is_string($existingPreparedSubscription->provider_subscription_id)
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
                        'installment_plan' => $installmentPlan,
                        'context' => 'upgrade',
                        'selected_billing_day' => $billingDay,
                        'selected_installment_count' => $installmentPlan['installment_count'],
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
                    'installment_plan' => $installmentPlan,
                    'context' => 'upgrade',
                    'selected_billing_day' => $billingDay,
                    'selected_installment_count' => $installmentPlan['installment_count'],
                ],
            ]);

            return [
                'invoice' => $invoice,
                'payment_subscription' => $paymentSubscription,
            ];
        });

        try {
            $productId = $package->paypal_product_id;

            if (! is_string($productId) || $productId === '') {
                $productId = $this->provider->createProduct($package, $installmentPlan)['id'];
            }

            $planId = $this->providerPlanIdForInstallmentConfig(
                package: $package,
                billingDay: $billingDay,
                installmentCount: (int) $installmentPlan['installment_count'],
            );

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

        DB::transaction(function () use ($package, $created, $productId, $planId, $urls, $billingDay, $installmentPlan): void {
            $metadata = is_array($package->metadata) ? $package->metadata : [];

            $paypalPlanIds = is_array($metadata['paypal_plan_ids'] ?? null)
                ? $metadata['paypal_plan_ids']
                : [];

            $paypalPlanKey = $this->providerPlanKeyForInstallmentConfig(
                billingDay: $billingDay,
                installmentCount: (int) $installmentPlan['installment_count'],
            );

            $paypalPlanIds[$paypalPlanKey] = $planId;
            $metadata['paypal_plan_ids'] = $paypalPlanIds;

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
                    'provider_prepare_stage' => 'plan_ready',
                    'provider_return_url' => $urls['return_url'],
                    'provider_cancel_url' => $urls['cancel_url'],
                    'selected_billing_day' => $billingDay,
                    'selected_installment_count' => (int) $installmentPlan['installment_count'],
                    'provider_plan_key' => $paypalPlanKey,
                ]),
            ])->save();
        });

        return [
            'invoice' => $created['invoice']->fresh(),
            'payment_subscription' => $created['payment_subscription']->fresh(),
            'provider_plan_id' => $planId,
        ];
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
                'context' => 'upgrade',
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

    private function providerPlanIdForInstallmentConfig(
        Package $package,
        ?int $billingDay,
        int $installmentCount,
    ): ?string {
        $metadata = is_array($package->metadata) ? $package->metadata : [];

        $paypalPlanIds = is_array($metadata['paypal_plan_ids'] ?? null)
            ? $metadata['paypal_plan_ids']
            : [];

        $planKey = $this->providerPlanKeyForInstallmentConfig($billingDay, $installmentCount);
        $planId = $paypalPlanIds[$planKey] ?? null;

        if (is_string($planId) && $planId !== '') {
            return $planId;
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy fallback
        |--------------------------------------------------------------------------
        |
        | Dulu plan hanya dibedakan berdasarkan billing_day. Sekarang jumlah cicilan
        | juga memengaruhi plan. Fallback ini hanya dipakai jika metadata lama masih
        | tersedia, tetapi plan baru akan disimpan dengan key yang lebih spesifik.
        |
        */
        $legacyPlanKey = $this->providerPlanKeyForBillingDay($billingDay);
        $legacyPlanId = $paypalPlanIds[$legacyPlanKey] ?? null;

        if (is_string($legacyPlanId) && $legacyPlanId !== '') {
            return $legacyPlanId;
        }

        if (
            in_array($package->fixed_billing_day, [null, $billingDay], true)
            && is_string($package->paypal_plan_id)
            && $package->paypal_plan_id !== ''
        ) {
            return $package->paypal_plan_id;
        }

        return null;
    }

    private function providerPlanKeyForInstallmentConfig(?int $billingDay, int $installmentCount): string
    {
        $billingDayKey = $billingDay === null ? 'default' : (string) $billingDay;

        return $billingDayKey.'_'.$installmentCount.'x';
    }

    private function providerPlanKeyForBillingDay(?int $billingDay): string
    {
        return $billingDay === null ? 'default' : (string) $billingDay;
    }
}