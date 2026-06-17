<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Services\AssessmentResultsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentResultController extends Controller
{
    public function __construct(
        private readonly AssessmentResultsService $resultsService,
    ) {}

    public function index(Assessment $assessment): Response
    {
        $assessment->load([
            'lesson.module',
            'questions.options',
            'attempts' => fn ($query) => $query
                ->where('status', AssessmentAttempt::STATUS_COMPLETED)
                ->with(['user', 'answers.option'])
                ->orderByDesc('completed_at')
                ->orderByDesc('id'),
        ]);

        return Inertia::render('Admin/Assessments/ResultsIndex', [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'status' => $assessment->status,
                'lesson' => $assessment->lesson ? [
                    'id' => $assessment->lesson->id,
                    'title' => $assessment->lesson->title,
                    'module_title' => $assessment->lesson->module?->title,
                ] : null,
            ],
            'results' => $assessment->attempts->map(function (AssessmentAttempt $attempt) {
                $metrics = $this->resultsService->buildAttemptListMetrics($attempt);

                return [
                    'id' => $attempt->id,
                    'attempt_number' => $attempt->attempt_number,
                    'name' => $attempt->user?->name,
                    'email' => $attempt->user?->email,
                    'correct_answers' => $metrics['correct_answers_label'],
                    'percentage' => $metrics['percentage'],
                    'completed_at' => $attempt->completed_at?->toDateTimeString(),
                ];
            })->values(),
            'status' => session('status'),
        ]);
    }

    public function show(Assessment $assessment, AssessmentAttempt $attempt): Response
    {
        abort_unless(
            $attempt->assessment_id === $assessment->id
            && $attempt->status === AssessmentAttempt::STATUS_COMPLETED,
            404,
        );

        $attempt->load([
            'user',
            'resultRange',
            'assessment.questions.options',
            'assessment.resultRanges',
            'answers.option',
        ]);

        $payload = $this->resultsService->buildAttemptPayload($attempt);

        return Inertia::render('Admin/Assessments/ResultDetail', [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'completed_at' => $attempt->completed_at?->toDateTimeString(),
                'user' => [
                    'name' => $attempt->user?->name,
                    'email' => $attempt->user?->email,
                    'phone' => $attempt->user?->whatsapp,
                ],
                'summary' => $payload['summary'],
                'questions' => $payload['questions'],
            ],
            'status' => session('status'),
        ]);
    }

    public function destroy(Assessment $assessment, AssessmentAttempt $attempt): RedirectResponse
    {
        abort_unless(
            $attempt->assessment_id === $assessment->id
            && $attempt->status === AssessmentAttempt::STATUS_COMPLETED,
            404,
        );

        $userId = $attempt->user_id;
        $assessmentId = $attempt->assessment_id;

        $attempt->delete();

        $this->resultsService->recomputeProgress($userId, $assessmentId);

        return redirect()
            ->route('admin.assessments.results.index', $assessment)
            ->with('status', 'assessment-result-deleted');
    }
}
