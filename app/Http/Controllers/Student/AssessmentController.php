<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentProgress;
use App\Models\AssessmentResultRange;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function intro(Request $request, Lesson $lesson): Response
    {
        $user = $request->user();
        abort_unless($user && $lesson->assessment, 404);

        $this->authorizeLessonAssessmentAccess($lesson, $user->id);
        $attempt = $this->resolveInProgressAttempt($lesson->assessment, $user->id);
        $progress = $this->lessonProgress($lesson, $user->id);

        return Inertia::render('Student/Assessments/Intro', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'module' => $lesson->module ? [
                    'title' => $lesson->module->title,
                    'url_slug' => $lesson->module->url_slug,
                ] : null,
            ],
            'assessment' => [
                'id' => $lesson->assessment->id,
                'title' => $lesson->assessment->title,
                'description' => $lesson->assessment->description,
                'duration_minutes' => $lesson->assessment->duration_minutes,
                'show_progress_bar' => $lesson->assessment->show_progress_bar,
                'allow_back_navigation' => $lesson->assessment->allow_back_navigation,
                'thumbnail_url' => $lesson->assessment->thumbnail
                    ? route('media.show', ['entity' => 'assessment', 'id' => $lesson->assessment->id, 'field' => 'thumbnail'])
                    : null,
            ],
            'eligibility' => [
                'is_unlocked' => $this->isAssessmentUnlocked($lesson, $progress),
                'watch_progress' => $progress?->watch_progress,
                'requires_watch_progress' => $lesson->lesson_video_id !== null,
            ],
            'attempt' => $attempt ? [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at?->toDateTimeString(),
                'expires_at' => $attempt->expires_at?->toDateTimeString(),
            ] : null,
        ]);
    }

    public function start(Request $request, Lesson $lesson): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $lesson->assessment, 404);

        $this->authorizeLessonAssessmentAccess($lesson, $user->id, true);

        $assessment = $lesson->assessment;
        $attempt = $this->resolveInProgressAttempt($assessment, $user->id);

        if (! $attempt) {
            $firstQuestion = $assessment->questions()->orderBy('sort_order')->orderBy('id')->first();

            if (! $firstQuestion) {
                throw ValidationException::withMessages([
                    'assessment' => 'This assessment has no questions yet.',
                ]);
            }

            $attempt = AssessmentAttempt::query()->create([
                'user_id' => $user->id,
                'assessment_id' => $assessment->id,
                'attempt_number' => ((int) AssessmentAttempt::query()
                    ->where('user_id', $user->id)
                    ->where('assessment_id', $assessment->id)
                    ->max('attempt_number')) + 1,
                'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'expires_at' => $assessment->duration_minutes
                    ? now()->addMinutes($assessment->duration_minutes)
                    : null,
                'current_question_id' => $firstQuestion->id,
            ]);
        }

        return redirect()->route('assessments.show', [
            'lesson' => $lesson->id,
            'attempt' => $attempt->id,
        ]);
    }

    public function show(Request $request, Lesson $lesson, AssessmentAttempt $attempt): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $lesson->assessment, 404);
        abort_unless($attempt->user_id === $user->id && $attempt->assessment_id === $lesson->assessment_id, 404);

        $this->authorizeLessonAssessmentAccess($lesson, $user->id, true);

        if ($redirect = $this->handleExpiry($lesson, $attempt)) {
            return $redirect;
        }

        $assessment = $lesson->assessment->load(['design', 'questions.options', 'resultRanges']);
        $question = $attempt->currentQuestion ?? $assessment->questions->sortBy('sort_order')->first();

        abort_unless($question, 404);

        $savedAnswers = $attempt->answers()
            ->where('question_id', $question->id)
            ->get();

        $orderedQuestions = $assessment->questions->sortBy('sort_order')->values();
        $currentIndex = $orderedQuestions->search(fn (Question $item) => $item->id === $question->id);

        return Inertia::render('Student/Assessments/Show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
            ],
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'allow_back_navigation' => $assessment->allow_back_navigation,
                'show_progress_bar' => $assessment->show_progress_bar,
                'design' => [
                    'logo_url' => $assessment->design?->logo
                        ? route('media.show', ['entity' => 'assessment-design', 'id' => $assessment->design->id, 'field' => 'logo'])
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
                    'expires_at' => $attempt->expires_at?->toIso8601String(),
                ],
                'progress' => [
                    'current' => $currentIndex === false ? 1 : $currentIndex + 1,
                    'total' => $orderedQuestions->count(),
                ],
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'expires_at' => $attempt->expires_at?->toIso8601String(),
            ],
            'question' => $this->serializePlayerQuestion($question, $savedAnswers),
            'canGoBack' => $assessment->allow_back_navigation && $currentIndex !== false && $currentIndex > 0,
            'isLastQuestion' => $currentIndex !== false && $currentIndex === $orderedQuestions->count() - 1,
        ]);
    }

    public function storeAnswer(Request $request, Lesson $lesson, AssessmentAttempt $attempt): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $lesson->assessment, 404);
        abort_unless($attempt->user_id === $user->id && $attempt->assessment_id === $lesson->assessment_id, 404);

        $this->authorizeLessonAssessmentAccess($lesson, $user->id, true);

        if ($redirect = $this->handleExpiry($lesson, $attempt)) {
            return $redirect;
        }

        $assessment = $lesson->assessment->load('questions.options', 'resultRanges');
        $question = $attempt->currentQuestion ?? $assessment->questions->sortBy('sort_order')->first();
        abort_unless($question, 404);

        $nextQuestion = $this->persistQuestionAnswer($request, $attempt, $question, $assessment->questions);

        if (! $nextQuestion) {
            return $this->completeAttempt($lesson, $attempt, AssessmentAttempt::FINISHED_REASON_MANUAL_SUBMIT);
        }

        $attempt->update([
            'current_question_id' => $nextQuestion->id,
        ]);

        return redirect()->route('assessments.show', [
            'lesson' => $lesson->id,
            'attempt' => $attempt->id,
        ]);
    }

    public function back(Request $request, Lesson $lesson, AssessmentAttempt $attempt): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $lesson->assessment, 404);
        abort_unless($attempt->user_id === $user->id && $attempt->assessment_id === $lesson->assessment_id, 404);

        $assessment = $lesson->assessment->load('questions');
        $currentQuestion = $attempt->currentQuestion ?? $assessment->questions->sortBy('sort_order')->first();
        abort_unless($currentQuestion, 404);

        $previousQuestion = $assessment->questions
            ->sortBy('sort_order')
            ->values()
            ->first(fn (Question $question) => $question->sort_order === $currentQuestion->sort_order - 1)
            ?? $assessment->questions
                ->sortBy('sort_order')
                ->values()
                ->filter(fn (Question $question) => $question->sort_order < $currentQuestion->sort_order)
                ->last();

        if ($previousQuestion) {
            $attempt->update([
                'current_question_id' => $previousQuestion->id,
            ]);
        }

        return redirect()->route('assessments.show', [
            'lesson' => $lesson->id,
            'attempt' => $attempt->id,
        ]);
    }

    public function result(Request $request, Lesson $lesson, AssessmentAttempt $attempt): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $lesson->assessment, 404);
        abort_unless($attempt->user_id === $user->id && $attempt->assessment_id === $lesson->assessment_id, 404);

        if ($attempt->status === AssessmentAttempt::STATUS_IN_PROGRESS) {
            return redirect()->route('assessments.show', [
                'lesson' => $lesson->id,
                'attempt' => $attempt->id,
            ]);
        }

        $attempt->load('resultRange');

        return Inertia::render('Student/Assessments/Result', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'module' => $lesson->module ? [
                    'title' => $lesson->module->title,
                    'url_slug' => $lesson->module->url_slug,
                ] : null,
            ],
            'assessment' => [
                'id' => $lesson->assessment->id,
                'title' => $lesson->assessment->title,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'finished_reason' => $attempt->finished_reason,
                'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
                'completed_at' => $attempt->completed_at?->toDateTimeString(),
                'total_score' => $attempt->total_score,
                'result_label' => $attempt->result_label,
                'result_description' => $attempt->resultRange?->description,
            ],
        ]);
    }

    private function authorizeLessonAssessmentAccess(Lesson $lesson, int $userId, bool $requireUnlocked = false): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && $lesson->assessment_id !== null
            && $lesson->assessment?->status === Assessment::STATUS_LIVE
            && $lesson->assessment?->is_active
            && $lesson->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists()
            && $lesson->module?->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        if ($requireUnlocked) {
            abort_unless($this->isAssessmentUnlocked($lesson, $this->lessonProgress($lesson, $userId)), 403);
        }
    }

    private function lessonProgress(Lesson $lesson, int $userId): ?LessonProgress
    {
        return LessonProgress::query()
            ->where('lesson_id', $lesson->id)
            ->where('user_id', $userId)
            ->first();
    }

    private function isAssessmentUnlocked(Lesson $lesson, ?LessonProgress $progress): bool
    {
        if ($lesson->lesson_video_id === null) {
            return true;
        }

        return (float) ($progress?->watch_progress ?? 0) >= 95;
    }

    private function resolveInProgressAttempt(Assessment $assessment, int $userId): ?AssessmentAttempt
    {
        $attempt = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('user_id', $userId)
            ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();

        if (! $attempt) {
            return null;
        }

        if ($attempt->expires_at && $attempt->expires_at->isPast()) {
            $this->expireAttempt($attempt);

            return null;
        }

        return $attempt;
    }

    private function handleExpiry(Lesson $lesson, AssessmentAttempt $attempt): ?RedirectResponse
    {
        if ($attempt->expires_at && $attempt->expires_at->isPast()) {
            return $this->expireAttempt($attempt, $lesson);
        }

        return null;
    }

    private function expireAttempt(AssessmentAttempt $attempt, ?Lesson $lesson = null): RedirectResponse
    {
        $lesson = $lesson ?? Lesson::query()->where('assessment_id', $attempt->assessment_id)->firstOrFail();

        return $this->completeAttempt($lesson, $attempt, AssessmentAttempt::FINISHED_REASON_EXPIRED);
    }

    private function completeAttempt(Lesson $lesson, AssessmentAttempt $attempt, string $finishedReason): RedirectResponse
    {
        $attempt->loadMissing(['assessment.resultRanges', 'answers']);

        $totalScore = (float) $attempt->answers->sum(fn (AssessmentAnswer $answer) => (float) ($answer->score_awarded ?? 0));
        $resultRange = $this->resolveResultRange($attempt->assessment, $totalScore);
        $isExpired = $finishedReason === AssessmentAttempt::FINISHED_REASON_EXPIRED;

        $attempt->update([
            'status' => $isExpired ? AssessmentAttempt::STATUS_EXPIRED : AssessmentAttempt::STATUS_COMPLETED,
            'submitted_at' => now(),
            'completed_at' => now(),
            'finished_reason' => $finishedReason,
            'total_score' => $totalScore,
            'result_range_id' => $resultRange?->id,
            'result_label' => $resultRange?->title,
            'current_question_id' => null,
        ]);

        AssessmentProgress::query()->updateOrCreate(
            [
                'user_id' => $attempt->user_id,
                'assessment_id' => $attempt->assessment_id,
            ],
            [
                'latest_score' => $totalScore,
                'highest_score' => max(
                    $totalScore,
                    (float) AssessmentProgress::query()
                        ->where('user_id', $attempt->user_id)
                        ->where('assessment_id', $attempt->assessment_id)
                        ->value('highest_score'),
                ),
                'total_attempts' => AssessmentAttempt::query()
                    ->where('user_id', $attempt->user_id)
                    ->where('assessment_id', $attempt->assessment_id)
                    ->count(),
                'is_done' => true,
                'completed_at' => now(),
            ],
        );

        LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $attempt->user_id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_done' => true,
                'completed_at' => now(),
            ],
        );

        return redirect()->route('assessments.result', [
            'lesson' => $lesson->id,
            'attempt' => $attempt->id,
        ]);
    }

    private function resolveResultRange(Assessment $assessment, float $totalScore): ?AssessmentResultRange
    {
        return $assessment->resultRanges
            ->first(fn (AssessmentResultRange $range) => $totalScore >= (float) $range->min_score && $totalScore <= (float) $range->max_score);
    }

    private function persistQuestionAnswer(Request $request, AssessmentAttempt $attempt, Question $question, Collection $questions): ?Question
    {
        $attempt->answers()->where('question_id', $question->id)->delete();
        $attempt->answers()
            ->whereHas('question', fn ($query) => $query->where('sort_order', '>', $question->sort_order))
            ->delete();

        $selectedOptions = collect();

        if ($question->isInfoScreen()) {
            $attempt->update([
                'last_answered_question_id' => $question->id,
            ]);

            return $this->resolveNextQuestion($question, $selectedOptions, $questions);
        }

        if ($question->isOptionBased()) {
            $optionIds = collect($request->input('option_ids', []))
                ->merge($request->filled('option_id') ? [$request->input('option_id')] : [])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();

            $availableOptions = $this->questionOptionsForPlayer($question);
            $selectedOptions = $availableOptions
                ->whereIn('id', $optionIds)
                ->values();

            if ($question->required && $selectedOptions->isEmpty()) {
                throw ValidationException::withMessages([
                    'option_ids' => 'Please choose at least one answer before continuing.',
                ]);
            }

            if (! $question->allow_multi_select && $selectedOptions->count() > 1) {
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

            foreach ($selectedOptions as $option) {
                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'question_option_id' => $option->id,
                    'score_awarded' => $option->scoring_enabled ? (float) ($option->score_value ?? 0) : 0,
                    'is_final' => true,
                    'answered_at' => now(),
                ]);
            }
        } elseif ($question->isNumericBased()) {
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

            if ($numericValue !== null || $question->required) {
                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'answer_number' => $numericValue,
                    'score_awarded' => $numericValue ?? 0,
                    'is_final' => true,
                    'answered_at' => now(),
                ]);
            }
        } else {
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

            if (trim($answerText) !== '' || $question->required) {
                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'answer_text' => $answerText,
                    'score_awarded' => 0,
                    'is_final' => true,
                    'answered_at' => now(),
                ]);
            }
        }

        $attempt->update([
            'last_answered_question_id' => $question->id,
        ]);

        return $this->resolveNextQuestion($question, $selectedOptions, $questions);
    }

    private function resolveNextQuestion(Question $question, Collection $selectedOptions, Collection $questions): ?Question
    {
        $answerJumpTargetId = $selectedOptions
            ->first(fn (QuestionOption $option) => $option->jump_enabled && $option->jump_to_question_id !== null)
            ?->jump_to_question_id;

        if ($answerJumpTargetId) {
            return $questions->firstWhere('id', $answerJumpTargetId);
        }

        if ($question->jump_enabled && $question->jump_to_question_id !== null) {
            return $questions->firstWhere('id', $question->jump_to_question_id);
        }

        return $questions
            ->sortBy('sort_order')
            ->values()
            ->first(fn (Question $candidate) => $candidate->sort_order > $question->sort_order);
    }

    private function questionOptionsForPlayer(Question $question): Collection
    {
        $options = $question->options->sortBy('sort_order')->values();

        if ($question->question_type === Question::TYPE_YES_NO_MAYBE && ! $question->show_maybe_answer) {
            $options = $options->reject(fn (QuestionOption $option) => $option->internal_value === 'maybe')->values();
        }

        if ($question->randomize_answers_order) {
            $options = $options->shuffle()->values();
        }

        return $options;
    }

    private function serializePlayerQuestion(Question $question, Collection $savedAnswers): array
    {
        return [
            'id' => $question->id,
            'title' => $question->title,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'show_instruction' => $question->show_instruction,
            'instruction_text' => $question->instruction_text,
            'required' => $question->required,
            'allow_multi_select' => $question->allow_multi_select,
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
                'option_ids' => $savedAnswers->pluck('question_option_id')->filter()->values()->all(),
                'answer_text' => $savedAnswers->first()?->answer_text,
                'answer_number' => $savedAnswers->first()?->answer_number,
            ],
            'options' => $this->questionOptionsForPlayer($question)
                ->map(fn (QuestionOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'internal_value' => $option->internal_value,
                    'is_other_option' => $option->is_other_option,
                    'image_url' => $option->image
                        ? route('media.show', ['entity' => 'question-option', 'id' => $option->id, 'field' => 'image'])
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }
}
