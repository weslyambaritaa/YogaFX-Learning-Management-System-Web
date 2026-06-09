<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseRequest;
use App\Models\AccessTier;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Courses/Index', [
            'courses' => Course::query()
                ->with('accessTier')
                ->orderBy('title')
                ->get()
                ->map(fn (Course $course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'url_slug' => $course->url_slug,
                    'access_tier' => $course->accessTier?->name,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'course',
                        $course->id,
                        'thumbnail',
                        $course->thumbnail,
                        versionSeed: $course->updated_at,
                    ),
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Courses/Create', [
            'accessTiers' => $this->accessTierOptions(),
        ]);
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile($request->file('thumbnail'), 'courses/thumbnails');

        Course::query()->create($data);

        return redirect()
            ->route('admin.courses.index')
            ->with('status', 'course-created');
    }

    public function edit(Course $course): Response
    {
        return Inertia::render('Admin/Courses/Edit', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'url_slug' => $course->url_slug,
                'access_tier_id' => $course->access_tier_id,
                'description' => $course->description,
                'video' => $course->video,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'course',
                    $course->id,
                    'thumbnail',
                    $course->thumbnail,
                    versionSeed: $course->updated_at,
                ),
            ],
            'accessTiers' => $this->accessTierOptions(),
            'status' => session('status'),
        ]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile(
            $request->file('thumbnail'),
            'courses/thumbnails',
            $course->thumbnail,
        );

        $course->update($data);

        return redirect()
            ->route('admin.courses.index')
            ->with('status', 'course-updated');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->deleteUploadedFile($course->thumbnail);
        $course->delete();

        return redirect()
            ->route('admin.courses.index')
            ->with('status', 'course-deleted');
    }

    private function accessTierOptions(): array
    {
        return AccessTier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (AccessTier $accessTier) => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'is_active' => $accessTier->is_active,
            ])
            ->all();
    }
}
