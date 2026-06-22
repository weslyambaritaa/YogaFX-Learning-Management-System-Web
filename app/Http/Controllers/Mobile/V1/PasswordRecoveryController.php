<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Support\MobileApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\HttpFoundation\Response;

class PasswordRecoveryController extends Controller
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    public function forgot(Request $request)
    {
        $validator = validator($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error(
                'The forgot password payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $email = $validator->validated()['email'];
        $user = User::query()->where('email', $email)->first();

        if ($user) {
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

            $resetUrl = $this->publicRoute('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);

            $this->emailNotificationService->sendPasswordResetRequestedWithOtp(
                $user,
                $resetUrl,
                $otpCode,
                $expiresInMinutes,
            );
        }

        return MobileApiResponse::success(
            [
                'email' => $email,
                'otp_required' => true,
            ],
            'Password reset email and OTP sent successfully.',
        );
    }

    public function reset(Request $request)
    {
        $validator = validator($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error(
                'The reset password payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();
        $passwordResetRequest = $this->resolvePasswordResetRequest(
            $validated['email'],
            $validated['token'],
        );

        if (! $passwordResetRequest || $passwordResetRequest->isExpired() || $passwordResetRequest->isUsed()) {
            return MobileApiResponse::error(
                'Unable to reset password.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'otp_code' => ['This password reset request is invalid or has expired. Please request a new one.'],
                ],
            );
        }

        if (! Hash::check($validated['otp_code'], $passwordResetRequest->otp_hash)) {
            return MobileApiResponse::error(
                'Unable to reset password.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'otp_code' => ['The OTP code is invalid. Please check the email you received and try again.'],
                ],
            );
        }

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user) use ($validated, $passwordResetRequest): void {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
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

        if ($status === Password::PASSWORD_RESET) {
            if ($request->user()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return MobileApiResponse::success(
                [
                    'email' => $validated['email'],
                    'password_reset' => true,
                ],
                'Password reset successfully.',
            );
        }

        return MobileApiResponse::error(
            'Unable to reset password.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'email' => [trans($status)],
            ],
        );
    }

    private function resolvePasswordResetRequest(string $email, string $token): ?StudentPasswordChangeRequest
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
