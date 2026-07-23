<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => User::ROLE_STUDENT,
            'access_tier_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_STUDENT,
        ]);
    }

    public function completeProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'whatsapp' => fake()->numerify('08##########'),
            'preferred_certificate_picture' => fake()->optional()->imageUrl(),
            'profile_photo' => fake()->optional()->imageUrl(240, 240, 'people'),
            'instagram' => '@'.fake()->userName(),
            'country' => fake()->country(),
            'birth_date' => fake()->date(),
            'gender' => 'female',
            'practicing_yoga_for' => '0_to_3_years',
            'yoga_sequence_experience' => json_encode(['other']),
            'hours_per_week' => '4_7',
            'current_fitness_level' => 'average',
            'flexibility_rating' => 'average',
            'motivation' => fake()->sentence(),
            'why_yogafx' => fake()->sentence(),
            'how_did_you_find_us' => json_encode(['instagram']),
        ])->afterMaking(function (User $user): void {
            $user->syncDisplayName();
        })->afterCreating(function (User $user): void {
            $user->syncDisplayName();
            $user->save();
        });
    }
}
