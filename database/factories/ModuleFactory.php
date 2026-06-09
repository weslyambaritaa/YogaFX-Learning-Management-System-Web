<?php

namespace Database\Factories;

use App\Models\AccessTier;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => Str::title($title),
            'url_slug' => Str::slug($title),
            'thumbnail' => 'modules/default-thumbnail.jpg',
            'access_tier_id' => AccessTier::factory(),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
