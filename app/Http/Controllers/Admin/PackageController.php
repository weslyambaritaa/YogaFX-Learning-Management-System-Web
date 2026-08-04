<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Models\AccessTier;
use App\Models\Package;
use App\Services\PackageAssignmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function __construct(
        private readonly PackageAssignmentService $assignmentService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Packages/Index', [
            'packages' => Package::query()
                ->with('accessTier:id,name,slug')
                ->withCount(['pendingRegistrations', 'invoices'])
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'title' => $package->title,
                    'slug' => $package->slug,
                    'public_link' => route('lead-registration.packages.show', ['packageSlug' => $package->slug]),
                    'description' => $package->description,
                    'payment_type' => $package->normalizedPaymentType(),
                    'image_url' => $this->protectedMediaUrl(
                        'package',
                        $package->id,
                        'image',
                        $package->image,
                        versionSeed: $package->updated_at,
                    ),
                    'price' => (float) $package->price,
                    'minimum_donation_amount' => $package->minimumDonationAmount(),
                    'suggested_donation_amount' => $package->suggestedDonationAmount(),
                    'currency_code' => $package->currency_code,
                    'is_active' => $package->is_active,

                    /*
                    |--------------------------------------------------------------------------
                    | New Installment Fields
                    |--------------------------------------------------------------------------
                    */
                    'installment_enabled' => $package->installment_enabled,
                    'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
                    'installment_count_mode' => $package->normalizedInstallmentCountMode(),
                    'installment_count' => $package->configuredInstallmentCount(),
                    'installment_count_selectable' => $package->installmentCountSelectable(),
                    'installment_deadline_date' => $package->installment_deadline_date?->toDateString(),
                    'allowed_billing_days' => $package->resolvedAllowedBillingDays(),
                    'checkout_billing_day_options' => $package->checkoutBillingDayOptions(),
                    'installment_policy_summary' => $this->installmentPolicySummary($package),

                    /*
                    |--------------------------------------------------------------------------
                    | Legacy Installment Fields
                    |--------------------------------------------------------------------------
                    |
                    | Masih dikirim sementara untuk menjaga kompatibilitas dengan halaman
                    | lain yang mungkin masih membaca field lama.
                    |
                    */
                    'billing_interval_unit' => $package->billing_interval_unit,
                    'billing_interval_count' => $package->billing_interval_count,
                    'fixed_billing_day' => $package->fixed_billing_day,
                    'installment_deadline_month' => $package->installment_deadline_month,
                    'installment_deadline_day' => $package->installment_deadline_day,

                    'access_tier' => $package->accessTier ? [
                        'id' => $package->accessTier->id,
                        'name' => $package->accessTier->name,
                        'slug' => $package->accessTier->slug,
                    ] : null,
                    'pending_registrations_count' => $package->pending_registrations_count,
                    'invoices_count' => $package->invoices_count,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Packages/Create', [
            'accessTiers' => $this->accessTierOptions(),
            'packagePublicBaseUrl' => url('/'),
        ]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $targetTier = isset($data['access_tier_id']) && $data['access_tier_id']
            ? AccessTier::query()->findOrFail($data['access_tier_id'])
            : null;

        [
            'allowed_billing_days' => $allowedBillingDays,
            'fixed_billing_day' => $fixedBillingDay,
        ] = $this->resolveBillingDayConfig($data);

        $data['allowed_billing_days'] = $allowedBillingDays;
        $data['fixed_billing_day'] = $fixedBillingDay;
        $data = $this->sanitizeInstallmentPolicyData($data);

        /*
        |--------------------------------------------------------------------------
        | Legacy defaults
        |--------------------------------------------------------------------------
        |
        | billing_interval_count sudah tidak dipakai di flow baru.
        | billing_interval_unit juga tidak perlu diatur dari form baru.
        | Namun kita tidak memaksa unset semua legacy field agar data/request lama
        | tetap aman selama proses transisi.
        |
        */
        if (! ($data['installment_enabled'] ?? false)) {
            $data['installment_deadline_date'] = null;
            $data['allowed_billing_days'] = null;
            $data['fixed_billing_day'] = null;
        }

        $data['image'] = $this->storeUploadedFileToBunny(
            $request->file('image'),
            'packages/images',
        );

        unset($data['access_tier_id']);

        $package = Package::query()->create($data);

        $this->assignmentService->assignToTier($package, $targetTier);

        return redirect()
            ->route('admin.packages.index')
            ->with('status', 'package-created');
    }

    public function edit(Package $package): Response
    {
        $package->loadCount(['pendingRegistrations', 'invoices']);

        return Inertia::render('Admin/Packages/Edit', [
            'package' => [
                'id' => $package->id,
                'title' => $package->title,
                'slug' => $package->slug,
                'public_link' => route('lead-registration.packages.show', ['packageSlug' => $package->slug]),
                'description' => $package->description,
                'payment_type' => $package->normalizedPaymentType(),
                'image_url' => $this->protectedMediaUrl(
                    'package',
                    $package->id,
                    'image',
                    $package->image,
                    versionSeed: $package->updated_at,
                ),
                'price' => (float) $package->price,
                'minimum_donation_amount' => $package->minimumDonationAmount(),
                'suggested_donation_amount' => $package->suggestedDonationAmount(),
                'currency_code' => $package->currency_code,
                'is_active' => $package->is_active,

                /*
                |--------------------------------------------------------------------------
                | New Installment Fields
                |--------------------------------------------------------------------------
                */
                'installment_enabled' => $package->installment_enabled,
                'installment_calculation_method' => $package->normalizedInstallmentCalculationMethod(),
                'installment_count_mode' => $package->normalizedInstallmentCountMode(),
                'installment_count' => $package->configuredInstallmentCount(),
                'installment_count_selectable' => $package->installmentCountSelectable(),
                'installment_deadline_date' => $package->installment_deadline_date?->toDateString(),
                'allowed_billing_days' => $package->resolvedAllowedBillingDays(),
                'checkout_billing_day_options' => $package->checkoutBillingDayOptions(),

                /*
                |--------------------------------------------------------------------------
                | Legacy Installment Fields
                |--------------------------------------------------------------------------
                |
                | Masih dikirim sementara selama service/checkout lama belum sepenuhnya
                | dipindahkan ke installment_deadline_date + allowed_billing_days.
                |
                */
                'billing_interval_unit' => $package->billing_interval_unit,
                'billing_interval_count' => $package->billing_interval_count,
                'fixed_billing_day' => $package->fixed_billing_day,
                'installment_deadline_month' => $package->installment_deadline_month,
                'installment_deadline_day' => $package->installment_deadline_day,

                'access_tier_id' => $package->access_tier_id,
                'pending_registrations_count' => $package->pending_registrations_count,
                'invoices_count' => $package->invoices_count,
            ],
            'accessTiers' => $this->accessTierOptions(),
            'packagePublicBaseUrl' => url('/p'),
            'status' => session('status'),
        ]);
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $data = $request->validated();

        $targetTier = isset($data['access_tier_id']) && $data['access_tier_id']
            ? AccessTier::query()->findOrFail($data['access_tier_id'])
            : null;

        [
            'allowed_billing_days' => $allowedBillingDays,
            'fixed_billing_day' => $fixedBillingDay,
        ] = $this->resolveBillingDayConfig($data, $package);

        $data['allowed_billing_days'] = $allowedBillingDays;
        $data['fixed_billing_day'] = $fixedBillingDay;
        $data = $this->sanitizeInstallmentPolicyData($data);

        if (! ($data['installment_enabled'] ?? false)) {
            $data['installment_deadline_date'] = null;
            $data['allowed_billing_days'] = null;
            $data['fixed_billing_day'] = null;
        }

        $data['image'] = $this->storeUploadedFileToBunny(
            $request->file('image'),
            'packages/images',
            $package->image,
        );

        unset($data['access_tier_id']);

        $package->update($data);

        $this->assignmentService->assignToTier($package, $targetTier);

        return redirect()
            ->route('admin.packages.index')
            ->with('status', 'package-updated');
    }

    public function destroy(Package $package): RedirectResponse
    {
        if ($package->pendingRegistrations()->exists() || $package->invoices()->exists()) {
            return redirect()
                ->route('admin.packages.index')
                ->withErrors([
                    'package' => 'This package cannot be deleted because it already has checkout history.',
                ]);
        }

        $this->deleteUploadedFileFromAnyStorage($package->image);

        $package->delete();

        return redirect()
            ->route('admin.packages.index')
            ->with('status', 'package-deleted');
    }

    private function accessTierOptions(): array
    {
        return AccessTier::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn (AccessTier $accessTier) => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'slug' => $accessTier->slug,
                'is_active' => $accessTier->is_active,
            ])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $allowedBillingDays
     * @return array<int, int>
     */
    private function normalizeAllowedBillingDays(array $allowedBillingDays): array
    {
        return collect($allowedBillingDays)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => in_array($day, Package::CUSTOMER_BILLING_DAY_OPTIONS, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{allowed_billing_days: array<int, int>|null, fixed_billing_day: int|null}
     */
    private function resolveBillingDayConfig(array $data, ?Package $existingPackage = null): array
    {
        $installmentEnabled = (bool) ($data['installment_enabled'] ?? $existingPackage?->installment_enabled ?? false);

        if (! $installmentEnabled) {
            return [
                'allowed_billing_days' => null,
                'fixed_billing_day' => null,
            ];
        }

        $submittedBillingDays = array_key_exists('allowed_billing_days', $data)
            ? $this->normalizeAllowedBillingDays((array) ($data['allowed_billing_days'] ?? []))
            : null;

        $resolvedBillingDays = $submittedBillingDays
            ?? $this->normalizeAllowedBillingDays((array) ($existingPackage?->allowed_billing_days ?? []));

        /*
        |--------------------------------------------------------------------------
        | Safety fallback
        |--------------------------------------------------------------------------
        |
        | PackageRequest seharusnya sudah memastikan allowed_billing_days wajib
        | saat installment_enabled = true. Fallback ini hanya menjaga agar update
        | terhadap data lama tidak langsung gagal total jika ada data lama yang belum
        | punya allowed_billing_days.
        |
        */
        if ($resolvedBillingDays === []) {
            $legacyBillingDay = (int) ($existingPackage?->fixed_billing_day ?? 15);

            $resolvedBillingDays = in_array($legacyBillingDay, Package::CUSTOMER_BILLING_DAY_OPTIONS, true)
                ? [$legacyBillingDay]
                : [15];
        }

        return [
            'allowed_billing_days' => $resolvedBillingDays,
            'fixed_billing_day' => $resolvedBillingDays[0] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeInstallmentPolicyData(array $data): array
    {
        $installmentEnabled = (bool) ($data['installment_enabled'] ?? false);
        $method = strtolower(trim((string) ($data['installment_calculation_method'] ?? Package::INSTALLMENT_CALCULATION_DATE)));

        if (! $installmentEnabled) {
            $data['installment_calculation_method'] = Package::INSTALLMENT_CALCULATION_DATE;
            $data['installment_count_mode'] = null;
            $data['installment_count'] = null;
            $data['installment_deadline_date'] = null;
            $data['allowed_billing_days'] = null;
            $data['fixed_billing_day'] = null;

            return $data;
        }

        $data['installment_calculation_method'] = $method === Package::INSTALLMENT_CALCULATION_NUMBER
            ? Package::INSTALLMENT_CALCULATION_NUMBER
            : Package::INSTALLMENT_CALCULATION_DATE;

        if ($data['installment_calculation_method'] === Package::INSTALLMENT_CALCULATION_DATE) {
            $data['installment_count_mode'] = null;
            $data['installment_count'] = null;

            return $data;
        }

        $mode = strtolower(trim((string) ($data['installment_count_mode'] ?? '')));

        $data['installment_count_mode'] = $mode === Package::INSTALLMENT_COUNT_MODE_FIXED
            ? Package::INSTALLMENT_COUNT_MODE_FIXED
            : Package::INSTALLMENT_COUNT_MODE_FLEX;
        $data['installment_count'] = min(
            max((int) ($data['installment_count'] ?? Package::MIN_INSTALLMENT_COUNT), Package::MIN_INSTALLMENT_COUNT),
            Package::MAX_INSTALLMENT_COUNT,
        );
        $data['installment_deadline_date'] = null;

        return $data;
    }

    private function installmentPolicySummary(Package $package): string
    {
        if (! $package->installment_enabled) {
            return 'One-time only';
        }

        if ($package->usesDateBasedInstallment()) {
            $deadline = $package->installment_deadline_date?->format('d M Y') ?? '-';

            return "Date-based · Deadline {$deadline}";
        }

        $count = $package->configuredInstallmentCount() ?? Package::MIN_INSTALLMENT_COUNT;

        if ($package->usesFixedInstallmentCount()) {
            return "Number · Fixed · Exactly {$count} payments";
        }

        return "Number · Flex · Up to {$count} payments";
    }
}
