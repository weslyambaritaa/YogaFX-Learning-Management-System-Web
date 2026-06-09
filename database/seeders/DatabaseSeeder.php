<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AccessTier;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Buat data Hak Akses (Access Tiers) terlebih dahulu
        $starterKit = AccessTier::create([
            'name' => 'Starter Kit',
            'description' => 'Paket akses tingkat dasar Starter Kit',
        ]);

        $masterClass = AccessTier::create([
            'name' => 'Master Class',
            'description' => 'Paket akses tingkat lanjut Master Class',
        ]);

        $online = AccessTier::create([
            'name' => 'Online',
            'description' => 'Paket akses penuh secara Online',
        ]);

        // 2. Buat Akun Test untuk STUDENT (Diberi paket Starter Kit)
        User::factory()->create([
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'access_tier_id' => $starterKit->id, // Terhubung ke paket Starter Kit
        ]);

        // 3. Buat Akun Test untuk ADMIN (access_tier_id dikosongkan/null)
        User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'access_tier_id' => null, // Menandakan bahwa ini adalah Admin
        ]);
    }
}