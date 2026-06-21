<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\AuthEmailOtpChallenge;
use App\Http\Requests\Mobile\V1\LoginRequest;
use App\Http\Resources\Mobile\V1\CurrentStudentResource;
use App\Models\User;
use App\Services\EmailOtpChallengeService;
use App\Services\StudentSessionTrackingService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly EmailOtpChallengeService $emailOtpChallengeService,
        private readonly StudentSessionTrackingService $studentSessionTrackingService,
    ) {}

    public function store(LoginRequest $request)
    {
        $user = $request->authenticateStudent();

        if (! $user->isStudent()) {
            return MobileApiResponse::error(
                'This mobile API is only available to student accounts.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (! $user->isStudentAccountActive()) {
            return MobileApiResponse::error(
                'Your student account is inactive.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $otpChallenge = $this->emailOtpChallengeService->createForLogin($user, [
            'device_name' => $request->deviceName(),
        ]);

        return MobileApiResponse::success([
            'otp_required' => true,
            'challenge_token' => $otpChallenge['token'],
            'email' => $user->email,
            'expires_at' => $otpChallenge['challenge']->expires_at?->toIso8601String(),
        ], 'OTP code sent to your email.', Response::HTTP_OK);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $challenge = $this->emailOtpChallengeService->resolve($validated['challenge_token']);

        if (! $challenge || $challenge->context !== AuthEmailOtpChallenge::CONTEXT_LOGIN) {
            throw ValidationException::withMessages([
                'challenge_token' => ['This verification request is invalid or has expired. Please start again.'],
            ]);
        }

        $user = $challenge->user()->firstOrFail();

        if (! $user->isStudent()) {
            return MobileApiResponse::error(
                'This mobile API is only available to student accounts.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (! $user->isStudentAccountActive()) {
            return MobileApiResponse::error(
                'Your student account is inactive.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $user = $this->emailOtpChallengeService->consumeLoginChallenge($challenge, $validated['otp_code']);

        return $this->issueMobileTokenResponse(
            $user->fresh(['accessTier']),
            (string) (($challenge->payload ?? [])['device_name'] ?? 'mobile-app'),
        );
    }

    public function resendOtp(Request $request)
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
        ]);

        $challenge = $this->emailOtpChallengeService->resolve($validated['challenge_token']);

        if (! $challenge || $challenge->context !== AuthEmailOtpChallenge::CONTEXT_LOGIN || $challenge->isUsed()) {
            throw ValidationException::withMessages([
                'challenge_token' => ['This verification request is invalid or has expired. Please start again.'],
            ]);
        }

        $this->ensureResendIsNotRateLimited($request, $challenge);

        $user = $challenge->user()->firstOrFail();

        if (! $user->isStudent()) {
            return MobileApiResponse::error(
                'This mobile API is only available to student accounts.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (! $user->isStudentAccountActive()) {
            return MobileApiResponse::error(
                'Your student account is inactive.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $otpChallenge = $this->emailOtpChallengeService->createForLogin($user, [
            'device_name' => (string) (($challenge->payload ?? [])['device_name'] ?? 'mobile-app'),
        ]);

        return MobileApiResponse::success([
            'otp_required' => true,
            'challenge_token' => $otpChallenge['token'],
            'email' => $user->email,
            'expires_at' => $otpChallenge['challenge']->expires_at?->toIso8601String(),
        ], 'OTP code sent to your email.', Response::HTTP_OK);
    }

    private function issueMobileTokenResponse(User $user, string $deviceName)
    {
        $token = $user->createToken($deviceName);

        $this->studentSessionTrackingService->startMobileSession($user, $token->accessToken->id);

        return MobileApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new CurrentStudentResource($user->load('accessTier')),
        ], 'Login successful.', Response::HTTP_OK);
    }

    private function ensureResendIsNotRateLimited(Request $request, AuthEmailOtpChallenge $challenge): void
    {
        $key = sprintf(
            'mobile-login-otp-resend:%s|%s',
            strtolower($challenge->email),
            $request->ip(),
        );

        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 60);

            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'challenge_token' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }

    public function destroy(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $this->studentSessionTrackingService->endMobileSession($request, $user);
        $request->user()?->currentAccessToken()?->delete();

        return MobileApiResponse::success([
            'user_id' => $user->id,
        ], 'Logout successful.');
    }
}
