<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentUpdateRequest;
use App\Http\Requests\SignupCompletionRequest;
use App\Models\OnboardingState;
use App\Services\SimulatedPaymentFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly SimulatedPaymentFlowService $paymentFlow,
    ) {}

    public function showEnrollment(OnboardingState $onboardingState): Response|RedirectResponse
    {
        $onboardingState->loadMissing('user', 'pendingRegistration.accessTier');

        if ($onboardingState->status === OnboardingState::STATUS_AWAITING_SIGNUP) {
            return redirect()->away($this->paymentFlow->signupUrl($onboardingState));
        }

        if ($onboardingState->status === OnboardingState::STATUS_COMPLETED) {
            return redirect()->route('login')->with('status', 'Your YogaFX account is ready. Please sign in.');
        }

        $user = $onboardingState->user;

        return Inertia::render('Public/Enrollment', [
            'onboarding' => [
                'id' => $onboardingState->id,
                'status' => $onboardingState->status,
                'submit_url' => $this->paymentFlow->enrollmentSubmitUrl($onboardingState),
                'access_tier' => [
                    'name' => $onboardingState->pendingRegistration->accessTier->name,
                    'slug' => $onboardingState->pendingRegistration->accessTier->slug,
                ],
            ],
            'student' => [
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'whatsapp' => $user->whatsapp,
                'preferred_certificate_picture' => $user->preferred_certificate_picture,
                'profile_photo' => $user->profile_photo,
                'instagram' => $user->instagram,
                'country' => $user->country,
                'birth_date' => optional($user->birth_date)->toDateString(),
                'gender' => $user->gender,
                'practicing_yoga_for' => $user->practicing_yoga_for,
                'yoga_sequence_experience' => $user->yoga_sequence_experience,
                'hours_per_week' => $user->hours_per_week,
                'current_fitness_level' => $user->current_fitness_level,
                'flexibility_rating' => $user->flexibility_rating,
                'motivation' => $user->motivation,
                'why_yogafx' => $user->why_yogafx,
                'how_did_you_find_us' => $user->how_did_you_find_us,
            ],
        ]);
    }

    public function storeEnrollment(
        EnrollmentUpdateRequest $request,
        OnboardingState $onboardingState,
    ): RedirectResponse {
        $onboardingState = $this->paymentFlow->completeEnrollment($onboardingState, $request->validated());

        return redirect()->away($this->paymentFlow->signupUrl($onboardingState));
    }

    public function showSignup(OnboardingState $onboardingState): Response|RedirectResponse
    {
        $onboardingState->loadMissing('user', 'pendingRegistration.accessTier');

        if ($onboardingState->status === OnboardingState::STATUS_AWAITING_ENROLLMENT) {
            return redirect()->away($this->paymentFlow->enrollmentUrl($onboardingState));
        }

        if ($onboardingState->status === OnboardingState::STATUS_COMPLETED) {
            return redirect()->route('login')->with('status', 'Your YogaFX account is ready. Please sign in.');
        }

        return Inertia::render('Public/Signup', [
            'onboarding' => [
                'id' => $onboardingState->id,
                'status' => $onboardingState->status,
                'submit_url' => $this->paymentFlow->signupSubmitUrl($onboardingState),
                'access_tier' => [
                    'name' => $onboardingState->pendingRegistration->accessTier->name,
                    'slug' => $onboardingState->pendingRegistration->accessTier->slug,
                ],
            ],
            'student' => [
                'name' => $onboardingState->user->name,
                'email' => $onboardingState->user->email,
            ],
        ]);
    }

    public function storeSignup(
        SignupCompletionRequest $request,
        OnboardingState $onboardingState,
    ): RedirectResponse {
        $user = $this->paymentFlow->completeSignup($onboardingState, (string) $request->string('password'));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard');
    }
}
