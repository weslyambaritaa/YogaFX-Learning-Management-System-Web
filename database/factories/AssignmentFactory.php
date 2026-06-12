<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'sort_order' => null,
            'status' => Assignment::STATUS_LIVE,
            'is_required' => true,
        ];
    }
}
