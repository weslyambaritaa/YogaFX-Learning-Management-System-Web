<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfilePasswordController extends Controller
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    public function request(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isStudent(), 403);
        abort_if(blank($user->email), 422, 'Student email is required before requesting a password change.');

        Password::broker()->deleteToken($user);
        StudentPasswordChangeRequest::query()
            ->where('user_id', $user->id)
            ->delete();

        $token = Password::broker()->createToken($user);
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresInMinutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        StudentPasswordChangeRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        $changePasswordUrl = $this->publicRoute('profile.password.change.edit', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $this->emailNotificationService->sendStudentPasswordChangeRequested(
            $user,
            $changePasswordUrl,
            $otpCode,
            $expiresInMinutes,
        );

        return Redirect::route('profile.edit')->with('status', 'student-password-change-email-sent');
    }

    public function edit(Request $request, string $token): Response
    {
        $email = (string) $request->query('email', '');

        abort_if($email === '', 404);

        $passwordChangeRequest = $this->resolvePasswordChangeRequest($email, $token);
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

        $passwordChangeRequest = $this->resolvePasswordChangeRequest(
            $validated['email'],
            $validated['token'],
        );

        if (! $passwordChangeRequest || $passwordChangeRequest->isExpired() || $passwordChangeRequest->isUsed()) {
            throw ValidationException::withMessages([
                'otp_code' => ['This password change request is invalid or has expired. Please request a new one from your profile page.'],
            ]);
        }

        if (! Hash::check($validated['otp_code'], $passwordChangeRequest->otp_hash)) {
            throw ValidationException::withMessages([
                'otp_code' => ['The OTP code is invalid. Please check the email you received and try again.'],
            ]);
        }

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['new_password'],
                'password_confirmation' => (string) $request->input('new_password_confirmation'),
            ],
            function (User $user) use ($request, $passwordChangeRequest, $validated): void {
                $user->forceFill([
                    'password' => Hash::make($validated['new_password']),
                    'remember_token' => Str::random(60),
                ])->save();

                $passwordChangeRequest->forceFill([
                    'used_at' => now(),
                ])->save();

                StudentPasswordChangeRequest::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $passwordChangeRequest->id)
                    ->delete();

                event(new PasswordReset($user));
            },
        );

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

        return Redirect::route('login')->with('status', 'Your password has been changed successfully. Please log in again.');
    }

    private function resolvePasswordChangeRequest(string $email, string $token): ?StudentPasswordChangeRequest
    {
        return StudentPasswordChangeRequest::query()
            ->where('email', $email)
            ->where('token_hash', hash('sha256', $token))
            ->latest('id')
            ->first();
    }

    private function publicRoute(string $routeName, array $parameters = []): string
    {
        $relativePath = URL::route($routeName, $parameters, false);

        return rtrim((string) config('app.public_url', config('app.url')), '/').$relativePath;
    }
}
