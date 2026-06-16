<?php

namespace App\Http\Middleware;

use App\Support\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileStudentAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isStudent()) {
            return MobileApiResponse::error('This mobile API is only available to student accounts.', Response::HTTP_FORBIDDEN);
        }

        if (! $user->isStudentAccountActive()) {
            return MobileApiResponse::error('Your student account is inactive.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
