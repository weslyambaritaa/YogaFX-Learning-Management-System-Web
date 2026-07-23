<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        $email = (string) $request->query('email', '');

        abort_if($email === '', 404);

        $passwordResetRequest = $this->resolvePasswordResetRequest($email, (string) $request->route('token'));
        abort_if(! $passwordResetRequest || $passwordResetRequest->isExpired() || $passwordResetRequest->isUsed(), 404);

        return Inertia::render('Auth/ResetPassword', [
            'email' => $email,
            'token' => $request->route('token'),
            'expires_at' => $passwordResetRequest->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'otp_code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $passwordResetRequest = $this->resolvePasswordResetRequest(
            (string) $request->input('email'),
            (string) $request->input('token'),
        );

        if (! $passwordResetRequest || $passwordResetRequest->isExpired() || $passwordResetRequest->isUsed()) {
            throw ValidationException::withMessages([
                'otp_code' => ['This password reset request is invalid or has expired. Please request a new one.'],
            ]);
        }

        if (! Hash::check((string) $request->input('otp_code'), $passwordResetRequest->otp_hash)) {
            throw ValidationException::withMessages([
                'otp_code' => ['The OTP code is invalid. Please check the email you received and try again.'],
            ]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $passwordResetRequest) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                $passwordResetRequest->forceFill([
                    'used_at' => now(),
                ])->save();

                StudentPasswordChangeRequest::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $passwordResetRequest->id)
                    ->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            if ($request->user()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return Redirect::route('login')->with('status', 'Your password has been reset successfully. Please log in again.');
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }

    private function resolvePasswordResetRequest(string $email, string $token): ?StudentPasswordChangeRequest
    {
        return StudentPasswordChangeRequest::query()
            ->where('email', $email)
            ->where('token_hash', hash('sha256', $token))
            ->latest('id')
            ->first();
    }
}
