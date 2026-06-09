<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

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
                'module' => $lesson->module ? [
                    'id' => $lesson->module->id,
                    'title' => $lesson->module->title,
                    'url_slug' => $lesson->module->url_slug,
                ] : null,
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
            ],
        ]);
    }
}
