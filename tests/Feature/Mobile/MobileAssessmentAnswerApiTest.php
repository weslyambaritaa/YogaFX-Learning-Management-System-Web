<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\Mobile\V1\StudentAssessmentApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAssessmentAnswerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_single_choice_correct_option_is_accepted(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();
        [$question, $correctOption] = $this->createSingleChoiceQuestion($assessment);

        $attempt = $this->createAttempt($student, $assessment, $question);

        Sanctum::actingAs($student);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'option_ids' => [$correctOption->id],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'result_redirect')
            ->assertJsonPath('data.attempt_id', $attempt->id);

        $this->assertDatabaseHas('assessment_answers', [
            'assessment_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_option_id' => $correctOption->id,
        ]);
    }

    public function test_mobile_single_choice_wrong_option_is_rejected(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();
        [$question, $correctOption, $wrongOption] = $this->createSingleChoiceQuestion($assessment);

        $attempt = $this->createAttempt($student, $assessment, $question);

        Sanctum::actingAs($student);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'option_ids' => [$wrongOption->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The submitted answer is incorrect.')
            ->assertJsonPath('errors.option_ids.0', StudentAssessmentApiService::WRONG_ANSWER_MESSAGE);

        $this->assertDatabaseMissing('assessment_answers', [
            'assessment_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_option_id' => $wrongOption->id,
        ]);

        $this->assertSame($question->id, $attempt->fresh()->current_question_id);
        $this->assertNotNull($correctOption);
    }

    public function test_mobile_get_question_options_stay_consistent_with_post_validation(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();
        [$question] = $this->createSingleChoiceQuestion($assessment);

        $attempt = $this->createAttempt($student, $assessment, $question);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'question');

        $questionPayload = $response->json('data.question');
        $correctOptionId = collect($questionPayload['options'] ?? [])
            ->firstWhere('is_correct', true)['id'] ?? null;

        $this->assertSame($question->id, $questionPayload['id']);
        $this->assertNotNull($correctOptionId);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}/answer", [
            'question_id' => $questionPayload['id'],
            'option_ids' => [$correctOptionId],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'result_redirect');
    }

    public function test_mobile_answer_redirects_when_mobile_retries_an_already_saved_previous_question(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();
        [$firstQuestion, $firstCorrectOption] = $this->createSingleChoiceQuestion($assessment, 1, 'First question');
        [$secondQuestion] = $this->createSingleChoiceQuestion($assessment, 2, 'Second question');

        $attempt = $this->createAttempt($student, $assessment, $firstQuestion);

        Sanctum::actingAs($student);

        $this->getJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.question.id', $firstQuestion->id);

        $attempt->answers()->create([
            'question_id' => $firstQuestion->id,
            'question_option_id' => $firstCorrectOption->id,
            'score_awarded' => 0,
            'is_final' => true,
            'answered_at' => now(),
        ]);

        $attempt->update([
            'current_question_id' => $secondQuestion->id,
            'last_answered_question_id' => $firstQuestion->id,
        ]);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}/answer", [
            'question_id' => $firstQuestion->id,
            'option_ids' => [$firstCorrectOption->id],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'question_redirect')
            ->assertJsonPath('data.question_id', $secondQuestion->id);

        $this->assertDatabaseCount('assessment_answers', 1);
    }

    public function test_mobile_answer_rejects_unknown_stale_question_payload(): void
    {
        [$student, $lesson, $assessment] = $this->makeAssessmentContext();
        [$firstQuestion, $firstCorrectOption] = $this->createSingleChoiceQuestion($assessment, 1, 'First question');
        [$secondQuestion] = $this->createSingleChoiceQuestion($assessment, 2, 'Second question');

        $attempt = $this->createAttempt($student, $assessment, $secondQuestion);

        Sanctum::actingAs($student);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/assessment/attempts/{$attempt->id}/answer", [
            'question_id' => $firstQuestion->id,
            'option_ids' => [$firstCorrectOption->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The assessment answer payload is invalid.')
            ->assertJsonPath('errors.question_id.0', StudentAssessmentApiService::INVALID_ACTIVE_QUESTION_MESSAGE);
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
            'is_active' => true,
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $assessment = Assessment::query()->create([
            'title' => 'Mobile Assessment',
            'slug' => Str::slug('mobile-assessment-'.Str::random(8)),
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

    /**
     * @return array{0: Question, 1: QuestionOption, 2: QuestionOption}
     */
    private function createSingleChoiceQuestion(
        Assessment $assessment,
        int $sortOrder = 1,
        string $promptPrefix = 'Single choice question',
    ): array {
        $question = $assessment->questions()->create([
            'title' => $promptPrefix,
            'question_text' => $promptPrefix.' text.',
            'question_type' => Question::TYPE_RADIO_BUTTONS,
            'sort_order' => $sortOrder,
            'required' => true,
            'allow_multi_select' => false,
            'show_labels' => true,
        ]);

        $correctOption = $question->options()->create([
            'label' => 'Correct option',
            'internal_value' => 'correct',
            'sort_order' => 1,
            'is_correct' => true,
        ]);

        $wrongOption = $question->options()->create([
            'label' => 'Wrong option',
            'internal_value' => 'wrong',
            'sort_order' => 2,
            'is_correct' => false,
        ]);

        return [$question, $correctOption, $wrongOption];
    }

    private function createAttempt(User $student, Assessment $assessment, Question $question): AssessmentAttempt
    {
        return AssessmentAttempt::query()->create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'attempt_number' => 1,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinute(),
            'current_question_id' => $question->id,
        ]);
    }
}
