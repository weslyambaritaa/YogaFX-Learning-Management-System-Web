<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentPlan;
use App\Models\PaypalSetting;
use App\Models\Package;
use App\Models\Student;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AdminAlertService;
use App\Services\InstallmentConfirmationPdfService;
use App\Services\HybridUpgradeService;
use App\Services\NotificationContextFactory;
use App\Services\NotificationEmailService;
use App\Services\NotificationWhatsappService;
use App\Services\PaypalClient;
use App\Support\CurrencyDisplay;
use App\Support\InstallmentAllocation;
use App\Support\InvoicePaymentViewData;
use App\Support\OpenGraphPackageData;
use App\Support\PaypalPhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaypalSubscriptionController extends Controller
{
    public function start(Request $request)
    {
        $data = $request->validate([
            'student_token' => ['required', 'string'],
            'billing_day' => ['required', 'integer', 'in:1,15'],
            'start_time' => ['nullable', 'string'],
        ]);

        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', (string) $data['student_token'])) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid token.'],
            ]);
        }

        $student = Student::query()->where('public_token', $data['student_token'])->first();
        if (! $student) {
            throw ValidationException::withMessages([
                'student_token' => ['Invalid token.'],
            ]);
        }

        $settings = PaypalSetting::query()->first();
        $paypalEnv = ($settings?->is_live ?? false) ? 'live' : 'sandbox';
        $planId = $settings?->getSubscriptionPlanIdForEnvironment($paypalEnv);
        if (! $planId) {
            return response()->json([
                'message' => 'Missing PayPal subscription plan id in settings.',
            ], 503);
        }

        $paypal = PaypalClient::fromSettings($paypalEnv);

        $paymentPlan = PaymentPlan::query()->create([
            'student_id' => $student->id,
            'contact_id' => $student->contact_id,
            'invoice_id' => $student->invoice_id,
            'type' => 'installment',
            'billing_day' => (int) $data['billing_day'],
            'billing_timezone' => 'UTC',
            'paypal_env' => $paypalEnv,
            'status' => 'active',
        ]);

        $returnUrl = url("/paypal/subscription/return?student_token={$student->public_token}");
        $cancelUrl = url("/paypal/subscription/cancel?student_token={$student->public_token}");

        $subscriber = array_filter([
            'name' => [
                'given_name' => $student->first_name,
                'surname' => $student->last_name ?: $student->first_name,
            ],
            'email_address' => $student->email ?: 'student@example.com',
            'phone' => PaypalPhoneNormalizer::normalize((string) ($student->whatsapp ?: ($student->contact?->phone ?? '')), (string) ($student->country ?: ($student->contact?->country ?? ''))),
        ], fn ($v) => ! is_null($v));

        $payload = [
            'plan_id' => $planId,
            'subscriber' => $subscriber,
            'application_context' => [
                'brand_name' => 'YogaFX Training',
                'locale' => 'en-US',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        if (is_string($data['start_time'] ?? null) && $data['start_time'] !== '') {
            $payload['start_time'] = $data['start_time'];
        }

        $subscription = $paypal->createSubscription($payload);

        $subscriptionId = $subscription['id'] ?? null;
        if (is_string($subscriptionId) && $subscriptionId !== '') {
            $paymentPlan->paypal_subscription_id = $subscriptionId;
            $paymentPlan->save();
        }

        $approveUrl = null;
        foreach (($subscription['links'] ?? []) as $link) {
            if (! is_array($link)) {
                continue;
            }
            if (($link['rel'] ?? null) === 'approve') {
                $approveUrl = $link['href'] ?? null;
                break;
            }
        }

        if (! is_string($approveUrl) || $approveUrl === '') {
            return response()->json([
                'message' => 'Missing approval link from PayPal.',
                'paypal' => $subscription,
            ], 502);
        }

        return response()->json([
            'approval_url' => $approveUrl,
            'subscription_id' => $subscriptionId,
        ]);
    }

    public function returned(Request $request)
    {
        $token = (string) $request->query('student_token', '');
        $planId = (int) $request->query('plan_id', 0);

        if ($token !== '') {
            if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $token)) {
                return redirect('/');
            }
            $student = Student::query()->with('package')->where('public_token', $token)->first();
            if (! $student) {
                return redirect('/');
            }

            return view('public.paypal-processing', [
                'studentToken' => $student->public_token,
                'planId' => null,
                'eventStartAt' => $student->package?->start_at,
                'openGraphPackage' => OpenGraphPackageData::fromPackage($student->package),
                'studentName' => $student->first_name ?: $student->full_name,
                'isUpgradeProcessing' => $this->isUpgradePlan(
                    PaymentPlan::query()
                        ->where('student_id', $student->id)
                        ->whereIn('type', ['installment', 'pay_in_4', 'upgrade_installment'])
                        ->latest('id')
                        ->first() ?: new PaymentPlan()
                ),
                'hasPreviousInstallment' => $this->hasPreviousSourceInstallment($student),
            ]);
        }

        if ($planId > 0) {
            $plan = PaymentPlan::query()->with('student.package')->find($planId);
            if (! $plan) {
                return redirect('/');
            }

            $student = $plan->student;

            return view('public.paypal-processing', [
                'studentToken' => $student?->public_token,
                'planId' => $plan->id,
                'eventStartAt' => $student?->package?->start_at,
                'openGraphPackage' => OpenGraphPackageData::fromPackage($student?->package),
                'studentName' => $student?->first_name ?: $student?->full_name,
                'isUpgradeProcessing' => $this->isUpgradePlan($plan),
                'hasPreviousInstallment' => $student ? $this->hasPreviousSourceInstallment($student, $plan) : false,
            ]);
        }

        return redirect('/');
    }

    public function finalize(Request $request)
    {
        $data = $request->validate([
            'student_token' => ['nullable', 'string'],
            'plan_id' => ['nullable', 'integer'],
        ]);

        $student = null;
        $plan = null;

        if (! empty($data['student_token'])) {
            if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', (string) $data['student_token'])) {
                return response()->json([
                    'message' => 'Invalid student token.',
                    'redirect_url' => url('/'),
                ], 404);
            }
            $student = Student::query()->where('public_token', $data['student_token'])->first();
            if (! $student) {
                return response()->json([
                    'message' => 'Invalid student token.',
                    'redirect_url' => url('/'),
                ], 404);
            }

            $plan = PaymentPlan::query()
                ->where('student_id', $student->id)
                ->whereIn('type', ['installment', 'pay_in_4', 'upgrade_installment'])
                ->latest('id')
                ->first();
        } elseif (! empty($data['plan_id'])) {
            $plan = PaymentPlan::query()->find((int) $data['plan_id']);
            if (! $plan) {
                return response()->json([
                    'message' => 'Payment plan not found.',
                    'redirect_url' => url('/'),
                ], 404);
            }

            $student = $plan->student ?: $this->resolveStudentFromPlan($plan);
        }

        if (! $plan) {
            return response()->json([
                'message' => 'Payment plan not found.',
                'redirect_url' => url('/'),
            ], 404);
        }

        $subscription = null;
        $status = null;
        $lastPaymentTime = null;
        $paypal = null;
        $firstPaymentWarning = false;
        if (filled($plan->paypal_subscription_id)) {
            try {
                $paypal = PaypalClient::fromSettings($plan->paypal_env ?? null);
                $subscription = $paypal->getSubscription((string) $plan->paypal_subscription_id);
                $status = (string) ($subscription['status'] ?? '');
                $lastPaymentTime = data_get($subscription, 'billing_info.last_payment.time');
                if ($status !== 'ACTIVE' && $status !== 'APPROVAL_PENDING') {
                    $terminal = in_array($status, ['CANCELLED', 'SUSPENDED', 'EXPIRED'], true);
                    if (! $terminal) {
                        return response()->json([
                            'status' => 'pending',
                            'message' => 'Subscription is still processing. Please wait a moment.',
                        ], 200);
                    }
                    $plan->status = 'cancelled';
                    $plan->save();
                    return response()->json([
                        'message' => 'Subscription not active.',
                        'redirect_url' => $student ? route('installment.show', ['studentRef' => $student->public_token]) : url('/'),
                    ], 409);
                }
            } catch (\Throwable) {
                return response()->json([
                    'message' => 'Unable to confirm subscription status.',
                    'redirect_url' => $student ? route('installment.show', ['studentRef' => $student->public_token]) : url('/'),
                ], 502);
            }
        }

        $isUpgradePlan = $this->isUpgradePlan($plan);

        if ((string) $plan->type === 'pay_in_4' || $isUpgradePlan) {
            // If PayPal already reports ACTIVE, allow the flow to continue even if
            // billing_info.last_payment hasn't propagated yet.
            $planMetadata = is_array($plan->metadata ?? null) ? $plan->metadata : [];
            $upgradeRequiresFirstPayment = (bool) ($planMetadata['requires_first_payment'] ?? true);
            $deliveryMode = (string) (
                $plan->invoice?->package?->delivery_mode
                ?? ''
            );
            $strictSetupFeeCheck = ($isUpgradePlan && $upgradeRequiresFirstPayment)
                || in_array($deliveryMode, ['online', 'starter_kit'], true);
            $firstPaymentConfirmed = $strictSetupFeeCheck ? false : ($status === 'ACTIVE');

            if (! $firstPaymentConfirmed && $paypal) {
                try {
                    $start = CarbonImmutable::instance($plan->created_at ?? now())
                        ->subDays(1)
                        ->utc()
                        ->format('Y-m-d\\TH:i:s\\Z');
                    $end = CarbonImmutable::now('UTC')->addDay()->format('Y-m-d\\TH:i:s\\Z');
                    $tx = $paypal->listSubscriptionTransactions((string) $plan->paypal_subscription_id, $start, $end);
                    $transactions = $tx['transactions'] ?? [];
                    if (is_array($transactions)) {
                        foreach ($transactions as $transaction) {
                            if (! is_array($transaction)) {
                                continue;
                            }
                            if (strtoupper((string) ($transaction['status'] ?? '')) === 'COMPLETED') {
                                $firstPaymentConfirmed = true;
                                break;
                            }
                        }
                    }
                } catch (\Throwable) {
                    // Ignore and fall back to pending state below.
                }
            }

            if (! $firstPaymentConfirmed && $strictSetupFeeCheck && ! $isUpgradePlan) {
                // Online pay-in-4: keep subscription active and continue onboarding.
                // Show warning on thank-you page instead of cancelling immediately.
                $firstPaymentWarning = true;
                $firstPaymentConfirmed = true;
            }

            if (! $firstPaymentConfirmed) {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'First payment is not confirmed yet.',
                ], 200);
            }
        }

        if (! $student) {
            $student = $this->resolveStudentFromPlan($plan);
        }

        if (! $student) {
            return response()->json([
                'message' => 'Student record not available.',
                'redirect_url' => url('/'),
            ], 404);
        }

        if ($plan && $isUpgradePlan) {
            $metadata = is_array($plan->metadata ?? null) ? $plan->metadata : [];
            $targetPackageId = (int) ($metadata['target_package_id'] ?? 0);
            $targetPackage = $targetPackageId > 0 ? Package::query()->find($targetPackageId) : null;
            $upgradeAlreadyApplied = $targetPackage
                && (int) ($student->package_id ?? 0) === (int) $targetPackage->id
                && $student->upgraded_at !== null;

            if ($targetPackage && ! $upgradeAlreadyApplied) {
                try {
                    app(HybridUpgradeService::class)->upgrade($student, $targetPackage);
                } catch (\Throwable $e) {
                    $newSubscriptionCancelError = null;
                    if ($paypal && filled($plan->paypal_subscription_id)) {
                        try {
                            $paypal->cancelSubscription((string) $plan->paypal_subscription_id, 'Upgrade failed because source installment could not be cancelled');
                        } catch (\Throwable $cancelError) {
                            $newSubscriptionCancelError = $cancelError->getMessage();
                            // Keep the original upgrade failure as the user-facing error.
                        }
                    }

                    $plan->status = 'cancelled';
                    $plan->save();

                    app(AdminAlertService::class)->upgradeFailed($student, 'Upgrade installment was approved by PayPal but the upgrade could not be applied.', [
                        'target_package_id' => $targetPackage->id,
                        'target_package' => $targetPackage->name,
                        'upgrade_payment_plan_id' => $plan->id,
                        'upgrade_subscription_id' => (string) ($plan->paypal_subscription_id ?? ''),
                        'paypal_env' => (string) ($plan->paypal_env ?? ''),
                        'upgrade_subscription_cancel_error' => (string) ($newSubscriptionCancelError ?? ''),
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'message' => $e->getMessage(),
                        'redirect_url' => route('installment.show', ['studentRef' => $student->public_token]),
                    ], 409);
                }

                $student = $student->fresh(['package']);
            }
        }

        if ($plan && $plan->status === 'pending') {
            $plan->status = 'active';
            $plan->save();
        }

        if (
            $plan &&
            $student &&
            is_string($student->email) && $student->email !== '' &&
            is_null($plan->confirmation_emailed_at)
        ) {
            $student->loadMissing('package');

            $planMetadata = is_array($plan->metadata ?? null) ? $plan->metadata : [];
            $setupFeeCountsAsInstallment = (string) ($plan->type ?? 'installment') === 'pay_in_4'
                || (bool) ($planMetadata['setup_fee_counts_as_installment'] ?? false);
            $invoice = $student->financialInvoice();
            $paymentView = InvoicePaymentViewData::make($invoice, $student, $plan);
            $currency = strtoupper((string) (
                $planMetadata['setup_fee_currency']
                ?? $paymentView['currency']
                ?? $student->financialCurrency()
            ));
            $installments = max(1, (int) ($plan->months ?? 1));
            $coursePrice = (float) (
                $planMetadata['course_price']
                ?? $paymentView['course_price']
                ?? $student->coursePriceAmount()
                ?? 0
            );
            if ($coursePrice <= 0) {
                $coursePrice = (float) ($student->package?->base_price ?? 0);
            }
            $monthly = (float) ($plan->monthly_amount ?? 0);
            $setupFeeAmount = (float) ($planMetadata['setup_fee_amount'] ?? 0);
            if ($setupFeeCountsAsInstallment && $setupFeeAmount <= 0) {
                $setupFeeAmount = $monthly;
            }
            $balanceTransferredTotal = (float) ($paymentView['balance_transferred_total'] ?? 0);
            $depositTransferred = $setupFeeCountsAsInstallment
                ? $setupFeeAmount
                : ($balanceTransferredTotal > 0.009 ? $balanceTransferredTotal : $student->creditedOrInitialPaidAmount());
            $remainingBeforeFirstPayment = max(0.0, (float) ($paymentView['remaining_balance'] ?? $student->remaining_balance ?? 0));
            $remaining = $setupFeeCountsAsInstallment
                ? max(0.0, $remainingBeforeFirstPayment - $depositTransferred)
                : $remainingBeforeFirstPayment;
            if ($monthly <= 0) {
                $recurringInstallments = $setupFeeCountsAsInstallment ? max(1, $installments - 1) : $installments;
                $monthly = $recurringInstallments > 0 ? ($remaining / $recurringInstallments) : $remaining;
            }
            if ($setupFeeCountsAsInstallment) {
                $lastAmount = (float) ($plan->last_amount ?? 0);
                if ($lastAmount <= 0) {
                    $recurringInstallments = max(1, $installments - 1);
                    $lastAmount = round($remaining - ($monthly * max(0, $recurringInstallments - 1)), 2);
                }
            } else {
                $lastAmount = round($remaining - ($monthly * ($installments - 1)), 2);
            }
            if ($lastAmount <= 0) {
                $lastAmount = $monthly;
            }

            $tz = (string) ($plan->billing_timezone ?: 'UTC');
            $billingDay = in_array((int) $plan->billing_day, [1, 15], true) ? (int) $plan->billing_day : 1;
            $base = $plan->created_at
                ? CarbonImmutable::parse((string) $plan->created_at, $tz)
                : CarbonImmutable::now($tz);
            $firstBilling = $base->day <= $billingDay
                ? $base->startOfMonth()->setDay($billingDay)
                : $base->addMonthNoOverflow()->startOfMonth()->setDay($billingDay);
            $isSetupFeeSchedule = $setupFeeCountsAsInstallment;
            $lastBilling = $firstBilling->addMonthsNoOverflow(max(0, $installments - ($isSetupFeeSchedule ? 2 : 1)));
            $planTransactions = Payment::query()
                ->where('student_id', $student->id)
                ->where('payment_plan_id', $plan->id)
                ->orderByRaw('COALESCE(paid_at, created_at) asc')
                ->get(['status', 'amount', 'paid_at', 'created_at'])
                ->map(fn (Payment $p) => [
                    'status' => (string) ($p->status ?? ''),
                    'amount' => (float) ($p->amount ?? 0),
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'created_at' => $p->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
            $tableSetupFeeAmount = $setupFeeCountsAsInstallment ? $setupFeeAmount : $depositTransferred;
            $installmentTable = $this->buildInstallmentTableHtml(
                $installments,
                $monthly,
                $lastAmount,
                $firstBilling,
                $currency,
                $isSetupFeeSchedule,
                $plan->created_at ? CarbonImmutable::parse((string) $plan->created_at, $tz) : null,
                $tableSetupFeeAmount,
                $planTransactions
            );

            $context = [
                'student' => [
                    'title' => (string) ($student->title ?? ''),
                    'first_name' => (string) ($student->first_name ?? ''),
                    'last_name' => (string) ($student->last_name ?? ''),
                    'email' => (string) ($student->email ?? ''),
                    'public_token' => (string) ($student->public_token ?? ''),
                ],
                'package' => [
                    'name' => (string) ($student->package?->name ?? ''),
                    'location' => (string) ($student->package?->location ?? ''),
                    'start_at' => $student->package?->start_at?->format('M, jS Y') ?? '',
                    'end_at' => $student->package?->end_at?->format('M, jS Y') ?? '',
                    'currency' => $currency,
                    'price' => $coursePrice,
                    'price_formatted' => CurrencyDisplay::format($currency, $coursePrice, 0),
                ],
                'links' => [
                    'confirmation_link' => route('confirmation.show', ['studentRef' => (string) ($student->public_token ?: $student->id)]),
                ],
                'payment_plan' => [
                    'id' => (int) $plan->id,
                    'type' => (string) ($plan->type ?? 'installment'),
                    'is_pay_in_4' => (string) ($plan->type ?? 'installment') === 'pay_in_4',
                    'billing_day' => (int) ($plan->billing_day ?? 1),
                    'billing_day_label' => $billingDay === 1 ? '1st' : '15th',
                    'months' => (int) ($plan->months ?? 1),
                    'monthly_amount' => (float) ($plan->monthly_amount ?? 0),
                    'monthly_amount_formatted' => CurrencyDisplay::format($currency, $monthly, 2),
                    'last_amount_formatted' => CurrencyDisplay::format($currency, $lastAmount, 2),
                    'course_price' => CurrencyDisplay::format($currency, $coursePrice, 0),
                    'deposit_transferred' => CurrencyDisplay::format($currency, $depositTransferred, 0),
                    'deposit_transferred_amount' => $depositTransferred,
                    'balance_due' => CurrencyDisplay::format($currency, $remaining, 2),
                    'first_billing_at' => $firstBilling->format('M, jS Y'),
                    'last_billing_at' => $lastBilling->format('M, jS Y'),
                    'paypal_subscription_id' => (string) ($plan->paypal_subscription_id ?? ''),
                    'installment_table_html' => $installmentTable,
                ],
            ];
            $context = app(NotificationContextFactory::class)->make(
                $student,
                $invoice,
                null,
                $plan,
                $context
            );

            $deliveryMode = (string) ($student->package?->delivery_mode ?? 'hybrid');
            $templateKey = match ($deliveryMode) {
                'online' => NotificationEmailService::TEMPLATE_PAYMENT_PLAN_CONFIRMED_ONLINE,
                'starter_kit' => NotificationEmailService::TEMPLATE_PAYMENT_PLAN_CONFIRMED_STARTER_KIT,
                default => NotificationEmailService::TEMPLATE_PAYMENT_PLAN_CONFIRMED,
            };

            try {
                $attachments = $deliveryMode === 'starter_kit'
                    ? []
                    : [app(InstallmentConfirmationPdfService::class)->makeAttachment($student, $plan)];

                app(NotificationEmailService::class)->sendTemplate(
                    $templateKey,
                    (string) $student->email,
                    $context,
                    $attachments,
                );

                if (in_array($deliveryMode, ['online', 'starter_kit'], true) && filled($student->whatsapp)) {
                    app(NotificationWhatsappService::class)->sendTemplate(
                        $deliveryMode === 'starter_kit'
                            ? NotificationWhatsappService::TEMPLATE_PAYMENT_PLAN_CONFIRMED_STARTER_KIT
                            : NotificationWhatsappService::TEMPLATE_PAYMENT_PLAN_CONFIRMED_ONLINE,
                        (string) $student->whatsapp,
                        $context,
                    );
                }

                $plan->confirmation_emailed_at = now();
                $plan->save();
            } catch (\Throwable $e) {
                Log::warning('Payment plan confirmation notification failed.', [
                    'payment_plan_id' => $plan->id,
                    'student_id' => $student->id,
                    'template_key' => $templateKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'is_upgrade' => $this->isUpgradePlan($plan),
            'redirect_url' => $student && $student->enrollment_submitted_at
                ? route('enrollment-thank-you.show', ['studentRef' => $student->public_token])
                : route('thank-you.installment', [
                    'studentRef' => $student?->public_token ?? '',
                    'type' => (string) $plan->type === 'pay_in_4' ? 'pay_in_4' : 'installment',
                    'first_payment_failed' => $firstPaymentWarning ? 1 : null,
                ]),
        ]);
    }

    public function cancelled(Request $request)
    {
        $token = (string) $request->query('student_token', '');
        $planId = (int) $request->query('plan_id', 0);

        if ($token !== '') {
            $student = Student::query()->where('public_token', $token)->first();
            if (! $student) {
                return redirect('/');
            }

            PaymentPlan::query()
                ->where('student_id', $student->id)
                ->whereIn('type', ['installment', 'pay_in_4', 'upgrade_installment'])
                ->latest('id')
                ->first()
                ?->update(['status' => 'cancelled']);

            return redirect()->route('installment.show', ['studentRef' => $student->public_token]);
        }

        if ($planId > 0) {
            $plan = PaymentPlan::query()->find($planId);
            if ($plan) {
                $plan->update(['status' => 'cancelled']);

                $redirectUrl = $this->resolvePlanPublicLink($plan);
                if ($redirectUrl) {
                    return redirect()->to($redirectUrl);
                }
            }
        }

        return redirect('/');
    }

    private function isUpgradePlan(PaymentPlan $plan): bool
    {
        if ((string) $plan->type === 'upgrade_installment') {
            return true;
        }

        $metadata = is_array($plan->metadata ?? null) ? $plan->metadata : [];

        return array_key_exists('target_package_id', $metadata)
            && array_key_exists('source_package_id', $metadata)
            && array_key_exists('credit_amount', $metadata);
    }

    private function hasPreviousSourceInstallment(Student $student, ?PaymentPlan $currentPlan = null): bool
    {
        return PaymentPlan::query()
            ->where('student_id', $student->id)
            ->whereIn('type', ['installment', 'pay_in_4'])
            ->whereNotIn('status', ['cancelled', 'canceled', 'completed'])
            ->when($currentPlan?->id, fn ($query) => $query->where('id', '!=', $currentPlan->id))
            ->get()
            ->contains(fn (PaymentPlan $plan): bool => ! $this->isUpgradePlan($plan));
    }

    private function resolveStudentFromPlan(PaymentPlan $plan): ?Student
    {
        if ($plan->student_id) {
            return $plan->student;
        }

        $invoice = $this->resolveFinancialInvoiceFromPlan($plan);
        if (! $invoice) {
            return null;
        }

        return $this->resolveStudentFromFinancialInvoice($plan, $invoice);
    }

    private function resolveFinancialInvoiceFromPlan(PaymentPlan $plan): ?Invoice
    {
        if ($plan->invoice_id) {
            return Invoice::query()
                ->with(['contact', 'package'])
                ->find((int) $plan->invoice_id);
        }

        $studentInvoiceId = $plan->student_id
            ? Student::query()->whereKey((int) $plan->student_id)->value('invoice_id')
            : null;
        if ($studentInvoiceId) {
            $invoice = Invoice::query()
                ->with(['contact', 'package'])
                ->find((int) $studentInvoiceId);
            if ($invoice) {
                $this->syncPlanInvoiceReference($plan, $invoice);
                return $invoice;
            }
        }

        if ($plan->student_id) {
            $invoice = Invoice::query()
                ->with(['contact', 'package'])
                ->where('student_id', (int) $plan->student_id)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->latest('id')
                ->first();

            if (! $invoice) {
                $invoice = Invoice::query()
                    ->with(['contact', 'package'])
                    ->where('student_id', (int) $plan->student_id)
                    ->latest('id')
                    ->first();
            }

            if ($invoice) {
                $this->syncPlanInvoiceReference($plan, $invoice);
                return $invoice;
            }
        }

        return null;
    }

    private function syncPlanInvoiceReference(PaymentPlan $plan, Invoice $invoice): void
    {
        $dirty = false;

        if ((int) ($plan->invoice_id ?? 0) !== (int) $invoice->id) {
            $plan->invoice_id = $invoice->id;
            $dirty = true;
        }

        if ((int) ($plan->contact_id ?? 0) !== (int) ($invoice->contact_id ?? 0)) {
            $plan->contact_id = $invoice->contact_id;
            $dirty = true;
        }

        if ($dirty) {
            $plan->save();
        }
    }

    private function resolvePlanPublicLink(PaymentPlan $plan): ?string
    {
        $invoice = $plan->invoice?->loadMissing(['package', 'contact']) ?? $this->resolveFinancialInvoiceFromPlan($plan);

        if ($invoice?->public_token) {
            return $invoice->publicLink();
        }

        return null;
    }

    private function resolveStudentFromFinancialInvoice(PaymentPlan $plan, Invoice $invoice): ?Student
    {
        $invoice->loadMissing(['contact', 'package']);

        if (! $invoice->contact_id || ! $invoice->package_id) {
            return null;
        }

        $student = Student::query()
            ->where('contact_id', $invoice->contact_id)
            ->where('package_id', $invoice->package_id)
            ->whereNull('archived_at')
            ->first();

        if (! $student) {
            $student = Student::query()->create([
                'public_token' => \Illuminate\Support\Str::random(32),
                'contact_id' => $invoice->contact_id,
                'package_id' => $invoice->package_id,
                'invoice_id' => $invoice->id,
                'title' => $invoice->contact?->title,
                'first_name' => $invoice->contact?->first_name ?? 'Student',
                'last_name' => $invoice->contact?->last_name,
                'email' => $invoice->contact?->email,
                'whatsapp' => $invoice->contact?->phone,
                'currency' => $invoice->currency,
                'total_price' => (float) $invoice->course_price,
                'deposit_paid' => 0,
                'status' => 'active',
            ]);
        }

        $student->invoice_id = $invoice->id;
        $student->title = $invoice->contact?->title;
        $student->first_name = $invoice->contact?->first_name ?? $student->first_name;
        $student->last_name = $invoice->contact?->last_name;
        $student->email = $invoice->contact?->email;
        $student->whatsapp = $invoice->contact?->phone;
        $student->currency = $invoice->currency;
        $student->total_price = (float) $invoice->course_price;
        $student->status = 'active';
        $student->save();

        $plan->student_id = $student->id;
        $plan->contact_id = $invoice->contact_id;
        $plan->invoice_id = $invoice->id;
        $plan->save();

        $invoice->student_id = $student->id;
        if (! in_array((string) $invoice->status, ['paid', 'cancelled', 'canceled', 'failed', 'expired'], true)) {
            $invoice->status = 'active';
            $invoice->paid_at = null;
        }
        $invoice->save();

        $invoice->contact?->update(['status' => 'online_enrolment']);

        return $student;
    }

    private function buildInstallmentTableHtml(
        int $installments,
        float $monthly,
        float $last,
        CarbonImmutable $firstBilling,
        string $currency,
        bool $isPayIn4 = false,
        ?CarbonImmutable $subscriptionStartedAt = null,
        float $setupFeeAmount = 0.0,
        array $transactions = []
    ): string
    {
        $scheduledAmounts = [];
        for ($i = 1; $i <= $installments; $i++) {
            if ($isPayIn4 && $i === 1) {
                $scheduledAmounts[] = $setupFeeAmount > 0 ? $setupFeeAmount : $monthly;
            } else {
                $scheduledAmounts[] = $i === $installments ? $last : $monthly;
            }
        }
        $allocation = InstallmentAllocation::allocate($scheduledAmounts, $transactions);

        $rows = [];
        for ($i = 1; $i <= $installments; $i++) {
            if ($isPayIn4) {
                if ($i === 1) {
                    $amount = $setupFeeAmount > 0 ? $setupFeeAmount : $monthly;
                    $due = ($subscriptionStartedAt ?? CarbonImmutable::now())->format('M, jS Y');
                } else {
                    $amount = $i === $installments ? $last : $monthly;
                    // For Pay in 4, installment #2 starts at selected billing day.
                    $due = $firstBilling->addMonthsNoOverflow($i - 2)->format('M, jS Y');
                }
            } else {
                $amount = $i === $installments ? $last : $monthly;
                $due = $firstBilling->addMonthsNoOverflow($i - 1)->format('M, jS Y');
            }
            $slot = $allocation['slots'][$i - 1] ?? ['paid' => false, 'paid_at' => null];
            $firstUnpaid = $allocation['first_unpaid_index'] ?? null;
            $status = ($slot['paid'] ?? false) ? 'paid' : (
                (($allocation['has_failed'] ?? false) && $firstUnpaid !== null && $firstUnpaid === ($i - 1))
                    ? 'failed'
                    : 'scheduled'
            );
            $statusLabel = match ($status) {
                'paid' => 'Paid ✅',
                'failed' => 'Failed',
                default => 'Scheduled',
            };
            $rows[] = sprintf(
                '<tr><td style="padding:6px 8px;border-bottom:1px solid #eee;">%s Installment</td><td style="padding:6px 8px;border-bottom:1px solid #eee;">%s</td><td style="padding:6px 8px;border-bottom:1px solid #eee;">%s</td><td style="padding:6px 8px;border-bottom:1px solid #eee;">%s</td></tr>',
                $this->ordinal($i),
                CurrencyDisplay::format($currency, $amount, 2),
                $due,
                $statusLabel
            );
        }
        $html = '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #111;">Installment</th><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #111;">Amount</th><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #111;">Due Date</th><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #111;">Status</th></tr></thead>'
            . '<tbody>' . implode('', $rows) . '</tbody></table>';
        if (($allocation['has_failed'] ?? false) && ! (($allocation['slots'][0]['paid'] ?? false))) {
            $html .= '<p style="margin:8px 0 0 0;color:#b91c1c;font-size:13px;line-height:20px;">'
                . 'We couldn’t process your first installment. We’ve added it to your next payment. '
                . 'If you have any problems, please contact us.'
                . '</p>';
        }

        return $html;
    }

    private function ordinal(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return $n . 'th';
        }

        return match ($n % 10) {
            1 => $n . 'st',
            2 => $n . 'nd',
            3 => $n . 'rd',
            default => $n . 'th',
        };
    }

}
