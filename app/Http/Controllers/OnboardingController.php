<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Requests\EnrollmentUpdateRequest;
use App\Http\Requests\SignupCompletionRequest;
use App\Models\OnboardingState;
use App\Services\EmailNotificationService;
use App\Services\PaymentCheckoutService;
use App\Support\CountryDirectory;
use App\Support\StudentProfileValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
        private readonly EmailNotificationService $emailNotifications,
    ) {}

    public function showPaymentSuccess(
        OnboardingState $onboardingState,
    ): Response|RedirectResponse {
        $onboardingState->loadMissing(
            'user',
            'pendingRegistration.accessTier',
            'pendingRegistration.invoices',
        );

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_COMPLETED
        ) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your YogaFX account is ready. Please sign in.',
                );
        }

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_AWAITING_SIGNUP
        ) {
            return redirect()->away(
                $this->paymentFlow->enrollmentSuccessUrl(
                    $onboardingState,
                ),
            );
        }

        $latestInvoice = $onboardingState
            ->pendingRegistration
            ->invoices()
            ->latest('id')
            ->first();

        $packagePaymentType =
            $latestInvoice?->package_payment_type
            ?? $latestInvoice?->package?->normalizedPaymentType()
            ?? 'paid';

        [$title, $eyebrow, $heading, $message] = match (
            $packagePaymentType
        ) {
            'free' => [
                'Registration Ready',
                'Free Access Ready',
                'Your free access is ready.',
                'Continue to enrollment to complete your YogaFX account.',
            ],

            'donation' => [
                'Donation Received',
                'Donation Approved',
                'Thank you for your donation. Your payment was received.',
                'Continue to enrollment to complete your YogaFX account.',
            ],

            default => [
                'Payment Success',
                'Payment Approved',
                'Your payment was received.',
                'Continue to enrollment to complete your YogaFX account.',
            ],
        };

        return Inertia::render('Public/PaymentSuccess', [
            'onboarding' => [
                'id' => $onboardingState->id,
                'status' => $onboardingState->status,

                'continue_url' =>
                    $this->paymentFlow->enrollmentUrl(
                        $onboardingState,
                    ),

                'title' => $title,
                'eyebrow' => $eyebrow,
                'heading' => $heading,
                'message' => $message,

                'access_tier' => [
                    'name' => $onboardingState
                        ->pendingRegistration
                        ->accessTier
                        ->name,

                    'slug' => $onboardingState
                        ->pendingRegistration
                        ->accessTier
                        ->slug,
                ],
            ],

            'student' => [
                'name' => $onboardingState->user->name,
                'email' => $onboardingState->user->email,
            ],
        ]);
    }

    public function showEnrollment(
        OnboardingState $onboardingState,
    ): Response|RedirectResponse {
        $onboardingState->loadMissing(
            'user',
            'pendingRegistration.accessTier',
        );

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_AWAITING_SIGNUP
        ) {
            return redirect()->away(
                $this->paymentFlow->enrollmentSuccessUrl(
                    $onboardingState,
                ),
            );
        }

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_COMPLETED
        ) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your YogaFX account is ready. Please sign in.',
                );
        }

        $user = $onboardingState->user;

        $whatsapp = CountryDirectory::splitPhoneNumber(
            $user->whatsapp,
            $user->country,
        );

        $emergencyWhatsapp = CountryDirectory::splitPhoneNumber(
            $user->emergency_contact_whatsapp,
            $user->country,
        );

        return Inertia::render('Public/Enrollment', [
            'onboarding' => [
                'id' => $onboardingState->id,
                'status' => $onboardingState->status,

                'submit_url' =>
                    $this->paymentFlow->enrollmentSubmitUrl(
                        $onboardingState,
                    ),

                'access_tier' => [
                    'name' => $onboardingState
                        ->pendingRegistration
                        ->accessTier
                        ->name,

                    'slug' => $onboardingState
                        ->pendingRegistration
                        ->accessTier
                        ->slug,
                ],
            ],

            'student' => [
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,

                'whatsapp' => $user->whatsapp,

                'whatsapp_country_code' =>
                    $whatsapp['country_code'],

                'whatsapp_number' =>
                    $whatsapp['local_number'],

                'emergency_contact_name' =>
                    $user->emergency_contact_name,

                'emergency_contact_relationship' =>
                    $user->emergency_contact_relationship,

                'emergency_contact_whatsapp' =>
                    $user->emergency_contact_whatsapp,

                'emergency_contact_country_code' =>
                    $emergencyWhatsapp['country_code'],

                'emergency_contact_number' =>
                    $emergencyWhatsapp['local_number'],

                'profile_photo' => $user->profile_photo,

                'profile_photo_url' => $this->protectedMediaUrl(
                    'user',
                    $user->id,
                    'profile_photo',
                    $user->profile_photo,
                    versionSeed: $user->updated_at,
                ),

                'instagram' => $user->instagram,
                'country' => $user->country,

                'birth_date' => optional(
                    $user->birth_date,
                )->toDateString(),

                'gender' => $user->gender,

                'tshirt_size' => $user->tshirt_size,
                'favorite_song' => $user->favorite_song,

                'has_medical_issues' =>
                    $user->has_medical_issues,

                'medical_issues_details' =>
                    $user->medical_issues_details,

                'is_taking_medication' =>
                    $user->is_taking_medication,

                'medication_details' =>
                    $user->medication_details,

                'practicing_yoga_for' =>
                    StudentProfileValue::normalizePracticingYogaFor(
                        $user->practicing_yoga_for,
                    ),

                'yoga_sequence_experience' =>
                    StudentProfileValue::normalizeYogaSequenceExperience(
                        $user->yoga_sequence_experience,
                    ),

                'hours_per_week' =>
                    StudentProfileValue::normalizeHoursPerWeek(
                        $user->hours_per_week,
                    ),

                'current_fitness_level' =>
                    $user->current_fitness_level,

                'flexibility_rating' =>
                    $user->flexibility_rating,

                'motivation' =>
                    $user->motivation,

                'why_yogafx' =>
                    $user->why_yogafx,

                'how_did_you_find_us' =>
                    StudentProfileValue::normalizeHowDidYouFindUs(
                        $user->how_did_you_find_us,
                    ),
            ],
        ]);
    }

    public function storeEnrollment(
    EnrollmentUpdateRequest $request,
    OnboardingState $onboardingState,
): RedirectResponse {
    $onboardingState->refresh();

    /*
     * Membuat endpoint enrollment aman ketika request
     * sebelumnya sebenarnya sudah berhasil.
     */
    if (
        $onboardingState->status ===
        OnboardingState::STATUS_AWAITING_SIGNUP
    ) {
        return redirect()->away(
            $this->paymentFlow->enrollmentSuccessUrl(
                $onboardingState,
            ),
        );
    }

    if (
        $onboardingState->status ===
        OnboardingState::STATUS_COMPLETED
    ) {
        return redirect()
            ->route('login')
            ->with(
                'status',
                'Your YogaFX account is ready. Please sign in.',
            );
    }

    abort_unless(
        $onboardingState->status ===
            OnboardingState::STATUS_AWAITING_ENROLLMENT,
        409,
        'Enrollment is no longer available for this onboarding flow.',
    );

    $validated = $request->validated();

    unset(
        $validated['profile_photo'],
        $validated['whatsapp_country_code'],
        $validated['whatsapp_number'],
        $validated['emergency_contact_country_code'],
        $validated['emergency_contact_number'],
    );

    $validated['yoga_sequence_experience'] =
        StudentProfileValue::encodeMultiSelect(
            $validated['yoga_sequence_experience']
                ?? null,
        );

    $validated['how_did_you_find_us'] =
        StudentProfileValue::encodeMultiSelect(
            $validated['how_did_you_find_us']
                ?? null,
        );

    $user = $onboardingState->user;

    $validated['birth_date'] =
        $validated['birth_date']
        ?? $request->input('birth_date');

    $validated['profile_photo'] =
        $this->storeUploadedFileToBunnyWithLocalFallback(
            $request->file('profile_photo'),
            'users/profile-photos',
            $user->profile_photo,
        );

    $onboardingState =
        $this->paymentFlow->completeEnrollment(
            $onboardingState,
            $validated,
        );

    /*
     * Kegagalan email tidak boleh membatalkan alur
     * enrollment yang sudah berhasil disimpan.
     */
    try {
        $this->emailNotifications
            ->sendEnrollmentSuccessNotification(
                $onboardingState,
            );
    } catch (\Throwable $exception) {
        report($exception);
    }

    return redirect()->away(
        $this->paymentFlow->enrollmentSuccessUrl(
            $onboardingState,
        ),
    );
}

    public function showEnrollmentSuccess(
        OnboardingState $onboardingState,
    ): Response|RedirectResponse {
        $onboardingState->loadMissing(
            'user',
            'pendingRegistration.accessTier',
            'pendingRegistration.package',
        );

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_AWAITING_ENROLLMENT
        ) {
            return redirect()->away(
                $this->paymentFlow->enrollmentUrl(
                    $onboardingState,
                ),
            );
        }

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_COMPLETED
        ) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your YogaFX account is ready. Please sign in.',
                );
        }

        return Inertia::render(
            'Public/EnrollmentSuccess',
            [
                'onboarding' => [
                    'id' => $onboardingState->id,
                    'status' => $onboardingState->status,

                    'continue_url' =>
                        $this->paymentFlow->signupUrl(
                            $onboardingState,
                        ),

                    'package' => [
                        'title' => $onboardingState
                            ->pendingRegistration
                            ->package
                            ?->title,

                        'slug' => $onboardingState
                            ->pendingRegistration
                            ->package
                            ?->slug,
                    ],

                    'access_tier' => [
                        'name' => $onboardingState
                            ->pendingRegistration
                            ->accessTier
                            ->name,

                        'slug' => $onboardingState
                            ->pendingRegistration
                            ->accessTier
                            ->slug,
                    ],
                ],

                'student' => [
                    'name' => $onboardingState->user->name,
                    'email' => $onboardingState->user->email,
                ],
            ],
        );
    }

    public function showSignup(
        OnboardingState $onboardingState,
    ): Response|RedirectResponse {
        $onboardingState->loadMissing(
            'user',
            'pendingRegistration.accessTier',
        );

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_AWAITING_ENROLLMENT
        ) {
            return redirect()->away(
                $this->paymentFlow->enrollmentUrl(
                    $onboardingState,
                ),
            );
        }

        if (
            $onboardingState->status ===
            OnboardingState::STATUS_COMPLETED
        ) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your YogaFX account is ready. Please sign in.',
                );
        }

        return Inertia::render('Public/Signup', [
            'onboarding' => [
                'id' => $onboardingState->id,
                'status' => $onboardingState->status,

                'submit_url' =>
                    $this->paymentFlow->signupSubmitUrl(
                        $onboardingState,
                    ),

                'access_tier' => [
                    'name' => $onboardingState
                        ->pendingRegistration
                        ->accessTier
                        ->name,

                    'slug' => $onboardingState
                        ->pendingRegistration
                        ->accessTier
                        ->slug,
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
        $user = $this->paymentFlow->completeSignup(
            $onboardingState,
            (string) $request->string('password'),
        );

        $this->emailNotifications->sendSignupNotification(
            $user,
            $onboardingState->fresh([
                'pendingRegistration',
                'user.accessTier',
            ]),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard');
    }
}