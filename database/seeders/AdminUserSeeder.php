<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'marketing@yogafx.com'],
            [
                'name' => 'Marketing YogaFX',
                'role' => User::ROLE_SUPER_ADMIN,
                'password' => Hash::make('marketingyogaFX'),
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'weslyambarita4@gmail.com'],
            [
                'name' => 'Wesly Ambarita',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('weslyambarita4'),
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'tasyaaprilda21@gmail.com'],
            [
                'name' => 'Tasya Aprilda',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('tasyaaprilda21'),
                'email_verified_at' => now(),
            ],
        );
    }
}
