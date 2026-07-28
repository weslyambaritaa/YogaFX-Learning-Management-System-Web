<?php

namespace Tests\Feature;

use App\Mail\TemplatedNotificationMail;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\PayPalService;
use App\Services\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutInvoiceReuseAndPaymentLinkEmailTest extends TestCase
{
    use RefreshDatabase;

    private function mockPayPalCreateOrder(int $times): void
    {
        $this->mock(PayPalService::class, function ($mock) use ($times): void {
            $mock->shouldReceive('createOrder')
                ->times($times)
                ->andReturnUsing(fn () => [
                    'order_id' => 'ORDER-'.uniqid('', true),
                    'approval_url' => 'https://paypal.example/approve',
                ]);
        });
    }

    public function test_retrying_pay_full_checkout_reuses_the_same_unpaid_invoice_and_sends_one_payment_link_email(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create(['slug' => AccessTier::SLUG_ONLINE]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'payment_type' => Package::PAYMENT_TYPE_PAID,
            'price' => 500,
            'installment_enabled' => false,
        ]);

        $this->mockPayPalCreateOrder(2);

        $pendingRegistration = app(PaymentCheckoutService::class)->createPendingRegistration([
            'package_id' => $package->id,
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
        ]);

        $first = app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_PAYPAL,
        ]);

        // Simulate the buyer abandoning the PayPal popup and retrying checkout.
        $second = app(PaymentCheckoutService::class)->startInitialCheckout($pendingRegistration, [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_PAYPAL,
        ]);

        $this->assertSame($first['invoice']->id, $second['invoice']->id);
        $this->assertSame(
            1,
            Invoice::query()->where('pending_registration_id', $pendingRegistration->id)->count(),
        );
        $this->assertSame(
            2,
            Payment::query()->where('invoice_id', $first['invoice']->id)->count(),
        );

        Mail::assertSent(TemplatedNotificationMail::class, 1);

        $expectedPathFragment = '/checkout/'.$pendingRegistration->id.'/'.$tier->slug;

        Mail::assertSent(
            TemplatedNotificationMail::class,
            fn (TemplatedNotificationMail $mail): bool => str_contains($mail->render(), $expectedPathFragment),
        );

        $this->assertDatabaseHas('email_logs', [
            'notification_type' => 'checkout_payment_link',
            'reference_type' => 'invoice',
            'reference_id' => $first['invoice']->id,
            'status' => 'sent',
        ]);
    }

    public function test_a_second_pending_registration_still_gets_its_own_invoice_and_email(): void
    {
        Mail::fake();

        $tier = AccessTier::factory()->create(['slug' => AccessTier::SLUG_ONLINE]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'payment_type' => Package::PAYMENT_TYPE_PAID,
            'price' => 500,
            'installment_enabled' => false,
        ]);

        $this->mockPayPalCreateOrder(2);

        $service = app(PaymentCheckoutService::class);

        $registrationOne = $service->createPendingRegistration([
            'package_id' => $package->id,
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone' => '+6281234567890',
            'country' => 'Indonesia',
        ]);

        $registrationTwo = $service->createPendingRegistration([
            'package_id' => $package->id,
            'first_name' => 'Beno',
            'last_name' => 'Stone',
            'email' => 'beno@example.com',
            'phone' => '+6281234567891',
            'country' => 'Indonesia',
        ]);

        $resultOne = $service->startInitialCheckout($registrationOne, [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_PAYPAL,
        ]);

        $resultTwo = $service->startInitialCheckout($registrationTwo, [
            'payment_type' => Invoice::PAYMENT_TYPE_FULL,
            'payment_method' => Payment::METHOD_PAYPAL,
        ]);

        $this->assertNotSame($resultOne['invoice']->id, $resultTwo['invoice']->id);
        $this->assertSame(2, Invoice::query()->count());
        Mail::assertSent(TemplatedNotificationMail::class, 2);
    }
}
