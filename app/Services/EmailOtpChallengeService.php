<?php

namespace App\Services;

use App\Mail\TemplatedNotificationMail;
use App\Models\AuthEmailOtpChallenge;
use App\Models\OnboardingState;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailOtpChallengeService
{
    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
        private readonly EmailNotificationService $emailNotifications,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createForLogin(User $user, array $payload = []): array
    {
        return $this->createChallenge($user, AuthEmailOtpChallenge::CONTEXT_LOGIN, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createForSignup(User $user, array $payload = []): array
    {
        return $this->createChallenge($user, AuthEmailOtpChallenge::CONTEXT_SIGNUP, $payload);
    }

    public function resolve(string $token): ?AuthEmailOtpChallenge
    {
        return AuthEmailOtpChallenge::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->latest('id')
            ->first();
    }

    public function verifyLoginChallenge(AuthEmailOtpChallenge $challenge, string $otpCode, $request): User
    {
        return DB::transaction(function () use ($challenge, $otpCode, $request): User {
            $user = $this->consumeLoginChallenge($challenge, $otpCode);

            Auth::login($user, (bool) ($challenge->payload['remember'] ?? false));
            $request->session()->regenerate();

            if (! ($user->isStudent() && ! $user->isStudentAccountActive())) {
                $this->sessionTrackingService->startStudentSession($request, $user);
            }

            return $user;
        });
    }

    public function consumeLoginChallenge(AuthEmailOtpChallenge $challenge, string $otpCode): User
    {
        $this->guardChallenge($challenge, $otpCode, AuthEmailOtpChallenge::CONTEXT_LOGIN);

        return DB::transaction(function () use ($challenge): User {
            $challenge->forceFill([
                'used_at' => now(),
            ])->save();

            return $challenge->user()->firstOrFail();
        });
    }

    public function verifySignupChallenge(AuthEmailOtpChallenge $challenge, string $otpCode, $request): User
    {
        $this->guardChallenge($challenge, $otpCode, AuthEmailOtpChallenge::CONTEXT_SIGNUP);

        $result = DB::transaction(function () use ($challenge, $request): array {
            $payload = $challenge->payload ?? [];
            $onboardingState = OnboardingState::query()
                ->with(['user.accessTier', 'pendingRegistration'])
                ->findOrFail($payload['onboarding_state_id'] ?? null);

            $user = $challenge->user()->firstOrFail();
            abort_if($user->id !== $onboardingState->user_id, 409, 'This signup verification no longer matches the onboarding account.');

            $user->forceFill([
                'is_active' => true,
                'password' => $payload['password_hash'] ?? $user->password,
                'remember_token' => Str::random(60),
            ])->save();

            $onboardingState->forceFill([
                'status' => OnboardingState::STATUS_COMPLETED,
                'signup_completed_at' => now(),
            ])->save();

            $onboardingState->pendingRegistration->forceFill([
                'status' => \App\Models\PendingRegistration::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            $challenge->forceFill([
                'used_at' => now(),
            ])->save();

            Auth::login($user);
            $request->session()->regenerate();
            $this->sessionTrackingService->startStudentSession($request, $user);

            return [
                'user' => $user->fresh(['accessTier']),
                'onboarding_state' => $onboardingState->fresh(['pendingRegistration', 'user.accessTier']),
            ];
        });

        $this->emailNotifications->sendSignupNotification(
            $result['user'],
            $result['onboarding_state'],
        );

        return $result['user'];
    }

    public function redirectPath(AuthEmailOtpChallenge $challenge, User $user): string
    {
        $redirectTo = (string) ($challenge->payload['redirect_to'] ?? '');

        if ($redirectTo !== '' && str_starts_with($redirectTo, '/')) {
            return $redirectTo;
        }

        return route($user->postLoginRouteName(), absolute: false);
    }

    public function signupRedirectPath(): string
    {
        return route('student.dashboard', absolute: false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{challenge: AuthEmailOtpChallenge, token: string, otp_code: string}
     */
    private function createChallenge(User $user, string $context, array $payload = []): array
    {
        $token = Str::uuid()->toString();
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(15);

        AuthEmailOtpChallenge::query()
            ->where('email', $user->email)
            ->where('context', $context)
            ->delete();

        $challenge = AuthEmailOtpChallenge::query()->create([
            'user_id' => $user->id,
            'context' => $context,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make($otpCode),
            'payload' => $payload,
            'expires_at' => $expiresAt,
        ]);

        $this->sendEmail($user, $challenge, $otpCode, 15);

        return [
            'challenge' => $challenge,
            'token' => $token,
            'otp_code' => $otpCode,
        ];
    }

    private function sendEmail(User $user, AuthEmailOtpChallenge $challenge, string $otpCode, int $expiresInMinutes): void
    {
        $subject = $challenge->context === AuthEmailOtpChallenge::CONTEXT_SIGNUP
            ? 'Verify your YogaFX sign up'
            : 'Verify your YogaFX login';
        $title = $challenge->context === AuthEmailOtpChallenge::CONTEXT_SIGNUP
            ? 'Signup Verification'
            : 'Login Verification';
        $body = implode('', [
            '<p>Hi '.e($user->name ?: $user->email).',</p>',
            '<p>Please use the OTP code below to continue your YogaFX '.e($challenge->context).' flow.</p>',
            '<p><strong>OTP Code: '.e($otpCode).'</strong></p>',
            '<p>This code expires in '.e((string) $expiresInMinutes).' minutes.</p>',
            '<p>Return to the verification page that is already open in your browser to continue.</p>',
        ]);

        dispatch(function () use ($user, $subject, $body, $title): void {
            try {
                Mail::to($user->email)->send(
                    new TemplatedNotificationMail($subject, $body, $title),
                );
            } catch (Throwable $throwable) {
                report($throwable);
            }
        })->afterResponse();
    }

    private function guardChallenge(AuthEmailOtpChallenge $challenge, string $otpCode, string $expectedContext): void
    {
        if ($challenge->context !== $expectedContext || $challenge->isExpired() || $challenge->isUsed()) {
            throw ValidationException::withMessages([
                'otp_code' => ['This verification request is invalid or has expired. Please start again.'],
            ]);
        }

        if (! Hash::check($otpCode, $challenge->otp_hash)) {
            throw ValidationException::withMessages([
                'otp_code' => ['The OTP code is invalid. Please check the email that YogaFX sent you and try again.'],
            ]);
        }
    }
}
