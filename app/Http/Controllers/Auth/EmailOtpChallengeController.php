<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthEmailOtpChallenge;
use App\Services\EmailOtpChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailOtpChallengeController extends Controller
{
    public function __construct(
        private readonly EmailOtpChallengeService $otpChallenges,
    ) {}

    public function show(string $token): Response|RedirectResponse
    {
        $challenge = $this->otpChallenges->resolve($token);

        if (! $challenge || $challenge->isExpired() || $challenge->isUsed()) {
            return redirect()->route('login')->with('status', 'This email verification request is no longer valid. Please start again.');
        }

        return Inertia::render('Auth/EmailOtpVerify', [
            'token' => $token,
            'context' => $challenge->context,
            'email' => $challenge->email,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
        ]);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $challenge = $this->otpChallenges->resolve($token);

        abort_if(! $challenge, 404);

        $validated = $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ]);

        if ($challenge->context === AuthEmailOtpChallenge::CONTEXT_SIGNUP) {
            $this->otpChallenges->verifySignupChallenge($challenge, $validated['otp_code'], $request);

            return redirect()->to($this->otpChallenges->signupRedirectPath());
        }

        $user = $this->otpChallenges->verifyLoginChallenge($challenge, $validated['otp_code'], $request);

        return redirect()->to($this->otpChallenges->redirectPath($challenge, $user));
    }
}
