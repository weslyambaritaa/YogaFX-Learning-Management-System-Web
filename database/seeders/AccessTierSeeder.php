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
                'payment_link' => '/starter-kit',
                'description' => 'Entry-level access tier for students who need a lighter starting point.',
                'thumbnail' => null,
                'price' => 0,
                'currency_code' => AccessTier::CURRENCY_IDR,
                'level' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Online',
                'slug' => AccessTier::SLUG_ONLINE,
                'payment_link' => '/online',
                'description' => 'Core online learning tier with full structured access for eligible students.',
                'thumbnail' => null,
                'price' => 0,
                'currency_code' => AccessTier::CURRENCY_IDR,
                'level' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Master Class',
                'slug' => AccessTier::SLUG_MASTER_CLASS,
                'payment_link' => '/masterclass',
                'description' => 'Advanced learning tier for premium master class access.',
                'thumbnail' => null,
                'price' => 0,
                'currency_code' => AccessTier::CURRENCY_IDR,
                'level' => 3,
                'is_active' => true,
            ],
        ], ['slug'], ['name', 'payment_link', 'description', 'thumbnail', 'price', 'currency_code', 'level', 'is_active']);
    }
}
