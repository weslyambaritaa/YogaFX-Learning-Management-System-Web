<?php

namespace App\Services;

use App\Models\AccessTier;
use App\Models\Package;

class PackageResolverService
{
    public function checkoutablePackages()
    {
        return Package::query()
            ->with('accessTier')
            ->where('is_active', true)
            ->whereNotNull('access_tier_id')
            ->orderBy('price')
            ->orderBy('title')
            ->get();
    }

    public function resolveActivePackageForTierSlug(string $tierSlug): ?Package
    {
        $canonicalSlug = AccessTier::canonicalSlug($tierSlug);

        $accessTier = AccessTier::query()
            ->where('is_active', true)
            ->where('slug', $canonicalSlug)
            ->first();

        if (! $accessTier) {
            return null;
        }

        return Package::query()
            ->with('accessTier')
            ->where('is_active', true)
            ->where('access_tier_id', $accessTier->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function resolveActivePackageBySlug(string $packageSlug): ?Package
    {
        return Package::query()
            ->with('accessTier')
            ->where('slug', $packageSlug)
            ->where('is_active', true)
            ->whereNotNull('access_tier_id')
            ->first();
    }
}
