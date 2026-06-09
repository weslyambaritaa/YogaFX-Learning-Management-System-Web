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
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
        'access_tier_id' => null, // Default tanpa paket akses
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
            'instagram' => '@'.fake()->userName(),
            'country' => fake()->country(),
            'birth_date' => fake()->date(),
            'gender' => 'prefer_not_to_say',
            'practicing_yoga_for' => '1-3 years',
            'yoga_sequence_experience' => 'Beginner',
            'hours_per_week' => 4,
            'current_fitness_level' => 'Intermediate',
            'flexibility_rating' => 'Moderate',
            'motivation' => fake()->sentence(),
            'why_yogafx' => fake()->sentence(),
            'how_did_you_find_us' => 'Instagram',
        ])->afterMaking(function (User $user): void {
            $user->syncDisplayName();
        })->afterCreating(function (User $user): void {
            $user->syncDisplayName();
            $user->save();
        });
    }
}
