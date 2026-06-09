<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'assessment_id' => null,
            'title' => fake()->sentence(3),
            'thumbnail' => 'lessons/default-thumbnail.jpg',
            'workbook' => null,
            'video' => fake()->url(),
            'audio' => fake()->url(),
            'content' => fake()->paragraph(),
            'sort_order' => null,
        ];
    }
}
