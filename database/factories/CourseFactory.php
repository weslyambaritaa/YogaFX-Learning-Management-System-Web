<?php

namespace Database\Factories;

use App\Models\AccessTier;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

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
            'access_tier_id' => AccessTier::factory(),
            'description' => fake()->paragraph(),
            'thumbnail' => 'courses/default-thumbnail.jpg',
            'video' => fake()->url(),
        ];
    }
}
