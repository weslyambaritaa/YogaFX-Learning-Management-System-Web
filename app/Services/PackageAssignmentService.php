<?php

namespace App\Services;

use App\Models\AccessTier;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class PackageAssignmentService
{
    public function assignToTier(Package $package, ?AccessTier $accessTier): Package
    {
        return DB::transaction(function () use ($package, $accessTier): Package {
            /** @var Package $package */
            $package = Package::query()->lockForUpdate()->findOrFail($package->id);

            if ($accessTier === null) {
                $package->forceFill([
                    'access_tier_id' => null,
                ])->save();

                return $package->fresh('accessTier');
            }

            Package::query()
                ->whereKeyNot($package->id)
                ->where('access_tier_id', $accessTier->id)
                ->update(['access_tier_id' => null]);

            $package->forceFill([
                'access_tier_id' => $accessTier->id,
            ])->save();

            return $package->fresh('accessTier');
        });
    }
}
