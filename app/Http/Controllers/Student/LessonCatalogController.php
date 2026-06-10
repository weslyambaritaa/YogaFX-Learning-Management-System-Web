<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonCatalogController extends Controller
{
    public function show(Request $request, Lesson $lesson): Response
    {
        $user = $request->user();

        abort_unless(
            $user
            && $lesson->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists()
            && $lesson->module?->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        return Inertia::render('Student/Lessons/Show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'video' => $lesson->video,
                'audio' => $lesson->audio,
                'content' => $lesson->content,
                'assessment_id' => $lesson->assessment_id,
                'assessment' => $lesson->assessment && $lesson->assessment->status === 'live' && $lesson->assessment->is_active ? [
                    'id' => $lesson->assessment->id,
                    'title' => $lesson->assessment->title,
                    'is_unlocked' => $lesson->video === null
                        || (float) LessonProgress::query()
                            ->where('lesson_id', $lesson->id)
                            ->where('user_id', $user->id)
                            ->value('watch_progress') >= 95,
                    'current_attempt_id' => AssessmentAttempt::query()
                        ->where('assessment_id', $lesson->assessment_id)
                        ->where('user_id', $user->id)
                        ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
                        ->latest('id')
                        ->value('id'),
                ] : null,
                'module' => $lesson->module ? [
                    'id' => $lesson->module->id,
                    'title' => $lesson->module->title,
                    'url_slug' => $lesson->module->url_slug,
                ] : null,
                'thumbnail_url' => route('media.show', ['entity' => 'lesson', 'id' => $lesson->id, 'field' => 'thumbnail']),
                'workbook_url' => $lesson->workbook
                    ? route('media.show', ['entity' => 'lesson', 'id' => $lesson->id, 'field' => 'workbook'])
                    : null,
            ],
        ]);
    }
}
