<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaypalSetting;
use App\Models\Student;
use App\Services\OnlineInvoicePaymentService;
use App\Services\PaypalClient;
use App\Services\PublicInvoiceResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnlinePaypalCheckoutController extends Controller
{
    public function createLinkPaymentOrder(Request $request, PaypalCheckoutController $checkout)
    {
        $data = $request->validate([
            'public_token' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        $resolved = app(PublicInvoiceResolver::class)->resolve((string) $data['public_token']);
        $invoice = $resolved['invoice']?->loadMissing(['contact', 'package']);
        $financialInvoiceId = $invoice?->id ?? null;

        if (! $invoice) {
            return $checkout->createDepositOrder($request);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Invoice already paid.'], 409);
        }
        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Invoice cancelled.'], 410);
        }
        if (now()->greaterThan($invoice->expires_at) || $invoice->status === 'expired') {
            return response()->json(['message' => 'Invoice expired.'], 410);
        }

        if ($invoice->contact) {
            $updates = array_filter([
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
            ], fn ($value) => ! is_null($value) && $value !== '');
            if ($updates !== []) {
                $invoice->contact->fill($updates)->save();
                $invoice->load('contact');
            }
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();

        $existing = Payment::query()
            ->where('invoice_id', $financialInvoiceId)
            ->where('type', 'online_invoice_checkout')
            ->where('paypal_env', $paypalEnv)
            ->where('status', 'pending')
            ->whereNotNull('paypal_order_id')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();

        if ($existing && is_string($existing->paypal_order_id) && $existing->paypal_order_id !== '') {
            return response()->json([
                'order_id' => $existing->paypal_order_id,
                'reused' => true,
            ]);
        }

        Payment::query()
            ->where('invoice_id', $financialInvoiceId)
            ->where('type', 'online_invoice_checkout')
            ->where('paypal_env', $paypalEnv)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $order = $paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $invoice->invoice_number,
                'description' => 'YogaFX online invoice ' . $invoice->invoice_number,
                'amount' => [
                    'currency_code' => $invoice->currency,
                    'value' => number_format((float) $invoice->invoice_amount, 2, '.', ''),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ],
            ],
        ]);

        Payment::query()->create([
            'student_id' => null,
            'invoice_id' => $financialInvoiceId,
            'payment_plan_id' => null,
            'type' => 'online_invoice_checkout',
            'source' => 'gateway',
            'amount' => (float) $invoice->invoice_amount,
            'currency' => $invoice->currency,
            'paypal_order_id' => $order['id'] ?? null,
            'paypal_env' => $paypalEnv,
            'status' => 'pending',
        ]);

        return response()->json([
            'order_id' => $order['id'] ?? null,
        ]);
    }

    public function captureLinkPaymentOrder(Request $request, PaypalCheckoutController $checkout)
    {
        $data = $request->validate([
            'public_token' => ['required', 'string'],
            'order_id' => ['required', 'string'],
        ]);

        $resolved = app(PublicInvoiceResolver::class)->resolve((string) $data['public_token']);
        $invoice = $resolved['invoice']?->loadMissing(['contact', 'package']);
        $financialInvoiceId = $invoice?->id ?? null;

        if (! $invoice) {
            return $checkout->captureDepositOrder($request);
        }

        if (now()->greaterThan($invoice->expires_at) || $invoice->status === 'expired') {
            return response()->json(['message' => 'Invoice expired.'], 410);
        }
        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Invoice cancelled.'], 410);
        }
        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Invoice already paid.'], 200);
        }

        $existing = Payment::query()
            ->where('invoice_id', $financialInvoiceId)
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'online_invoice_checkout')
            ->latest('id')
            ->first();

        if ($existing && $existing->status === 'paid') {
            return response()->json(['message' => 'Payment already captured.'], 200);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();
        $capture = $paypal->captureOrder($data['order_id']);

        $status = (string) ($capture['status'] ?? '');
        if ($status !== 'COMPLETED') {
            $invoice->update([
                'status' => 'failed',
                'payment_failed_at' => now(),
            ]);

            $existing?->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
            ]);

            return response()->json([
                'message' => 'Payment not completed.',
                'paypal_status' => $status,
            ], 402);
        }

        $expected = (float) $invoice->invoice_amount;
        $amount = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
        $currency = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? '');
        if ($expected > 0 && (abs($amount - $expected) > 0.01 || $currency !== (string) $invoice->currency)) {
            $existing?->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
            ]);

            return response()->json(['message' => 'Payment amount mismatch.'], 409);
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $student = app(OnlineInvoicePaymentService::class)->syncPaidInvoice($invoice);

        $existing?->update([
            'student_id' => $student->id,
            'invoice_id' => $financialInvoiceId ?? $student->invoice_id,
            'status' => 'paid',
            'paypal_env' => $paypalEnv,
            'paid_at' => now(),
        ]);

        Payment::query()
            ->where('invoice_id', $financialInvoiceId ?? $student->invoice_id)
            ->where('type', 'online_invoice_checkout')
            ->where('status', 'pending')
            ->where('paypal_order_id', '!=', $data['order_id'])
            ->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'OK',
            'student_token' => $student->public_token,
            'redirect_url' => $student->enrollment_submitted_at
                ? route('enrollment-thank-you.show', ['studentRef' => $student->public_token])
                : route('thank-you.installment', ['studentRef' => $student->public_token, 'type' => 'full']),
        ]);
    }

    public function createPaymentOrder(Request $request, PaypalCheckoutController $checkout)
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
        ]);

        $isOnlinePackage = \App\Models\Package::query()
            ->where('id', (int) $data['package_id'])
            ->whereIn('delivery_mode', ['online', 'starter_kit'])
            ->exists();

        if (! $isOnlinePackage) {
            return response()->json(['message' => 'Online package not found.'], 404);
        }

        return $checkout->createPackageOrder($request);
    }

    public function capturePaymentOrder(Request $request, PaypalCheckoutController $checkout)
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $payment = Payment::query()
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'deposit_checkout')
            ->with('invoice.package')
            ->latest('id')
            ->first();

        $isOnline = in_array((string) ($payment?->invoice?->package?->delivery_mode ?? ''), ['online', 'starter_kit'], true);
        if (! $isOnline) {
            return response()->json(['message' => 'Online payment not found.'], 404);
        }

        return $checkout->capturePackageOrder($request);
    }

    public function createRemainingBalanceOrder(Request $request, PaypalCheckoutController $checkout)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
        ]);

        $student = Student::query()
            ->with('package')
            ->where('public_token', (string) $data['student_token'])
            ->first();

        if (! $student || ! in_array((string) ($student->package?->delivery_mode ?? ''), ['online', 'starter_kit'], true)) {
            return response()->json(['message' => 'Online student not found.'], 404);
        }

        return $checkout->createRemainingBalanceOrder($request);
    }

    public function captureRemainingBalanceOrder(Request $request, PaypalCheckoutController $checkout)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
        ]);

        $student = Student::query()
            ->with('package')
            ->where('public_token', (string) $data['student_token'])
            ->first();

        if (! $student || ! in_array((string) ($student->package?->delivery_mode ?? ''), ['online', 'starter_kit'], true)) {
            return response()->json(['message' => 'Online student not found.'], 404);
        }

        return $checkout->captureRemainingBalanceOrder($request);
    }
}
