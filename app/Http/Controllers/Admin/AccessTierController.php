<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccessTierRequest;
use App\Models\AccessTier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccessTierController extends Controller
{
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/AccessTiers/Index', [
            'accessTiers' => AccessTier::query()
                ->withCount('users')
                ->orderByDesc('is_active')
                ->orderBy('level')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'description' => $accessTier->description,
                    'level' => $accessTier->level,
                    'is_active' => $accessTier->is_active,
                    'has_full_standing_dialog_access' => $accessTier->has_full_standing_dialog_access,
                    'has_full_floor_dialog_access' => $accessTier->has_full_floor_dialog_access,
                    'users_count' => $accessTier->users_count,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AccessTiers/Create');
    }

    public function store(AccessTierRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['payment_link'] = AccessTier::publicPaymentPathForSlug($data['slug']);
        $data['thumbnail'] = null;
        $data['price'] = 0;
        $data['currency_code'] = AccessTier::CURRENCY_IDR;

        AccessTier::query()->create($data);

        return redirect()
            ->route('admin.access-tiers.index')
            ->with('status', 'access-tier-created');
    }

    public function edit(AccessTier $accessTier): Response
    {
        $accessTier->loadCount('users');

        return Inertia::render('Admin/AccessTiers/Edit', [
            'accessTier' => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'description' => $accessTier->description,
                'level' => $accessTier->level,
                'is_active' => $accessTier->is_active,
                'has_full_standing_dialog_access' => $accessTier->has_full_standing_dialog_access,
                'has_full_floor_dialog_access' => $accessTier->has_full_floor_dialog_access,
                'users_count' => $accessTier->users_count,
            ],
            'status' => session('status'),
        ]);
    }

    public function update(AccessTierRequest $request, AccessTier $accessTier): RedirectResponse
    {
        $data = $request->validated();
        $data['payment_link'] = AccessTier::publicPaymentPathForSlug($data['slug']);
        $data['thumbnail'] = $accessTier->thumbnail;
        $data['price'] = $accessTier->price;
        $data['currency_code'] = $accessTier->currency_code;

        $accessTier->update($data);

        return redirect()
            ->route('admin.access-tiers.index')
            ->with('status', 'access-tier-updated');
    }

    public function destroy(AccessTier $accessTier): RedirectResponse
    {
        if ($accessTier->users()->exists()) {
            return redirect()
                ->route('admin.access-tiers.index')
                ->withErrors([
                    'access_tier' => 'This access tier cannot be deleted because it is assigned to one or more students.',
                ]);
        }

        $this->deleteUploadedFileFromAnyStorage($accessTier->thumbnail);
        $accessTier->delete();

        return redirect()
            ->route('admin.access-tiers.index')
            ->with('status', 'access-tier-deleted');
    }
}
