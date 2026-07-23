<?php

namespace App\Models;

class PaymentActivity extends Payment
{
    public const METHOD_PAYPAL_CARD = self::METHOD_PAYPAL;
    public const PAYMENT_TYPE_FULL = self::TYPE_PAY_FULL;
    public const PAYMENT_TYPE_INSTALLMENT = self::TYPE_INSTALLMENT;
}
