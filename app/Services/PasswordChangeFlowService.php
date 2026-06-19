<?php

namespace App\Services;

use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordChangeFlowService
{
    /**
     * @return array{
     *     email: string,
     *     token: string,
     *     otp_code: string,
     *     origin: string,
     *     expires_in_minutes: int,
     *     expires_at: string,
     *     change_password_url: string
     * }
     */
    public function prepareResetRequest(
        User $user,
        string $token,
        string $origin = StudentPasswordChangeRequest::ORIGIN_WEB,
    ): array {
        StudentPasswordChangeRequest::query()
            ->where('user_id', $user->id)
            ->delete();

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresInMinutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );
        $expiresAt = now()->addMinutes($expiresInMinutes);

        StudentPasswordChangeRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make($otpCode),
            'origin' => $origin,
            'expires_at' => $expiresAt,
        ]);

        return [
            'email' => $user->email,
            'token' => $token,
            'otp_code' => $otpCode,
            'origin' => $origin,
            'expires_in_minutes' => $expiresInMinutes,
            'expires_at' => $expiresAt->toIso8601String(),
            'change_password_url' => route('profile.password.change.edit', [
                'token' => $token,
                'email' => $user->email,
            ]),
        ];
    }

    public function issueForUser(
        User $user,
        string $origin = StudentPasswordChangeRequest::ORIGIN_WEB,
    ): array
    {
        Password::broker()->deleteToken($user);

        return $this->prepareResetRequest(
            $user,
            Password::broker()->createToken($user),
            $origin,
        );
    }

    public function resolveActiveRequest(string $email, string $token): ?StudentPasswordChangeRequest
    {
        return StudentPasswordChangeRequest::query()
            ->where('email', $email)
            ->where('token_hash', hash('sha256', $token))
            ->latest('id')
            ->first();
    }

    public function assertRequestCanBeUsed(
        string $email,
        string $token,
        ?string $otpCode = null,
    ): StudentPasswordChangeRequest {
        $passwordChangeRequest = $this->resolveActiveRequest($email, $token);

        if (! $passwordChangeRequest || $passwordChangeRequest->isExpired() || $passwordChangeRequest->isUsed()) {
            throw ValidationException::withMessages([
                'otp_code' => ['This password change request is invalid or has expired. Please request a new one and try again.'],
            ]);
        }

        if ($otpCode !== null && ! Hash::check($otpCode, $passwordChangeRequest->otp_hash)) {
            throw ValidationException::withMessages([
                'otp_code' => ['The OTP code is invalid. Please check the email you received and try again.'],
            ]);
        }

        return $passwordChangeRequest;
    }

    public function resetWithOtp(
        string $email,
        string $token,
        string $otpCode,
        string $password,
        string $passwordConfirmation,
    ): array {
        $passwordChangeRequest = $this->assertRequestCanBeUsed($email, $token, $otpCode);

        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            function (User $user) use ($password, $passwordChangeRequest): void {
                $user->forceFill([
                    'password' => Hash::make($password),
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

        return [
            'status' => $status,
            'password_change_request' => $passwordChangeRequest,
        ];
    }
}
