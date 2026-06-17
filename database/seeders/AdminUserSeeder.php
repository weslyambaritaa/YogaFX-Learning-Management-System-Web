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
            ['email' => 'rahelhbn@gmail.com'],
            [
                'name' => 'Rahel Hasibuan',
                'first_name' => 'Rahel',
                'last_name' => 'Hasibuan',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('rahelcantik123'),
                'email_verified_at' => now(),
            ],
        );
    }
}