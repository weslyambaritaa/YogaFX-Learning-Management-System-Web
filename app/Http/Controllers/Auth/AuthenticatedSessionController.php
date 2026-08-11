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

        if (
            ! $user ||
            ! $user->hasRole(
                $user::ROLE_SUPER_ADMIN,
                $user::ROLE_ADMIN,
                $user::ROLE_STUDENT,
            )
        ) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This account is not authorized to access YogaFX LMS.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Student Welcome Flow
        |--------------------------------------------------------------------------
        |
        | Simpan dulu status apakah user merupakan student sebelum session
        | authentication sementara dihancurkan untuk masuk ke flow OTP.
        |
        | Flag show_welcome_popup baru akan dimasukkan kembali setelah
        | invalidate(), sehingga tidak ikut terhapus.
        |
        */

        $shouldShowWelcomePopup = $user->isStudent();

        $otpChallenge = $this->otpChallenges->createForLogin(
            $user,
            [
                'remember' => true,

                'redirect_to' => $request->session()->get(
                    'url.intended',
                    route(
                        $user->postLoginRouteName(),
                        absolute: false,
                    ),
                ),
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | End Temporary Password Authentication
        |--------------------------------------------------------------------------
        */

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /*
        |--------------------------------------------------------------------------
        | Restore Welcome Flag Into New OTP Session
        |--------------------------------------------------------------------------
        |
        | Normal student:
        | login -> OTP -> dashboard -> welcome popup muncul.
        |
        | HomeController menggunakan pull('show_welcome_popup'), sehingga:
        | refresh dashboard -> popup tidak muncul lagi.
        |
        | Setelah logout dan login kembali, flag dibuat lagi di sini.
        |
        | Tester tetap ditangani secara khusus oleh HomeController dan akan
        | selalu melihat welcome popup setiap dashboard dibuka/refresh.
        |
        */

        if ($shouldShowWelcomePopup) {
            $request->session()->put(
                'show_welcome_popup',
                true,
            );
        }

        return redirect()->route(
            'auth.otp.show',
            [
                'token' => $otpChallenge['token'],
            ],
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->sessionTrackingService->endStudentSession(
            $request,
            $request->user(),
        );

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}