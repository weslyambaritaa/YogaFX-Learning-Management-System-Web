<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceImpersonationReadOnly
{
    /**
     * Routes with a business-consequence side effect on GET that must stay
     * blocked even though the request method itself is otherwise allowed.
     *
     * @var array<int, string>
     */
    private const BLOCKED_GET_ROUTES = [
        'student.upgrades.installments.cancel',
        'lessons.workbook.download',
        'student.certificates.download',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::active()) {
            return $next($request);
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return back()->with('error', 'This action is disabled while browsing as a student (read-only mode).');
        }

        if ($request->routeIs(self::BLOCKED_GET_ROUTES)) {
            return back()->with('error', 'This action is disabled while browsing as a student (read-only mode).');
        }

        return $next($request);
    }
}
