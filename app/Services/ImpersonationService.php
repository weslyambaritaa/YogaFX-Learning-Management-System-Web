<?php

namespace App\Services;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonationService
{
    public function start(User $admin, User $student, Request $request): void
    {
        abort_unless($admin->isAdmin(), 403);
        abort_unless($student->isStudent(), 404);
        abort_unless(
            $student->isStudentAccountActive() && $student->hasCompletedStudentProfile(),
            422,
            'This student cannot be impersonated right now.',
        );
        abort_if(Impersonation::active(), 409, 'Already impersonating a student. Return to admin first.');

        session([
            Impersonation::SESSION_KEY => $admin->id,
            Impersonation::STARTED_AT_KEY => now()->timestamp,
        ]);

        Auth::loginUsingId($student->id);
        $request->session()->regenerate();

        Log::info('admin.impersonation.started', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'student_id' => $student->id,
            'student_email' => $student->email,
            'ip' => $request->ip(),
        ]);
    }

    public function stop(Request $request): void
    {
        if (! Impersonation::active()) {
            return;
        }

        $adminId = Impersonation::adminId();
        $startedAt = Impersonation::startedAt();
        $student = $request->user();

        Auth::loginUsingId($adminId);
        $request->session()->forget([Impersonation::SESSION_KEY, Impersonation::STARTED_AT_KEY]);
        $request->session()->regenerate();

        Log::info('admin.impersonation.ended', [
            'admin_id' => $adminId,
            'student_id' => $student?->id,
            'student_email' => $student?->email,
            'ip' => $request->ip(),
            'duration_seconds' => $startedAt !== null ? now()->timestamp - $startedAt : null,
        ]);
    }
}
