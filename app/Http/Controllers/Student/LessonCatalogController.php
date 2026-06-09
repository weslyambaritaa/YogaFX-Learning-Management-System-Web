<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
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
            && $lesson->access_tier_id === $user->access_tier_id
            && $lesson->module?->access_tier_id === $user->access_tier_id,
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
