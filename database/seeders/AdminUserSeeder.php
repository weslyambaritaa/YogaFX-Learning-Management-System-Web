<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'tasyaaprilda21@gmail.com'],
            [
                'name' => 'Tasya Aprilda',
                'first_name' => 'Tasya',
                'last_name' => 'Aprilda',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('tasyaaprilda21'),
                'email_verified_at' => now(),
            ],
        );
    }
}
