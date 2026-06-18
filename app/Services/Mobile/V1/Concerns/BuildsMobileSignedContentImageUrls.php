<?php

namespace App\Services\Mobile\V1\Concerns;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use App\Support\MobileSignedUrl;
use Illuminate\Support\Facades\Storage;

trait BuildsMobileSignedContentImageUrls
{
    use BuildsProtectedMediaUrls;

    protected function mobileSignedContentImageUrl(
        User $user,
        string $entity,
        int $id,
        string $field,
        ?string $path,
        mixed $versionSeed = null,
    ): ?string {
        if (! filled($path)) {
            return null;
        }

        if (BunnyAssetPath::isBunnyPath($path)) {
            return app(BunnyStorageService::class)->url($path);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (! Storage::disk('local')->exists($path)) {
            return $this->protectedMediaUrl(
                $entity,
                $id,
                $field,
                $path,
                versionSeed: $versionSeed,
            );
        }

        return MobileSignedUrl::temporarySignedRoute(
            'mobile.api.v1.content-images.show',
            now()->addHour(),
            [
                'entity' => $entity,
                'id' => $id,
                'field' => $field,
                'student' => $user->id,
            ],
        );
    }

    protected function publicMobileMediaUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (BunnyAssetPath::isBunnyPath($path)) {
            return app(BunnyStorageService::class)->url($path);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return null;
    }
}
