<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StudentSessionTrackingService
{
    public function startStudentSession(Request $request, User $user): void
    {
        if (! $user->isStudent() || ! $this->timeTrackingSchemaReady()) {
            return;
        }

        $this->reconcileTimedOutSessions($user);
        $this->closeAllActiveSessions($user);

        $now = now();
        $session = UserSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $request->session()->getId(),
            'login_at' => $now,
            'last_activity_at' => $now,
            'is_active' => true,
        ]);

        $request->session()->put('student_user_session_id', $session->id);
    }

    public function touchStudentSession(Request $request, User $user): void
    {
        if (! $user->isStudent() || ! $this->timeTrackingSchemaReady()) {
            return;
        }

        $this->reconcileTimedOutSessions($user);

        $session = $this->resolveActiveSession($request, $user);

        if (! $session) {
            $this->startStudentSession($request, $user);

            return;
        }

        $session->forceFill([
            'last_activity_at' => now(),
        ])->save();
    }

    public function endStudentSession(Request $request, ?User $user): void
    {
        if (! $user || ! $user->isStudent() || ! $this->timeTrackingSchemaReady()) {
            return;
        }

        $this->reconcileTimedOutSessions($user);

        $session = $this->resolveActiveSession($request, $user);

        if (! $session) {
            return;
        }

        $this->closeSession($session, $user, now());
        $request->session()->forget('student_user_session_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user, bool $includeActiveDuration = true): array
    {
        if (! $this->timeTrackingSchemaReady()) {
            return [
                'total_access_duration_seconds' => 0,
                'running_total_access_duration_seconds' => 0,
                'formatted_total_access_duration' => $this->formatDurationLabel(0),
                'formatted_total_access_parts' => $this->formatDurationParts(0),
                'active_session_login_at' => null,
                'currently_active' => false,
                'last_visit_at' => null,
            ];
        }

        $this->reconcileTimedOutSessions($user);

        $activeSession = $user->userSessions()
            ->where('is_active', true)
            ->orderByDesc('login_at')
            ->first();

        $latestSession = $user->userSessions()
            ->orderByDesc('last_activity_at')
            ->orderByDesc('login_at')
            ->first();

        $baseTotalSeconds = (int) ($user->total_access_duration_seconds ?? 0);
        $activeDurationSeconds = 0;

        if ($includeActiveDuration && $activeSession?->login_at) {
            $activeDurationSeconds = max(
                0,
                $activeSession->login_at->diffInSeconds(now()),
            );
        }

        $totalSecondsForDisplay = $baseTotalSeconds + $activeDurationSeconds;
        $lastVisitAt = $activeSession?->last_activity_at
            ?? $latestSession?->last_activity_at
            ?? $latestSession?->logout_at
            ?? $latestSession?->login_at;

        return [
            'total_access_duration_seconds' => $baseTotalSeconds,
            'running_total_access_duration_seconds' => $totalSecondsForDisplay,
            'formatted_total_access_duration' => $this->formatDurationLabel(
                $totalSecondsForDisplay,
            ),
            'formatted_total_access_parts' => $this->formatDurationParts(
                $totalSecondsForDisplay,
            ),
            'active_session_login_at' => $activeSession?->login_at?->toIso8601String(),
            'currently_active' => (bool) $activeSession,
            'last_visit_at' => $lastVisitAt?->toDateTimeString(),
        ];
    }

    /**
     * @return array{hours: string, minutes: string, seconds: string}
     */
    public function formatDurationParts(int $seconds): array
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return [
            'hours' => str_pad((string) $hours, 2, '0', STR_PAD_LEFT),
            'minutes' => str_pad((string) $minutes, 2, '0', STR_PAD_LEFT),
            'seconds' => str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT),
        ];
    }

    public function formatDurationLabel(int $seconds): string
    {
        $parts = $this->formatDurationParts($seconds);

        return "{$parts['hours']} hours {$parts['minutes']} minutes {$parts['seconds']} seconds";
    }

    public function reconcileTimedOutSessions(User $user): void
    {
        if (! $user->isStudent() || ! $this->timeTrackingSchemaReady()) {
            return;
        }

        $cutoff = now()->subMinutes((int) config('session.lifetime', 120));

        $user->userSessions()
            ->where('is_active', true)
            ->where(function ($query) use ($cutoff) {
                $query->where('last_activity_at', '<=', $cutoff)
                    ->orWhere(function ($innerQuery) use ($cutoff) {
                        $innerQuery->whereNull('last_activity_at')
                            ->where('login_at', '<=', $cutoff);
                    });
            })
            ->get()
            ->each(function (UserSession $session) use ($user) {
                $endTime = $session->last_activity_at
                    ?? $session->login_at
                    ?? now();

                $this->closeSession($session, $user, $endTime);
            });
    }

    private function closeAllActiveSessions(User $user): void
    {
        if (! $this->timeTrackingSchemaReady()) {
            return;
        }

        $user->userSessions()
            ->where('is_active', true)
            ->get()
            ->each(function (UserSession $session) use ($user) {
                $endTime = $session->last_activity_at
                    ?? $session->login_at
                    ?? now();

                $this->closeSession($session, $user, $endTime);
            });
    }

    private function resolveActiveSession(Request $request, User $user): ?UserSession
    {
        if (! $this->timeTrackingSchemaReady()) {
            return null;
        }

        $sessionId = $request->session()->getId();
        $storedSessionRowId = $request->session()->get('student_user_session_id');

        if ($storedSessionRowId) {
            $session = UserSession::query()
                ->whereKey($storedSessionRowId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($session) {
                return $session;
            }
        }

        return UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    private function closeSession(
        UserSession $session,
        User $user,
        CarbonInterface $endTime,
    ): void {
        if (! $session->is_active || ! $this->timeTrackingSchemaReady()) {
            return;
        }

        $loginAt = $session->login_at ?? $endTime;
        $durationSeconds = $this->durationInSeconds($loginAt, $endTime);

        $session->forceFill([
            'logout_at' => $endTime,
            'last_activity_at' => $session->last_activity_at ?? $endTime,
            'session_duration_seconds' => $durationSeconds,
            'is_active' => false,
        ])->save();

        if ($durationSeconds > 0) {
            $user->forceFill([
                'total_access_duration_seconds' => (int) ($user->total_access_duration_seconds ?? 0) + $durationSeconds,
            ])->save();

            $user->refresh();
        }
    }

    private function durationInSeconds(
        CarbonInterface $startTime,
        CarbonInterface $endTime,
    ): int {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return $start->diffInSeconds($end);
    }

    private function timeTrackingSchemaReady(): bool
    {
        return Schema::hasTable('user_sessions')
            && Schema::hasColumn('users', 'total_access_duration_seconds');
    }
}
