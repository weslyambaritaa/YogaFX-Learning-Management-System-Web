<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutPaymentRequest;
use App\Models\AccessTier;
use App\Models\OnboardingState;
use App\Models\PendingRegistration;
use App\Services\SimulatedPaymentFlowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly SimulatedPaymentFlowService $paymentFlow,
    ) {}

    public function show(PendingRegistration $pendingRegistration, string $accessTierSlug): Response|RedirectResponse
    {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $pendingRegistration->loadMissing('accessTier', 'onboardingState');

        if ($pendingRegistration->status === PendingRegistration::STATUS_PAYMENT_SUCCESS && $pendingRegistration->onboardingState) {
            return redirect()->away($this->paymentFlow->enrollmentUrl($pendingRegistration->onboardingState));
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
                'amount' => (float) $pendingRegistration->amount_snapshot,
                'status' => $pendingRegistration->status,
                'access_tier' => [
                    'id' => $pendingRegistration->accessTier->id,
                    'name' => $pendingRegistration->accessTier->name,
                    'slug' => $pendingRegistration->accessTier->slug,
                ],
                'pay_url' => $this->paymentFlow->checkoutPayUrl($pendingRegistration),
            ],
        ]);
    }

    public function pay(
        CheckoutPaymentRequest $request,
        PendingRegistration $pendingRegistration,
        string $accessTierSlug,
    ): RedirectResponse {
        $accessTier = AccessTier::query()
            ->where('slug', $accessTierSlug)
            ->firstOrFail();

        abort_unless($pendingRegistration->access_tier_id === $accessTier->id, 404);

        $result = $this->paymentFlow->processInitialPayment($pendingRegistration, $request->validated());

        /** @var OnboardingState $onboardingState */
        $onboardingState = $result['onboarding_state'];

        return redirect()->away($this->paymentFlow->enrollmentUrl($onboardingState));
    }
}
