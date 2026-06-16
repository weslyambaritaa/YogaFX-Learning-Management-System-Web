<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\PaymentActivity;
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
                    PaymentActivity::METHOD_PAYPAL_CARD,
                    PaymentActivity::METHOD_BANK_TRANSFER,
                ]),
            ],
        ];
    }
}
