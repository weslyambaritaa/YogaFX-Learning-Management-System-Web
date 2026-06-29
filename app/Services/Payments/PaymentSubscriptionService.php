<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentSubscription;
use App\Models\PendingRegistration;
use App\Services\Installments\InstallmentPlanCalculator;
use App\Services\InvoiceNumberService;
use DomainException;
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
        int $billingDay,
    ): array {
        $pendingRegistration->loadMissing('package', 'accessTier');

        /** @var Package|null $package */
        $package = $pendingRegistration->package;
        if (! $package instanceof Package || ! $this->installmentPlanCalculator->isEligible($package)) {
            abort(422, 'This package is not eligible for installment checkout.');
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

        if (
            $existingPreparedSubscription instanceof PaymentSubscription
            && $existingPreparedSubscription->invoice instanceof Invoice
            && (int) $existingPreparedSubscription->billing_day === $billingDay
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
            && (int) $existingPreparedSubscription->billing_day !== $billingDay
            && (
                (is_string($existingPreparedSubscription->provider_subscription_id) && $existingPreparedSubscription->provider_subscription_id !== '')
                || $existingPreparedSubscription->status !== PaymentSubscription::STATUS_DRAFT
            )
        ) {
            abort(409, 'Monthly billing date cannot be changed after PayPal approval has started.');
        }

        try {
            $installmentPlan = $this->installmentPlanCalculator->calculate(
                $package,
                $pendingRegistration->checkout_opened_at ?? now(),
                $billingDay,
            );
        } catch (DomainException) {
            abort(422, 'This package is not eligible for installment checkout.');
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
                'provider' => 'paypal',
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

            $planId = $this->providerPlanIdForBillingDay($package, $billingDay);
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

        DB::transaction(function () use ($package, $created, $productId, $planId, $urls, $billingDay): void {
            $metadata = is_array($package->metadata) ? $package->metadata : [];
            $paypalPlanIds = is_array($metadata['paypal_plan_ids'] ?? null)
                ? $metadata['paypal_plan_ids']
                : [];
            $paypalPlanIds[(string) $billingDay] = $planId;
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

    public function markApprovalCancelled(PaymentSubscription $paymentSubscription): PaymentSubscription
    {
        $paymentSubscription->forceFill([
            'status' => PaymentSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        return $paymentSubscription->fresh();
    }

    private function providerPlanIdForBillingDay(Package $package, int $billingDay): ?string
    {
        $metadata = is_array($package->metadata) ? $package->metadata : [];
        $paypalPlanIds = is_array($metadata['paypal_plan_ids'] ?? null)
            ? $metadata['paypal_plan_ids']
            : [];
        $planId = $paypalPlanIds[(string) $billingDay] ?? null;

        if (is_string($planId) && $planId !== '') {
            return $planId;
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
}
