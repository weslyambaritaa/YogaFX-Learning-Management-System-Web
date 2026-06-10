<?php

namespace App\Http\Controllers\Concerns;

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
