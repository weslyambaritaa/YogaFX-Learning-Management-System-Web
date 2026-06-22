<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutPaymentRequest;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Services\PayPalService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentFinalizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
        private readonly PaymentFinalizerService $paymentFinalizer,
        private readonly PayPalService $paypalService,
    ) {}

    public function show(PendingRegistration $pendingRegistration, string $accessTierSlug): InertiaResponse|RedirectResponse
    {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $pendingRegistration->loadMissing('accessTier', 'onboardingState');

        if ($pendingRegistration->status === PendingRegistration::STATUS_PAYMENT_SUCCESS && $pendingRegistration->onboardingState) {
            return redirect()->away($this->paymentFlow->paymentSuccessUrl($pendingRegistration->onboardingState));
        }

        if ($pendingRegistration->status === PendingRegistration::STATUS_COMPLETED) {
            return redirect()->route('login')->with('status', 'Your YogaFX account is already ready. Please sign in.');
        }

        $pendingRegistration = $this->paymentFlow->markCheckoutOpened($pendingRegistration);

        $clientToken = null;

        try {
            $clientToken = $this->paypalService->generateClientToken();
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        return Inertia::render('Public/Checkout', [
            'checkout' => [
                'id' => $pendingRegistration->id,
                'first_name' => $pendingRegistration->first_name,
                'last_name' => $pendingRegistration->last_name,
                'email' => $pendingRegistration->email,
                'phone' => $pendingRegistration->phone,
                'country' => $pendingRegistration->country,
                'amount' => (float) $pendingRegistration->accessTier->price,
                'currency_code' => $pendingRegistration->accessTier->currency_code,
                'status' => $pendingRegistration->status,
                'access_tier' => [
                    'id' => $pendingRegistration->accessTier->id,
                    'name' => $pendingRegistration->accessTier->name,
                    'slug' => $pendingRegistration->accessTier->slug,
                    'price' => (float) $pendingRegistration->accessTier->price,
                    'currency_code' => $pendingRegistration->accessTier->currency_code,
                ],
                'pay_url' => $this->paymentFlow->checkoutPayUrl($pendingRegistration),
                'create_order_url' => $this->paymentFlow->checkoutOrderCreateUrl($pendingRegistration),
                'payment_method_options' => $this->paymentFlow->availablePaymentMethodOptions(),
                'paypal' => [
                    'client_id' => $this->paypalService->clientId(),
                    'client_token' => $clientToken,
                    'currency_code' => $pendingRegistration->accessTier->currency_code,
                    'components' => 'buttons,card-fields',
                    'intent' => 'capture',
                ],
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

            return redirect()->away($checkoutUrl)
                ->withErrors(['payment_method' => 'Please use the onsite checkout form on this page.']);
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

        $validated = $request->validated();
        $result = $this->paymentFlow->startInitialCheckout($pendingRegistration, $validated);

        if (($validated['payment_method'] ?? null) === Payment::METHOD_MOCK) {
            return response()->json([
                'status' => 'success',
                'redirect_url' => $result['redirect_url'],
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

            abort_unless($result['onboarding_state'] !== null, 409, 'Onboarding continuation is not available for this invoice.');

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
}
