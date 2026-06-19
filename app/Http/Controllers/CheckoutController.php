<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutPaymentRequest;
use App\Models\AccessTier;
use App\Models\PendingRegistration;
use App\Services\PaymentCheckoutService;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
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
                'payment_method_options' => $this->paymentFlow->availablePaymentMethodOptions(),
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

        $result = $this->paymentFlow->startInitialCheckout($pendingRegistration, $request->validated());

        if ($request->header('X-Inertia')) {
            return Inertia::location($result['redirect_url']);
        }

        return redirect()->away($result['redirect_url']);
    }
}
