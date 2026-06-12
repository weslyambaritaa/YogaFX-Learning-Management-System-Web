<?php

namespace Database\Factories;

use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'assignment_id' => null,
            'assignment_type' => 'graduation_video',
            'assignment_video' => fake()->url(),
            'assignment_status' => AssignmentSubmission::STATUS_PENDING_REVIEW,
            'assignment_feedback' => null,
            'submitted_at' => now()->subDay(),
            'graded_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }
}
