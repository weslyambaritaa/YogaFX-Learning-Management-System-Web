<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardAnalyticsService $analyticsService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $studentScope = $request->string('student_scope')->toString() ?: 'all';
        $activityRange = $request->string('activity_range')->toString() ?: 'weekly';

        return Inertia::render('Admin/Dashboard', [
            'filters' => [
                'student_scope' => $studentScope,
                'activity_range' => $activityRange,
            ],
            'dashboard' => $this->analyticsService->build(
                $studentScope,
                $activityRange,
            ),
        ]);
    }
}
