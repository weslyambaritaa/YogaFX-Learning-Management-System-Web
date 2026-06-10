<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScoreboardBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_choice_checkboxes_allows_multiple_correct_answers(): void
    {
        $admin = User::factory()->admin()->create();
        [$assessment, $question, $firstOption, $secondOption] = $this->makeOptionQuestion(
            Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES,
        );

        $this->actingAs($admin)
            ->patch($this->optionUpdateRoute($assessment, $question, $firstOption), $this->optionPayload($firstOption, [
                'is_correct' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->patch($this->optionUpdateRoute($assessment, $question, $secondOption), $this->optionPayload($secondOption, [
                'is_correct' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('question_options', [
            'id' => $firstOption->id,
            'is_correct' => true,
        ]);

        $this->assertDatabaseHas('question_options', [
            'id' => $secondOption->id,
            'is_correct' => true,
        ]);
    }

    public function test_radio_buttons_still_reject_multiple_correct_answers(): void
    {
        $admin = User::factory()->admin()->create();
        [$assessment, $question, $firstOption, $secondOption] = $this->makeOptionQuestion(
            Question::TYPE_RADIO_BUTTONS,
        );

        $this->actingAs($admin)
            ->patch($this->optionUpdateRoute($assessment, $question, $firstOption), $this->optionPayload($firstOption, [
                'is_correct' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->patch($this->optionUpdateRoute($assessment, $question, $secondOption), $this->optionPayload($secondOption, [
                'is_correct' => true,
            ]))
            ->assertSessionHasErrors('is_correct');

        $this->assertDatabaseHas('question_options', [
            'id' => $firstOption->id,
            'is_correct' => true,
        ]);

        $this->assertDatabaseHas('question_options', [
            'id' => $secondOption->id,
            'is_correct' => false,
        ]);
    }

    /**
     * @return array{Assessment, Question, QuestionOption, QuestionOption}
     */
    private function makeOptionQuestion(string $questionType): array
    {
        $assessment = Assessment::query()->create([
            'title' => 'Builder Regression Assessment',
            'slug' => Str::slug($questionType.'-'.Str::random(8)),
            'status' => Assessment::STATUS_DRAFT,
        ]);

        $question = $assessment->questions()->create([
            'title' => 'Builder Regression Question',
            'question_text' => 'Pick the correct answers.',
            'question_type' => $questionType,
            'sort_order' => 1,
            'show_labels' => true,
            'scoring_category' => Question::SCORING_CATEGORY_OVERALL_ONLY,
        ]);

        $firstOption = $question->options()->create([
            'label' => 'Option A',
            'internal_value' => 'option_a',
            'sort_order' => 1,
        ]);

        $secondOption = $question->options()->create([
            'label' => 'Option B',
            'internal_value' => 'option_b',
            'sort_order' => 2,
        ]);

        return [$assessment, $question, $firstOption, $secondOption];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function optionPayload(QuestionOption $option, array $overrides = []): array
    {
        return array_merge([
            'label' => $option->label,
            'internal_value' => $option->internal_value,
            'sort_order' => $option->sort_order,
            'is_correct' => $option->is_correct,
            'scoring_enabled' => false,
            'score_value' => null,
            'jump_enabled' => false,
            'jump_to_question_id' => null,
            'is_other_option' => false,
        ], $overrides);
    }

    private function optionUpdateRoute(
        Assessment $assessment,
        Question $question,
        QuestionOption $option,
    ): string {
        return route('admin.scoreboards.options.update', [
            'assessment' => $assessment,
            'question' => $question,
            'option' => $option,
        ]);
    }
}
