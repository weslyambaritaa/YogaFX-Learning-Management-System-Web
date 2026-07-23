<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalServiceTest extends TestCase
{
    public function test_it_marks_sandbox_environment_from_paypal_base_url(): void
    {
        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');

        $this->assertSame('sandbox', app(PayPalService::class)->environment());
    }

    public function test_it_marks_live_environment_from_paypal_base_url(): void
    {
        config()->set('services.paypal.base_url', 'https://api-m.paypal.com');

        $this->assertSame('live', app(PayPalService::class)->environment());
    }

    public function test_it_creates_checkout_order_without_shipping_preference_override(): void
    {
        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');
        config()->set('services.paypal.client_id', 'client-id');
        config()->set('services.paypal.secret', 'client-secret');

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-123',
                'links' => [
                    [
                        'rel' => 'approve',
                        'href' => 'https://www.paypal.com/checkoutnow?token=ORDER-123',
                    ],
                ],
            ]),
        ]);

        $invoice = new Invoice([
            'invoice_number' => 'INV-0001',
            'currency_code' => 'USD',
        ]);

        $payment = new Payment([
            'id' => 99,
            'amount_paid' => 300,
        ]);

        $response = app(PayPalService::class)->createOrder(
            $invoice,
            $payment,
            'https://example.com/success',
            'https://example.com/cancel',
        );

        $this->assertSame('ORDER-123', $response['order_id']);

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            return data_get($request->data(), 'application_context.shipping_preference') === null;
        });
    }
}
