<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Services\Installments\InstallmentPlanCalculator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class CheckoutPaymentRequest extends FormRequest
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
                    Payment::METHOD_INTERNAL,
                ]),
            ],
            'checkout_mode' => [
    'nullable',
    'string',
    Rule::in([
        'card',
        'paypal',
        'mock',
        'internal',
    ]),
],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'billing_postcode' => ['nullable', 'string', 'max:50'],
            'billing_country' => ['nullable', 'string', 'max:120'],
            'billing_address_line_1' => ['nullable', 'string', 'max:255'],
            'billing_address_line_2' => ['nullable', 'string', 'max:255'],
            'donation_amount' => ['nullable', 'numeric', 'min:0'],

            /*
            |--------------------------------------------------------------------------
            | Installment fields
            |--------------------------------------------------------------------------
            |
            | billing_day:
            | Calon student memilih salah satu tanggal billing yang diaktifkan admin.
            | Nilainya hanya boleh 1 atau 15.
            |
            | installment_count:
            | Calon student memilih jumlah cicilan. Jumlah ini akan divalidasi agar
            | tidak melebihi maksimum berdasarkan payment date sampai deadline date.
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
        /** @var PendingRegistration|null $pendingRegistration */
        $pendingRegistration = $this->route('pendingRegistration');
        $package = $pendingRegistration?->package;

        $paymentType = (string) $this->input('payment_type');

        if ($this->has('donation_amount')) {
            $donationAmount = $this->input('donation_amount');

            $this->merge([
                'donation_amount' => $donationAmount === null || $donationAmount === ''
                    ? null
                    : (float) $donationAmount,
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
        | Non-installment checkout should not carry installment-only fields
        |--------------------------------------------------------------------------
        */
        if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            if ($package instanceof Package && $package->isFreePackage()) {
                $this->merge([
                    'payment_method' => Payment::METHOD_INTERNAL,
                ]);
            }

            return;
        }

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
                /** @var PendingRegistration|null $pendingRegistration */
                $pendingRegistration = $this->route('pendingRegistration');
                $package = $pendingRegistration?->package;

                if (! $package instanceof Package) {
                    return;
                }

                $paymentType = (string) $this->input('payment_type');
                $donationAmount = $this->input('donation_amount');

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
                            'Billing day is only available for installment checkout.'
                        );
                    }

                    if ($hasInstallmentCount) {
                        $validator->errors()->add(
                            'installment_count',
                            'Installment count is only available for installment checkout.'
                        );
                    }

                    if ($package->isFreePackage() && (string) $this->input('payment_method') !== Payment::METHOD_INTERNAL) {
                        $validator->errors()->add(
                            'payment_method',
                            'Free packages must continue without PayPal.'
                        );
                    }

                    if ($package->isDonationPackage()) {
                        if ($donationAmount === null || $donationAmount === '') {
                            $validator->errors()->add(
                                'donation_amount',
                                'Donation amount is required for this package.'
                            );

                            return;
                        }

                        if ((float) $donationAmount < $package->minimumDonationAmount()) {
                            $validator->errors()->add(
                                'donation_amount',
                                'Donation amount must be at least '.number_format($package->minimumDonationAmount(), 2, '.', '').'.'
                            );
                        }
                    }

                    return;
                }

                if (! $package->isPaidPackage()) {
                    $validator->errors()->add(
                        'payment_type',
                        'Installment checkout is only available for paid packages.'
                    );

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
                        'Installment checkout is not available for this package.'
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
                        'Billing day is required for installment checkout.'
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
                        'Installment count is required for installment checkout.'
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
                | Maksimum dihitung dari tanggal checkout hari ini sampai
                | installment_deadline_date berdasarkan billing_day pilihan student.
                |
                */
                try {
                    /** @var InstallmentPlanCalculator $calculator */
                    $calculator = app(InstallmentPlanCalculator::class);

                    $summary = $calculator->calculate(
                        package: $package,
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
                            'This package does not have enough available billing dates for installment checkout.'
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
                        $exception->getMessage() ?: 'Unable to validate installment count for this checkout.'
                    );
                }
            },
        ];
    }
}
