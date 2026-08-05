<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutPaymentRequest;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\PaymentSubscription;
use App\Services\PayPalService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentFinalizerService;
use App\Services\Payments\PaymentSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
        private readonly PaymentFinalizerService $paymentFinalizer,
        private readonly PayPalService $paypalService,
        private readonly PaymentSubscriptionService $paymentSubscriptionService,
    ) {}

    public function show(PendingRegistration $pendingRegistration, string $accessTierSlug): InertiaResponse|RedirectResponse
    {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $pendingRegistration->loadMissing('accessTier', 'package', 'onboardingState');

        if ($pendingRegistration->status === PendingRegistration::STATUS_PAYMENT_SUCCESS && $pendingRegistration->onboardingState) {
            return redirect()->away($this->paymentFlow->paymentSuccessUrl($pendingRegistration->onboardingState));
        }

        if ($pendingRegistration->status === PendingRegistration::STATUS_COMPLETED) {
            return redirect()
                ->route('login')
                ->with('status', 'Your YogaFX account is already ready. Please sign in.');
        }

        $pendingRegistration = $this->paymentFlow->markCheckoutOpened($pendingRegistration);

        return Inertia::render('Public/Checkout', [
            'checkout' => [
                ...$this->paymentFlow->checkoutPayload($pendingRegistration),
                'paypal' => $this->paypalFrontendConfig($pendingRegistration),
            ],
        ]);
    }

    public function pay(
        CheckoutPaymentRequest $request,
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
    ): RedirectResponse|HttpResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $validated = $request->validated();

        if (($validated['payment_method'] ?? null) !== Payment::METHOD_MOCK) {
            $checkoutUrl = $this->paymentFlow->checkoutUrl($pendingRegistration);

            if ($request->header('X-Inertia')) {
                return Inertia::location($checkoutUrl);
            }

            return redirect()
                ->away($checkoutUrl)
                ->withErrors([
                    'payment_method' => 'Please use the onsite checkout form on this page.',
                ]);
        }

        $result = $this->paymentFlow->startInitialCheckout($pendingRegistration, $validated);

        if ($request->header('X-Inertia')) {
            return Inertia::location($result['redirect_url']);
        }

        return redirect()->away($result['redirect_url']);
    }

    public function createOrder(
        CheckoutPaymentRequest $request,
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
    ): JsonResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        /*
        |--------------------------------------------------------------------------
        | Important
        |--------------------------------------------------------------------------
        |
        | $validated now includes installment_count from CheckoutPaymentRequest.
        | We pass the whole validated payload to PaymentCheckoutService so the
        | selected installment count can reach the calculator and subscription flow.
        |
        */
        $validated = $request->validated();

        $result = $this->paymentFlow->startInitialCheckout($pendingRegistration, $validated);

        if (
            in_array(($validated['payment_method'] ?? null), [Payment::METHOD_MOCK, Payment::METHOD_INTERNAL], true)
            && isset($result['redirect_url'])
        ) {
            return response()->json([
                'status' => 'success',
                'redirect_url' => $result['redirect_url'],
            ]);
        }

        if (($validated['payment_type'] ?? null) === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            /** @var PaymentSubscription|null $paymentSubscription */
            $paymentSubscription = $result['payment_subscription'] ?? null;

            abort_unless(
                $paymentSubscription instanceof PaymentSubscription,
                409,
                'Subscription checkout data is unavailable.'
            );

            $metadata = is_array($paymentSubscription->metadata)
                ? $paymentSubscription->metadata
                : [];

            $installmentPlan = is_array($metadata['installment_plan'] ?? null)
                ? $metadata['installment_plan']
                : [];

            return response()->json([
                'status' => 'prepared',
                'flow' => 'subscription',
                'invoice_id' => $result['invoice']->id,
                'payment_subscription_id' => $paymentSubscription->id,
                'provider_plan_id' => $paymentSubscription->provider_plan_id,
                'provider_subscription_id' => $paymentSubscription->provider_subscription_id,

                /*
                |--------------------------------------------------------------------------
                | New installment response fields
                |--------------------------------------------------------------------------
                |
                | These fields help the frontend confirm that the prepared PayPal
                | subscription matches the student-selected billing day and count.
                |
                */
                'billing_day' => $paymentSubscription->billing_day,
                'installment_count' => $paymentSubscription->installment_count,
                'first_payment_amount' =>
    (float) $paymentSubscription->first_payment_amount,
'first_recurring_payment_amount' => (float) (
    $installmentPlan['first_recurring_payment_amount']
        ?? $paymentSubscription->next_billing_amount
),
'recurring_payment_amount' => (float) (
    $installmentPlan['recurring_payment_amount']
        ?? $paymentSubscription->monthly_base_amount
),
                'total_amount' => (float) $paymentSubscription->total_amount,
                'currency_code' => $paymentSubscription->currency_code,
                'next_due_at' => $paymentSubscription->next_due_at?->toDateString(),
                'paypal_subscription_start_time' => $this->paypalSubscriptionStartTime($paymentSubscription),
                'final_due_at' => $paymentSubscription->final_due_at?->toDateString(),
                'grace_deadline_at' => $paymentSubscription->grace_deadline_at?->toDateString(),
                'installment_plan' => $installmentPlan,

                'paypal_client_id' => $this->paypalService->clientId(),
                'environment' => $this->paypalService->environment(),
                'status_url' => $this->paymentFlow->checkoutSubscriptionStatusUrl($pendingRegistration),
            ]);
        }

        $pendingRegistration->loadMissing('accessTier');

        return response()->json([
            'status' => 'created',
            'order_id' => (string) $result['payment_activity']->payment_reference,
            'invoice_id' => $result['invoice']->id,
            'capture_url' => $this->paymentFlow->checkoutOrderCaptureUrl($pendingRegistration, $result['invoice']),
            'cancel_url' => $this->paymentFlow->checkoutOrderCancelUrl($pendingRegistration, $result['invoice']),
        ]);
    }

    public function approveInstallment(
        Request $request,
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
    ): JsonResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $validated = $request->validate([
            'payment_subscription_id' => ['required', 'integer'],
            'provider_subscription_id' => ['required', 'string', 'max:255'],
        ]);

        /** @var PaymentSubscription $paymentSubscription */
        $paymentSubscription = PaymentSubscription::query()
            ->with('invoice', 'pendingRegistration.onboardingState')
            ->where('pending_registration_id', $pendingRegistration->id)
            ->findOrFail($validated['payment_subscription_id']);

        $paymentSubscription = $this->paymentSubscriptionService->attachApprovedSubscription(
            $pendingRegistration,
            $paymentSubscription,
            (string) $validated['provider_subscription_id'],
        );

        return response()->json([
            'status' => 'approval_attached',
            'flow' => 'subscription',
            'invoice_id' => $paymentSubscription->invoice_id,
            'payment_subscription_id' => $paymentSubscription->id,
            'provider_subscription_id' => $paymentSubscription->provider_subscription_id,
            'billing_day' => $paymentSubscription->billing_day,
            'installment_count' => $paymentSubscription->installment_count,
            'awaiting_webhook' => true,
            'onboarding_ready' => false,
            'status_url' => $this->paymentFlow->checkoutSubscriptionStatusUrl($pendingRegistration),
        ]);
    }

    public function installmentStatus(
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
    ): JsonResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $pendingRegistration->loadMissing('onboardingState', 'package', 'accessTier');

        if ($pendingRegistration->onboardingState instanceof OnboardingState) {
            return response()->json([
                'status' => 'onboarding_ready',
                'onboarding_ready' => true,
                'onboarding_url' => $this->paymentFlow->paymentSuccessUrl($pendingRegistration->onboardingState),
                'message' => 'Your first installment payment has been confirmed. Continue to enrollment.',
            ]);
        }

        /** @var PaymentSubscription|null $paymentSubscription */
        $paymentSubscription = PaymentSubscription::query()
            ->where('pending_registration_id', $pendingRegistration->id)
            ->latest('id')
            ->first();

        return response()->json([
            'status' => $paymentSubscription?->provider_subscription_id
                ? 'waiting_for_first_payment'
                : 'approval_required',
            'onboarding_ready' => false,
            'onboarding_url' => null,
            'payment_subscription_id' => $paymentSubscription?->id,
            'payment_subscription_status' => $paymentSubscription?->status,
            'billing_day' => $paymentSubscription?->billing_day,
            'installment_count' => $paymentSubscription?->installment_count,
            'next_due_at' => $paymentSubscription?->next_due_at?->toDateString(),
            'final_due_at' => $paymentSubscription?->final_due_at?->toDateString(),
        ]);
    }

    public function captureOrder(
        Request $request,
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
        Invoice $invoice,
    ): JsonResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);
        abort_unless($invoice->pending_registration_id === $pendingRegistration->id, 404);

        $validated = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        /** @var Payment|null $paymentActivity */
        $paymentActivity = $invoice->paymentActivities()
            ->where('payment_reference', $validated['order_id'])
            ->latest('id')
            ->first();

        abort_unless($paymentActivity instanceof Payment, 404);

        try {
            $capture = $this->paypalService->captureOrder($validated['order_id']);
        } catch (\Throwable $throwable) {
            report($throwable);

            $this->paymentFinalizer->failPendingPayment(
                $paymentActivity,
                'PayPal capture failed before completion.',
            );

            return response()->json([
                'status' => 'failed',
                'redirect_url' => $this->paymentFlow->checkoutStatusUrl($invoice),
            ], 422);
        }

        $providerStatus = strtoupper((string) ($capture['status'] ?? ''));

        if ($providerStatus === 'COMPLETED') {
            $result = $this->paymentFinalizer->finalizeSuccessfulPayment(
                $paymentActivity,
                $validated['order_id'],
            );

            abort_unless(
                $result['onboarding_state'] !== null,
                409,
                'Onboarding continuation is not available for this invoice.'
            );

            return response()->json([
                'status' => 'success',
                'redirect_url' => $this->paymentFlow->paymentSuccessUrl($result['onboarding_state']),
            ]);
        }

        if ($providerStatus === 'PENDING') {
            $this->paymentFinalizer->keepPaymentPending(
                $paymentActivity,
                'PayPal capture returned pending.',
            );

            return response()->json([
                'status' => 'pending',
                'redirect_url' => $this->paymentFlow->checkoutStatusUrl($invoice),
            ], 202);
        }

        $this->paymentFinalizer->failPendingPayment(
            $paymentActivity,
            'PayPal capture returned unexpected status: '.$providerStatus,
        );

        return response()->json([
            'status' => 'failed',
            'redirect_url' => $this->paymentFlow->checkoutStatusUrl($invoice),
        ], 422);
    }

    public function cancelOrder(
        Request $request,
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
        Invoice $invoice,
    ): JsonResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);
        abort_unless($invoice->pending_registration_id === $pendingRegistration->id, 404);

        $validated = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        /** @var Payment|null $paymentActivity */
        $paymentActivity = $invoice->paymentActivities()
            ->where('payment_reference', $validated['order_id'])
            ->latest('id')
            ->first();

        abort_unless($paymentActivity instanceof Payment, 404);

        $this->paymentFinalizer->cancelPendingPayment($paymentActivity);

        return response()->json([
            'status' => 'failed',
            'redirect_url' => $this->paymentFlow->checkoutStatusUrl($invoice),
        ]);
    }

    public function status(Invoice $invoice): InertiaResponse|RedirectResponse
    {
        abort_unless($invoice->pending_registration_id !== null, 404);

        $invoice->loadMissing('pendingRegistration.accessTier', 'pendingRegistration.onboardingState');

        $pendingRegistration = $invoice->pendingRegistration;

        abort_unless($pendingRegistration instanceof PendingRegistration, 404);

        if (
            in_array($invoice->status, [Invoice::STATUS_PAID_FULL, Invoice::STATUS_INSTALLMENT], true)
            && $pendingRegistration->onboardingState
        ) {
            return redirect()->away($this->paymentFlow->paymentSuccessUrl($pendingRegistration->onboardingState));
        }

        /** @var Payment|null $latestPayment */
        $latestPayment = $invoice->paymentActivities()
            ->latest('id')
            ->first();

        abort_unless($latestPayment instanceof Payment, 404);

        $status = $latestPayment->status === Payment::STATUS_PENDING
            ? 'pending'
            : 'failed';

        return Inertia::render('Public/CheckoutStatus', [
            'statusPage' => [
                'status' => $status,
                'invoice_number' => $invoice->invoice_number,
                'message' => $status === 'pending'
                    ? 'PayPal is still processing this transaction. We will keep the invoice open until the provider returns a final result.'
                    : 'This payment did not complete. You can safely return to the checkout page and try again.',
                'retry_url' => $this->paymentFlow->checkoutUrl($pendingRegistration),
                'refresh_url' => $this->paymentFlow->checkoutStatusUrl($invoice),
                'access_tier' => [
                    'name' => $pendingRegistration->accessTier->name,
                    'slug' => $pendingRegistration->accessTier->slug,
                ],
                'amount' => (float) $invoice->total_amount,
                'currency_code' => $invoice->currency_code,
            ],
        ]);
    }

    public function subscriptionReturn(
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
        Request $request,
    ): RedirectResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        return redirect()
            ->away($this->paymentFlow->checkoutUrl($pendingRegistration))
            ->with('status', 'Your PayPal installment approval was received. We are waiting for payment confirmation.');
    }

    public function subscriptionCancel(
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
        Request $request,
    ): RedirectResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $subscriptionId = (string) $request->query('subscription_id', '');

        if ($subscriptionId !== '') {
            $paymentSubscription = PaymentSubscription::query()
                ->where('pending_registration_id', $pendingRegistration->id)
                ->where('provider_subscription_id', $subscriptionId)
                ->latest('id')
                ->first();

            if ($paymentSubscription instanceof PaymentSubscription) {
                $this->paymentSubscriptionService->markApprovalCancelled($paymentSubscription);
            }
        }

        return redirect()
            ->away($this->paymentFlow->checkoutUrl($pendingRegistration))
            ->withErrors([
                'payment_method' => 'The PayPal installment checkout was cancelled.',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paypalFrontendConfig(PendingRegistration $pendingRegistration): array
    {
        $currencyCode = $pendingRegistration->currency_code
            ?? $pendingRegistration->package?->currency_code
            ?? $pendingRegistration->accessTier->currency_code;

        return [
            'client_id' => $this->paypalService->clientId(),
            'client_token' => null,
            'currency_code' => $currencyCode,
            'components' => 'buttons',

            /*
            |--------------------------------------------------------------------------
            | Full payment config
            |--------------------------------------------------------------------------
            |
            | Full payment still uses PayPal Orders API, so its SDK intent remains
            | capture.
            |
            */
            'intent' => 'capture',

            /*
            |--------------------------------------------------------------------------
            | Subscription config
            |--------------------------------------------------------------------------
            |
            | Installment checkout uses PayPal Subscriptions. The frontend can use
            | this block when loading/rendering the subscription button.
            |
            */
            'subscription' => [
                'components' => 'buttons',
                'vault' => 'true',
                'intent' => 'subscription',
            ],

            'environment' => $this->paypalService->environment(),
        ];
    }

    private function paypalSubscriptionStartTime(PaymentSubscription $paymentSubscription): ?string
    {
        if (! $paymentSubscription->next_due_at) {
            return null;
        }

        return $paymentSubscription->next_due_at
            ->copy()
            ->utc()
            ->startOfDay()
            ->format('Y-m-d\TH:i:s\Z');
    }
}
