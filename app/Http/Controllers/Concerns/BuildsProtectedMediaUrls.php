<?php

namespace App\Http\Controllers\Concerns;

use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use Illuminate\Support\Facades\Storage;

trait BuildsProtectedMediaUrls
{
    protected function protectedMediaUrl(
        string $entity,
        int $id,
        string $field,
        ?string $path,
        bool $download = false,
        mixed $versionSeed = null,
        array $extraParameters = [],
    ): ?string {
        if (! filled($path)) {
            return null;
        }

        if (BunnyAssetPath::isBunnyPath($path)) {
            $url = app(BunnyStorageService::class)->url($path);

            if (! $url) {
                return null;
            }

            $query = array_filter(array_merge($extraParameters, [
                'v' => sha1(implode('|', [
                    $entity,
                    $id,
                    $field,
                    (string) $path,
                    (string) $versionSeed,
                    json_encode($extraParameters),
                ])),
                'download' => $download ? 1 : null,
            ]), fn (mixed $value) => $value !== null && $value !== '');

            return $query === []
                ? $url
                : $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $parameters = [
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'v' => sha1(implode('|', [
                $entity,
                $id,
                $field,
                (string) $path,
                Storage::disk('local')->exists($path)
                    ? (string) Storage::disk('local')->lastModified($path)
                    : '',
                (string) $versionSeed,
                json_encode($extraParameters),
            ])),
        ];

        if ($download) {
            $parameters['download'] = 1;
        }

        return route('media.show', array_merge($parameters, $extraParameters));
    }
}
