<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRegistrationRequest;
use App\Models\Package;
use App\Models\PendingRegistration;
use App\Services\PackageResolverService;
use App\Services\PaymentCheckoutService;
use App\Services\PayPalService;
use App\Support\PublicPageMeta;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LeadRegistrationController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
        private readonly PayPalService $paypalService,
        private readonly PackageResolverService $packageResolver,
        private readonly PublicPageMeta $publicPageMeta,
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

            $request->merge([
                'package_id' => $resolvedPackage->id,
            ]);
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

    public function submitted(PendingRegistration $pendingRegistration): RedirectResponse
    {
        return redirect()->away($this->paymentFlow->checkoutUrl($pendingRegistration));
    }

    private function renderScoreboardPage(?Package $selectedPackage = null, ?string $submitUrl = null): Response
    {
        $packages = $this->packageResolver->checkoutablePackages();

        if ($selectedPackage instanceof Package) {
            $packages = $packages->where('id', $selectedPackage->id);
        }

        $response = Inertia::render('Public/Scoreboard', [
            'packages' => $packages
                ->values()
                ->map(fn (Package $package) => $this->scoreboardPackagePayload($package)),
            'submit_url' => $submitUrl ? url($submitUrl) : route('lead-registration.store'),
            'selected_package_id' => $selectedPackage?->id,
            'is_package_locked' => $selectedPackage instanceof Package,

            /*
            |--------------------------------------------------------------------------
            | Initial PayPal Config
            |--------------------------------------------------------------------------
            |
            | Dikirim sejak halaman pertama dibuka supaya frontend bisa langsung
            | memuat PayPal SDK dan merender tombol resmi PayPal / Debit or Credit Card.
            |
            | Tombol payment tetap bisa dikunci di frontend sampai data diri lengkap,
            | tetapi secara tampilan tombolnya sudah bisa muncul dari awal.
            |
            */
            'paypal' => $this->initialPaypalFrontendConfig($selectedPackage),
        ]);

        return $response->withViewData([
            'meta' => $selectedPackage instanceof Package
                ? $this->publicPageMeta->forPackage(request(), $selectedPackage)
                : $this->publicPageMeta->forScoreboard(request()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreboardPackagePayload(Package $package): array
    {
        $allowedBillingDays = $this->resolveAllowedBillingDays($package);
        $installmentEnabled = $this->packageInstallmentIsEnabled($package, $allowedBillingDays);
        $maximumInstallmentCount = $this->resolveMaximumInstallmentCount($package, $allowedBillingDays);

        return [
            'id' => $package->id,
            'title' => $package->title,
            'slug' => $package->slug,
            'description' => $package->description,
            'payment_type' => $package->normalizedPaymentType(),
'price' => (float) $package->price,
'setup_fee' => $package->initialInstallmentSetupFee(),
'minimum_donation_amount' => $package->minimumDonationAmount(),
            'suggested_donation_amount' => $package->suggestedDonationAmount(),
            'currency_code' => $package->currency_code,

            /*
            |--------------------------------------------------------------------------
            | Installment Preview Data
            |--------------------------------------------------------------------------
            |
            | Data ini dipakai frontend untuk langsung menampilkan pilihan:
            | - Pay in full
            | - Pay in installment
            |
            | Pilihan ini bisa tampil sejak awal, tetapi interaksinya dikunci
            | sampai data diri calon student lengkap.
            |
            | installment_maximum_count / maximum_installment_count penting untuk
            | slider Number of Installment. Tanpa field ini frontend akan fallback
            | ke 2, sehingga slider terlihat mentok di 2 installment.
            |
            */
            'installment_enabled' => $package->supportsInstallments() && $installmentEnabled,
            'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
            'installment_count_mode' => $package->normalizedInstallmentCountMode(),
            'installment_count' => $package->configuredInstallmentCount(),
            'installment_count_selectable' => $package->installmentCountSelectable(),
            'configured_installment_count' => $package->configuredInstallmentCount(),
            'minimum_installment_count' => $package->minimumInstallmentCount(),
            'fixed_installment_count' => $package->fixedInstallmentCount(),
            'installment_deadline_date' => $this->formatDateValue($package->installment_deadline_date ?? null),
            'allowed_billing_days' => $allowedBillingDays,
            'checkout_billing_day_options' => $allowedBillingDays,
            'installment_billing_day_options' => $allowedBillingDays,
            'installment_maximum_count' => $maximumInstallmentCount,
            'maximum_installment_count' => $maximumInstallmentCount,

            'access_tier' => $package->accessTier ? [
                'id' => $package->accessTier->id,
                'name' => $package->accessTier->name,
                'slug' => $package->accessTier->slug,
                'currency_code' => $package->accessTier->currency_code ?? null,
            ] : null,
        ];
    }

    /**
     * @param  array<int, int>  $allowedBillingDays
     */
    private function packageInstallmentIsEnabled(Package $package, array $allowedBillingDays): bool
    {
        if (property_exists($package, 'installment_enabled') || isset($package->installment_enabled)) {
            return (bool) $package->installment_enabled && count($allowedBillingDays) > 0;
        }

        return count($allowedBillingDays) > 0;
    }

    /**
     * @return array<int, int>
     */
    private function resolveAllowedBillingDays(Package $package): array
    {
        if (method_exists($package, 'resolvedAllowedBillingDays')) {
            return collect($package->resolvedAllowedBillingDays())
                ->map(fn ($day) => (int) $day)
                ->filter(fn (int $day) => in_array($day, [1, 15], true))
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $allowedBillingDays = $package->allowed_billing_days ?? [];

        if (is_string($allowedBillingDays)) {
            $decoded = json_decode($allowedBillingDays, true);
            $allowedBillingDays = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($allowedBillingDays)) {
            $allowedBillingDays = [];
        }

        return collect($allowedBillingDays)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => in_array($day, [1, 15], true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $allowedBillingDays
     */
    private function resolveMaximumInstallmentCount(Package $package, array $allowedBillingDays): int
    {
        if ($package->usesNumberBasedInstallment()) {
            return min(
                Package::MAX_INSTALLMENT_COUNT,
                max(
                    $package->usesFixedInstallmentCount()
                        ? ($package->fixedInstallmentCount() ?? Package::MIN_INSTALLMENT_COUNT)
                        : Package::MIN_INSTALLMENT_COUNT,
                    $package->configuredInstallmentCount() ?? Package::MIN_INSTALLMENT_COUNT,
                ),
            );
        }

        if (method_exists($package, 'resolvedMaximumInstallmentCount')) {
            $resolvedMaximumInstallmentCount = (int) $package->resolvedMaximumInstallmentCount();

            if ($resolvedMaximumInstallmentCount >= 2) {
                return min($resolvedMaximumInstallmentCount, Package::MAX_PROVIDER_INSTALLMENT_COUNT);
            }
        }

        foreach ([
            'installment_maximum_count',
            'maximum_installment_count',
            'max_installment_count',
            'installment_count',
        ] as $attribute) {
            $value = $package->{$attribute} ?? null;

            if (is_numeric($value) && (int) $value >= 2) {
                return min((int) $value, Package::MAX_PROVIDER_INSTALLMENT_COUNT);
            }
        }

        $deadline = $this->parseDateValue($package->installment_deadline_date ?? null);

        if (! $deadline || count($allowedBillingDays) === 0) {
            return Package::MIN_INSTALLMENT_COUNT;
        }

        $today = CarbonImmutable::today();
        $deadline = $deadline->endOfDay();

        if ($deadline->lessThanOrEqualTo($today)) {
            return Package::MIN_INSTALLMENT_COUNT;
        }

        $recurringPaymentDates = $this->countRecurringInstallmentDatesUntilDeadline(
            $today,
            $deadline,
            $allowedBillingDays,
        );

        /*
        |--------------------------------------------------------------------------
        | Total Installment Count
        |--------------------------------------------------------------------------
        |
        | 1 payment pertama terjadi hari ini.
        | Sisanya adalah recurring payment berdasarkan allowed billing days
        | sampai installment_deadline_date.
        |
        | Contoh:
        | - first payment today
        | - recurring 1 bulan depan
        | - recurring 2 bulan depan
        |
        | Maka total installment count = 1 + jumlah recurring dates.
        |
        */
        return min(Package::MAX_PROVIDER_INSTALLMENT_COUNT, max(Package::MIN_INSTALLMENT_COUNT, 1 + $recurringPaymentDates));
    }

    /**
     * @param  array<int, int>  $allowedBillingDays
     */
    private function countRecurringInstallmentDatesUntilDeadline(
        CarbonImmutable $today,
        CarbonImmutable $deadline,
        array $allowedBillingDays,
    ): int {
        $allowedBillingDays = collect($allowedBillingDays)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => in_array($day, [1, 15], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($allowedBillingDays) === 0) {
            return 0;
        }

        $count = 0;
        $cursor = $today->startOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Safety Limit
        |--------------------------------------------------------------------------
        |
        | Dipasang agar loop tetap aman jika deadline diset sangat jauh.
        | 120 bulan = 10 tahun, lebih dari cukup untuk konteks installment course.
        |
        */
        for ($monthOffset = 0; $monthOffset <= 120; $monthOffset++) {
            foreach ($allowedBillingDays as $billingDay) {
                $candidate = $cursor->addDays($billingDay - 1);

                if ($candidate->lessThanOrEqualTo($today)) {
                    continue;
                }

                if ($candidate->greaterThan($deadline)) {
                    continue;
                }

                $count++;
            }

            $cursor = $cursor->addMonthNoOverflow()->startOfMonth();

            if ($cursor->greaterThan($deadline)) {
                break;
            }
        }

        return $count;
    }

    private function parseDateValue(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof CarbonImmutable) {
                return $value;
            }

            if ($value instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }

            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value) && method_exists($value, 'toDateString')) {
            return $value->toDateString();
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function initialPaypalFrontendConfig(?Package $package = null): array
    {
        $currencyCode = $package?->currency_code
            ?? $package?->accessTier?->currency_code
            ?? 'USD';

        return [
            'client_id' => $this->paypalService->clientId(),
            'client_token' => null,
            'currency_code' => $currencyCode,
            'components' => 'buttons',
            'intent' => 'capture',
            'environment' => $this->paypalService->environment(),

            /*
            |--------------------------------------------------------------------------
            | Subscription SDK config
            |--------------------------------------------------------------------------
            |
            | Disediakan juga sejak awal agar frontend bisa siap untuk mode
            | Pay in Installment.
            |
            */
            'subscription' => [
                'components' => 'buttons',
                'vault' => 'true',
                'intent' => 'subscription',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paypalFrontendConfig(PendingRegistration $pendingRegistration): array
    {
        return [
            'client_id' => $this->paypalService->clientId(),
            'client_token' => null,
            'currency_code' => $pendingRegistration->currency_code
                ?? $pendingRegistration->package?->currency_code
                ?? $pendingRegistration->accessTier->currency_code
                ?? 'USD',
            'components' => 'buttons',
            'intent' => 'capture',
            'environment' => $this->paypalService->environment(),
            'subscription' => [
                'components' => 'buttons',
                'vault' => 'true',
                'intent' => 'subscription',
            ],
        ];
    }
}
