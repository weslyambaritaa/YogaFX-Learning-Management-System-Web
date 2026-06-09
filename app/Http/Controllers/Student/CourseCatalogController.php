<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Student/Courses/Index', [
            'courses' => Course::query()
                ->where('access_tier_id', $user?->access_tier_id)
                ->orderBy('title')
                ->get()
                ->map(fn (Course $course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'url_slug' => $course->url_slug,
                    'description' => $course->description,
                    'video' => $course->video,
                    'thumbnail_url' => route('media.show', ['entity' => 'course', 'id' => $course->id, 'field' => 'thumbnail']),
                ]),
        ]);
    }
}
