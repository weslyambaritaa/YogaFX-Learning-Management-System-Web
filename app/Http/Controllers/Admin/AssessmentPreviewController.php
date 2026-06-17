<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AssessmentResultsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentPreviewController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly AssessmentResultsService $resultsService,
    ) {}

    public function show(Request $request, Assessment $assessment): Response|RedirectResponse
    {
        $assessment->loadMissing(['design', 'questions.options', 'resultRanges']);
        $questions = $assessment->questions->sortBy('sort_order')->values();
        $firstQuestion = $questions->first();

        if (! $firstQuestion) {
            return redirect()
                ->route('admin.scoreboards.index')
                ->withErrors([
                    'assessment' => 'This assessment cannot be previewed because it has no questions yet.',
                ]);
        }

        if ($request->boolean('restart') || ! $request->session()->has($this->sessionKey($assessment))) {
            $this->putState($request, $assessment, [
                'started_at' => now()->toIso8601String(),
                'current_question_id' => $firstQuestion->id,
                'history' => [],
                'answers' => [],
                'completed_at' => null,
            ]);
        }

        $state = $this->state($request, $assessment);

        if (! empty($state['completed_at'])) {
            return redirect()->route('admin.assessments.preview.result', $assessment);
        }

        $currentQuestion = $questions->firstWhere('id', $state['current_question_id'] ?? $firstQuestion->id)
            ?? $firstQuestion;
        $currentIndex = $questions->search(fn (Question $question) => $question->id === $currentQuestion->id);

        return Inertia::render('Admin/Assessments/Preview', [
            'assessment' => $this->serializeAssessment($assessment, $questions, $currentIndex),
            'question' => $this->serializePlayerQuestion(
                $currentQuestion,
                collect($state['answers'][$currentQuestion->id] ?? []),
            ),
            'preview' => [
                'can_go_back' => $assessment->allow_back_navigation && ! empty($state['history']),
            ],
            'status' => session('status'),
        ]);
    }

    public function answer(Request $request, Assessment $assessment): RedirectResponse
    {
        $assessment->loadMissing(['questions.options', 'resultRanges']);
        $questions = $assessment->questions->sortBy('sort_order')->values();
        $firstQuestion = $questions->first();
        abort_unless($firstQuestion, 404);

        $state = $this->state($request, $assessment);
        $currentQuestion = $questions->firstWhere('id', $state['current_question_id'] ?? $firstQuestion->id)
            ?? $firstQuestion;

        $state['answers'] = collect($state['answers'] ?? [])
            ->only([
                ...collect($state['history'] ?? [])->map(fn ($value) => (string) $value)->all(),
                (string) $currentQuestion->id,
            ])
            ->all();

        $state['answers'][$currentQuestion->id] = $this->snapshotForQuestion($request, $currentQuestion);
        $state['completed_at'] = null;

        $nextQuestion = $this->resolveNextQuestion(
            $currentQuestion,
            $state['answers'][$currentQuestion->id],
            $questions,
        );

        if (! $nextQuestion) {
            $state['current_question_id'] = null;
            $state['completed_at'] = now()->toIso8601String();

            $this->putState($request, $assessment, $state);

            return redirect()->route('admin.assessments.preview.result', $assessment);
        }

        $history = collect($state['history'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($history->last() !== $currentQuestion->id) {
            $history->push($currentQuestion->id);
        }

        $state['history'] = $history->all();
        $state['current_question_id'] = $nextQuestion->id;

        $this->putState($request, $assessment, $state);

        return redirect()->route('admin.assessments.preview', $assessment);
    }

    public function back(Request $request, Assessment $assessment): RedirectResponse
    {
        $state = $this->state($request, $assessment);
        $history = collect($state['history'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($history->isEmpty()) {
            return redirect()->route('admin.assessments.preview', $assessment);
        }

        $state['current_question_id'] = $history->pop();
        $state['history'] = $history->all();
        $state['completed_at'] = null;

        $this->putState($request, $assessment, $state);

        return redirect()->route('admin.assessments.preview', $assessment);
    }

    public function result(Request $request, Assessment $assessment): Response|RedirectResponse
    {
        $assessment->loadMissing(['questions.options', 'resultRanges']);
        $state = $this->state($request, $assessment);

        if (empty($state['completed_at'])) {
            return redirect()->route('admin.assessments.preview', $assessment);
        }

        return Inertia::render('Admin/Assessments/PreviewResult', [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
            ],
            'preview_result' => $this->resultsService->buildPreviewPayload($assessment, $state),
        ]);
    }

    private function sessionKey(Assessment $assessment): string
    {
        return 'admin_assessment_preview.'.$assessment->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Request $request, Assessment $assessment): array
    {
        return $request->session()->get($this->sessionKey($assessment), []);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function putState(Request $request, Assessment $assessment, array $state): void
    {
        $request->session()->put($this->sessionKey($assessment), $state);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotForQuestion(Request $request, Question $question): array
    {
        if ($question->isInfoScreen()) {
            return [
                'option_ids' => [],
                'answer_text' => null,
                'answer_number' => null,
            ];
        }

        if ($question->isOptionBased()) {
            $availableOptions = $this->questionOptionsForPlayer($question);
            $selectedOptions = $this->validatedSelectedOptions(
                $request,
                $question,
                $availableOptions,
            );

            if (
                $this->questionUsesCorrectnessGate($question, $availableOptions)
                && ! $this->selectionIsFullyCorrect($selectedOptions, $availableOptions)
            ) {
                throw ValidationException::withMessages([
                    'option_ids' => 'Oops!!! Wrong Answer! Please refer to your workbook and try again.',
                ]);
            }

            return [
                'option_ids' => $selectedOptions->pluck('id')->map(fn ($value) => (int) $value)->all(),
                'answer_text' => null,
                'answer_number' => null,
            ];
        }

        if ($question->isNumericBased()) {
            $answerNumber = $request->input('answer_number');

            if ($question->required && ($answerNumber === null || $answerNumber === '')) {
                throw ValidationException::withMessages([
                    'answer_number' => 'Please enter a value before continuing.',
                ]);
            }

            if ($answerNumber !== null && $answerNumber !== '' && ! is_numeric($answerNumber)) {
                throw ValidationException::withMessages([
                    'answer_number' => 'Answer must be numeric.',
                ]);
            }

            $numericValue = $answerNumber === null || $answerNumber === ''
                ? null
                : (float) $answerNumber;

            if ($numericValue !== null && $question->score_range_min !== null && $numericValue < (float) $question->score_range_min) {
                throw ValidationException::withMessages([
                    'answer_number' => 'Answer is below the allowed minimum.',
                ]);
            }

            if ($numericValue !== null && $question->score_range_max !== null && $numericValue > (float) $question->score_range_max) {
                throw ValidationException::withMessages([
                    'answer_number' => 'Answer is above the allowed maximum.',
                ]);
            }

            if (! $question->allow_decimals && $numericValue !== null && floor($numericValue) !== $numericValue) {
                throw ValidationException::withMessages([
                    'answer_number' => 'This question only accepts whole numbers.',
                ]);
            }

            return [
                'option_ids' => [],
                'answer_text' => null,
                'answer_number' => $numericValue,
            ];
        }

        $answerText = $request->string('answer_text')->toString();

        if ($question->required && trim($answerText) === '') {
            throw ValidationException::withMessages([
                'answer_text' => 'Please enter your answer before continuing.',
            ]);
        }

        if ($question->character_limit !== null && mb_strlen($answerText) > $question->character_limit) {
            throw ValidationException::withMessages([
                'answer_text' => 'Answer exceeds the allowed character limit.',
            ]);
        }

        return [
            'option_ids' => [],
            'answer_text' => trim($answerText) === '' ? null : $answerText,
            'answer_number' => null,
        ];
    }

    private function resolveNextQuestion(
        Question $question,
        array $snapshot,
        Collection $questions,
    ): ?Question {
        $selectedOptionIds = collect($snapshot['option_ids'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $optionJumpTargetId = $question->options
            ->first(fn (QuestionOption $option) => $option->jump_enabled
                && $option->jump_to_question_id !== null
                && $selectedOptionIds->contains((int) $option->id))
            ?->jump_to_question_id;

        if ($optionJumpTargetId) {
            return $questions->firstWhere('id', $optionJumpTargetId);
        }

        if ($question->jump_enabled && $question->jump_to_question_id !== null) {
            return $questions->firstWhere('id', $question->jump_to_question_id);
        }

        return $questions->first(
            fn (Question $candidate) => $candidate->sort_order > $question->sort_order,
        );
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @return array<string, mixed>
     */
    private function serializeAssessment(Assessment $assessment, Collection $questions, int|false $currentIndex): array
    {
        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'allow_back_navigation' => $assessment->allow_back_navigation,
            'show_progress_bar' => $assessment->show_progress_bar,
            'design' => [
                'logo_url' => $assessment->design?->logo
                    ? $this->protectedMediaUrl(
                        'assessment-design',
                        $assessment->design->id,
                        'logo',
                        $assessment->design->logo,
                        versionSeed: $assessment->design->updated_at?->timestamp,
                    )
                    : null,
                'logo_max_width' => $assessment->design?->logo_max_width,
                'logo_alignment' => $assessment->design?->logo_alignment,
                'header_position' => $assessment->design?->header_position,
                'section_background' => $assessment->design?->section_background,
                'top_margin' => $assessment->design?->top_margin,
                'bottom_margin' => $assessment->design?->bottom_margin,
                'footer_content' => $assessment->design?->footer_content,
                'logo_link' => $assessment->design?->logo_link,
            ],
            'timer' => [
                'expires_at' => null,
            ],
            'progress' => [
                'current' => $currentIndex === false ? 1 : $currentIndex + 1,
                'total' => $questions->count(),
            ],
        ];
    }

    /**
     * @param  Collection<string, mixed>  $savedAnswer
     * @return array<string, mixed>
     */
    private function serializePlayerQuestion(Question $question, Collection $savedAnswer): array
    {
        return [
            'id' => $question->id,
            'title' => $question->title,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'show_instruction' => $question->show_instruction,
            'instruction_text' => $question->instruction_text,
            'required' => $question->required,
            'allow_multi_select' => $this->questionAllowsMultiSelect($question),
            'has_correctness_gate' => $this->questionUsesCorrectnessGate($question),
            'min_count' => $question->min_count,
            'max_count' => $question->max_count,
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
            'left_label' => $question->left_label,
            'center_label' => $question->center_label,
            'right_label' => $question->right_label,
            'saved' => [
                'option_ids' => collect($savedAnswer->get('option_ids', []))
                    ->map(fn ($value) => (int) $value)
                    ->values()
                    ->all(),
                'answer_text' => $savedAnswer->get('answer_text'),
                'answer_number' => $savedAnswer->get('answer_number'),
            ],
            'options' => $this->questionOptionsForPlayer($question)
                ->map(fn (QuestionOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'internal_value' => $option->internal_value,
                    'is_correct' => (bool) $option->is_correct,
                    'is_other_option' => $option->is_other_option,
                    'image_url' => $option->image
                        ? $this->protectedMediaUrl(
                            'question-option',
                            $option->id,
                            'image',
                            $option->image,
                            versionSeed: $option->updated_at?->timestamp,
                        )
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, QuestionOption>
     */
    private function questionOptionsForPlayer(Question $question): Collection
    {
        $options = $question->options->sortBy('sort_order')->values();

        if ($question->question_type === Question::TYPE_YES_NO_MAYBE) {
            $options = $options->reject(
                fn (QuestionOption $option) => $option->internal_value === 'maybe',
            )->values();
        }

        if ($question->randomize_answers_order) {
            $options = $options->shuffle()->values();
        }

        return $options;
    }

    /**
     * @param  Collection<int, QuestionOption>  $availableOptions
     * @return Collection<int, QuestionOption>
     */
    private function validatedSelectedOptions(
        Request $request,
        Question $question,
        Collection $availableOptions,
    ): Collection {
        $optionIds = collect($request->input('option_ids', []))
            ->merge($request->filled('option_id') ? [$request->input('option_id')] : [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $selectedOptions = $availableOptions->whereIn('id', $optionIds)->values();

        if ($question->required && $selectedOptions->isEmpty()) {
            throw ValidationException::withMessages([
                'option_ids' => 'Please choose at least one answer before continuing.',
            ]);
        }

        if (! $this->questionAllowsMultiSelect($question) && $selectedOptions->count() > 1) {
            throw ValidationException::withMessages([
                'option_ids' => 'Only one answer can be selected for this question.',
            ]);
        }

        if ($question->min_count !== null && $selectedOptions->count() < $question->min_count) {
            throw ValidationException::withMessages([
                'option_ids' => 'Select at least '.$question->min_count.' answers.',
            ]);
        }

        if ($question->max_count !== null && $selectedOptions->count() > $question->max_count) {
            throw ValidationException::withMessages([
                'option_ids' => 'Select no more than '.$question->max_count.' answers.',
            ]);
        }

        return $selectedOptions;
    }

    private function questionAllowsMultiSelect(Question $question): bool
    {
        if ($question->question_type === Question::TYPE_MULTIPLE_CHOICE_CHECKBOXES) {
            return true;
        }

        if (in_array($question->question_type, [
            Question::TYPE_MULTIPLE_CHOICE_BUTTONS,
            Question::TYPE_IMAGE_BUTTON,
        ], true)) {
            return (bool) $question->allow_multi_select;
        }

        return false;
    }

    private function questionUsesCorrectnessGate(
        Question $question,
        ?Collection $availableOptions = null,
    ): bool {
        if (! $question->isOptionBased()) {
            return false;
        }

        $availableOptions ??= $this->questionOptionsForPlayer($question);

        return $availableOptions->contains(
            fn (QuestionOption $option) => (bool) $option->is_correct,
        );
    }

    /**
     * @param  Collection<int, QuestionOption>  $selectedOptions
     * @param  Collection<int, QuestionOption>  $availableOptions
     */
    private function selectionIsFullyCorrect(
        Collection $selectedOptions,
        Collection $availableOptions,
    ): bool {
        $selectedIds = $selectedOptions
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        $correctIds = $availableOptions
            ->filter(fn (QuestionOption $option) => (bool) $option->is_correct)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        return $selectedIds->isNotEmpty()
            && $selectedIds->count() === $correctIds->count()
            && $selectedIds->values()->all() === $correctIds->values()->all();
    }
}
