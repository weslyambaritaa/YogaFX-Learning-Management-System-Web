<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentAssessmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_choice_checkboxes_store_multiple_selected_answers(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();

        $question = $assessment->questions()->create([
            'title' => 'Select all that apply',
            'question_text' => 'Choose every option that fits.',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES,
            'sort_order' => 1,
            'required' => true,
            'allow_multi_select' => false,
            'show_labels' => true,
        ]);

        $firstOption = $question->options()->create([
            'label' => 'Option A',
            'internal_value' => 'a',
            'sort_order' => 1,
        ]);

        $secondOption = $question->options()->create([
            'label' => 'Option B',
            'internal_value' => 'b',
            'sort_order' => 2,
        ]);

        $attempt = AssessmentAttempt::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinute(),
            'current_question_id' => $question->id,
        ]);

        $this->actingAs($student)->post(
            route('assessments.answer', [
                'lesson' => $lesson,
                'attempt' => $attempt,
            ]),
            [
                'option_ids' => [$firstOption->id, $secondOption->id],
            ],
        )->assertRedirect(route('assessments.result', [
            'lesson' => $lesson->id,
            'attempt' => $attempt->id,
        ]));

        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_option_id' => $firstOption->id,
        ]);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_option_id' => $secondOption->id,
        ]);
    }

    public function test_completed_student_is_redirected_to_existing_result_instead_of_retaking(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();

        $question = $assessment->questions()->create([
            'title' => 'Single choice',
            'question_text' => 'Choose one option.',
            'question_type' => Question::TYPE_RADIO_BUTTONS,
            'sort_order' => 1,
            'required' => true,
        ]);

        $completedAttempt = AssessmentAttempt::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(6),
            'submitted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
            'last_answered_question_id' => $question->id,
            'total_score' => 10,
        ]);

        $this->actingAs($student)
            ->get(route('assessments.intro', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Assessments/Intro')
                ->where('completedAttempt.id', $completedAttempt->id));

        $this->actingAs($student)
            ->post(route('assessments.start', $lesson))
            ->assertRedirect(route('assessments.result', [
                'lesson' => $lesson->id,
                'attempt' => $completedAttempt->id,
            ]));

        $this->assertDatabaseCount('assessment_attempts', 1);
    }

    /**
     * @return array{0: User, 1: Lesson, 2: Assessment}
     */
    private function makeAssessmentContext(): array
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
        ]);

        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $assessment = Assessment::query()->create([
            'title' => 'Focused Assessment',
            'slug' => Str::slug('focused-assessment-'.Str::random(8)),
            'status' => Assessment::STATUS_LIVE,
            'is_active' => true,
            'show_progress_bar' => true,
            'allow_back_navigation' => true,
        ]);

        $assessment->design()->create([]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'assessment_id' => $assessment->id,
            'lesson_video_id' => null,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        return [$student, $lesson, $assessment];
    }
}
