<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'lesson_id' => Lesson::factory(),
            'watch_progress' => 100,
            'is_workbook_downloaded' => true,
            'workbook_downloaded_at' => now()->subDay(),
            'video_completed_at' => now()->subDay(),
            'is_done' => true,
            'completed_at' => now()->subDay(),
        ];
    }
}
