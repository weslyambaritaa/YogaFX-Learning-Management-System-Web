<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function show(Request $request, Lesson $lesson): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        abort_unless(
            $user
            && $lesson->accessTiers()->where('access_tiers.id', $accessTierId)->exists()
            && $lesson->module?->accessTiers()->where('access_tiers.id', $accessTierId)->exists(),
            403,
        );

        $lessonNavigation = Lesson::query()
            ->where('module_id', $lesson->module_id)
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $progressMap = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonNavigation->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        $completedLessons = $lessonNavigation->filter(fn (Lesson $item) => (bool) optional($progressMap->get($item->id))->is_done)->count();
        $currentProgress = $progressMap->get($lesson->id);

        return Inertia::render('Student/Lessons/Show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'video' => $lesson->video,
                'audio' => $lesson->audio,
                'content' => $lesson->content,
                'assessment_id' => $lesson->assessment_id,
                'module' => $lesson->module ? [
                    'id' => $lesson->module->id,
                    'title' => $lesson->module->title,
                    'url_slug' => $lesson->module->url_slug,
                    'lesson_count' => $lessonNavigation->count(),
                    'completed_lessons' => $completedLessons,
                    'progress_percentage' => $lessonNavigation->count() > 0
                        ? (int) round(($completedLessons / $lessonNavigation->count()) * 100)
                        : 0,
                ] : null,
                'progress' => [
                    'watch_progress' => (int) round((float) ($currentProgress?->watch_progress ?? 0)),
                    'is_done' => (bool) ($currentProgress?->is_done ?? false),
                ],
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'thumbnail',
                    $lesson->thumbnail,
                    versionSeed: $lesson->updated_at,
                ),
                'workbook_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'workbook',
                    $lesson->workbook,
                    versionSeed: $lesson->updated_at,
                ),
                'navigation' => $lessonNavigation->map(fn (Lesson $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'sort_order' => $item->sort_order,
                    'status' => (bool) optional($progressMap->get($item->id))->is_done
                        ? 'completed'
                        : ($item->id === $lesson->id ? 'current' : 'available'),
                    'progress_percentage' => (int) round((float) (optional($progressMap->get($item->id))->watch_progress ?? 0)),
                    'url' => route('lessons.show', $item),
                ]),
            ],
        ]);
    }
}
