<?php

namespace App\Support;

use Illuminate\Http\Request;

class PublicUrl
{
    public static function base(): string
    {
        return rtrim((string) config('app.public_url', config('app.url', 'http://localhost')), '/');
    }

    public static function studentLogin(): string
    {
        $configuredUrl = trim((string) config('app.student_login_url', ''));

        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/');
        }

        return self::fromRelativePath('/login');
    }

    public static function fromRelativePath(string $path = ''): string
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '' || $normalizedPath === '/') {
            return self::base();
        }

        return self::base().'/'.ltrim($normalizedPath, '/');
    }

    public static function fromUri(string $uri): string
    {
        if (filter_var($uri, FILTER_VALIDATE_URL)) {
            return $uri;
        }

        return self::fromRelativePath($uri);
    }

    public static function current(Request $request): string
    {
        $path = trim($request->getPathInfo(), '/');
        $queryString = $request->getQueryString();

        return self::fromRelativePath($path).($queryString ? '?'.$queryString : '');
    }
}
