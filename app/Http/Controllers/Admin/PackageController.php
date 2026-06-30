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
                    'image_url' => $this->protectedMediaUrl(
                        'package',
                        $package->id,
                        'image',
                        $package->image,
                        versionSeed: $package->updated_at,
                    ),
                'price' => (float) $package->price,
                'currency_code' => $package->currency_code,
                'is_active' => $package->is_active,
                    'installment_enabled' => $package->installment_enabled,
                    'billing_interval_unit' => $package->billing_interval_unit,
                    'billing_interval_count' => $package->billing_interval_count,
                    'checkout_billing_day_options' => $package->checkoutBillingDayOptions(),
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
        ['allowed_billing_days' => $allowedBillingDays, 'fixed_billing_day' => $fixedBillingDay] = $this->resolveBillingDayConfig($data);
        $data['allowed_billing_days'] = $allowedBillingDays;
        $data['fixed_billing_day'] = $fixedBillingDay;

        $data['image'] = $this->storeUploadedFile(
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
                'image_url' => $this->protectedMediaUrl(
                    'package',
                    $package->id,
                    'image',
                    $package->image,
                    versionSeed: $package->updated_at,
                ),
                'price' => (float) $package->price,
                'currency_code' => $package->currency_code,
                'is_active' => $package->is_active,
                'installment_enabled' => $package->installment_enabled,
                'billing_interval_unit' => $package->billing_interval_unit,
                'billing_interval_count' => $package->billing_interval_count,
                'checkout_billing_day_options' => $package->checkoutBillingDayOptions(),
                'installment_deadline_month' => $package->installment_deadline_month,
                'installment_deadline_day' => $package->installment_deadline_day,
                'access_tier_id' => $package->access_tier_id,
                'pending_registrations_count' => $package->pending_registrations_count,
                'invoices_count' => $package->invoices_count,
            ],
            'accessTiers' => $this->accessTierOptions(),
            'packagePublicBaseUrl' => url('/'),
            'status' => session('status'),
        ]);
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $data = $request->validated();
        $targetTier = isset($data['access_tier_id']) && $data['access_tier_id']
            ? AccessTier::query()->findOrFail($data['access_tier_id'])
            : null;
        ['allowed_billing_days' => $allowedBillingDays, 'fixed_billing_day' => $fixedBillingDay] = $this->resolveBillingDayConfig($data, $package);
        $data['allowed_billing_days'] = $allowedBillingDays;
        $data['fixed_billing_day'] = $fixedBillingDay;

        $data['image'] = $this->storeUploadedFile(
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

        $this->deleteUploadedFile($package->image);
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
        $submittedBillingDays = array_key_exists('allowed_billing_days', $data)
            ? $this->normalizeAllowedBillingDays((array) ($data['allowed_billing_days'] ?? []))
            : null;
        $billingIntervalUnit = strtoupper(trim((string) ($data['billing_interval_unit'] ?? $existingPackage?->billing_interval_unit ?? '')));
        $installmentEnabled = (bool) ($data['installment_enabled'] ?? $existingPackage?->installment_enabled ?? false);

        if (! $installmentEnabled) {
            return [
                'allowed_billing_days' => $submittedBillingDays ?? $existingPackage?->allowed_billing_days,
                'fixed_billing_day' => $existingPackage?->fixed_billing_day,
            ];
        }

        if ($billingIntervalUnit !== 'MONTH') {
            return [
                'allowed_billing_days' => $submittedBillingDays ?? $existingPackage?->allowed_billing_days,
                'fixed_billing_day' => $existingPackage?->fixed_billing_day,
            ];
        }

        $resolvedBillingDays = $submittedBillingDays
            ?? $this->normalizeAllowedBillingDays((array) ($existingPackage?->allowed_billing_days ?? []));

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
}
