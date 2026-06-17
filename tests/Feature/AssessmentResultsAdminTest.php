<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentProgress;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentResultsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_completed_results_only(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->completeProfile()->create();
        $assessment = $this->makeAssessment('Completed Results');
        [$question, $correctOption, $wrongOption] = $this->makeRadioQuestion($assessment);

        $firstAttempt = $this->makeCompletedAttempt($assessment, $student, 1, 10, $question, $correctOption);
        $secondAttempt = $this->makeCompletedAttempt($assessment, $student, 2, 0, $question, $wrongOption);

        AssessmentAttempt::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'attempt_number' => 3,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'current_question_id' => $question->id,
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.assessments.results.index', $assessment),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Assessments/ResultsIndex')
            ->where('assessment.id', $assessment->id)
            ->has('results', 2)
            ->where('results.0.id', $secondAttempt->id)
            ->where('results.1.id', $firstAttempt->id));
    }

    public function test_result_detail_only_shows_the_traversed_questions(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'whatsapp' => '+628123456789',
        ]);
        $assessment = $this->makeAssessment('Jump Logic Detail');

        $firstQuestion = $assessment->questions()->create([
            'title' => 'Choose a path',
            'question_text' => 'Select the branch to continue.',
            'question_type' => Question::TYPE_RADIO_BUTTONS,
            'sort_order' => 1,
            'show_labels' => true,
        ]);

        $skippedQuestion = $assessment->questions()->create([
            'title' => 'Skipped question',
            'question_text' => 'This question should not appear in the review.',
            'question_type' => Question::TYPE_OPEN_TEXT,
            'sort_order' => 2,
            'input_type' => 'text',
        ]);

        $jumpTargetQuestion = $assessment->questions()->create([
            'title' => 'Jump target',
            'question_text' => 'This is the question actually reached after the jump.',
            'question_type' => Question::TYPE_NUMERIC,
            'sort_order' => 3,
            'allow_decimals' => false,
            'score_range_min' => 0,
            'score_range_max' => 10,
        ]);

        $jumpOption = $firstQuestion->options()->create([
            'label' => 'Jump forward',
            'internal_value' => 'jump',
            'sort_order' => 1,
            'is_correct' => true,
            'jump_enabled' => true,
            'jump_to_question_id' => $jumpTargetQuestion->id,
        ]);

        $firstQuestion->options()->create([
            'label' => 'Stay on default path',
            'internal_value' => 'default',
            'sort_order' => 2,
        ]);

        $attempt = AssessmentAttempt::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now()->subMinutes(1),
            'completed_at' => now()->subMinutes(1),
            'last_answered_question_id' => $jumpTargetQuestion->id,
            'total_score' => 7,
        ]);

        $attempt->answers()->create([
            'question_id' => $firstQuestion->id,
            'question_option_id' => $jumpOption->id,
            'score_awarded' => 0,
            'is_final' => true,
            'answered_at' => now()->subMinutes(4),
        ]);

        $attempt->answers()->create([
            'question_id' => $jumpTargetQuestion->id,
            'answer_number' => 7,
            'score_awarded' => 7,
            'is_final' => true,
            'answered_at' => now()->subMinutes(3),
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.assessments.results.show', [
                'assessment' => $assessment,
                'attempt' => $attempt,
            ]),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Assessments/ResultDetail')
            ->where('attempt.user.phone', '+628123456789')
            ->where('attempt.summary.correct_answers_label', '1 / 1')
            ->has('attempt.questions', 2)
            ->where('attempt.questions.0.title', 'Choose a path')
            ->where('attempt.questions.1.title', 'Jump target'));
    }

    public function test_admin_can_delete_a_result_and_recompute_assessment_progress(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->completeProfile()->create();
        $assessment = $this->makeAssessment('Delete Result');
        [$question, $correctOption] = $this->makeRadioQuestion($assessment);

        $olderAttempt = $this->makeCompletedAttempt($assessment, $student, 1, 5, $question, $correctOption, now()->subDays(2));
        $latestAttempt = $this->makeCompletedAttempt($assessment, $student, 2, 8, $question, $correctOption, now()->subDay());

        AssessmentProgress::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'latest_score' => 8,
            'highest_score' => 8,
            'total_attempts' => 2,
            'is_done' => true,
            'completed_at' => $latestAttempt->completed_at,
        ]);

        $response = $this->actingAs($admin)->delete(
            route('admin.assessments.results.destroy', [
                'assessment' => $assessment,
                'attempt' => $latestAttempt,
            ]),
        );

        $response->assertRedirect(route('admin.assessments.results.index', $assessment));

        $this->assertDatabaseMissing('assessment_attempts', [
            'id' => $latestAttempt->id,
        ]);
        $this->assertDatabaseHas('assessment_attempts', [
            'id' => $olderAttempt->id,
        ]);
        $this->assertDatabaseHas('assessment_progress', [
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'latest_score' => 5,
            'highest_score' => 5,
            'total_attempts' => 1,
            'is_done' => true,
        ]);
    }

    public function test_admin_preview_is_ephemeral_and_does_not_create_attempt_records(): void
    {
        $admin = User::factory()->admin()->create();
        $assessment = $this->makeAssessment('Preview Ephemeral');
        [$question, $correctOption] = $this->makeRadioQuestion($assessment);

        $this->actingAs($admin)->get(
            route('admin.assessments.preview', [
                'assessment' => $assessment,
                'restart' => 1,
            ]),
        )->assertOk();

        $response = $this->actingAs($admin)->post(
            route('admin.assessments.preview.answer', $assessment),
            [
                'option_id' => $correctOption->id,
            ],
        );

        $response->assertRedirect(route('admin.assessments.preview.result', $assessment));

        $this->assertDatabaseCount('assessment_attempts', 0);
        $this->assertDatabaseCount('assessment_answers', 0);
        $this->assertDatabaseCount('assessment_progress', 0);
    }

    private function makeAssessment(string $title): Assessment
    {
        return Assessment::query()->create([
            'title' => $title,
            'slug' => Str::slug($title.'-'.Str::random(8)),
            'status' => Assessment::STATUS_DRAFT,
            'is_active' => true,
            'show_progress_bar' => true,
            'allow_back_navigation' => true,
        ]);
    }

    /**
     * @return array{Question, QuestionOption, QuestionOption}
     */
    private function makeRadioQuestion(Assessment $assessment): array
    {
        $question = $assessment->questions()->create([
            'title' => 'Pick the correct answer',
            'question_text' => 'Choose one option.',
            'question_type' => Question::TYPE_RADIO_BUTTONS,
            'sort_order' => 1,
            'show_labels' => true,
        ]);

        $correctOption = $question->options()->create([
            'label' => 'Correct',
            'internal_value' => 'correct',
            'sort_order' => 1,
            'is_correct' => true,
        ]);

        $wrongOption = $question->options()->create([
            'label' => 'Wrong',
            'internal_value' => 'wrong',
            'sort_order' => 2,
        ]);

        return [$question, $correctOption, $wrongOption];
    }

    private function makeCompletedAttempt(
        Assessment $assessment,
        User $student,
        int $attemptNumber,
        float $score,
        Question $question,
        QuestionOption $selectedOption,
        $completedAt = null,
    ): AssessmentAttempt {
        $completedAt = $completedAt ?? now()->subMinutes(10 - $attemptNumber);

        $attempt = AssessmentAttempt::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'attempt_number' => $attemptNumber,
            'status' => AssessmentAttempt::STATUS_COMPLETED,
            'started_at' => $completedAt->copy()->subMinutes(3),
            'submitted_at' => $completedAt,
            'completed_at' => $completedAt,
            'last_answered_question_id' => $question->id,
            'total_score' => $score,
        ]);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'question_option_id' => $selectedOption->id,
            'score_awarded' => $score,
            'is_final' => true,
            'answered_at' => $completedAt->copy()->subMinute(),
        ]);

        return $attempt;
    }
}
