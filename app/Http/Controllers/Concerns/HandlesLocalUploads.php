<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesLocalUploads
{
    protected function storeUploadedFile(?UploadedFile $file, string $directory, ?string $currentPath = null): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        if ($currentPath) {
            Storage::disk('local')->delete($currentPath);
        }

        return $file->store($directory, 'local');
    }

    protected function deleteUploadedFile(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
