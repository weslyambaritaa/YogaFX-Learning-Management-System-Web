<?php

namespace App\Http\Middleware;

use App\Services\StudentSessionTrackingService;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackStudentSessionActivity
{
    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStudent() && ! Impersonation::active()) {
            $this->sessionTrackingService->touchStudentSession($request, $user);
        }

        return $next($request);
    }
}
