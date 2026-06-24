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

        try {
            if ($paymentActivity->status !== Payment::STATUS_SUCCESS) {
                $capture = $this->paypalService->captureOrder(
                    (string) $paymentActivity->payment_reference,
                );

                $providerStatus = strtoupper((string) ($capture['status'] ?? ''));

                if ($providerStatus === 'PENDING') {
                    $this->paymentFinalizer->keepPaymentPending(
                        $paymentActivity,
                        'Legacy PayPal callback returned pending.',
                    );

                    return $this->redirectBackToCheckout(
                        $invoice,
                        'PayPal is still processing this transaction. Please continue from the same checkout page.',
                    );
                }

                if ($providerStatus !== 'COMPLETED') {
                    $this->paymentFinalizer->failPendingPayment(
                        $paymentActivity,
                        'Legacy PayPal callback returned unexpected status: '.$providerStatus,
                    );

                    return $this->redirectBackToCheckout(
                        $invoice,
                        'This payment did not complete. Please try again from the same checkout page.',
                    );
                }
            }

            $result = $this->paymentFinalizer->finalizeSuccessfulPayment(
                $paymentActivity,
                (string) $paymentActivity->payment_reference,
            );
        } catch (\Throwable $throwable) {
            report($throwable);

            $this->paymentFinalizer->failPendingPayment(
                $paymentActivity,
                'Legacy PayPal success callback failed before completion.',
            );

            return $this->redirectBackToCheckout(
                $invoice,
                'The PayPal payment could not be completed. Please try again from the same checkout page.',
            );
        }

        if ($invoice->type === Invoice::TYPE_UPGRADE) {
            return redirect()->away(
                $this->paymentFlow->upgradePaymentSuccessUrl($result['invoice'])
            );
        }

        abort_unless(
            $result['onboarding_state'] !== null,
            409,
            'Onboarding continuation is not available for this invoice.',
        );

        return redirect()->away(
            $this->paymentFlow->paymentSuccessUrl($result['onboarding_state'])
        );
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $paymentActivity = $this->paymentActivityForInvoiceAndToken(
            $invoice,
            (string) $request->query('token', ''),
        );

        $this->paymentFinalizer->cancelPendingPayment($paymentActivity);

        return $this->redirectBackToCheckout(
            $invoice,
            'The PayPal checkout was cancelled. Your billing details are still there, so you can try again right away.',
        );
    }

    private function redirectBackToCheckout(Invoice $invoice, string $message): RedirectResponse
    {
        if ($invoice->type === Invoice::TYPE_UPGRADE) {
            return redirect()
                ->route('student.upgrades.show', $invoice->access_tier_id)
                ->withErrors(['payment_method' => $message]);
        }

        abort_unless($invoice->pendingRegistration, 404);

        return redirect()
            ->away($this->paymentFlow->checkoutUrl($invoice->pendingRegistration))
            ->withErrors(['payment_method' => $message]);
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