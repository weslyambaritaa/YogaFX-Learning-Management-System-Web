<?php

namespace Database\Seeders;

use App\Models\AccessTier;
use App\Models\Package;
use App\Services\PackageAssignmentService;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(PackageAssignmentService $assignmentService): void
    {
        $definitions = [
            AccessTier::SLUG_MASTER_CLASS => 'Masterclass Standard',
            AccessTier::SLUG_ONLINE => 'Online Standard',
            AccessTier::SLUG_STARTER_KIT => 'Starter-kit Standard',
        ];

        foreach ($definitions as $tierSlug => $title) {
            $tier = AccessTier::query()
                ->where('slug', $tierSlug)
                ->first();

            if (! $tier) {
                continue;
            }

            $package = Package::query()->updateOrCreate(
                ['slug' => str($title)->slug()->value()],
                [
                    'title' => $title,
                    'description' => $tier->description,
                    'image' => $tier->thumbnail,
                    'price' => $tier->price,
                    'currency_code' => $tier->currency_code,
                    'is_active' => $tier->is_active,
                    'installment_enabled' => false,
                ],
            );

            $assignmentService->assignToTier($package, $tier);
        }
    }
}
