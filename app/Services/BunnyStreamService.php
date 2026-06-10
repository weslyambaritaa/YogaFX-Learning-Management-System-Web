<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BunnyStreamService
{
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

        $cdnBaseUrl = (string) config('bunny.stream.cdn_base_url');

        if ($cdnBaseUrl === '') {
            throw new RuntimeException('Bunny Stream CDN base URL is not configured.');
        }

        return $cdnBaseUrl.'/'.rawurlencode((string) $videoId).'/playlist.m3u8';
    }

    private function libraryUrl(string $suffix): string
    {
        return rtrim((string) config('bunny.stream.api_base_url'), '/')
            .'/library/'.trim((string) config('bunny.stream.library_id'), '/')
            .'/'.ltrim($suffix, '/');
    }

    private function ensureConfigured(): void
    {
        if (! filled(config('bunny.stream.library_id'))
            || ! filled(config('bunny.stream.access_key'))) {
            throw new RuntimeException('Bunny Stream is not configured.');
        }
    }
}
