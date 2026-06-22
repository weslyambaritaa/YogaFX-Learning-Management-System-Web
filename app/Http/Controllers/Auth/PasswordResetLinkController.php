<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

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

        return back()->with('status', __('We have emailed your password reset link.'));
    }

    private function publicRoute(string $routeName, array $parameters = []): string
    {
        $relativePath = URL::route($routeName, $parameters, false);

        return rtrim((string) config('app.public_url', config('app.url')), '/').$relativePath;
    }
}
