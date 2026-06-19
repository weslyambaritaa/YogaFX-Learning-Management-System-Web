<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\EmailNotificationService;
use App\Services\PasswordChangeFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfilePasswordController extends Controller
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
        private readonly PasswordChangeFlowService $passwordChangeFlowService,
    ) {}

    public function request(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isStudent(), 403);
        abort_if(blank($user->email), 422, 'Student email is required before requesting a password change.');

        Password::broker()->deleteToken($user);
        $this->emailNotificationService->sendPasswordResetRequested(
            $user,
            Password::broker()->createToken($user),
        );

        return Redirect::route('profile.edit')->with('status', 'student-password-change-email-sent');
    }

    public function edit(Request $request, string $token): Response
    {
        $email = (string) $request->query('email', '');

        abort_if($email === '', 404);

        $passwordChangeRequest = $this->passwordChangeFlowService->resolveActiveRequest($email, $token);
        abort_if(! $passwordChangeRequest || $passwordChangeRequest->isExpired() || $passwordChangeRequest->isUsed(), 404);

        return Inertia::render('Auth/StudentPasswordChange', [
            'token' => $token,
            'email' => $email,
            'expires_at' => $passwordChangeRequest->expires_at?->toIso8601String(),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
            'new_password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ]);

        $result = $this->passwordChangeFlowService->resetWithOtp(
            $validated['email'],
            $validated['token'],
            $validated['otp_code'],
            $validated['new_password'],
            (string) $request->input('new_password_confirmation'),
        );

        $status = $result['status'];
        $passwordChangeRequest = $result['password_change_request'];

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        if ($request->user()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($passwordChangeRequest->isMobileOrigin()) {
            return Redirect::route('password.success.mobile');
        }

        return Redirect::route('login')->with('status', 'Your password has been changed successfully. Please log in again.');
    }
}
