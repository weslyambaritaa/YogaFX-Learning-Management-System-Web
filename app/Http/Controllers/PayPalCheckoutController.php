<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PayPalService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentFinalizerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayPalCheckoutController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypalService,
        private readonly PaymentFinalizerService $paymentFinalizer,
        private readonly PaymentCheckoutService $paymentFlow,
    ) {}

    public function success(Request $request, Invoice $invoice): RedirectResponse
    {
        $paymentActivity = $this->paymentActivityForInvoiceAndToken(
            $invoice,
            (string) $request->query('token', ''),
        );

        if ($paymentActivity->status !== Payment::STATUS_SUCCESS) {
            $this->paypalService->captureOrder((string) $paymentActivity->payment_reference);
        }

        $result = $this->paymentFinalizer->finalizeSuccessfulPayment(
            $paymentActivity,
            (string) $paymentActivity->payment_reference,
        );

        if ($invoice->type === Invoice::TYPE_UPGRADE) {
            return redirect()->away($this->paymentFlow->upgradePaymentSuccessUrl($result['invoice']));
        }

        abort_unless($result['onboarding_state'] !== null, 409, 'Onboarding continuation is not available for this invoice.');

        return redirect()->away($this->paymentFlow->paymentSuccessUrl($result['onboarding_state']));
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $paymentActivity = $this->paymentActivityForInvoiceAndToken(
            $invoice,
            (string) $request->query('token', ''),
        );

        $this->paymentFinalizer->cancelPendingPayment($paymentActivity);

        if ($invoice->type === Invoice::TYPE_UPGRADE) {
            return redirect()
                ->route('student.upgrades.show', $invoice->access_tier_id)
                ->withErrors(['payment_method' => 'The PayPal checkout was cancelled.']);
        }

        abort_unless($invoice->pendingRegistration, 404);

        return redirect()
            ->away($this->paymentFlow->checkoutUrl($invoice->pendingRegistration))
            ->withErrors(['payment_method' => 'The PayPal checkout was cancelled.']);
    }

    private function paymentActivityForInvoiceAndToken(Invoice $invoice, string $token): Payment
    {
        abort_if($token === '', 422, 'Missing PayPal order token.');

        $paymentActivity = $invoice->paymentActivities()
            ->where('payment_reference', $token)
            ->latest('id')
            ->first();

        abort_unless($paymentActivity instanceof Payment, 404);

        return $paymentActivity;
    }
}
