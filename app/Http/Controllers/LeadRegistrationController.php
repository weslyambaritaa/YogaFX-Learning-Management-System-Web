<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRegistrationRequest;
use App\Models\AccessTier;
use App\Models\PendingRegistration;
use App\Services\PayPalService;
use App\Services\PaymentCheckoutService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class LeadRegistrationController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
        private readonly PayPalService $paypalService,
    ) {}

    public function create(): Response
    {
        return $this->renderScoreboardPage();
    }

    public function showProductPaymentLink(string $paymentLinkSlug): Response
    {
        $canonicalTierSlug = AccessTier::canonicalSlug($paymentLinkSlug);

        $selectedAccessTier = AccessTier::query()
            ->where('is_active', true)
            ->where('slug', $canonicalTierSlug)
            ->firstOrFail();

        return $this->renderScoreboardPage($selectedAccessTier, request()->path());
    }

    public function store(LeadRegistrationRequest $request, ?string $paymentLinkSlug = null): RedirectResponse|JsonResponse
    {
        if ($paymentLinkSlug !== null) {
            $expectedTierSlug = AccessTier::canonicalSlug($paymentLinkSlug);
            $selectedAccessTier = AccessTier::query()->findOrFail($request->integer('access_tier_id'));

            abort_unless(
                $selectedAccessTier->slug === $expectedTierSlug,
                404,
            );
        }

        $pendingRegistration = $this->paymentFlow->createPendingRegistration($request->validated());
        $pendingRegistration = $this->paymentFlow->markCheckoutOpened($pendingRegistration);

        if ($request->expectsJson()) {
            $clientToken = null;

            try {
                $clientToken = $this->paypalService->generateClientToken();
            } catch (\Throwable $throwable) {
                report($throwable);
            }

            return response()->json([
                'status' => 'checkout_ready',
                'registration' => [
                    'id' => $pendingRegistration->id,
                    'full_name' => $pendingRegistration->fullName(),
                    'email' => $pendingRegistration->email,
                    'phone' => $pendingRegistration->phone,
                    'country' => $pendingRegistration->country,
                    'status' => $pendingRegistration->status,
                ],
                'checkout' => [
                    ...$this->paymentFlow->checkoutPayload($pendingRegistration),
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

        return redirect()->away($this->paymentFlow->checkoutUrl($pendingRegistration));
    }

    public function submitted(PendingRegistration $pendingRegistration): RedirectResponse
    {
        return redirect()->away($this->paymentFlow->checkoutUrl($pendingRegistration));
    }

    private function renderScoreboardPage(?AccessTier $selectedAccessTier = null, ?string $submitUrl = null): Response
    {
        $query = AccessTier::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->orderBy('name');

        if ($selectedAccessTier instanceof AccessTier) {
            $query->whereKey($selectedAccessTier->id);
        }

        return Inertia::render('Public/Scoreboard', [
            'accessTiers' => $query
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'payment_link' => $accessTier->payment_link,
                    'description' => $accessTier->description,
                    'price' => (float) $accessTier->price,
                    'currency_code' => $accessTier->currency_code,
                ]),
            'submit_url' => $submitUrl ? url($submitUrl) : route('lead-registration.store'),
            'selected_access_tier_id' => $selectedAccessTier?->id,
            'is_access_tier_locked' => $selectedAccessTier instanceof AccessTier,
        ]);
    }
}
