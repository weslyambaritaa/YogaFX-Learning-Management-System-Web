<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccessTierRequest;
use App\Models\AccessTier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccessTierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/AccessTiers/Index', [
            'accessTiers' => AccessTier::query()
                ->withCount('users')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'description' => $accessTier->description,
                    'is_active' => $accessTier->is_active,
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
        AccessTier::query()->create($request->validated());

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
                'slug' => $accessTier->slug,
                'description' => $accessTier->description,
                'is_active' => $accessTier->is_active,
                'users_count' => $accessTier->users_count,
            ],
            'status' => session('status'),
        ]);
    }

    public function update(AccessTierRequest $request, AccessTier $accessTier): RedirectResponse
    {
        $accessTier->update($request->validated());

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

        $accessTier->delete();

        return redirect()
            ->route('admin.access-tiers.index')
            ->with('status', 'access-tier-deleted');
    }
}
