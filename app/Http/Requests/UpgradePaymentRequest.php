<?php

namespace App\Http\Requests;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'billing_day' => ['nullable', 'integer', Rule::in(Package::CUSTOMER_BILLING_DAY_OPTIONS)],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ((string) $this->input('payment_type') !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            $this->merge([
                'billing_day' => null,
            ]);

            return;
        }

        $package = $this->targetPackage();

        if (
            $package instanceof Package
            && (string) $this->input('payment_type') === Invoice::PAYMENT_TYPE_INSTALLMENT
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
                $hasBillingDay = $billingDay !== null && $billingDay !== '';

                if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
                    if ($hasBillingDay) {
                        $validator->errors()->add('billing_day', 'Billing day is only available for installment upgrade checkout.');
                    }

                    return;
                }

                if (! $package->checkoutAcceptsBillingDay()) {
                    if ($hasBillingDay) {
                        $validator->errors()->add('billing_day', 'Billing day is not available for this package.');
                    }

                    return;
                }

                if ($package->checkoutRequiresBillingDayChoice() && ! $hasBillingDay) {
                    $validator->errors()->add('billing_day', 'Billing day is required for this package checkout.');

                    return;
                }

                if ($hasBillingDay && ! in_array((int) $billingDay, $package->checkoutBillingDayOptions(), true)) {
                    $validator->errors()->add('billing_day', 'The selected billing day is not available for this package.');
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
