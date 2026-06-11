<?php

namespace Database\Seeders;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $onlineTierId = AccessTier::query()
            ->where('slug', AccessTier::SLUG_ONLINE)
            ->value('id');

        User::query()->updateOrCreate(
            ['email' => 'student@yogafx.test'],
            [
                'name' => 'YogaFX Student',
                'role' => User::ROLE_STUDENT,
                'access_tier_id' => $onlineTierId,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'first_name' => 'YogaFX',
                'last_name' => 'Student',
                'whatsapp' => '081234567890',
                'preferred_certificate_picture' => null,
                'profile_photo' => null,
                'instagram' => '@yogafxstudent',
                'country' => 'Indonesia',
                'birth_date' => '1998-01-01',
                'gender' => 'prefer_not_to_say',
                'practicing_yoga_for' => '1-3 years',
                'yoga_sequence_experience' => 'Beginner',
                'hours_per_week' => 4,
                'current_fitness_level' => 'Intermediate',
                'flexibility_rating' => 'Moderate',
                'motivation' => 'Improve yoga consistency and deepen practice.',
                'why_yogafx' => 'To study in a structured YogaFX learning path.',
                'how_did_you_find_us' => 'Instagram',
            ],
        );
    }
}
