<?php

namespace App\Services\Mobile\V1;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Support\Collection;

class MobileUpgradeOptionService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function optionsForStudent(User $user): array
    {
        $currentLevel = (int) ($user->accessTier?->level ?? 0);

        return AccessTier::query()
            ->where('is_active', true)
            ->where('level', '>', $currentLevel)
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn (AccessTier $accessTier) => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'slug' => $accessTier->slug,
                'description' => $accessTier->description,
                'price' => (float) $accessTier->price,
                'currency_code' => $accessTier->currency_code,
                'level' => $accessTier->level,
                'upgrade_url' => route('student.upgrades.show', $accessTier),
            ])
            ->values()
            ->all();
    }
}
