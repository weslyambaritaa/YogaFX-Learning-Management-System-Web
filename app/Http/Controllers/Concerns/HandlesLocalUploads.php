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
}
