<?php

namespace App\Services\Mobile\V1;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentProgress;
use App\Models\AssessmentResultRange;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\StudentLearningMilestoneEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StudentAssessmentApiService
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly StudentLearningMilestoneEmailService $studentLearningMilestoneEmailService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function introForUser(User $user, Lesson $lesson): ?array
    {
        if (! $lesson->assessment) {
            return null;
        }

        $this->authorizeLessonAssessmentAccess($lesson, $user->id);
        $attempt = $this->resolveInProgressAttempt($lesson->assessment, $user->id);
        $completedAttempt = $this->resolveCompletedAttempt($lesson->assessment, $user->id);
        $progress = $this->lessonProgress($lesson, $user->id);

        return [
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
            'completed_attempt' => $completedAttempt ? [
                'id' => $completedAttempt->id,
                'completed_at' => $completedAttempt->completed_at?->toDateTimeString(),
                'total_score' => $completedAttempt->total_score,
                'result_label' => $completedAttempt->result_label,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function startForUser(User $user, Lesson $lesson): ?array
    {
        if (! $lesson->assessment) {
            return null;
        }

        $this->authorizeLessonAssessmentAccess($lesson, $user->id, true);
        $assessment = $lesson->assessment;
        $completedAttempt = $this->resolveCompletedAttempt($assessment, $user->id);

        if ($completedAttempt) {
            return [
                'mode' => 'completed',
                'attempt_id' => $completedAttempt->id,
            ];
        }

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

        return [
            'mode' => 'in_progress',
            'attempt_id' => $attempt->id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attemptForUser(User $user, Lesson $lesson, AssessmentAttempt $attempt): ?array
    {
        if (! $lesson->assessment) {
            return null;
        }

        if ($attempt->user_id !== $user->id || $attempt->assessment_id !== $lesson->assessment_id) {
            return null;
        }

        $this->authorizeLessonAssessmentAccess($lesson, $user->id, true);

        if ($attempt->status === AssessmentAttempt::STATUS_COMPLETED || $attempt->status === AssessmentAttempt::STATUS_EXPIRED) {
            return [
                'mode' => 'result_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        if ($attempt->expires_at && $attempt->expires_at->isPast()) {
            $this->completeAttempt($lesson, $attempt, AssessmentAttempt::FINISHED_REASON_EXPIRED);

            return [
                'mode' => 'result_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        $assessment = $lesson->assessment->load(['design', 'questions.options', 'resultRanges']);
        $question = $attempt->currentQuestion ?? $assessment->questions->sortBy('sort_order')->first();
        abort_unless($question, 404);

        $history = $this->rebuildAttemptHistory($assessment, $attempt, $question);
        $savedAnswers = $attempt->answers()->where('question_id', $question->id)->get();
        $orderedQuestions = $assessment->questions->sortBy('sort_order')->values();
        $currentIndex = $orderedQuestions->search(fn (Question $item) => $item->id === $question->id);

        return [
            'mode' => 'question',
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
            'can_go_back' => $assessment->allow_back_navigation && ! empty($history),
            'is_last_question' => $currentIndex !== false && $currentIndex === $orderedQuestions->count() - 1,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function storeAnswerForUser(Request $request, User $user, Lesson $lesson, AssessmentAttempt $attempt): ?array
    {
        if (! $lesson->assessment) {
            return null;
        }

        if ($attempt->user_id !== $user->id || $attempt->assessment_id !== $lesson->assessment_id) {
            return null;
        }

        $this->authorizeLessonAssessmentAccess($lesson, $user->id, true);

        if ($attempt->status === AssessmentAttempt::STATUS_COMPLETED || $attempt->status === AssessmentAttempt::STATUS_EXPIRED) {
            return [
                'mode' => 'result_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        if ($attempt->expires_at && $attempt->expires_at->isPast()) {
            $this->completeAttempt($lesson, $attempt, AssessmentAttempt::FINISHED_REASON_EXPIRED);

            return [
                'mode' => 'result_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        $assessment = $lesson->assessment->load('questions.options', 'resultRanges');
        $question = $attempt->currentQuestion ?? $assessment->questions->sortBy('sort_order')->first();
        abort_unless($question, 404);

        $nextQuestion = $this->persistQuestionAnswer($request, $attempt, $question, $assessment->questions);

        if (! $nextQuestion) {
            $this->completeAttempt($lesson, $attempt, AssessmentAttempt::FINISHED_REASON_MANUAL_SUBMIT);

            return [
                'mode' => 'result_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        $attempt->update([
            'current_question_id' => $nextQuestion->id,
        ]);

        return [
            'mode' => 'question_redirect',
            'attempt_id' => $attempt->id,
            'question_id' => $nextQuestion->id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function backForUser(User $user, Lesson $lesson, AssessmentAttempt $attempt): ?array
    {
        if (! $lesson->assessment) {
            return null;
        }

        if ($attempt->user_id !== $user->id || $attempt->assessment_id !== $lesson->assessment_id) {
            return null;
        }

        if ($attempt->status === AssessmentAttempt::STATUS_COMPLETED || $attempt->status === AssessmentAttempt::STATUS_EXPIRED) {
            return [
                'mode' => 'result_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        $assessment = $lesson->assessment->load('questions', 'questions.options');
        $currentQuestion = $attempt->currentQuestion ?? $assessment->questions->sortBy('sort_order')->first();
        abort_unless($currentQuestion, 404);

        $history = collect($this->rebuildAttemptHistory($assessment, $attempt, $currentQuestion));
        $previousQuestionId = $history->pop();
        $previousQuestion = $previousQuestionId
            ? $assessment->questions->firstWhere('id', (int) $previousQuestionId)
            : null;

        if ($previousQuestion) {
            $attempt->update([
                'current_question_id' => $previousQuestion->id,
            ]);
        }

        return [
            'mode' => 'question_redirect',
            'attempt_id' => $attempt->id,
            'question_id' => $previousQuestion?->id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resultForUser(User $user, Lesson $lesson, AssessmentAttempt $attempt): ?array
    {
        if (! $lesson->assessment) {
            return null;
        }

        if ($attempt->user_id !== $user->id || $attempt->assessment_id !== $lesson->assessment_id) {
            return null;
        }

        if ($attempt->status === AssessmentAttempt::STATUS_IN_PROGRESS) {
            return [
                'mode' => 'attempt_redirect',
                'attempt_id' => $attempt->id,
            ];
        }

        $attempt->load([
            'resultRange',
            'answers.questionOption',
            'assessment.questions.options',
        ]);

        $resultMetrics = $this->buildAttemptResultMetrics($attempt);

        return [
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
                'correct_answers' => $resultMetrics['correct_answers'],
                'gradable_questions' => $resultMetrics['gradable_questions'],
                'percentage_correct' => $resultMetrics['percentage_correct'],
            ],
        ];
    }

    private function authorizeLessonAssessmentAccess(Lesson $lesson, int $userId, bool $requireUnlocked = false): void
    {
        $user = User::query()->findOrFail($userId);

        abort_unless(
            $lesson->assessment_id !== null
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
            $this->completeAttempt(
                Lesson::query()->where('assessment_id', $attempt->assessment_id)->firstOrFail(),
                $attempt,
                AssessmentAttempt::FINISHED_REASON_EXPIRED,
            );

            return null;
        }

        return $attempt;
    }

    private function resolveCompletedAttempt(Assessment $assessment, int $userId): ?AssessmentAttempt
    {
        return AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('user_id', $userId)
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    private function completeAttempt(Lesson $lesson, AssessmentAttempt $attempt, string $finishedReason): void
    {
        $previousStatus = $attempt->status;
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

        if ($previousStatus !== AssessmentAttempt::STATUS_COMPLETED && ! $isExpired) {
            $attempt->loadMissing(['user']);

            if ($attempt->user) {
                $this->studentLearningMilestoneEmailService->syncLessonMilestones($attempt->user, $lesson);
            }
        }
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
            $availableOptions = $this->questionOptionsForPlayer($question);
            $selectedOptions = $this->validatedSelectedOptions($request, $question, $availableOptions);

            if (
                $this->questionUsesCorrectnessGate($question, $availableOptions)
                && ! $this->selectionIsFullyCorrect($selectedOptions, $availableOptions)
            ) {
                throw ValidationException::withMessages([
                    'option_ids' => 'Oops!!! Wrong Answer! Please refer to your workbook and try again.',
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
            $answerText = trim((string) $request->input('answer_text', ''));

            if ($question->required && $answerText === '') {
                throw ValidationException::withMessages([
                    'answer_text' => 'Please enter your answer before continuing.',
                ]);
            }

            if ($question->character_limit !== null && mb_strlen($answerText) > $question->character_limit) {
                throw ValidationException::withMessages([
                    'answer_text' => 'Answer exceeds the allowed character limit.',
                ]);
            }

            if ($answerText !== '' || $question->required) {
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

        if ($question->question_type === Question::TYPE_YES_NO_MAYBE) {
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
                'option_ids' => $savedAnswers->pluck('question_option_id')->filter()->values()->all(),
                'answer_text' => $savedAnswers->first()?->answer_text,
                'answer_number' => $savedAnswers->first()?->answer_number,
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

    private function validatedSelectedOptions(Request $request, Question $question, Collection $availableOptions): Collection
    {
        $optionIds = collect($request->input('option_ids', []))
            ->merge($request->filled('option_id') ? [$request->input('option_id')] : [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $selectedOptions = $availableOptions
            ->whereIn('id', $optionIds)
            ->values();

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

    private function questionUsesCorrectnessGate(Question $question, ?Collection $availableOptions = null): bool
    {
        if (! $question->isOptionBased()) {
            return false;
        }

        $availableOptions ??= $this->questionOptionsForPlayer($question);

        return $availableOptions->contains(fn (QuestionOption $option) => (bool) $option->is_correct);
    }

    private function selectionIsFullyCorrect(Collection $selectedOptions, Collection $availableOptions): bool
    {
        $selectedIds = $selectedOptions->pluck('id')->map(fn ($value) => (int) $value)->sort()->values();
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

    private function rebuildAttemptHistory(Assessment $assessment, AssessmentAttempt $attempt, Question $currentQuestion): array
    {
        $questions = $assessment->questions->sortBy('sort_order')->values();
        $firstQuestion = $questions->first();

        if (! $firstQuestion || $firstQuestion->id === $currentQuestion->id) {
            return [];
        }

        $answersByQuestion = $attempt->answers()->with('questionOption')->get()->groupBy('question_id');
        $history = [];
        $cursor = $firstQuestion;
        $guard = 0;

        while ($cursor && $cursor->id !== $currentQuestion->id && $guard < $questions->count()) {
            $nextQuestion = $this->resolveNextQuestionFromSavedAnswers(
                $cursor,
                $questions,
                $answersByQuestion->get($cursor->id, collect()),
            );

            if (! $nextQuestion) {
                break;
            }

            $history[] = $cursor->id;
            $cursor = $nextQuestion;
            $guard++;
        }

        return $cursor && $cursor->id === $currentQuestion->id ? $history : [];
    }

    private function resolveNextQuestionFromSavedAnswers(Question $question, Collection $questions, Collection $savedAnswers): ?Question
    {
        $selectedOptions = $question->options
            ->whereIn('id', $savedAnswers->pluck('question_option_id')->filter()->map(fn ($value) => (int) $value))
            ->values();

        return $this->resolveNextQuestion($question, $selectedOptions, $questions);
    }

    private function buildAttemptResultMetrics(AssessmentAttempt $attempt): array
    {
        $gradableQuestions = $attempt->assessment->questions
            ->filter(fn (Question $question) => $this->questionUsesCorrectnessGate($question))
            ->values();

        if ($gradableQuestions->isEmpty()) {
            return [
                'correct_answers' => 0,
                'gradable_questions' => 0,
                'percentage_correct' => 0,
            ];
        }

        $answersByQuestion = $attempt->answers->groupBy('question_id');
        $correctAnswers = $gradableQuestions->reduce(
            function (int $total, Question $question) use ($answersByQuestion): int {
                $selectedIds = $answersByQuestion
                    ->get($question->id, collect())
                    ->pluck('question_option_id')
                    ->filter()
                    ->map(fn ($value) => (int) $value)
                    ->sort()
                    ->values();

                $correctIds = $this->questionOptionsForPlayer($question)
                    ->filter(fn (QuestionOption $option) => (bool) $option->is_correct)
                    ->pluck('id')
                    ->map(fn ($value) => (int) $value)
                    ->sort()
                    ->values();

                $isCorrect = $selectedIds->isNotEmpty()
                    && $selectedIds->count() === $correctIds->count()
                    && $selectedIds->all() === $correctIds->all();

                return $total + ($isCorrect ? 1 : 0);
            },
            0,
        );

        return [
            'correct_answers' => $correctAnswers,
            'gradable_questions' => $gradableQuestions->count(),
            'percentage_correct' => (int) round(($correctAnswers / max($gradableQuestions->count(), 1)) * 100),
        ];
    }
}
