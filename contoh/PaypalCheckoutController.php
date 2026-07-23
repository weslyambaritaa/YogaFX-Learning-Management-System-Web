<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Student;
use App\Services\DepositPaymentService;
use App\Services\DepositNumberService;
use App\Services\AdminAlertService;
use App\Services\FinancialInvoiceSyncService;
use App\Services\HybridUpgradeService;
use App\Services\InstallmentConfirmationPdfService;
use App\Services\PaypalClient;
use App\Services\NotificationEmailService;
use App\Services\PublicInvoiceResolver;
use App\Support\PaypalPhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PaypalCheckoutController extends Controller
{
    public function createUpgradeOrder(Request $request)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
            'target_package_id' => ['required', 'integer'],
        ]);

        $student = Student::findByPublicRef((string) $data['student_token'], ['contact', 'package']);
        $package = Package::query()->find((int) $data['target_package_id']);

        if (! $student || ! $package) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid upgrade link.'],
            ]);
        }

        $upgrade = app(HybridUpgradeService::class);
        if (! $upgrade->canUpgrade($student, $package)) {
            throw ValidationException::withMessages([
                'target_package_id' => ['Invalid upgrade target.'],
            ]);
        }

        $quote = $upgrade->quote($student, $package);
        $remaining = (float) $quote['remaining'];
        if ($remaining <= 0.009) {
            return response()->json([
                'message' => 'No upgrade balance due.',
            ], 409);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();

        if (! app(HybridUpgradeService::class)->canUpgrade($student, $package)) {
            throw ValidationException::withMessages([
                'target_package_id' => ['Invalid upgrade target.'],
            ]);
        }

        $existing = Payment::query()
            ->where('student_id', $student->id)
            ->where('type', 'upgrade_balance_checkout')
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
            ->where('student_id', $student->id)
            ->where('type', 'upgrade_balance_checkout')
            ->where('paypal_env', $paypalEnv)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $payer = $this->buildPayerProfileFromStudent($student);
        $currency = (string) $quote['currency'];
        $order = $paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'YFX-UPGRADE-' . $student->id . '-' . $package->id,
                    'description' => 'YogaFX upgrade balance for ' . ($student->full_name ?: $student->email),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($remaining, 2, '.', ''),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => array_filter([
                    'name' => $payer['name'] ?? null,
                    'email_address' => $payer['email'] ?? null,
                    'phone' => $payer['phone'] ?? null,
                    'experience_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ], fn ($v) => ! is_null($v)),
            ],
        ]);

        Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $student->invoice_id,
            'type' => 'upgrade_balance_checkout',
            'source' => 'gateway',
            'amount' => $remaining,
            'currency' => $currency,
            'paypal_order_id' => $order['id'] ?? null,
            'paypal_env' => $paypalEnv,
            'status' => 'pending',
            'notes' => json_encode([
                'target_package_id' => $package->id,
                'credit_amount' => (float) $quote['credit'],
                'credit_currency' => $currency,
                'source_package_id' => $student->package_id,
            ]),
        ]);

        return response()->json([
            'order_id' => $order['id'] ?? null,
        ]);
    }

    public function captureUpgradeOrder(Request $request)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
            'target_package_id' => ['required', 'integer'],
            'order_id' => ['required', 'string'],
        ]);

        $student = Student::findByPublicRef((string) $data['student_token'], ['contact', 'package']);
        $package = Package::query()->find((int) $data['target_package_id']);
        if (! $student || ! $package) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid upgrade link.'],
            ]);
        }

        $existing = Payment::query()
            ->where('student_id', $student->id)
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'upgrade_balance_checkout')
            ->latest('id')
            ->first();

        if ($existing && $existing->status === 'paid') {
            return response()->json([
                'redirect_url' => route('installment.show', ['studentRef' => $student->public_token]),
            ]);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();
        $capture = $this->safeCaptureOrder($paypal, (string) $data['order_id'], 'upgrade_balance_checkout');
        if ($capture instanceof \Illuminate\Http\JsonResponse) {
            return $capture;
        }

        $status = (string) ($capture['status'] ?? '');
        if ($status !== 'COMPLETED') {
            $existing?->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
            ]);

            return response()->json([
                'message' => 'Payment not completed.',
                'paypal_status' => $status,
            ], 402);
        }

        $quote = app(HybridUpgradeService::class)->quote($student, $package);
        $expected = (float) $quote['remaining'];
        $amount = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
        $currency = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? '');
        if ($expected > 0 && (abs($amount - $expected) > 0.01 || $currency !== (string) $quote['currency'])) {
            $existing?->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
            ]);

            app(AdminAlertService::class)->upgradeFailed($student, 'Upgrade payment amount mismatch.', [
                'target_package_id' => $package->id,
                'target_package' => $package->name,
                'paypal_order_id' => (string) $data['order_id'],
                'expected_amount' => $expected,
                'expected_currency' => (string) $quote['currency'],
                'captured_amount' => $amount,
                'captured_currency' => $currency,
                'paypal_env' => $paypalEnv,
            ]);

            return response()->json([
                'message' => 'Payment amount mismatch.',
            ], 409);
        }

        $captureId = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? '');
        try {
            $result = app(HybridUpgradeService::class)->upgrade($student, $package);
        } catch (\Throwable $e) {
            $existing?->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
                'paypal_capture_id' => $captureId !== '' ? $captureId : null,
                'paid_at' => now(),
                'notes' => json_encode([
                    'target_package_id' => $package->id,
                    'paypal_order_id' => (string) $data['order_id'],
                    'upgrade_apply_failed' => true,
                    'error' => $e->getMessage(),
                ]),
            ]);

            app(AdminAlertService::class)->upgradeFailed($student, 'Upgrade payment was captured but the upgrade could not be applied.', [
                'target_package_id' => $package->id,
                'target_package' => $package->name,
                'paypal_order_id' => (string) $data['order_id'],
                'paypal_capture_id' => $captureId,
                'paypal_env' => $paypalEnv,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment was captured, but the upgrade could not be completed automatically. Admin has been notified.',
            ], 409);
        }
        $student->refresh();

        $existing?->update([
            'invoice_id' => $student->invoice_id,
            'status' => 'paid',
            'paypal_env' => $paypalEnv,
            'paypal_capture_id' => $captureId !== '' ? $captureId : null,
            'paid_at' => now(),
        ]);

        Payment::query()
            ->where('student_id', $student->id)
            ->where('type', 'upgrade_balance_checkout')
            ->where('status', 'pending')
            ->where('paypal_order_id', '!=', $data['order_id'])
            ->update(['status' => 'cancelled']);

        return response()->json([
            'redirect_url' => route('installment.show', ['studentRef' => $student->public_token]),
        ]);
    }

    public function createRemainingBalanceOrder(Request $request)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
        ]);

        $student = Student::query()
            ->where('public_token', $data['student_token'])
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid token.'],
            ]);
        }

        $pendingUpgrade = app(HybridUpgradeService::class)->pendingUpgrade($student->loadMissing(['contact', 'package']));
        if ($pendingUpgrade) {
            $quote = $pendingUpgrade['quote'];
            $remaining = (float) ($quote['remaining'] ?? 0);
            if ($remaining <= 0) {
                return response()->json([
                    'message' => 'No remaining balance.',
                ], 409);
            }

            $paypal = PaypalClient::fromSettings();
            $paypalEnv = $paypal->environment();

            $existing = Payment::query()
                ->where('student_id', $student->id)
                ->where('type', 'upgrade_balance_checkout')
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
                ->where('student_id', $student->id)
                ->where('type', 'upgrade_balance_checkout')
                ->where('paypal_env', $paypalEnv)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $payer = $this->buildPayerProfileFromStudent($student);
            $currency = (string) ($quote['currency'] ?? $student->financialCurrency());
            $order = $paypal->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'YFX-UPGRADE-' . $student->id . '-' . $pendingUpgrade['package']->id,
                        'description' => 'YogaFX upgrade balance for ' . ($student->full_name ?: $student->email),
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($remaining, 2, '.', ''),
                        ],
                    ],
                ],
                'payment_source' => [
                    'paypal' => array_filter([
                        'name' => $payer['name'] ?? null,
                        'email_address' => $payer['email'] ?? null,
                        'phone' => $payer['phone'] ?? null,
                        'experience_context' => [
                            'shipping_preference' => 'NO_SHIPPING',
                        ],
                    ], fn ($v) => ! is_null($v)),
                ],
            ]);

            Payment::query()->create([
                'student_id' => $student->id,
                'invoice_id' => $student->invoice_id,
                'type' => 'upgrade_balance_checkout',
                'source' => 'gateway',
                'amount' => $remaining,
                'currency' => $currency,
                'paypal_order_id' => $order['id'] ?? null,
                'paypal_env' => $paypalEnv,
                'status' => 'pending',
                'notes' => json_encode([
                    'target_package_id' => $pendingUpgrade['package']->id,
                    'credit_amount' => (float) ($quote['credit'] ?? 0),
                    'credit_currency' => $currency,
                    'source_package_id' => $student->package_id,
                ]),
            ]);

            return response()->json([
                'order_id' => $order['id'] ?? null,
            ]);
        }

        $invoice = $student->financialInvoice();

        if (! $invoice) {
            return response()->json([
                'message' => 'Missing invoice.',
            ], 409);
        }

        $remaining = (float) $student->remaining_balance;
        $currency = $student->financialCurrency();
        if ($remaining <= 0) {
            return response()->json([
                'message' => 'No remaining balance.',
            ], 409);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();

        // Prevent a pile of pending rows when user clicks / refreshes.
        $existing = Payment::query()
            ->where('student_id', $student->id)
            ->where('invoice_id', $student->invoice_id)
            ->where('type', 'remaining_balance_checkout')
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

        // Cancel older pending attempts before creating a new PayPal order.
        Payment::query()
            ->where('student_id', $student->id)
            ->where('invoice_id', $student->invoice_id)
            ->where('type', 'remaining_balance_checkout')
            ->where('paypal_env', $paypalEnv)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
            ]);
        $payer = $this->buildPayerProfileFromStudent($student->loadMissing('contact'));

        $order = $paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $invoice->invoice_number . '-RB',
                    'description' => 'YogaFX remaining balance for ' . $invoice->invoice_number,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($remaining, 2, '.', ''),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => array_filter([
                    'name' => $payer['name'] ?? null,
                    'email_address' => $payer['email'] ?? null,
                    'phone' => $payer['phone'] ?? null,
                    'experience_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ], fn ($v) => ! is_null($v)),
            ],
        ]);

        Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $student->invoice_id,
            'payment_plan_id' => null,
            'type' => 'remaining_balance_checkout',
            'source' => 'gateway',
            'amount' => $remaining,
            'currency' => $currency,
            'paypal_order_id' => $order['id'] ?? null,
            'paypal_env' => $paypalEnv,
            'status' => 'pending',
        ]);

        return response()->json([
            'order_id' => $order['id'] ?? null,
        ]);
    }

    public function captureRemainingBalanceOrder(Request $request)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
            'order_id' => ['required', 'string'],
        ]);

        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', (string) $data['student_token'])) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid token.'],
            ]);
        }

        $student = Student::query()
            ->where('public_token', $data['student_token'])
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid token.'],
            ]);
        }

        $pendingUpgrade = app(HybridUpgradeService::class)->pendingUpgrade($student->loadMissing(['contact', 'package']));
        if ($pendingUpgrade) {
            $existing = Payment::query()
                ->where('student_id', $student->id)
                ->where('paypal_order_id', $data['order_id'])
                ->where('type', 'upgrade_balance_checkout')
                ->latest('id')
                ->first();

            if ($existing && $existing->status === 'paid') {
                return response()->json([
                    'message' => 'Payment already captured.',
                ], 200);
            }

            $paypal = PaypalClient::fromSettings();
            $paypalEnv = $paypal->environment();
            $capture = $this->safeCaptureOrder($paypal, (string) $data['order_id'], 'upgrade_balance_checkout');
            if ($capture instanceof \Illuminate\Http\JsonResponse) {
                return $capture;
            }

            $status = (string) ($capture['status'] ?? '');
            if ($status !== 'COMPLETED') {
                $existing?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                ]);

                return response()->json([
                    'message' => 'Payment not completed.',
                    'paypal_status' => $status,
                ], 402);
            }

            $quote = $pendingUpgrade['quote'];
            $expected = (float) ($quote['remaining'] ?? 0);
            $amount = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
            $currency = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? '');
            if ($expected > 0 && (abs($amount - $expected) > 0.01 || $currency !== (string) ($quote['currency'] ?? ''))) {
                $existing?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                ]);

                app(AdminAlertService::class)->upgradeFailed($student, 'Upgrade balance payment amount mismatch.', [
                    'target_package_id' => $pendingUpgrade['package']->id,
                    'target_package' => $pendingUpgrade['package']->name,
                    'paypal_order_id' => (string) $data['order_id'],
                    'expected_amount' => $expected,
                    'expected_currency' => (string) ($quote['currency'] ?? ''),
                    'captured_amount' => $amount,
                    'captured_currency' => $currency,
                    'paypal_env' => $paypalEnv,
                ]);

                return response()->json([
                    'message' => 'Payment amount mismatch.',
                ], 409);
            }

            $captureId = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? '');
            try {
                $result = app(HybridUpgradeService::class)->upgrade($student, $pendingUpgrade['package']);
            } catch (\Throwable $e) {
                $existing?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                    'paypal_capture_id' => $captureId !== '' ? $captureId : null,
                    'paid_at' => now(),
                    'notes' => json_encode([
                        'target_package_id' => $pendingUpgrade['package']->id,
                        'paypal_order_id' => (string) $data['order_id'],
                        'upgrade_apply_failed' => true,
                        'error' => $e->getMessage(),
                    ]),
                ]);

                app(AdminAlertService::class)->upgradeFailed($student, 'Upgrade balance payment was captured but the upgrade could not be applied.', [
                    'target_package_id' => $pendingUpgrade['package']->id,
                    'target_package' => $pendingUpgrade['package']->name,
                    'paypal_order_id' => (string) $data['order_id'],
                    'paypal_capture_id' => $captureId,
                    'paypal_env' => $paypalEnv,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Payment was captured, but the upgrade could not be completed automatically. Admin has been notified.',
                ], 409);
            }
            $student->refresh();

            $existing?->update([
                'invoice_id' => $student->invoice_id,
                'status' => 'paid',
                'paypal_env' => $paypalEnv,
                'paypal_capture_id' => $captureId !== '' ? $captureId : null,
                'paid_at' => now(),
            ]);

            Payment::query()
                ->where('student_id', $student->id)
                ->where('type', 'upgrade_balance_checkout')
                ->where('status', 'pending')
                ->where('paypal_order_id', '!=', $data['order_id'])
                ->update(['status' => 'cancelled']);

            $student->status = 'paid_full';
            $student->save();

            if (filled($student->email)) {
                try {
                    $student->loadMissing('package');

                    $plan = new PaymentPlan([
                        'type' => 'full',
                        'months' => 1,
                        'monthly_amount' => $expected,
                        'billing_day' => 1,
                        'billing_timezone' => $student->package?->timezone ?? 'UTC',
                    ]);
                    $plan->setRelation('student', $student);
                    $plan->created_at = now();

                    $attachments = [
                        app(InstallmentConfirmationPdfService::class)->makeAttachment($student, $plan),
                    ];

                    app(NotificationEmailService::class)->sendTemplate(
                        NotificationEmailService::TEMPLATE_PAYMENT_COMPLETE_MASTERCLASS_FULL,
                        (string) $student->email,
                        [
                            'student' => [
                                'title' => $student->title,
                                'first_name' => $student->first_name,
                                'last_name' => $student->last_name,
                                'email' => $student->email,
                                'public_token' => $student->public_token,
                            ],
                            'package' => [
                                'name' => $student->package?->name ?? '',
                                'location' => $student->package?->location ?? '',
                                'start_at' => $student->package?->start_at?->format('M, jS Y') ?? '',
                                'end_at' => $student->package?->end_at?->format('M, jS Y') ?? '',
                            ],
                        ],
                        $attachments,
                    );
                } catch (\Throwable) {
                    // Payment should still succeed even if email fails.
                }
            }

            return response()->json([
                'message' => 'OK',
            ]);
        }

        $invoice = $student->financialInvoice();

        if (! $invoice) {
            return response()->json([
                'message' => 'Missing invoice.',
            ], 409);
        }

        if ($student->remaining_balance <= 0) {
            return response()->json([
                'message' => 'Remaining balance already paid.',
            ], 200);
        }

        $existing = Payment::query()
            ->where('student_id', $student->id)
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'remaining_balance_checkout')
            ->latest('id')
            ->first();

        if ($existing && $existing->status === 'paid') {
            return response()->json([
                'message' => 'Payment already captured.',
            ], 200);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();
        $capture = $this->safeCaptureOrder($paypal, (string) $data['order_id'], 'remaining_balance_checkout');
        if ($capture instanceof \Illuminate\Http\JsonResponse) {
            return $capture;
        }

        $status = (string) ($capture['status'] ?? '');
        if ($status !== 'COMPLETED') {
            Payment::query()
                ->where('student_id', $student->id)
                ->where('paypal_order_id', $data['order_id'])
                ->where('type', 'remaining_balance_checkout')
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                ]);

            return response()->json([
                'message' => 'Payment not completed.',
                'paypal_status' => $status,
            ], 402);
        }

        $expected = (float) $student->remaining_balance;
        $amount = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
        $currency = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? '');
        if ($expected > 0 && (abs($amount - $expected) > 0.01 || $currency !== $student->financialCurrency())) {
            Payment::query()
                ->where('student_id', $student->id)
                ->where('paypal_order_id', $data['order_id'])
                ->where('type', 'remaining_balance_checkout')
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                ]);

            return response()->json([
                'message' => 'Payment amount mismatch.',
            ], 409);
        }

        Payment::query()
            ->where('student_id', $student->id)
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'remaining_balance_checkout')
            ->latest('id')
            ->first()
            ?->update([
                'invoice_id' => $student->invoice_id,
                'status' => 'paid',
                'paypal_env' => $paypalEnv,
                'paid_at' => now(),
            ]);

        // Clean up older pending attempts (common when user retries).
        Payment::query()
            ->where('student_id', $student->id)
            ->where('invoice_id', $student->invoice_id)
            ->where('type', 'remaining_balance_checkout')
            ->where('status', 'pending')
            ->where('paypal_order_id', '!=', $data['order_id'])
            ->update([
                'status' => 'cancelled',
            ]);

        $student->status = 'paid_full';
        $student->save();

        $deliveryMode = (string) ($student->package?->delivery_mode ?? 'hybrid');
        $contactStatus = match ($deliveryMode) {
            'online', 'starter_kit' => 'online_enrolment',
            default => 'masterclass_enrolment',
        };
        $student->contact?->update(['status' => $contactStatus]);

        if (filled($student->email)) {
            try {
                $student->loadMissing('package');

                $balance = $student->remaining_balance;
                $plan = new PaymentPlan([
                    'type' => 'full',
                    'months' => 1,
                    'monthly_amount' => $balance,
                    'billing_day' => 1,
                    'billing_timezone' => $student->package?->timezone ?? 'UTC',
                ]);
                $plan->setRelation('student', $student);
                $plan->created_at = now();

                $deliveryMode = (string) ($student->package?->delivery_mode ?? 'hybrid');
                $attachments = $deliveryMode === 'starter_kit'
                    ? []
                    : [app(InstallmentConfirmationPdfService::class)->makeAttachment($student, $plan)];

                $templateKey = match ($deliveryMode) {
                    'online' => NotificationEmailService::TEMPLATE_PAYMENT_COMPLETE_ONLINE_FULL,
                    'starter_kit' => NotificationEmailService::TEMPLATE_PAYMENT_COMPLETE_STARTER_KIT_FULL,
                    default => NotificationEmailService::TEMPLATE_PAYMENT_COMPLETE_MASTERCLASS_FULL,
                };

                app(NotificationEmailService::class)->sendTemplate(
                    $templateKey,
                    (string) $student->email,
                    [
                        'student' => [
                            'title' => $student->title,
                            'first_name' => $student->first_name,
                            'last_name' => $student->last_name,
                            'email' => $student->email,
                            'public_token' => $student->public_token,
                        ],
                        'package' => [
                            'name' => $student->package?->name ?? '',
                            'location' => $student->package?->location ?? '',
                            'start_at' => $student->package?->start_at?->format('M, jS Y') ?? '',
                            'end_at' => $student->package?->end_at?->format('M, jS Y') ?? '',
                        ],
                    ],
                    $attachments,
                );
            } catch (\Throwable) {
                // Payment should still succeed even if email fails.
            }
        }

        return response()->json([
            'message' => 'OK',
        ]);
    }

    public function createDepositOrder(Request $request)
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
        $invoice = $this->resolveMasterclassPaymentInvoice($resolved);
        $financialInvoiceId = $invoice?->id;

        if (! $invoice) {
            throw ValidationException::withMessages([
                'public_token' => ['Invalid token.'],
            ]);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Deposit already paid.',
            ], 409);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'Deposit cancelled.',
            ], 410);
        }

        if (now()->greaterThan($invoice->expires_at) || $invoice->status === 'expired') {
            return response()->json([
                'message' => 'Deposit expired.',
            ], 410);
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

        // Prevent a pile of pending rows when user clicks / refreshes.
        $existing = Payment::query()
            ->where('invoice_id', $financialInvoiceId)
            ->where('type', 'deposit_checkout')
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

        // Cancel older pending attempts before creating a new PayPal order.
        Payment::query()
            ->where('invoice_id', $financialInvoiceId)
            ->where('type', 'deposit_checkout')
            ->where('paypal_env', $paypalEnv)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
            ]);
        $payer = $this->buildPayerProfileFromContact($invoice->contact);
        $paymentAmount = $this->masterclassInvoicePaymentAmount($invoice);

        $order = $paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $invoice->invoice_number,
                    'description' => 'YogaFX payment ' . $invoice->invoice_number,
                    'amount' => [
                        'currency_code' => $invoice->currency,
                        'value' => number_format($paymentAmount, 2, '.', ''),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => array_filter([
                    'name' => $payer['name'] ?? null,
                    'email_address' => $payer['email'] ?? null,
                    'phone' => $payer['phone'] ?? null,
                    'experience_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ], fn ($v) => ! is_null($v)),
            ],
        ]);

        Payment::query()->create([
            'student_id' => null,
            'invoice_id' => $financialInvoiceId,
            'payment_plan_id' => null,
            'type' => 'deposit_checkout',
            'source' => 'gateway',
            'amount' => $paymentAmount,
            'currency' => $invoice->currency,
            'paypal_order_id' => $order['id'] ?? null,
            'paypal_env' => $paypalEnv,
            'status' => 'pending',
        ]);

        return response()->json([
            'order_id' => $order['id'] ?? null,
        ]);
    }

    public function createPackageOrder(Request $request)
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        $package = Package::query()
            ->where('id', $data['package_id'])
            ->where('active', true)
            ->first();

        if (! $package) {
            return response()->json([
                'message' => 'Package not found.',
            ], 404);
        }

        $total = (float) ($package->base_price ?? 0);
        $depositAmount = $package->delivery_mode === 'online'
            ? ($total > 0 ? $total : (float) ($package->deposit_amount ?? 0))
            : (float) ($package->deposit_amount ?? 0);

        if ($depositAmount <= 0) {
            return response()->json([
                'message' => 'Invalid payment amount.',
            ], 409);
        }

        $alreadyEnrolledByEmail = Student::query()
            ->where('email', $data['email'])
            ->whereNull('archived_at')
            ->exists();
        if ($alreadyEnrolledByEmail) {
            return response()->json([
                'message' => 'You are already registered. Please contact our support.',
            ], 409);
        }

        $normalizedPhone = Contact::normalizePhone($data['phone'] ?? null);
        if (! is_string($normalizedPhone) || $normalizedPhone === '') {
            return response()->json([
                'message' => 'Phone number is invalid.',
            ], 422);
        }
        $contact = Contact::query()->where('email', $data['email'])->first();
        if (! $contact) {
            $contact = Contact::query()->where('phone', $normalizedPhone)->first();
        }

        if ($contact) {
            $contactEmail = strtolower(trim((string) ($contact->email ?? '')));
            $inputEmail = strtolower((string) $data['email']);

            if ($contactEmail !== '' && $inputEmail !== '' && $contactEmail !== $inputEmail) {
                $contact = null;
            }
        }

        if ($contact) {
            $alreadyEnrolled = Student::query()
                ->where('contact_id', $contact->id)
                ->whereNull('archived_at')
                ->exists();

            if ($alreadyEnrolled) {
                return response()->json([
                    'message' => 'You are already registered. Please contact our support.',
                ], 409);
            }
        }

        $payload = [
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $normalizedPhone,
            'country' => $data['country'] ?? null,
            'status' => 'deposit_sent',
        ];

        if ($contact) {
            $contact->fill($payload)->save();
        } else {
            $contact = Contact::query()->create($payload);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();

        $existing = Payment::query()
            ->where('type', 'deposit_checkout')
            ->where('paypal_env', $paypalEnv)
            ->where('status', 'pending')
            ->whereNotNull('paypal_order_id')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->whereHas('invoice', function ($query) use ($contact, $package) {
                $query->where('contact_id', $contact->id)
                    ->where('package_id', $package->id)
                    ->where('status', 'sent');
            })
            ->latest('id')
            ->first();

        if ($existing && is_string($existing->paypal_order_id) && $existing->paypal_order_id !== '') {
            return response()->json([
                'order_id' => $existing->paypal_order_id,
                'reused' => true,
            ]);
        }

        $financialInvoice = Invoice::query()->create([
            'invoice_number' => app(DepositNumberService::class)->next(),
            'public_token' => Str::random(32),
            'contact_id' => $contact->id,
            'package_id' => $package->id,
            'currency' => $package->base_currency ?: 'USD',
            'course_price' => $total,
            'deposit_required' => $depositAmount,
            'invoice_amount' => $depositAmount,
            'credit_amount' => 0,
            'total_paid' => 0,
            'balance_due' => $total,
            'status' => 'sent',
            'expires_at' => $package->offer_expires_at ?? now()->addDays(7),
            'sent_at' => now(),
            'metadata' => [
                'source' => 'package_checkout',
            ],
        ]);

        $order = $paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                        'reference_id' => $financialInvoice->invoice_number,
                        'description' => 'YogaFX payment ' . $financialInvoice->invoice_number,
                        'amount' => [
                            'currency_code' => $financialInvoice->currency,
                            'value' => number_format((float) ($financialInvoice->deposit_required ?: $financialInvoice->invoice_amount), 2, '.', ''),
                        ],
                    ],
            ],
            'payment_source' => [
                'paypal' => array_filter([
                    'name' => $this->buildPayerProfileFromContact($contact)['name'] ?? null,
                    'email_address' => $contact->email,
                    'phone' => PaypalPhoneNormalizer::normalize((string) $contact->phone, (string) ($contact->country ?? '')),
                    'experience_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ], fn ($v) => ! is_null($v)),
            ],
        ]);

        Payment::query()->create([
            'student_id' => null,
            'invoice_id' => $financialInvoice->id,
            'payment_plan_id' => null,
            'type' => 'deposit_checkout',
            'source' => 'gateway',
            'amount' => (float) ($financialInvoice->deposit_required ?: $financialInvoice->invoice_amount),
            'currency' => $financialInvoice->currency,
            'paypal_order_id' => $order['id'] ?? null,
            'paypal_env' => $paypalEnv,
            'status' => 'pending',
        ]);

        return response()->json([
            'order_id' => $order['id'] ?? null,
        ]);
    }

    public function capturePackageOrder(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $payment = Payment::query()
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'deposit_checkout')
            ->latest('id')
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Payment not found.',
            ], 404);
        }

        $invoice = $this->resolvePackageCheckoutInvoice($payment);
        if (! $invoice) {
            return response()->json([
                'message' => 'Invoice not found.',
            ], 404);
        }

        if (now()->greaterThan($invoice->expires_at) || $invoice->status === 'expired') {
            return response()->json([
                'message' => 'Invoice expired.',
            ], 410);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'Invoice cancelled.',
            ], 410);
        }

        if ($invoice->status === 'paid') {
            $studentToken = $invoice->student?->public_token;
            return response()->json([
                'message' => 'Invoice already paid.',
                'student_token' => $studentToken,
            ], 200);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();
        $capture = $this->safeCaptureOrder($paypal, (string) $data['order_id'], 'package_checkout');
        if ($capture instanceof \Illuminate\Http\JsonResponse) {
            return $capture;
        }

        $status = (string) ($capture['status'] ?? '');
        if ($status !== 'COMPLETED') {
            $invoice->fill([
                'status' => 'failed',
                'payment_failed_at' => now(),
            ])->save();

            $payment->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
            ]);

            return response()->json([
                'message' => 'Payment not completed.',
                'paypal_status' => $status,
            ], 402);
        }

        $expected = $this->masterclassInvoicePaymentAmount($invoice);
        $amount = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
        $currency = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? '');
        if ($expected > 0 && (abs($amount - $expected) > 0.01 || $currency !== (string) $invoice->currency)) {
            $payment->update([
                'status' => 'failed',
                'paypal_env' => $paypalEnv,
            ]);

            return response()->json([
                'message' => 'Payment amount mismatch.',
            ], 409);
        }

        $invoice->fill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $student = app(DepositPaymentService::class)->syncPaidInvoice($invoice);

        $payment->update([
            'student_id' => $student->id,
            'invoice_id' => $invoice->id ?: $student->invoice_id,
            'status' => 'paid',
            'paypal_env' => $paypalEnv,
            'paid_at' => now(),
        ]);

        Payment::query()
            ->where('invoice_id', $payment->invoice_id)
            ->where('type', 'deposit_checkout')
            ->where('status', 'pending')
            ->where('paypal_order_id', '!=', $data['order_id'])
            ->update([
                'status' => 'cancelled',
            ]);

        return response()->json([
            'message' => 'OK',
            'student_token' => $student->public_token,
            'redirect_url' => $this->resolveNextOnboardingUrl($student),
        ]);
    }

    public function captureDepositOrder(Request $request)
    {
        $data = $request->validate([
            'public_token' => ['required', 'string'],
            'order_id' => ['required', 'string'],
        ]);

        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', (string) $data['public_token'])) {
            throw ValidationException::withMessages([
                'public_token' => ['Invalid token.'],
            ]);
        }

        $resolved = app(PublicInvoiceResolver::class)->resolve((string) $data['public_token']);
        $invoice = $this->resolveMasterclassPaymentInvoice($resolved);
        $financialInvoiceId = $invoice?->id;

        if (! $invoice) {
            throw ValidationException::withMessages([
                'public_token' => ['Invalid token.'],
            ]);
        }

        if (now()->greaterThan($invoice->expires_at) || $invoice->status === 'expired') {
            return response()->json([
                'message' => 'Deposit expired.',
            ], 410);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'Deposit cancelled.',
            ], 410);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Deposit already paid.',
            ], 200);
        }

        $existing = Payment::query()
            ->where('invoice_id', $financialInvoiceId)
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'deposit_checkout')
            ->latest('id')
            ->first();

        if ($existing && $existing->status === 'paid') {
            return response()->json([
                'message' => 'Payment already captured.',
            ], 200);
        }

        $paypal = PaypalClient::fromSettings();
        $paypalEnv = $paypal->environment();
        $capture = $this->safeCaptureOrder($paypal, (string) $data['order_id'], 'deposit_checkout');
        if ($capture instanceof \Illuminate\Http\JsonResponse) {
            return $capture;
        }

        $status = (string) ($capture['status'] ?? '');
        if ($status !== 'COMPLETED') {
            $invoice->fill([
                'status' => 'failed',
                'payment_failed_at' => now(),
            ])->save();

            Payment::query()
                ->where('invoice_id', $financialInvoiceId)
                ->where('paypal_order_id', $data['order_id'])
                ->where('type', 'deposit_checkout')
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                ]);

            return response()->json([
                'message' => 'Payment not completed.',
                'paypal_status' => $status,
            ], 402);
        }

        $expected = $this->masterclassInvoicePaymentAmount($invoice);
        $amount = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
        $currency = (string) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? '');
        if ($expected > 0 && (abs($amount - $expected) > 0.01 || $currency !== (string) $invoice->currency)) {
            Payment::query()
                ->where('invoice_id', $financialInvoiceId)
                ->where('paypal_order_id', $data['order_id'])
                ->where('type', 'deposit_checkout')
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'failed',
                    'paypal_env' => $paypalEnv,
                ]);

            return response()->json([
                'message' => 'Payment amount mismatch.',
            ], 409);
        }

        $invoice->fill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $student = app(DepositPaymentService::class)->syncPaidInvoice($invoice);

        Payment::query()
            ->where('invoice_id', $financialInvoiceId ?? $student->invoice_id)
            ->where('paypal_order_id', $data['order_id'])
            ->where('type', 'deposit_checkout')
            ->latest('id')
            ->first()
            ?->update([
                'student_id' => $student->id,
                'invoice_id' => $financialInvoiceId ?? $student->invoice_id,
                'status' => 'paid',
                'paypal_env' => $paypalEnv,
                'paid_at' => now(),
            ]);

        // Clean up older pending attempts for this deposit.
        Payment::query()
            ->where('invoice_id', $financialInvoiceId ?? $student->invoice_id)
            ->where('type', 'deposit_checkout')
            ->where('status', 'pending')
            ->where('paypal_order_id', '!=', $data['order_id'])
            ->update([
                'status' => 'cancelled',
            ]);

        return response()->json([
            'message' => 'OK',
            'student_token' => $student->public_token,
            'redirect_url' => $this->resolveNextOnboardingUrl($student),
        ]);
    }

    private function resolvePackageCheckoutInvoice(Payment $payment): ?Invoice
    {
        if ($payment->invoice) {
            return $payment->invoice->loadMissing(['contact', 'package', 'student']);
        }

        return null;
    }

    private function resolveMasterclassPaymentInvoice(?array $resolved): ?Invoice
    {
        if (($resolved['invoice'] ?? null) instanceof Invoice) {
            return $resolved['invoice']->loadMissing(['contact', 'package']);
        }

        return null;
    }

    private function masterclassInvoicePaymentAmount(Invoice $invoice): float
    {
        return (float) ($invoice->deposit_required ?: $invoice->invoice_amount ?: $invoice->balance_due ?: $invoice->course_price);
    }

    private function resolveNextOnboardingUrl(Student $student): string
    {
        $student->loadMissing('package');

        $isOnline = (string) ($student->package?->delivery_mode ?? 'hybrid') === 'online';
        if ($isOnline && $student->enrollment_submitted_at) {
            return route('enrollment-thank-you.show', ['studentRef' => $student->public_token]);
        }

        return route('thank-you.installment', [
            'studentRef' => $student->public_token,
            'type' => 'full',
        ]);
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\JsonResponse
     */
    private function safeCaptureOrder(PaypalClient $paypal, string $orderId, string $flow)
    {
        try {
            return $paypal->captureOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('PayPal capture failed.', [
                'flow' => $flow,
                'order_id' => $orderId,
                'environment' => $paypal->environment(),
                'error' => $e->getMessage(),
            ]);

            $message = 'PayPal capture failed.';
            $status = 502;
            $raw = $e->getMessage();

            if (str_contains($raw, 'PayPal capture failed:')
                && preg_match('/PayPal capture failed:\s*(\d{3})\s*(.*)$/s', $raw, $m) === 1) {
                $remoteStatus = (int) $m[1];
                $remoteBody = trim((string) ($m[2] ?? ''));
                if ($remoteBody !== '') {
                    $message = $remoteBody;
                }
                $status = in_array($remoteStatus, [400, 401, 403, 404, 409, 422, 429], true)
                    ? $remoteStatus
                    : 502;
            }

            return response()->json(['message' => $message], $status);
        }
    }

    /**
     * @return array{name?:array<string,string>,email?:string,phone?:array<string,mixed>}
     */
    private function buildPayerProfileFromContact(mixed $contact): array
    {
        if (! $contact) {
            return [];
        }

        $first = trim((string) ($contact->first_name ?? ''));
        $last = trim((string) ($contact->last_name ?? ''));
        $email = trim((string) ($contact->email ?? ''));
        $phone = PaypalPhoneNormalizer::normalize((string) ($contact->phone ?? ''), (string) ($contact->country ?? ''));

        return array_filter([
            'name' => $first !== '' ? [
                'given_name' => $first,
                'surname' => $last !== '' ? $last : $first,
            ] : null,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone' => $phone,
        ], fn ($v) => ! is_null($v));
    }

    /**
     * @return array{name?:array<string,string>,email?:string,phone?:array<string,mixed>}
     */
    private function buildPayerProfileFromStudent(Student $student): array
    {
        $first = trim((string) ($student->first_name ?? ''));
        $last = trim((string) ($student->last_name ?? ''));
        $email = trim((string) ($student->email ?? ''));
        $fallbackCountry = (string) ($student->country ?: ($student->contact?->country ?? ''));
        $phone = PaypalPhoneNormalizer::normalize((string) ($student->whatsapp ?: ($student->contact?->phone ?? '')), $fallbackCountry);

        return array_filter([
            'name' => $first !== '' ? [
                'given_name' => $first,
                'surname' => $last !== '' ? $last : $first,
            ] : null,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone' => $phone,
        ], fn ($v) => ! is_null($v));
    }

}
