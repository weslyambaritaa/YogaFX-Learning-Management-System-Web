<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRegistrationRequest;
use App\Models\AccessTier;
use App\Models\PendingRegistration;
use App\Services\SimulatedPaymentFlowService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class LeadRegistrationController extends Controller
{
    public function __construct(
        private readonly SimulatedPaymentFlowService $paymentFlow,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Public/Scoreboard', [
            'accessTiers' => AccessTier::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'description' => $accessTier->description,
                    'price' => (float) $accessTier->price,
                    'currency_code' => $accessTier->currency_code,
                ]),
        ]);
    }

    public function store(LeadRegistrationRequest $request): RedirectResponse
    {
        $pendingRegistration = $this->paymentFlow->createPendingRegistration($request->validated());

        return redirect()->route('lead-registration.submitted', $pendingRegistration);
    }

    public function submitted(PendingRegistration $pendingRegistration): Response
    {
        $pendingRegistration->loadMissing('accessTier');

        return Inertia::render('Public/ScoreboardSubmitted', [
            'registration' => [
                'id' => $pendingRegistration->id,
                'full_name' => $pendingRegistration->fullName(),
                'email' => $pendingRegistration->email,
                'phone' => $pendingRegistration->phone,
                'country' => $pendingRegistration->country,
                'status' => $pendingRegistration->status,
                'amount' => (float) $pendingRegistration->amount_snapshot,
                'checkout_url' => $this->paymentFlow->checkoutUrl($pendingRegistration),
                'access_tier' => [
                    'id' => $pendingRegistration->accessTier->id,
                    'name' => $pendingRegistration->accessTier->name,
                    'slug' => $pendingRegistration->accessTier->slug,
                    'price' => (float) $pendingRegistration->accessTier->price,
                    'currency_code' => $pendingRegistration->accessTier->currency_code,
                ],
            ],
        ]);
    }
}
