<?php

namespace App\Support;

class BunnyAssetPath
{
    public const PREFIX = 'bunny://';

    public static function fromObjectKey(string $objectKey): string
    {
        return self::PREFIX.ltrim($objectKey, '/');
    }

    public static function isBunnyPath(?string $path): bool
    {
        return filled($path) && str_starts_with((string) $path, self::PREFIX);
    }

    public static function objectKey(string $path): string
    {
        return ltrim(substr($path, strlen(self::PREFIX)), '/');
    }
}
