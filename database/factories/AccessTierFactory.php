<?php

namespace Database\Factories;

use App\Models\AccessTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessTier>
 */
class AccessTierFactory extends Factory
{
    protected $model = AccessTier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name, '_'),
            'description' => fake()->sentence(),
            'price_amount' => fake()->randomFloat(2, 49, 999),
            'is_active' => true,
        ];
    }
}
