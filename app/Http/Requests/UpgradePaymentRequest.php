<?php

namespace App\Http\Requests;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Installments\InstallmentPlanCalculator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class UpgradePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_type' => [
                'required',
                'string',
                Rule::in([
                    Invoice::PAYMENT_TYPE_FULL,
                    Invoice::PAYMENT_TYPE_INSTALLMENT,
                ]),
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::in([
                    Payment::METHOD_PAYPAL,
                    Payment::METHOD_MOCK,
                ]),
            ],
            'checkout_mode' => [
                'nullable',
                'string',
                Rule::in([
                    'paypal',
                    'mock',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Installment fields
            |--------------------------------------------------------------------------
            |
            | billing_day:
            | Student memilih satu tanggal billing yang tersedia dari package.
            | Nilainya hanya boleh 1 atau 15.
            |
            | installment_count:
            | Student memilih jumlah cicilan. Jumlah ini divalidasi supaya tidak
            | melebihi maksimum berdasarkan deadline date package.
            |
            */
            'billing_day' => [
                'nullable',
                'integer',
                Rule::in(Package::CUSTOMER_BILLING_DAY_OPTIONS),
            ],
            'installment_count' => [
                'nullable',
                'integer',
                'min:2',
            ],

            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $paymentType = (string) $this->input('payment_type');

        /*
        |--------------------------------------------------------------------------
        | Normalize billing_day
        |--------------------------------------------------------------------------
        */
        if ($this->has('billing_day')) {
            $billingDay = $this->input('billing_day');

            $this->merge([
                'billing_day' => $billingDay === null || $billingDay === ''
                    ? null
                    : (int) $billingDay,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize installment_count
        |--------------------------------------------------------------------------
        */
        if ($this->has('installment_count')) {
            $installmentCount = $this->input('installment_count');

            $this->merge([
                'installment_count' => $installmentCount === null || $installmentCount === ''
                    ? null
                    : (int) $installmentCount,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Non-installment upgrade should not carry installment-only fields
        |--------------------------------------------------------------------------
        */
        if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return;
        }

        $package = $this->targetPackage();

        if ($package instanceof Package && $package->usesFixedInstallmentCount()) {
            $this->merge([
                'installment_count' => $package->configuredInstallmentCount(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | If package does not expose billing day, force billing_day to null
        |--------------------------------------------------------------------------
        */
        if (
            $package instanceof Package
            && ! $package->checkoutAcceptsBillingDay()
        ) {
            $this->merge([
                'billing_day' => null,
            ]);
        }
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $package = $this->targetPackage();

                if (! $package instanceof Package) {
                    return;
                }

                $paymentType = (string) $this->input('payment_type');

                $billingDay = $this->input('billing_day');
                $installmentCount = $this->input('installment_count');

                $hasBillingDay = $billingDay !== null && $billingDay !== '';
                $hasInstallmentCount = $installmentCount !== null && $installmentCount !== '';

                /*
                |--------------------------------------------------------------------------
                | Full payment must not send installment fields
                |--------------------------------------------------------------------------
                */
                if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
                    if ($hasBillingDay) {
                        $validator->errors()->add(
                            'billing_day',
                            'Billing day is only available for installment upgrade checkout.'
                        );
                    }

                    if ($hasInstallmentCount) {
                        $validator->errors()->add(
                            'installment_count',
                            'Installment count is only available for installment upgrade checkout.'
                        );
                    }

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Installment package eligibility
                |--------------------------------------------------------------------------
                */
                if (! $package->installment_enabled) {
                    $validator->errors()->add(
                        'payment_type',
                        'Installment upgrade checkout is not available for this package.'
                    );

                    return;
                }

                if (! $package->checkoutAcceptsBillingDay()) {
                    $validator->errors()->add(
                        'billing_day',
                        'Billing day is not available for this package.'
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | billing_day is required for new installment flow
                |--------------------------------------------------------------------------
                */
                if (! $hasBillingDay) {
                    $validator->errors()->add(
                        'billing_day',
                        'Billing day is required for installment upgrade checkout.'
                    );

                    return;
                }

                if (! in_array((int) $billingDay, $package->checkoutBillingDayOptions(), true)) {
                    $validator->errors()->add(
                        'billing_day',
                        'The selected billing day is not available for this package.'
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | installment_count is required for new installment flow
                |--------------------------------------------------------------------------
                */
                if (! $hasInstallmentCount) {
                    if ($package->usesFixedInstallmentCount()) {
                        return;
                    }

                    $validator->errors()->add(
                        'installment_count',
                        'Installment count is required for installment upgrade checkout.'
                    );

                    return;
                }

                $installmentCount = (int) $installmentCount;

                if (! $package->usesFixedInstallmentCount() && $installmentCount < Package::MIN_INSTALLMENT_COUNT) {
                    $validator->errors()->add(
                        'installment_count',
                        'Installment count must be at least '.Package::MIN_INSTALLMENT_COUNT.'.'
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Validate maximum installment count using calculator
                |--------------------------------------------------------------------------
                |
                | Untuk validasi request, amount tidak memengaruhi jumlah maksimum
                | installment. Karena itu kita cukup memakai nominal dummy 1.
                | Perhitungan nominal upgrade yang sebenarnya tetap dilakukan ulang
                | di PaymentCheckoutService.
                |
                */
                try {
                    /** @var InstallmentPlanCalculator $calculator */
                    $calculator = app(InstallmentPlanCalculator::class);

                    $summary = $calculator->calculateForAmount(
                        package: $package,
                        totalAmountOverride: 1,
                        checkoutAt: now(),
                        billingDay: (int) $billingDay,
                        installmentCount: null,
                    );

                    $maximumInstallmentCount = (int) ($summary['maximum_installment_count'] ?? 0);
                    $fixedInstallmentCount = $summary['fixed_installment_count'] ?? null;

                    if ($fixedInstallmentCount !== null && $installmentCount !== (int) $fixedInstallmentCount) {
                        $validator->errors()->add(
                            'installment_count',
                            "Installment count must match the configured fixed installment count of {$fixedInstallmentCount}."
                        );

                        return;
                    }

                    if ($maximumInstallmentCount < Package::MIN_INSTALLMENT_COUNT) {
                        $validator->errors()->add(
                            'installment_count',
                            'This package does not have enough available billing dates for installment upgrade checkout.'
                        );

                        return;
                    }

                    if ($installmentCount > $maximumInstallmentCount) {
                        $validator->errors()->add(
                            'installment_count',
                            "Installment count cannot be greater than {$maximumInstallmentCount} for the selected billing day."
                        );
                    }
                } catch (Throwable $exception) {
                    $validator->errors()->add(
                        'installment_count',
                        $exception->getMessage() ?: 'Unable to validate installment count for this upgrade checkout.'
                    );
                }
            },
        ];
    }

    private function targetPackage(): ?Package
    {
        /** @var AccessTier|null $accessTier */
        $accessTier = $this->route('accessTier');

        if (! $accessTier instanceof AccessTier) {
            return null;
        }

        return $accessTier->packages()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }
}
