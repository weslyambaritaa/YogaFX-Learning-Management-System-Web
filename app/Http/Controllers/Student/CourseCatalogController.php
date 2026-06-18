<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\BunnyStreamService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Student/Courses/Index', [
            'courses' => Course::query()
                ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user?->access_tier_id))
                ->orderBy('title')
                ->get()
                ->values()
                ->map(fn (Course $course, int $index) => $this->coursePayload($course, $index + 1)),
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->access_tier_id !== null
            && $course->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        $courses = Course::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->orderBy('title')
            ->get()
            ->values();

        $currentIndex = $courses->search(fn (Course $item) => $item->id === $course->id);
        $nextCourse = $currentIndex !== false ? $courses->get($currentIndex + 1) : null;

        return Inertia::render('Student/Courses/Show', [
            'course' => array_merge(
                $this->coursePayload($course, $currentIndex === false ? null : $currentIndex + 1),
                [
                    'next_course' => $nextCourse ? [
                        'id' => $nextCourse->id,
                        'title' => $nextCourse->title,
                        'url_slug' => $nextCourse->url_slug,
                        'url' => route('courses.show', $nextCourse->url_slug),
                    ] : null,
                    'navigation' => $courses->map(fn (Course $item, int $index) => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'url_slug' => $item->url_slug,
                        'thumbnail_url' => $this->protectedMediaUrl(
                            'course',
                            $item->id,
                            'thumbnail',
                            $item->thumbnail,
                            versionSeed: $item->updated_at,
                        ) ?: $this->bunnyStreamService->thumbnailUrl($item->video),
                        'status' => $item->id === $course->id ? 'current' : 'available',
                        'index' => $index + 1,
                        'url' => route('courses.show', $item->url_slug),
                    ])->all(),
                ],
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(Course $course, ?int $index): array
    {
        $videoState = $this->videoStateForCourse($course);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'url_slug' => $course->url_slug,
            'description' => $course->description,
            'video' => $videoState,
            'index' => $index,
            'status' => $videoState['is_ready'] ? 'ready' : 'unavailable',
            'thumbnail_url' => $this->protectedMediaUrl(
                'course',
                $course->id,
                'thumbnail',
                $course->thumbnail,
                versionSeed: $course->updated_at,
            ) ?: $this->bunnyStreamService->thumbnailUrl($course->video),
        ];
    }

    /**
     * @return array{
     *     video_id: string|null,
     *     hls_url: string|null,
     *     is_ready: bool,
     *     is_configured: bool,
     *     is_valid_id: bool,
     *     is_found_in_library: bool|null,
     *     warning_message: string|null
     * }
     */
    private function videoStateForCourse(Course $course): array
    {
        $videoId = is_string($course->video)
            ? trim($course->video)
            : null;

        if (! filled($videoId)) {
            return [
                'video_id' => null,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => $this->bunnyStreamService->hasPlaybackConfig(),
                'is_valid_id' => false,
                'is_found_in_library' => null,
                'warning_message' => null,
            ];
        }

        if (! \Illuminate\Support\Str::isUuid($videoId)) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => $this->bunnyStreamService->hasPlaybackConfig(),
                'is_valid_id' => false,
                'is_found_in_library' => null,
                'warning_message' => 'This lecturer video is not using a valid Bunny Stream video ID yet.',
            ];
        }

        if (! $this->bunnyStreamService->hasPlaybackConfig()) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => false,
                'is_valid_id' => true,
                'is_found_in_library' => null,
                'warning_message' => 'Bunny Stream CDN is not configured yet in the current environment.',
            ];
        }

        $videoInspection = $this->bunnyStreamService->inspectVideoId($videoId);

        if ($videoInspection['is_verified'] && ! $videoInspection['is_found']) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => true,
                'is_valid_id' => true,
                'is_found_in_library' => false,
                'warning_message' => 'This lecturer video ID was not found in the configured Bunny Stream library.',
            ];
        }

        return [
            'video_id' => $videoId,
            'hls_url' => $this->bunnyStreamService->hlsUrl($videoId),
            'is_ready' => true,
            'is_configured' => true,
            'is_valid_id' => true,
            'is_found_in_library' => $videoInspection['is_verified'] ? true : null,
            'warning_message' => null,
        ];
    }
}
