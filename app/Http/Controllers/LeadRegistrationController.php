<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRegistrationRequest;
use App\Models\AccessTier;
use App\Models\Package;
use App\Services\PayPalService;
use App\Services\PackageResolverService;
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
        private readonly PackageResolverService $packageResolver,
    ) {}

    public function create(): Response
    {
        return $this->renderScoreboardPage();
    }

    public function showProductPaymentLink(string $paymentLinkSlug): Response
    {
        $package = $this->packageResolver->resolveActivePackageForTierSlug($paymentLinkSlug);
        abort_unless($package instanceof Package && $package->accessTier, 404);

        return $this->renderScoreboardPage($package, request()->path());
    }

    public function showPackagePaymentLink(string $packageSlug): Response
    {
        $package = $this->packageResolver->resolveActivePackageBySlug($packageSlug);
        abort_unless($package instanceof Package && $package->accessTier, 404);

        return $this->renderScoreboardPage($package, request()->path());
    }

    public function store(
        LeadRegistrationRequest $request,
        ?string $paymentLinkSlug = null,
        ?string $packageSlug = null,
    ): RedirectResponse|JsonResponse {
        $resolvedPackage = null;

        if ($paymentLinkSlug !== null) {
            $resolvedPackage = $this->packageResolver->resolveActivePackageForTierSlug($paymentLinkSlug);
        } elseif ($packageSlug !== null) {
            $resolvedPackage = $this->packageResolver->resolveActivePackageBySlug($packageSlug);
        }

        if ($resolvedPackage instanceof Package) {
            abort_unless($resolvedPackage->accessTier, 404);
            $request->merge(['package_id' => $resolvedPackage->id]);
        }

        $pendingRegistration = $this->paymentFlow->createPendingRegistration($request->validated());
        $pendingRegistration = $this->paymentFlow->markCheckoutOpened($pendingRegistration);

        if ($request->expectsJson()) {
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
                    'paypal' => $this->paypalFrontendConfig($pendingRegistration),
                ],
            ]);
        }

        return redirect()->away($this->paymentFlow->checkoutUrl($pendingRegistration));
    }

    public function submitted(\App\Models\PendingRegistration $pendingRegistration): RedirectResponse
    {
        return redirect()->away($this->paymentFlow->checkoutUrl($pendingRegistration));
    }

    private function renderScoreboardPage(?Package $selectedPackage = null, ?string $submitUrl = null): Response
    {
        $query = $this->packageResolver->checkoutablePackages();

        if ($selectedPackage instanceof Package) {
            $query = $query->where('id', $selectedPackage->id);
        }

        return Inertia::render('Public/Scoreboard', [
            'packages' => $query
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'title' => $package->title,
                    'slug' => $package->slug,
                    'description' => $package->description,
                    'price' => (float) $package->price,
                    'currency_code' => $package->currency_code,
                    'access_tier' => $package->accessTier ? [
                        'id' => $package->accessTier->id,
                        'name' => $package->accessTier->name,
                        'slug' => $package->accessTier->slug,
                    ] : null,
                ]),
            'submit_url' => $submitUrl ? url($submitUrl) : route('lead-registration.store'),
            'selected_package_id' => $selectedPackage?->id,
            'is_package_locked' => $selectedPackage instanceof Package,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function paypalFrontendConfig(\App\Models\PendingRegistration $pendingRegistration): array
    {
        return [
            'client_id' => $this->paypalService->clientId(),
            'client_token' => null,
            'currency_code' => $pendingRegistration->currency_code ?? $pendingRegistration->package?->currency_code ?? $pendingRegistration->accessTier->currency_code,
            'components' => 'buttons,card-fields',
            'intent' => 'capture',
            'environment' => $this->paypalService->environment(),
        ];
    }
}
