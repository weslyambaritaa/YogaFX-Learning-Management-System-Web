<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class MobileSignedUrl
{
    public static function temporarySignedRoute(string $routeName, \DateTimeInterface $expiration, array $parameters = []): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            $routeName,
            $expiration,
            $parameters,
            absolute: false,
        );

        $absoluteUrl = self::requestRoot().$relativeUrl;

        Log::info('Mobile signed URL generated.', [
            'route_name' => $routeName,
            'parameters' => $parameters,
            'app_url' => config('app.url'),
            'request_root' => self::requestRoot(),
            'relative_url' => $relativeUrl,
            'absolute_url' => $absoluteUrl,
        ]);

        return $absoluteUrl;
    }

    public static function hasValidSignature(Request $request): bool
    {
        $isValid = $request->hasValidSignature(absolute: false);

        Log::info('Mobile signed URL validation checked.', [
            'route_name' => $request->route()?->getName(),
            'app_url' => config('app.url'),
            'request_root' => $request->getSchemeAndHttpHost(),
            'full_url' => $request->fullUrl(),
            'path' => $request->path(),
            'query' => $request->query(),
            'is_valid' => $isValid,
        ]);

        return $isValid;
    }

    private static function requestRoot(): string
    {
        /** @var Request|null $request */
        $request = request();

        if ($request instanceof Request) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return rtrim((string) config('app.url'), '/');
    }
}
