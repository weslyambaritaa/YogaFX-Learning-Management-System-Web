<?php

namespace App\Http\Controllers\Concerns;

trait BuildsProtectedMediaUrls
{
    protected function protectedMediaUrl(
        string $entity,
        int $id,
        string $field,
        ?string $path,
        bool $download = false,
        mixed $versionSeed = null,
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
                (string) $versionSeed,
            ])),
        ];

        if ($download) {
            $parameters['download'] = 1;
        }

        return route('media.show', $parameters);
    }
}
