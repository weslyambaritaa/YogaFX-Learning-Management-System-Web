<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PendingRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                ]),
            ],
            'checkout_mode' => [
                'nullable',
                'string',
                Rule::in([
                    'card',
                    'paypal',
                    'mock',
                ]),
            ],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'billing_postcode' => ['nullable', 'string', 'max:50'],
            'billing_country' => ['nullable', 'string', 'max:120'],
            'billing_address_line_1' => ['nullable', 'string', 'max:255'],
            'billing_address_line_2' => ['nullable', 'string', 'max:255'],
            'billing_day' => ['nullable', 'integer', Rule::in(Package::CUSTOMER_BILLING_DAY_OPTIONS)],
            'terms_accepted' => ['required', 'accepted'],
        ];
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
                $billingDay = $this->input('billing_day');
                $hasBillingDay = $billingDay !== null && $billingDay !== '';

                if ($paymentType !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
                    if ($hasBillingDay) {
                        $validator->errors()->add('billing_day', 'Billing day is only available for installment checkout packages that expose it.');
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
}
