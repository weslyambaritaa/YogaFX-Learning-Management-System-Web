<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScoreboardRequest;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardController extends Controller
{
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Scoreboards/Index', [
            'scoreboards' => Assessment::query()
                ->withCount(['questions', 'attempts'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Assessment $assessment) => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'slug' => $assessment->slug,
                    'status' => $assessment->status,
                    'is_active' => $assessment->is_active,
                    'questions_count' => $assessment->questions_count,
                    'attempts_count' => $assessment->attempts_count,
                    'updated_at' => $assessment->updated_at?->toDateTimeString(),
                    'thumbnail_url' => $assessment->thumbnail
                        ? route('media.show', ['entity' => 'assessment', 'id' => $assessment->id, 'field' => 'thumbnail'])
                        : null,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Scoreboards/Create', [
            'statusOptions' => $this->statusOptions(),
            'scoringModeOptions' => $this->scoringModeOptions(),
            'resultModeOptions' => $this->resultModeOptions(),
        ]);
    }

    public function store(ScoreboardRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile(
            $request->file('thumbnail'),
            'assessments/thumbnails',
        );

        $assessment = Assessment::query()->create($data);
        $assessment->design()->create([]);

        return redirect()
            ->route('admin.scoreboards.builder', $assessment)
            ->with('status', 'scoreboard-created');
    }

    public function edit(Assessment $assessment): Response
    {
        return Inertia::render('Admin/Scoreboards/Edit', [
            'scoreboard' => $this->serializeMeta($assessment),
            'statusOptions' => $this->statusOptions(),
            'scoringModeOptions' => $this->scoringModeOptions(),
            'resultModeOptions' => $this->resultModeOptions(),
            'status' => session('status'),
        ]);
    }

    public function update(ScoreboardRequest $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile(
            $request->file('thumbnail'),
            'assessments/thumbnails',
            $assessment->thumbnail,
        );

        $assessment->update($data);

        return redirect()
            ->route('admin.scoreboards.edit', $assessment)
            ->with('status', 'scoreboard-updated');
    }

    public function destroy(Assessment $assessment): RedirectResponse
    {
        $this->deleteUploadedFile($assessment->thumbnail);
        $this->deleteUploadedFile($assessment->design?->logo);

        foreach ($assessment->questions as $question) {
            foreach ($question->options as $option) {
                $this->deleteUploadedFile($option->image);
            }
        }

        Lesson::query()
            ->where('assessment_id', $assessment->id)
            ->update(['assessment_id' => null]);

        AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->delete();

        $assessment->delete();

        return redirect()
            ->route('admin.scoreboards.index')
            ->with('status', 'scoreboard-deleted');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => Assessment::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => Assessment::STATUS_LIVE, 'label' => 'Live'],
            ['value' => Assessment::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function scoringModeOptions(): array
    {
        return [
            ['value' => 'points', 'label' => 'Points'],
            ['value' => 'raw_value', 'label' => 'Raw Value'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function resultModeOptions(): array
    {
        return [
            ['value' => 'score_or_range', 'label' => 'Score or Result Range'],
            ['value' => 'score_only', 'label' => 'Score Only'],
        ];
    }

    private function serializeMeta(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'slug' => $assessment->slug,
            'description' => $assessment->description,
            'status' => $assessment->status,
            'duration_minutes' => $assessment->duration_minutes,
            'scoring_mode' => $assessment->scoring_mode,
            'result_mode' => $assessment->result_mode,
            'is_active' => $assessment->is_active,
            'show_progress_bar' => $assessment->show_progress_bar,
            'allow_back_navigation' => $assessment->allow_back_navigation,
            'thumbnail_url' => $assessment->thumbnail
                ? route('media.show', ['entity' => 'assessment', 'id' => $assessment->id, 'field' => 'thumbnail'])
                : null,
        ];
    }
}
