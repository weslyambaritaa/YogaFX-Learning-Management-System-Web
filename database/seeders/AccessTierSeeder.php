<?php

namespace Database\Seeders;

use App\Models\AccessTier;
use Illuminate\Database\Seeder;

class AccessTierSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AccessTier::query()->upsert([
            [
                'name' => 'Starter Kit',
                'slug' => AccessTier::SLUG_STARTER_KIT,
                'description' => 'Entry-level access tier for students who need a lighter starting point.',
                'is_active' => true,
            ],
            [
                'name' => 'Online',
                'slug' => AccessTier::SLUG_ONLINE,
                'description' => 'Core online learning tier with full structured access for eligible students.',
                'is_active' => true,
            ],
            [
                'name' => 'Master Class',
                'slug' => AccessTier::SLUG_MASTER_CLASS,
                'description' => 'Advanced learning tier for premium master class access.',
                'is_active' => true,
            ],
        ], ['slug'], ['name', 'description', 'is_active']);
    }
}
