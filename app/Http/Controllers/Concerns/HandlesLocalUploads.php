<?php

namespace App\Http\Controllers\Concerns;

use App\Services\BunnyStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait HandlesLocalUploads
{
    protected function storeUploadedFile(?UploadedFile $file, string $directory, ?string $currentPath = null): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        $newPath = $file->store($directory, 'local');

        if ($currentPath) {
            Storage::disk('local')->delete($currentPath);
        }

        return $newPath;
    }

    protected function deleteUploadedFile(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    protected function storeUploadedFileToBunny(?UploadedFile $file, string $directory, ?string $currentPath = null): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        $newPath = app(BunnyStorageService::class)->upload($file, $directory, $currentPath);

        if ($currentPath && $currentPath !== $newPath) {
            app(BunnyStorageService::class)->delete($currentPath);
        }

        return $newPath;
    }

    protected function storeUploadedFileToBunnyWithLocalFallback(
        ?UploadedFile $file,
        string $directory,
        ?string $currentPath = null,
    ): ?string {
        if (! $file) {
            return $currentPath;
        }

        try {
            return $this->storeUploadedFileToBunny($file, $directory, $currentPath);
        } catch (Throwable $throwable) {
            Log::warning('Falling back to local upload after Bunny Storage failure.', [
                'directory' => $directory,
                'client_filename' => $file->getClientOriginalName(),
                'current_path' => $currentPath,
                'message' => $throwable->getMessage(),
            ]);

            $newPath = $file->store($directory, 'local');

            if ($currentPath && $currentPath !== $newPath) {
                $this->deleteUploadedFileFromAnyStorage($currentPath);
            }

            return $newPath;
        }
    }

    protected function deleteUploadedFileFromAnyStorage(?string $path): void
    {
        if (! $path) {
            return;
        }

        app(BunnyStorageService::class)->delete($path);
    }
}
