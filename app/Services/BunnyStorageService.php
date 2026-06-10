<?php

namespace App\Services;

use App\Support\BunnyAssetPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BunnyStorageService
{
    public function upload(UploadedFile $file, string $directory, ?string $currentPath = null): string
    {
        $objectKey = null;

        try {
            $this->ensureConfigured();

            $fileContents = file_get_contents($file->getRealPath());

            if ($fileContents === false) {
                throw new RuntimeException('The uploaded file could not be read before sending it to Bunny Storage.');
            }

            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid()->toString().($extension ? '.'.Str::lower($extension) : '');
            $objectKey = trim($directory, '/').'/'.$filename;

            $response = Http::withHeaders([
                'AccessKey' => (string) config('bunny.storage.access_key'),
            ])->withBody(
                $fileContents,
                $file->getMimeType() ?: 'application/octet-stream',
            )->timeout(120)->put($this->uploadUrl($objectKey));

            if (! $response->successful()) {
                Log::error('Bunny Storage upload failed.', [
                    'directory' => $directory,
                    'object_key' => $objectKey,
                    'status' => $response->status(),
                    'response_body' => Str::limit(trim($response->body()), 1000),
                    'client_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                ]);

                throw new RuntimeException($this->uploadFailureMessage($response->status(), $response->body()));
            }

            return BunnyAssetPath::fromObjectKey($objectKey);
        } catch (Throwable $exception) {
            $context = [
                'directory' => $directory,
                'object_key' => $objectKey,
                'client_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($exception instanceof RequestException && $exception->response !== null) {
                $context['response_status'] = $exception->response->status();
                $context['response_body'] = Str::limit(trim($exception->response->body()), 1000);
            }

            if ($exception instanceof ConnectionException) {
                $context['connection_exception'] = true;
            }

            Log::error('Bunny Storage upload exception.', $context);

            if (config('app.debug')) {
                throw $exception;
            }

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Unexpected Bunny Storage upload exception: '.$exception->getMessage(), previous: $exception);
        }
    }

    public function delete(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        if (! BunnyAssetPath::isBunnyPath($path)) {
            Storage::disk('local')->delete($path);

            return;
        }

        $this->ensureConfigured();

        Http::withHeaders([
            'AccessKey' => (string) config('bunny.storage.access_key'),
        ])->timeout(60)->delete($this->uploadUrl(BunnyAssetPath::objectKey($path)));
    }

    public function url(?string $path): ?string
    {
        if (! filled($path) || ! BunnyAssetPath::isBunnyPath($path)) {
            return null;
        }

        $this->ensureConfigured();

        return $this->publicUrlForObjectKey(BunnyAssetPath::objectKey($path));
    }

    public function publicUrlForObjectKey(string $objectKey): string
    {
        $baseUrl = (string) config('bunny.storage.public_base_url');
        $encodedPath = collect(explode('/', trim($objectKey, '/')))
            ->filter()
            ->map(fn (string $segment) => rawurlencode($segment))
            ->implode('/');

        return $baseUrl.'/'.$encodedPath;
    }

    private function uploadUrl(string $objectKey): string
    {
        $baseUrl = (string) config('bunny.storage.api_base_url');
        $zoneName = trim((string) config('bunny.storage.zone_name'), '/');
        $encodedPath = collect(explode('/', trim($objectKey, '/')))
            ->filter()
            ->map(fn (string $segment) => rawurlencode($segment))
            ->implode('/');

        return $baseUrl.'/'.$zoneName.'/'.$encodedPath;
    }

    private function ensureConfigured(): void
    {
        if (! filled(config('bunny.storage.access_key'))
            || ! filled(config('bunny.storage.zone_name'))
            || ! filled(config('bunny.storage.public_base_url'))) {
            throw new RuntimeException('Bunny Storage is not configured.');
        }
    }

    private function uploadFailureMessage(int $status, string $responseBody): string
    {
        $message = match ($status) {
            401, 403 => 'Bunny Storage rejected the upload because the storage credentials or permissions are invalid.',
            413 => 'Bunny Storage rejected the upload because the file is too large for the upstream storage request.',
            default => 'Bunny Storage rejected the upload request.',
        };

        $details = trim($responseBody);

        if ($details === '') {
            return $message.' HTTP '.$status.'.';
        }

        return $message.' HTTP '.$status.'. Response: '.Str::limit($details, 180);
    }
}
