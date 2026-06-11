<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BunnyStreamService
{
    public function hasManagementConfig(): bool
    {
        return filled(config('bunny.stream.library_id'))
            && filled(config('bunny.stream.access_key'));
    }

    public function hasPlaybackConfig(): bool
    {
        return filled(config('bunny.stream.cdn_base_url'));
    }

    public function createAndUpload(UploadedFile $file, ?string $title = null): string
    {
        $videoId = $this->createVideo($title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Lecturer Video');
        $this->uploadVideo($videoId, $file);

        return $videoId;
    }

    public function createVideo(string $title): string
    {
        $this->ensureConfigured();

        $response = Http::withHeaders([
            'AccessKey' => (string) config('bunny.stream.access_key'),
            'Accept' => 'application/json',
        ])->timeout(60)->post($this->libraryUrl('/videos'), [
            'title' => Str::limit($title, 255, ''),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create a Bunny Stream video resource.');
        }

        $videoId = $response->json('guid');

        if (! is_string($videoId) || $videoId === '') {
            throw new RuntimeException('Bunny Stream did not return a valid video id.');
        }

        return $videoId;
    }

    public function uploadVideo(string $videoId, UploadedFile $file): void
    {
        $this->ensureConfigured();

        $response = Http::withHeaders([
            'AccessKey' => (string) config('bunny.stream.access_key'),
        ])->withBody(
            file_get_contents($file->getRealPath()),
            'application/octet-stream',
        )->timeout(120)->put($this->libraryUrl('/videos/'.$videoId));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to upload the lecturer video to Bunny Stream.');
        }
    }

    public function hlsUrl(?string $videoId): ?string
    {
        if (! filled($videoId)) {
            return null;
        }

        if (! $this->hasPlaybackConfig()) {
            throw new RuntimeException('Bunny Stream CDN base URL is not configured.');
        }

        return rtrim((string) config('bunny.stream.cdn_base_url'), '/')
            .'/'.rawurlencode((string) $videoId).'/playlist.m3u8';
    }

    /**
     * @return array{
     *     video_id: string|null,
     *     is_verified: bool,
     *     is_found: bool,
     *     title: string|null,
     *     error_code: 'empty'|'invalid_format'|'missing_management_config'|'request_failed'|null
     * }
     */
    public function inspectVideoId(?string $videoId): array
    {
        $normalizedVideoId = is_string($videoId)
            ? trim($videoId)
            : null;

        if (! filled($normalizedVideoId)) {
            return [
                'video_id' => null,
                'is_verified' => false,
                'is_found' => false,
                'title' => null,
                'error_code' => 'empty',
            ];
        }

        if (! Str::isUuid($normalizedVideoId)) {
            return [
                'video_id' => $normalizedVideoId,
                'is_verified' => false,
                'is_found' => false,
                'title' => null,
                'error_code' => 'invalid_format',
            ];
        }

        if (! $this->hasManagementConfig()) {
            return [
                'video_id' => $normalizedVideoId,
                'is_verified' => false,
                'is_found' => false,
                'title' => null,
                'error_code' => 'missing_management_config',
            ];
        }

        try {
            $response = Http::withHeaders([
                'AccessKey' => (string) config('bunny.stream.access_key'),
                'Accept' => 'application/json',
            ])->timeout(20)->get($this->libraryUrl('/videos/'.$normalizedVideoId));

            if ($response->status() === 404) {
                return [
                    'video_id' => $normalizedVideoId,
                    'is_verified' => true,
                    'is_found' => false,
                    'title' => null,
                    'error_code' => null,
                ];
            }

            if (! $response->successful()) {
                return [
                    'video_id' => $normalizedVideoId,
                    'is_verified' => false,
                    'is_found' => false,
                    'title' => null,
                    'error_code' => 'request_failed',
                ];
            }

            $title = $response->json('title');

            return [
                'video_id' => $normalizedVideoId,
                'is_verified' => true,
                'is_found' => true,
                'title' => is_string($title) && $title !== '' ? $title : null,
                'error_code' => null,
            ];
        } catch (Throwable) {
            return [
                'video_id' => $normalizedVideoId,
                'is_verified' => false,
                'is_found' => false,
                'title' => null,
                'error_code' => 'request_failed',
            ];
        }
    }

    private function libraryUrl(string $suffix): string
    {
        return rtrim((string) config('bunny.stream.api_base_url'), '/')
            .'/library/'.trim((string) config('bunny.stream.library_id'), '/')
            .'/'.ltrim($suffix, '/');
    }

    private function ensureConfigured(): void
    {
        if (! $this->hasManagementConfig()) {
            throw new RuntimeException('Bunny Stream is not configured.');
        }
    }
}
