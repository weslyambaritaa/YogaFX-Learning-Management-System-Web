<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ModuleCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        $modules = Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select(['id', 'module_id', 'title', 'sort_order'])
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $lessonProgressMap = $this->lessonProgressMap(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $activeLessonId = $this->latestProgressLessonId($user?->id, $lessonProgressMap);

        return Inertia::render('Student/Modules/Index', [
            'modules' => $modules->map(function (Module $module) use ($activeLessonId, $lessonProgressMap) {
                $totalLessons = $module->lessons->count();
                $completedLessons = $module->lessons->filter(function (Lesson $lesson) use ($lessonProgressMap) {
                    return (bool) optional($lessonProgressMap->get($lesson->id))->is_done;
                })->count();
                $isActive = $module->lessons->contains(fn (Lesson $lesson) => $lesson->id === $activeLessonId);
                $status = $totalLessons > 0 && $completedLessons === $totalLessons
                    ? 'completed'
                    : ($isActive ? 'active' : 'available');

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'url_slug' => $module->url_slug,
                    'sort_order' => $module->sort_order,
                    'lesson_count' => $totalLessons,
                    'completed_lessons' => $completedLessons,
                    'progress_percentage' => $totalLessons > 0
                        ? (int) round(($completedLessons / $totalLessons) * 100)
                        : 0,
                    'status' => $status,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'module',
                        $module->id,
                        'thumbnail',
                        $module->thumbnail,
                        versionSeed: $module->updated_at,
                    ),
                ];
            }),
        ]);
    }

    public function show(Request $request, Module $module): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;
        abort_unless(
            $user
            && $module->accessTiers()->where('access_tiers.id', $accessTierId)->exists(),
            403,
        );

        $lessons = Lesson::query()
            ->where('module_id', $module->id)
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $lessonProgressMap = $this->lessonProgressMap(
            $user?->id,
            $lessons->pluck('id'),
        );
        $activeLessonId = $this->latestProgressLessonId($user?->id, $lessonProgressMap);
        $completedLessons = $lessons->filter(fn (Lesson $lesson) => (bool) optional($lessonProgressMap->get($lesson->id))->is_done)->count();

        return Inertia::render('Student/Modules/Show', [
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'url_slug' => $module->url_slug,
                'sort_order' => $module->sort_order,
                'lesson_count' => $lessons->count(),
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $lessons->count() > 0
                    ? (int) round(($completedLessons / $lessons->count()) * 100)
                    : 0,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
                'lessons' => $lessons->map(fn (Lesson $lesson) => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'sort_order' => $lesson->sort_order,
                        'has_workbook' => $lesson->workbook !== null,
                        'has_video' => $lesson->lesson_video_id !== null,
                        'has_audio' => $lesson->audio_url !== null,
                        'has_content' => $lesson->content !== null,
                        'status' => (bool) optional($lessonProgressMap->get($lesson->id))->is_done
                            ? 'completed'
                            : ($lesson->id === $activeLessonId ? 'active' : 'available'),
                        'progress_percentage' => (int) round((float) (optional($lessonProgressMap->get($lesson->id))->watch_progress ?? 0)),
                        'thumbnail_url' => $this->protectedMediaUrl(
                            'lesson',
                            $lesson->id,
                            'thumbnail',
                            $lesson->thumbnail,
                            versionSeed: $lesson->updated_at,
                        ) ?: $this->protectedMediaUrl(
                            'module',
                            $module->id,
                            'thumbnail',
                            $module->thumbnail,
                            versionSeed: $module->updated_at,
                        ),
                    ]),
            ],
        ]);
    }

    protected function lessonProgressMap(?int $userId, iterable $lessonIds): Collection
    {
        $lessonIds = collect($lessonIds)->filter()->values();

        if (! $userId || $lessonIds->isEmpty()) {
            return collect();
        }

        return LessonProgress::query()
            ->where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');
    }

    protected function latestProgressLessonId(?int $userId, Collection $lessonProgressMap): ?int
    {
        if (! $userId || $lessonProgressMap->isEmpty()) {
            return null;
        }

        return $lessonProgressMap
            ->sortByDesc(fn (LessonProgress $progress) => sprintf(
                '%010d-%010d',
                $progress->updated_at?->getTimestamp() ?? 0,
                $progress->id,
            ))
            ->keys()
            ->first();
    }
}
