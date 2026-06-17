<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentDesign;
use App\Models\AssessmentResultRange;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardBuilderController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function show(Request $request, Assessment $assessment): Response
    {
        $assessment->load([
            'design',
            'questions.options',
            'resultRanges',
        ]);

        $selectedQuestion = $assessment->questions
            ->firstWhere('id', (int) $request->integer('question'))
            ?? $assessment->questions->first();

        return Inertia::render('Admin/Scoreboards/Builder', [
            'scoreboard' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'status' => $assessment->status,
                'is_active' => $assessment->is_active,
                'allow_back_navigation' => $assessment->allow_back_navigation,
                'show_progress_bar' => $assessment->show_progress_bar,
            ],
            'questions' => $assessment->questions->map(fn (Question $question) => $this->serializeQuestion($question))->values()->all(),
            'selectedQuestionId' => $selectedQuestion?->id,
            'design' => $this->serializeDesign($assessment->design),
            'resultRanges' => $assessment->resultRanges->map(fn (AssessmentResultRange $range) => [
                'id' => $range->id,
                'title' => $range->title,
                'description' => $range->description,
                'min_score' => $range->min_score,
                'max_score' => $range->max_score,
                'sort_order' => $range->sort_order,
            ])->values()->all(),
            'questionTypeOptions' => $this->questionTypeOptions(),
            'questionTargets' => $assessment->questions
                ->map(fn (Question $question) => [
                    'id' => $question->id,
                    'label' => trim(($question->title ?: 'Question '.$question->sort_order).' (#'.$question->sort_order.')'),
                ])
                ->values()
                ->all(),
            'status' => session('status'),
        ]);
    }

    public function storeQuestion(Assessment $assessment): RedirectResponse
    {
        $sortOrder = ((int) $assessment->questions()->max('sort_order')) + 1;

        $question = $assessment->questions()->create([
            'title' => 'Question '.$sortOrder,
            'question_text' => '',
            'question_type' => Question::TYPE_RADIO_BUTTONS,
            'sort_order' => $sortOrder,
            'show_labels' => true,
            'scoring_category' => Question::SCORING_CATEGORY_OVERALL_ONLY,
        ]);

        return redirect()
            ->route('admin.scoreboards.builder', ['assessment' => $assessment->id, 'question' => $question->id])
            ->with('status', 'scoreboard-question-created');
    }

    public function updateQuestion(Request $request, Assessment $assessment, Question $question): RedirectResponse
    {
        abort_unless($question->assessment_id === $assessment->id, 404);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'question_text' => ['nullable', 'string'],
            'question_type' => ['required', Rule::in(array_column($this->questionTypeOptions(), 'value'))],
            'sort_order' => ['required', 'integer', 'min:1'],
            'show_instruction' => ['required', 'boolean'],
            'instruction_text' => ['nullable', 'string'],
            'required' => ['required', 'boolean'],
            'randomize_answers_order' => ['required', 'boolean'],
            'jump_enabled' => ['required', 'boolean'],
            'jump_to_question_id' => ['nullable', Rule::exists('questions', 'id')->where('assessment_id', $assessment->id)],
            'show_maybe_answer' => ['required', 'boolean'],
            'allow_multi_select' => ['required', 'boolean'],
            'min_count' => ['nullable', 'integer', 'min:0'],
            'max_count' => ['nullable', 'integer', 'min:0'],
            'allow_other_option' => ['required', 'boolean'],
            'show_labels' => ['required', 'boolean'],
            'score_range_min' => ['nullable', 'numeric'],
            'score_range_max' => ['nullable', 'numeric'],
            'starting_score' => ['nullable', 'numeric'],
            'section_count' => ['nullable', 'integer', 'min:1'],
            'allow_decimals' => ['required', 'boolean'],
            'input_type' => ['nullable', 'string', 'max:100'],
            'character_limit' => ['nullable', 'integer', 'min:1'],
            'show_score_tooltip' => ['required', 'boolean'],
            'score_tooltip_format' => ['nullable', 'string', 'max:255'],
            'answer_image_fit' => ['nullable', 'string', 'max:100'],
            'answers_per_row' => ['nullable', 'integer', 'min:1', 'max:6'],
            'scoring_category' => ['required', 'string', 'max:100'],
            'left_label' => ['nullable', 'string', 'max:255'],
            'center_label' => ['nullable', 'string', 'max:255'],
            'right_label' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['jump_enabled'] && (int) $validated['jump_to_question_id'] === $question->id) {
            throw ValidationException::withMessages([
                'jump_to_question_id' => 'Question jump target cannot point to the same question.',
            ]);
        }

        if (
            isset($validated['min_count'], $validated['max_count'])
            && $validated['max_count'] !== null
            && $validated['min_count'] !== null
            && $validated['min_count'] > $validated['max_count']
        ) {
            throw ValidationException::withMessages([
                'max_count' => 'Maximum selection count must be greater than or equal to minimum count.',
            ]);
        }

        if (
            isset($validated['score_range_min'], $validated['score_range_max'])
            && $validated['score_range_min'] !== null
            && $validated['score_range_max'] !== null
            && $validated['score_range_min'] > $validated['score_range_max']
        ) {
            throw ValidationException::withMessages([
                'score_range_max' => 'Score range max must be greater than or equal to min.',
            ]);
        }

        $validated = $this->normalizeQuestionSettings($validated);
        $this->validateSelectionRules($question, $validated);

        DB::transaction(function () use ($question, $validated): void {
            $question->update($validated);

            $freshQuestion = $question->refresh();

            $this->syncFixedOptions($freshQuestion);

            $validatedQuestion = $freshQuestion->refresh();

            $this->validateMultiSelectJumpTargets($validatedQuestion);
            $this->validateCorrectAnswerSelections($validatedQuestion);
        });

        return redirect()
            ->route('admin.scoreboards.builder', ['assessment' => $assessment->id, 'question' => $question->id])
            ->with('status', 'scoreboard-question-saved');
    }

    public function destroyQuestion(Assessment $assessment, Question $question): RedirectResponse
    {
        abort_unless($question->assessment_id === $assessment->id, 404);

        foreach ($question->options as $option) {
            $this->deleteUploadedFileFromAnyStorage($option->image);
        }

        $question->delete();

        return redirect()
            ->route('admin.scoreboards.builder', $assessment)
            ->with('status', 'scoreboard-question-deleted');
    }

    public function storeOption(Assessment $assessment, Question $question): RedirectResponse
    {
        abort_unless($question->assessment_id === $assessment->id, 404);

        $sortOrder = ((int) $question->options()->max('sort_order')) + 1;

        $option = $question->options()->create([
            'label' => 'Option '.$sortOrder,
            'internal_value' => 'option_'.$sortOrder,
            'sort_order' => $sortOrder,
            'is_correct' => false,
        ]);

        return redirect()
            ->route('admin.scoreboards.builder', ['assessment' => $assessment->id, 'question' => $question->id])
            ->with('status', 'scoreboard-option-created');
    }

    public function updateOption(Request $request, Assessment $assessment, Question $question, QuestionOption $option): RedirectResponse
    {
        abort_unless($question->assessment_id === $assessment->id && $option->question_id === $question->id, 404);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'internal_value' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'is_correct' => ['required', 'boolean'],
            'scoring_enabled' => ['required', 'boolean'],
            'score_value' => ['nullable', 'numeric'],
            'jump_enabled' => ['required', 'boolean'],
            'jump_to_question_id' => ['nullable', Rule::exists('questions', 'id')->where('assessment_id', $assessment->id)],
            'is_other_option' => ['required', 'boolean'],
        ]);

        if ($option->is_fixed_option && in_array($question->question_type, [Question::TYPE_YES_NO_MAYBE], true)) {
            unset($validated['label'], $validated['internal_value'], $validated['is_other_option']);
        }

        if ($validated['jump_enabled'] && (int) $validated['jump_to_question_id'] === $question->id) {
            throw ValidationException::withMessages([
                'jump_to_question_id' => 'Option jump target cannot point back to the same question.',
            ]);
        }

        if ($validated['scoring_enabled'] && $validated['score_value'] === null) {
            throw ValidationException::withMessages([
                'score_value' => 'Score value is required when scoring is enabled.',
            ]);
        }

        if ($validated['jump_enabled'] && $validated['jump_to_question_id'] === null) {
            throw ValidationException::withMessages([
                'jump_to_question_id' => 'Jump target is required when jump is enabled.',
            ]);
        }

        $validated['image'] = $question->question_type === Question::TYPE_IMAGE_BUTTON
            ? $this->storeUploadedFileToBunny(
                $request->file('image'),
                'assessments/question-options',
                $option->image,
            )
            : $this->storeUploadedFile(
                $request->file('image'),
                'assessments/question-options',
                $option->image,
            );

        DB::transaction(function () use ($option, $validated, $question): void {
            $option->update($validated);

            $validatedQuestion = $question->refresh();

            if ($validated['is_correct']) {
                $this->syncCorrectAnswerSelection($validatedQuestion, $option);
                $validatedQuestion = $question->refresh();
            }

            $this->validateMultiSelectJumpTargets($validatedQuestion);
            $this->validateCorrectAnswerSelections($validatedQuestion);
            $this->validateSelectionRules($validatedQuestion);
        });

        return redirect()
            ->route('admin.scoreboards.builder', ['assessment' => $assessment->id, 'question' => $question->id])
            ->with('status', 'scoreboard-option-saved');
    }

    public function destroyOption(Assessment $assessment, Question $question, QuestionOption $option): RedirectResponse
    {
        abort_unless($question->assessment_id === $assessment->id && $option->question_id === $question->id, 404);

        if ($option->is_fixed_option) {
            throw ValidationException::withMessages([
                'option' => 'Fixed options cannot be deleted.',
            ]);
        }

        $this->deleteUploadedFileFromAnyStorage($option->image);
        $option->delete();

        return redirect()
            ->route('admin.scoreboards.builder', ['assessment' => $assessment->id, 'question' => $question->id])
            ->with('status', 'scoreboard-option-deleted');
    }

    public function updateDesign(Request $request, Assessment $assessment): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'logo_max_width' => ['nullable', 'integer', 'min:10', 'max:1200'],
            'logo_alignment' => ['nullable', 'string', 'max:100'],
            'logo_link' => ['nullable', 'url', 'max:2048'],
            'header_position' => ['nullable', 'string', 'max:100'],
            'section_background' => ['nullable', 'string', 'max:255'],
            'top_margin' => ['nullable', 'integer', 'min:0', 'max:400'],
            'bottom_margin' => ['nullable', 'integer', 'min:0', 'max:400'],
            'footer_content' => ['nullable', 'string'],
        ]);

        $design = $assessment->design()->firstOrCreate([]);
        $validated['logo'] = $this->storeUploadedFile(
            $request->file('logo'),
            'assessments/designs',
            $design->logo,
        );

        $design->update($validated);

        return redirect()
            ->route('admin.scoreboards.builder', $assessment)
            ->with('status', 'scoreboard-design-saved');
    }

    public function storeResultRange(Assessment $assessment): RedirectResponse
    {
        $sortOrder = ((int) $assessment->resultRanges()->max('sort_order')) + 1;

        $assessment->resultRanges()->create([
            'title' => 'Range '.$sortOrder,
            'description' => null,
            'min_score' => 0,
            'max_score' => 0,
            'sort_order' => $sortOrder,
        ]);

        return redirect()
            ->route('admin.scoreboards.builder', $assessment)
            ->with('status', 'scoreboard-result-range-created');
    }

    public function updateResultRange(Request $request, Assessment $assessment, AssessmentResultRange $resultRange): RedirectResponse
    {
        abort_unless($resultRange->assessment_id === $assessment->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_score' => ['required', 'numeric'],
            'max_score' => ['required', 'numeric'],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        if ((float) $validated['min_score'] > (float) $validated['max_score']) {
            throw ValidationException::withMessages([
                'max_score' => 'Max score must be greater than or equal to min score.',
            ]);
        }

        $this->validateResultRanges($assessment, $resultRange, $validated);
        $resultRange->update($validated);

        return redirect()
            ->route('admin.scoreboards.builder', $assessment)
            ->with('status', 'scoreboard-result-range-saved');
    }

    public function destroyResultRange(Assessment $assessment, AssessmentResultRange $resultRange): RedirectResponse
    {
        abort_unless($resultRange->assessment_id === $assessment->id, 404);

        $resultRange->delete();

        return redirect()
            ->route('admin.scoreboards.builder', $assessment)
            ->with('status', 'scoreboard-result-range-deleted');
    }

    private function serializeQuestion(Question $question): array
    {
        return [
            'id' => $question->id,
            'title' => $question->title,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'sort_order' => $question->sort_order,
            'show_instruction' => $question->show_instruction,
            'instruction_text' => $question->instruction_text,
            'required' => $question->required,
            'randomize_answers_order' => $question->randomize_answers_order,
            'jump_enabled' => $question->jump_enabled,
            'jump_to_question_id' => $question->jump_to_question_id,
            'show_maybe_answer' => $question->show_maybe_answer,
            'allow_multi_select' => $question->allow_multi_select,
            'min_count' => $question->min_count,
            'max_count' => $question->max_count,
            'allow_other_option' => $question->allow_other_option,
            'show_labels' => $question->show_labels,
            'score_range_min' => $question->score_range_min,
            'score_range_max' => $question->score_range_max,
            'starting_score' => $question->starting_score,
            'section_count' => $question->section_count,
            'allow_decimals' => $question->allow_decimals,
            'input_type' => $question->input_type,
            'character_limit' => $question->character_limit,
            'show_score_tooltip' => $question->show_score_tooltip,
            'score_tooltip_format' => $question->score_tooltip_format,
            'answer_image_fit' => $question->answer_image_fit,
            'answers_per_row' => $question->answers_per_row,
            'scoring_category' => $question->scoring_category,
            'left_label' => $question->left_label,
            'center_label' => $question->center_label,
            'right_label' => $question->right_label,
            'options' => $question->options->map(fn (QuestionOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'internal_value' => $option->internal_value,
                'sort_order' => $option->sort_order,
                'is_correct' => $option->is_correct,
                'scoring_enabled' => $option->scoring_enabled,
                'score_value' => $option->score_value,
                'jump_enabled' => $option->jump_enabled,
                'jump_to_question_id' => $option->jump_to_question_id,
                'is_other_option' => $option->is_other_option,
                'is_fixed_option' => $option->is_fixed_option,
                'image_url' => $this->protectedMediaUrl(
                    'question-option',
                    $option->id,
                    'image',
                    $option->image,
                    versionSeed: $option->updated_at?->timestamp,
                ),
            ])->values()->all(),
        ];
    }

    private function serializeDesign(?AssessmentDesign $design): array
    {
        return [
            'logo_max_width' => $design?->logo_max_width,
            'logo_alignment' => $design?->logo_alignment,
            'logo_link' => $design?->logo_link,
            'header_position' => $design?->header_position,
            'section_background' => $design?->section_background,
            'top_margin' => $design?->top_margin,
            'bottom_margin' => $design?->bottom_margin,
            'footer_content' => $design?->footer_content,
            'logo_url' => $design?->logo
                ? route('media.show', ['entity' => 'assessment-design', 'id' => $design->id, 'field' => 'logo'])
                : null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function questionTypeOptions(): array
    {
        return [
            ['value' => Question::TYPE_YES_NO_MAYBE, 'label' => 'Yes / No'],
            ['value' => Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES, 'label' => 'Multiple Choice Checkboxes'],
            ['value' => Question::TYPE_MULTIPLE_CHOICE_BUTTONS, 'label' => 'Multiple Choice Buttons'],
            ['value' => Question::TYPE_RADIO_BUTTONS, 'label' => 'Radio Buttons'],
            ['value' => Question::TYPE_SLIDING_SCALE, 'label' => 'Sliding Scale'],
            ['value' => Question::TYPE_LINEAR_SCALE, 'label' => 'Linear Scale'],
            ['value' => Question::TYPE_DIVIDED_SCALE, 'label' => 'Divided Scale'],
            ['value' => Question::TYPE_NUMERIC, 'label' => 'Numeric'],
            ['value' => Question::TYPE_OPEN_TEXT, 'label' => 'Open Text'],
            ['value' => Question::TYPE_IMAGE_BUTTON, 'label' => 'Image Button'],
            ['value' => Question::TYPE_INFO_SCREEN, 'label' => 'Info Screen'],
        ];
    }

    private function syncFixedOptions(Question $question): void
    {
        if ($question->question_type === Question::TYPE_YES_NO_MAYBE) {
            $fixedOptions = collect([
                ['label' => 'Yes', 'internal_value' => 'yes', 'sort_order' => 1],
                ['label' => 'No', 'internal_value' => 'no', 'sort_order' => 2],
            ]);

            foreach ($fixedOptions as $fixedOption) {
                $question->options()->updateOrCreate(
                    ['internal_value' => $fixedOption['internal_value']],
                    [
                        'label' => $fixedOption['label'],
                        'sort_order' => $fixedOption['sort_order'],
                        'is_fixed_option' => true,
                    ],
                );
            }

            $maybeOption = $question->options()
                ->where('internal_value', 'maybe')
                ->first();

            if ($maybeOption) {
                $this->deleteUploadedFileFromAnyStorage($maybeOption->image);
                $maybeOption->delete();
            }
        }

        $supportsOtherOption = in_array($question->question_type, [
            Question::TYPE_MULTIPLE_CHOICE_BUTTONS,
            Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES,
            Question::TYPE_RADIO_BUTTONS,
            Question::TYPE_IMAGE_BUTTON,
        ], true);

        if ($question->allow_other_option && $supportsOtherOption) {
            $question->options()->updateOrCreate(
                ['is_other_option' => true],
                [
                    'label' => 'Other',
                    'internal_value' => 'other',
                    'sort_order' => ((int) $question->options()->where('is_other_option', false)->max('sort_order')) + 1,
                ],
            );
        } else {
            $otherOption = $question->options()->where('is_other_option', true)->first();

            if ($otherOption) {
                $this->deleteUploadedFileFromAnyStorage($otherOption->image);
                $otherOption->delete();
            }
        }
    }

    private function validateMultiSelectJumpTargets(Question $question): void
    {
        if (! $question->allow_multi_select) {
            return;
        }

        $targets = $question->options()
            ->where('jump_enabled', true)
            ->whereNotNull('jump_to_question_id')
            ->pluck('jump_to_question_id')
            ->unique();

        if ($targets->count() > 1) {
            throw ValidationException::withMessages([
                'jump_to_question_id' => 'Multi-select questions cannot have conflicting answer-level jump targets.',
            ]);
        }
    }

    private function validateCorrectAnswerSelections(Question $question): void
    {
        if (! $question->isOptionBased() || $this->questionAllowsMultipleCorrectAnswers($question)) {
            return;
        }

        $correctOptionCount = $question->options()
            ->where('is_correct', true)
            ->count();

        if ($correctOptionCount > 1) {
            throw ValidationException::withMessages([
                'is_correct' => 'This question type only allows one correct answer.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function validateSelectionRules(Question|array $questionOrPayload, ?array $payload = null): void
    {
        $questionType = $payload['question_type']
            ?? ($questionOrPayload instanceof Question
                ? $questionOrPayload->question_type
                : (string) ($questionOrPayload['question_type'] ?? ''));

        if ($questionType !== Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES) {
            return;
        }

        $minCount = $payload['min_count']
            ?? ($questionOrPayload instanceof Question
                ? $questionOrPayload->min_count
                : ($questionOrPayload['min_count'] ?? null));
        $maxCount = $payload['max_count']
            ?? ($questionOrPayload instanceof Question
                ? $questionOrPayload->max_count
                : ($questionOrPayload['max_count'] ?? null));
        $optionCount = $questionOrPayload instanceof Question
            ? $questionOrPayload->options()->where('is_other_option', false)->count()
            : null;
        $correctOptionCount = $questionOrPayload instanceof Question
            ? $questionOrPayload->options()->where('is_correct', true)->count()
            : null;

        if ($minCount !== null && (int) $minCount < 1) {
            throw ValidationException::withMessages([
                'min_count' => 'Minimum selection count must be at least 1 for checkbox questions.',
            ]);
        }

        if ($maxCount !== null && $minCount !== null && (int) $maxCount < (int) $minCount) {
            throw ValidationException::withMessages([
                'max_count' => 'Maximum selection count must be greater than or equal to minimum count.',
            ]);
        }

        if ($optionCount !== null && $maxCount !== null && (int) $maxCount > $optionCount) {
            throw ValidationException::withMessages([
                'max_count' => 'Maximum selection count cannot exceed the number of available checkbox answers.',
            ]);
        }

        if ($correctOptionCount !== null && $maxCount !== null && $correctOptionCount > (int) $maxCount) {
            throw ValidationException::withMessages([
                'max_count' => 'Maximum selection count cannot be lower than the number of correct checkbox answers.',
            ]);
        }
    }

    private function syncCorrectAnswerSelection(Question $question, QuestionOption $selectedOption): void
    {
        if ($this->questionAllowsMultipleCorrectAnswers($question)) {
            return;
        }

        $question->options()
            ->whereKeyNot($selectedOption->id)
            ->update(['is_correct' => false]);
    }

    private function questionAllowsMultipleCorrectAnswers(Question $question): bool
    {
        if ($question->question_type === Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES) {
            return true;
        }

        if (in_array($question->question_type, [
            Question::TYPE_MULTIPLE_CHOICE_BUTTONS,
            Question::TYPE_IMAGE_BUTTON,
        ], true)) {
            return $question->allow_multi_select;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeQuestionSettings(array $validated): array
    {
        $questionType = (string) ($validated['question_type'] ?? '');

        if ($questionType === Question::TYPE_YES_NO_MAYBE) {
            $validated['show_maybe_answer'] = false;
        }

        if (in_array($questionType, [
            Question::TYPE_LINEAR_SCALE,
            Question::TYPE_DIVIDED_SCALE,
        ], true)) {
            $validated['starting_score'] = null;
            $validated['allow_decimals'] = false;
        }

        if ($questionType !== Question::TYPE_DIVIDED_SCALE) {
            $validated['section_count'] = null;
        }

        if ($questionType !== Question::TYPE_SLIDING_SCALE) {
            $validated['show_score_tooltip'] = false;
            $validated['score_tooltip_format'] = null;
        }

        if (! in_array($questionType, [
            Question::TYPE_SLIDING_SCALE,
            Question::TYPE_LINEAR_SCALE,
            Question::TYPE_DIVIDED_SCALE,
        ], true)) {
            $validated['left_label'] = null;
            $validated['center_label'] = null;
            $validated['right_label'] = null;
        }

        if ($questionType !== Question::TYPE_OPEN_TEXT) {
            $validated['input_type'] = null;
            $validated['character_limit'] = null;
        } else {
            $validated['input_type'] = $this->normalizeOpenTextInputType(
                $validated['input_type'] ?? null,
            );
        }

        if ($questionType !== Question::TYPE_IMAGE_BUTTON) {
            $validated['answer_image_fit'] = null;
            $validated['answers_per_row'] = null;
            $validated['show_labels'] = true;
        } else {
            $validated['answer_image_fit'] = $this->normalizeAnswerImageFit(
                $validated['answer_image_fit'] ?? null,
            );
            $validated['answers_per_row'] = $this->normalizeAnswersPerRow(
                $validated['answers_per_row'] ?? null,
            );
        }

        if (! in_array($questionType, [
            Question::TYPE_MULTIPLE_CHOICE_BUTTONS,
            Question::TYPE_IMAGE_BUTTON,
        ], true)) {
            $validated['allow_multi_select'] = $questionType === Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES;
        }

        if ($questionType !== Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES) {
            $validated['min_count'] = null;
            $validated['max_count'] = null;
        }

        if ($questionType !== Question::TYPE_MULTIPLE_CHOICE_BUTTONS) {
            $validated['allow_other_option'] = false;
        }

        return $validated;
    }

    private function normalizeOpenTextInputType(mixed $inputType): string
    {
        return $inputType === 'textarea' || $inputType === 'multi_line'
            ? 'multi_line'
            : 'single_line';
    }

    private function normalizeAnswerImageFit(mixed $fit): string
    {
        return $fit === 'contain' ? 'contain' : 'cover';
    }

    private function normalizeAnswersPerRow(mixed $answersPerRow): int
    {
        return (int) ((int) $answersPerRow === 4 ? 4 : 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateResultRanges(Assessment $assessment, AssessmentResultRange $resultRange, array $payload): void
    {
        /** @var Collection<int, array{min_score: float, max_score: float}> $ranges */
        $ranges = $assessment->resultRanges()
            ->whereKeyNot($resultRange->id)
            ->get(['min_score', 'max_score'])
            ->map(fn (AssessmentResultRange $range) => [
                'min_score' => (float) $range->min_score,
                'max_score' => (float) $range->max_score,
            ]);

        $ranges->push([
            'min_score' => (float) $payload['min_score'],
            'max_score' => (float) $payload['max_score'],
        ]);

        $sorted = $ranges->sortBy('min_score')->values();

        for ($index = 1; $index < $sorted->count(); $index++) {
            $previous = $sorted[$index - 1];
            $current = $sorted[$index];

            if ($current['min_score'] <= $previous['max_score']) {
                throw ValidationException::withMessages([
                    'min_score' => 'Result ranges cannot overlap.',
                ]);
            }

            if (round($current['min_score'] - $previous['max_score'], 2) > 1) {
                throw ValidationException::withMessages([
                    'min_score' => 'Result ranges should not leave score gaps.',
                ]);
            }
        }
    }
}
