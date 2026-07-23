<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonIrregularActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonIrregularActivity>
 */
class LessonIrregularActivityFactory extends Factory
{
    protected $model = LessonIrregularActivity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'violation_count' => fake()->numberBetween(0, 2),
            'last_violation_at' => now()->subDay(),
            'blocked_at' => null,
            'blocked_reason' => null,
            'reset_at' => null,
        ];
    }
}
