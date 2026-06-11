<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentProgress;
use App\Models\AssessmentResultRange;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Support\BunnyAssetPath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AssessmentResultsService
{
    /**
     * @return array{summary: array<string, mixed>, questions: array<int, array<string, mixed>>}
     */
    public function buildAttemptPayload(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing([
            'assessment.questions.options',
            'assessment.resultRanges',
            'resultRange',
            'answers.option',
        ]);

        $reviews = $this->buildQuestionReviews(
            $attempt->assessment,
            $this->mapAttemptAnswers($attempt),
        );
        $metrics = $this->metricsFromReviews($reviews);

        return [
            'summary' => [
                'correct_answers' => $metrics['correct_answers'],
                'gradable_questions' => $metrics['gradable_questions'],
                'correct_answers_label' => $this->correctAnswersLabel(
                    $metrics['correct_answers'],
                    $metrics['gradable_questions'],
                ),
                'percentage' => $metrics['percentage'],
                'points' => (float) ($attempt->total_score ?? $metrics['points']),
                'time_taken' => $this->calculateAttemptTimeTaken($attempt),
                'result_label' => $attempt->result_label,
                'result_description' => $attempt->resultRange?->description,
                'user_comments' => $this->extractUserComments($reviews),
            ],
            'questions' => $reviews,
        ];
    }

    /**
     * @param  array<string, mixed>  $previewState
     * @return array{summary: array<string, mixed>, questions: array<int, array<string, mixed>>}
     */
    public function buildPreviewPayload(Assessment $assessment, array $previewState): array
    {
        $assessment->loadMissing(['questions.options', 'resultRanges']);

        $reviews = $this->buildQuestionReviews(
            $assessment,
            collect($previewState['answers'] ?? [])
                ->mapWithKeys(fn ($answer, $questionId) => [
                    (int) $questionId => $this->normalizeSnapshot($answer),
                ])
                ->all(),
        );
        $metrics = $this->metricsFromReviews($reviews);
        $resultRange = $this->resolveResultRange($assessment, $metrics['points']);

        return [
            'summary' => [
                'correct_answers' => $metrics['correct_answers'],
                'gradable_questions' => $metrics['gradable_questions'],
                'correct_answers_label' => $this->correctAnswersLabel(
                    $metrics['correct_answers'],
                    $metrics['gradable_questions'],
                ),
                'percentage' => $metrics['percentage'],
                'points' => $metrics['points'],
                'time_taken' => $this->previewTimeTaken($previewState),
                'result_label' => $resultRange?->title,
                'result_description' => $resultRange?->description,
                'user_comments' => $this->extractUserComments($reviews),
            ],
            'questions' => $reviews,
        ];
    }

    /**
     * @return array{correct_answers: int, gradable_questions: int, correct_answers_label: string, percentage: int}
     */
    public function buildAttemptListMetrics(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing(['assessment.questions.options', 'answers.option']);

        $reviews = $this->buildQuestionReviews(
            $attempt->assessment,
            $this->mapAttemptAnswers($attempt),
        );
        $metrics = $this->metricsFromReviews($reviews);

        return [
            'correct_answers' => $metrics['correct_answers'],
            'gradable_questions' => $metrics['gradable_questions'],
            'correct_answers_label' => $this->correctAnswersLabel(
                $metrics['correct_answers'],
                $metrics['gradable_questions'],
            ),
            'percentage' => $metrics['percentage'],
        ];
    }

    public function recomputeProgress(int $userId, int $assessmentId): void
    {
        $allAttempts = AssessmentAttempt::query()
            ->where('user_id', $userId)
            ->where('assessment_id', $assessmentId)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        if ($allAttempts->isEmpty()) {
            AssessmentProgress::query()
                ->where('user_id', $userId)
                ->where('assessment_id', $assessmentId)
                ->delete();

            return;
        }

        $completedAttempts = $allAttempts
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->values();

        $latestCompletedAttempt = $completedAttempts->first();

        AssessmentProgress::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'assessment_id' => $assessmentId,
            ],
            [
                'latest_score' => $latestCompletedAttempt
                    ? (float) $latestCompletedAttempt->total_score
                    : 0,
                'highest_score' => $completedAttempts->isNotEmpty()
                    ? (float) $completedAttempts->max(fn (AssessmentAttempt $attempt) => (float) $attempt->total_score)
                    : 0,
                'total_attempts' => $allAttempts->count(),
                'is_done' => $latestCompletedAttempt !== null,
                'completed_at' => $latestCompletedAttempt?->completed_at,
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $answerSnapshots
     * @return array<int, array<string, mixed>>
     */
    private function buildQuestionReviews(Assessment $assessment, array $answerSnapshots): array
    {
        $questions = $assessment->questions->sortBy('sort_order')->values();
        $questionsById = $questions->keyBy('id');
        $currentQuestion = $questions->first();
        $visited = [];
        $reviews = [];

        while ($currentQuestion && ! isset($visited[$currentQuestion->id])) {
            $visited[$currentQuestion->id] = true;

            $snapshot = $this->normalizeSnapshot($answerSnapshots[$currentQuestion->id] ?? []);
            $reviews[] = $this->buildQuestionReview($currentQuestion, $snapshot);

            $currentQuestion = $this->resolveNextQuestion(
                $currentQuestion,
                $snapshot,
                $questions,
                $questionsById,
            );
        }

        return $reviews;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function buildQuestionReview(Question $question, array $snapshot): array
    {
        $selectedOptionIds = collect($snapshot['option_ids'])
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->unique()
            ->values();
        $visibleOptions = $this->questionOptionsForReview($question);
        $correctOptionIds = $visibleOptions
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->unique()
            ->values();

        $status = null;

        if ($question->isOptionBased() && $correctOptionIds->isNotEmpty()) {
            $status = $selectedOptionIds->values()->all() === $correctOptionIds->values()->all()
                ? 'correct'
                : 'incorrect';
        }

        $pointsAwarded = $this->pointsAwardedForSnapshot($question, $snapshot, $visibleOptions);

        return [
            'id' => $question->id,
            'step_number' => $question->sort_order,
            'title' => $question->title,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'is_info_screen' => $question->isInfoScreen(),
            'status' => $status,
            'status_label' => $status ? ucfirst($status) : null,
            'points_awarded' => $pointsAwarded,
            'selected_labels' => $visibleOptions
                ->whereIn('id', $selectedOptionIds)
                ->pluck('label')
                ->values()
                ->all(),
            'correct_labels' => $visibleOptions
                ->where('is_correct', true)
                ->pluck('label')
                ->values()
                ->all(),
            'answer_text' => $snapshot['answer_text'],
            'answer_number' => $snapshot['answer_number'],
            'display_answer' => $this->displayAnswerForSnapshot($question, $snapshot, $visibleOptions),
            'options' => $visibleOptions
                ->map(fn (QuestionOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'is_selected' => $selectedOptionIds->contains((int) $option->id),
                    'is_correct' => (bool) $option->is_correct,
                    'image_url' => $option->image
                        ? $this->questionOptionImageUrl($option)
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveNextQuestion(
        Question $currentQuestion,
        array $snapshot,
        Collection $questions,
        Collection $questionsById,
    ): ?Question {
        $selectedOptionIds = collect($snapshot['option_ids'])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $optionJumpTargetId = $currentQuestion->options
            ->first(fn (QuestionOption $option) => $option->jump_enabled
                && $option->jump_to_question_id !== null
                && $selectedOptionIds->contains((int) $option->id))
            ?->jump_to_question_id;

        if ($optionJumpTargetId) {
            return $questionsById->get($optionJumpTargetId);
        }

        if ($currentQuestion->jump_enabled && $currentQuestion->jump_to_question_id !== null) {
            return $questionsById->get($currentQuestion->jump_to_question_id);
        }

        return $questions->first(
            fn (Question $question) => $question->sort_order > $currentQuestion->sort_order,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapAttemptAnswers(AssessmentAttempt $attempt): array
    {
        return $attempt->answers
            ->groupBy('question_id')
            ->map(fn (Collection $answers) => [
                'option_ids' => $answers
                    ->pluck('question_option_id')
                    ->filter()
                    ->map(fn ($value) => (int) $value)
                    ->values()
                    ->all(),
                'answer_text' => $answers->first()?->answer_text,
                'answer_number' => $answers->first()?->answer_number,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array{correct_answers: int, gradable_questions: int, percentage: int, points: float}
     */
    private function metricsFromReviews(array $reviews): array
    {
        $reviewCollection = collect($reviews);
        $gradableQuestions = $reviewCollection->whereNotNull('status')->count();
        $correctAnswers = $reviewCollection->where('status', 'correct')->count();
        $percentage = $gradableQuestions > 0
            ? (int) round(($correctAnswers / $gradableQuestions) * 100)
            : 0;

        return [
            'correct_answers' => $correctAnswers,
            'gradable_questions' => $gradableQuestions,
            'percentage' => $percentage,
            'points' => (float) $reviewCollection->sum('points_awarded'),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function pointsAwardedForSnapshot(
        Question $question,
        array $snapshot,
        Collection $visibleOptions,
    ): float {
        if ($question->isOptionBased()) {
            return (float) $visibleOptions
                ->whereIn('id', collect($snapshot['option_ids'])->map(fn ($value) => (int) $value))
                ->sum(fn (QuestionOption $option) => $option->scoring_enabled
                    ? (float) ($option->score_value ?? 0)
                    : 0);
        }

        if ($question->isNumericBased()) {
            return (float) ($snapshot['answer_number'] ?? 0);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function displayAnswerForSnapshot(
        Question $question,
        array $snapshot,
        Collection $visibleOptions,
    ): ?string {
        if ($question->isInfoScreen()) {
            return null;
        }

        if ($question->isOptionBased()) {
            $selectedLabels = $visibleOptions
                ->whereIn('id', collect($snapshot['option_ids'])->map(fn ($value) => (int) $value))
                ->pluck('label')
                ->filter()
                ->values()
                ->all();

            return empty($selectedLabels) ? null : implode(', ', $selectedLabels);
        }

        if ($question->isNumericBased()) {
            return $snapshot['answer_number'] !== null
                ? (string) $snapshot['answer_number']
                : null;
        }

        return filled($snapshot['answer_text']) ? (string) $snapshot['answer_text'] : null;
    }

    /**
     * @return Collection<int, QuestionOption>
     */
    private function questionOptionsForReview(Question $question): Collection
    {
        $options = $question->options->sortBy('sort_order')->values();

        if ($question->question_type === Question::TYPE_YES_NO_MAYBE) {
            $options = $options
                ->reject(fn (QuestionOption $option) => $option->internal_value === 'maybe')
                ->values();
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{option_ids: array<int, int>, answer_text: ?string, answer_number: float|int|string|null}
     */
    private function normalizeSnapshot(array $snapshot): array
    {
        return [
            'option_ids' => collect($snapshot['option_ids'] ?? [])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'answer_text' => filled($snapshot['answer_text'] ?? null)
                ? (string) $snapshot['answer_text']
                : null,
            'answer_number' => ($snapshot['answer_number'] ?? null) === ''
                ? null
                : ($snapshot['answer_number'] ?? null),
        ];
    }

    private function resolveResultRange(Assessment $assessment, float $points): ?AssessmentResultRange
    {
        return $assessment->resultRanges
            ->first(fn (AssessmentResultRange $range) => $points >= (float) $range->min_score
                && $points <= (float) $range->max_score);
    }

    private function parsePreviewTimestamp(mixed $timestamp): ?CarbonInterface
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return null;
        }

        return Carbon::parse($timestamp);
    }

    /**
     * @return array{available: bool, label: string, parts: array{hours: string, minutes: string, seconds: string}}
     */
    private function calculateAttemptTimeTaken(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing('answers');

        $answeredTimes = $attempt->answers
            ->pluck('answered_at')
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sort()
            ->values();

        $startedAt = $attempt->started_at;
        $submittedAt = $attempt->submitted_at;
        $completedAt = $attempt->completed_at;
        $firstAnsweredAt = $answeredTimes->first();
        $lastAnsweredAt = $answeredTimes->last();

        $durationSeconds = $this->durationFromRange($startedAt, $submittedAt);

        if ($durationSeconds === null) {
            $durationSeconds = $this->durationFromRange($startedAt, $completedAt);
        }

        if ($durationSeconds === null) {
            $durationSeconds = $this->durationFromRange($startedAt, $lastAnsweredAt);
        }

        if ($durationSeconds === null) {
            $durationSeconds = $this->durationFromRange($firstAnsweredAt, $lastAnsweredAt);
        }

        return $this->durationPayload($durationSeconds);
    }

    private function correctAnswersLabel(int $correctAnswers, int $gradableQuestions): string
    {
        return $gradableQuestions > 0
            ? sprintf('%d / %d', $correctAnswers, $gradableQuestions)
            : '0 / 0';
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<int, array{question_title: string, answer: string}>
     */
    private function extractUserComments(array $reviews): array
    {
        return collect($reviews)
            ->filter(fn (array $review) => $review['question_type'] === Question::TYPE_OPEN_TEXT
                && filled($review['display_answer'] ?? null))
            ->map(fn (array $review) => [
                'question_title' => (string) ($review['title'] ?: 'Comment'),
                'answer' => (string) $review['display_answer'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $previewState
     * @return array{available: bool, label: string, parts: array{hours: string, minutes: string, seconds: string}}
     */
    private function previewTimeTaken(array $previewState): array
    {
        $durationSeconds = $this->durationFromRange(
            $this->parsePreviewTimestamp($previewState['started_at'] ?? null),
            $this->parsePreviewTimestamp($previewState['completed_at'] ?? null),
        );

        return $this->durationPayload($durationSeconds);
    }

    private function durationFromRange(
        ?CarbonInterface $startTime,
        ?CarbonInterface $endTime,
    ): ?int {
        if (! $startTime || ! $endTime) {
            return null;
        }

        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        $seconds = $start->diffInSeconds($end);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * @return array{available: bool, label: string, parts: array{hours: string, minutes: string, seconds: string}}
     */
    private function durationPayload(?int $durationSeconds): array
    {
        if ($durationSeconds === null) {
            return [
                'available' => false,
                'label' => 'Unavailable',
                'parts' => [
                    'hours' => '00',
                    'minutes' => '00',
                    'seconds' => '00',
                ],
            ];
        }

        $hours = intdiv($durationSeconds, 3600);
        $minutes = intdiv($durationSeconds % 3600, 60);
        $seconds = $durationSeconds % 60;

        return [
            'available' => true,
            'label' => sprintf(
                '%02d hours %02d minutes %02d seconds',
                $hours,
                $minutes,
                $seconds,
            ),
            'parts' => [
                'hours' => str_pad((string) $hours, 2, '0', STR_PAD_LEFT),
                'minutes' => str_pad((string) $minutes, 2, '0', STR_PAD_LEFT),
                'seconds' => str_pad((string) $seconds, 2, '0', STR_PAD_LEFT),
            ],
        ];
    }

    private function questionOptionImageUrl(QuestionOption $option): ?string
    {
        if (! filled($option->image)) {
            return null;
        }

        if (BunnyAssetPath::isBunnyPath($option->image)) {
            return app(BunnyStorageService::class)->url($option->image);
        }

        return route('media.show', ['entity' => 'question-option', 'id' => $option->id, 'field' => 'image']);
    }
}
