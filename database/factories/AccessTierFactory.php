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
            'thumbnail' => null,
            'price' => fake()->randomFloat(2, 49, 999),
            'currency_code' => fake()->randomElement(AccessTier::CURRENCY_OPTIONS),
            'level' => fake()->numberBetween(1, 5),
            'is_active' => true,
        ];
    }
}
