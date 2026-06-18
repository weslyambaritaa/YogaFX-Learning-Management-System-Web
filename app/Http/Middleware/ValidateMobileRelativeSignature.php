<?php

namespace App\Http\Middleware;

use App\Support\MobileApiResponse;
use App\Support\MobileSignedUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMobileRelativeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MobileSignedUrl::hasValidSignature($request)) {
            return MobileApiResponse::error('Invalid signature.', 403);
        }

        return $next($request);
    }
}
