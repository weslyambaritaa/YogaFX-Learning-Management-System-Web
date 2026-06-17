<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\EmailOtpChallengeService;
use App\Services\StudentSessionTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly EmailOtpChallengeService $otpChallenges,
        private readonly StudentSessionTrackingService $sessionTrackingService,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if (! $user || ! $user->hasRole($user::ROLE_ADMIN, $user::ROLE_STUDENT)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account is not authorized to access YogaFX LMS.',
            ]);
        }

        $otpChallenge = $this->otpChallenges->createForLogin($user, [
            'remember' => $request->boolean('remember'),
            'redirect_to' => $request->session()->get(
                'url.intended',
                route($user->postLoginRouteName(), absolute: false),
            ),
        ]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.otp.show', [
            'token' => $otpChallenge['token'],
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->sessionTrackingService->endStudentSession($request, $request->user());

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
