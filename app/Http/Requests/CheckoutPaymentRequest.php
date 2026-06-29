<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Payment;
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
            'billing_day' => ['nullable', 'integer', Rule::in([1, 15])],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    $this->input('payment_type') === Invoice::PAYMENT_TYPE_INSTALLMENT
                    && $this->input('billing_day') === null
                ) {
                    $validator->errors()->add('billing_day', 'Monthly billing date is required for installment checkout.');
                }
            },
        ];
    }
}
