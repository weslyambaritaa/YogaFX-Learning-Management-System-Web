<?php

namespace App\Services\Mobile\V1;

use App\Models\Course;
use App\Models\User;
use App\Services\Mobile\V1\Concerns\BuildsMobileSignedContentImageUrls;
use App\Services\BunnyStreamService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentCourseApiService
{
    use BuildsMobileSignedContentImageUrls;

    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listForUser(User $user): array
    {
        $courses = $this->coursesForUser($user);

        return [
            'items' => $courses
                ->values()
                ->map(fn (Course $course, int $index) => $this->coursePayload($user, $course, $index + 1))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detailForUser(User $user, Course $course): ?array
    {
        if (! $this->isAccessibleToUser($user, $course)) {
            return null;
        }

        return $this->coursePayload($user, $course, null);
    }

    /**
     * @return Collection<int, Course>
     */
    private function coursesForUser(User $user): Collection
    {
        return Course::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->orderBy('title')
            ->get();
    }

    private function isAccessibleToUser(User $user, Course $course): bool
    {
        if (! $user->isStudent() || $user->access_tier_id === null) {
            return false;
        }

        return $course->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(User $user, Course $course, ?int $index): array
    {
        $videoState = $this->videoStateForCourse($course);
        $thumbnailUrl = $this->mobileSignedContentImageUrl($user, 'course', $course->id, 'thumbnail', $course->thumbnail, $course->updated_at)
            ?: $this->bunnyStreamService->thumbnailUrl($course->video);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'url_slug' => $course->url_slug,
            'description' => $course->description,
            'index' => $index,
            'status' => $videoState['is_ready'] ? 'ready' : 'unavailable',
            'thumbnail_url' => $thumbnailUrl,
            'thumbnail' => [
                'url' => $thumbnailUrl,
                'is_available' => filled($thumbnailUrl),
            ],
            'video' => $videoState,
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

        if (! Str::isUuid($videoId)) {
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
