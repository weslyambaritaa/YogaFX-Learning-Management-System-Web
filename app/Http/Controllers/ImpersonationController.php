<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonationService) {}

    public function start(Request $request, User $student): RedirectResponse
    {
        $this->impersonationService->start($request->user(), $student, $request);

        return redirect()->route('student.dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $this->impersonationService->stop($request);

        return redirect()->route('admin.students.index');
    }
}
