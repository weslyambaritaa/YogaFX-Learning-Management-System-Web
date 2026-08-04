<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\User;
use App\Services\LinkControlSettingService;
use App\Services\SupportSettingService;
use App\Support\CountryDirectory;
use App\Support\Impersonation;
use App\Support\StudentProfileValue;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use BuildsProtectedMediaUrls;

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $linkControlSettingService = app(
            LinkControlSettingService::class,
        );

        $supportSettingService = app(
            SupportSettingService::class,
        );

        $linkControlSetting =
            $linkControlSettingService->current();

        $appDownloadPayload =
            $linkControlSettingService->publicPayload();

        /*
         * Pisahkan nomor WhatsApp hanya sekali agar tidak memanggil
         * CountryDirectory::splitPhoneNumber() berulang kali.
         */
        $whatsapp = $user
            ? CountryDirectory::splitPhoneNumber(
                $user->whatsapp,
                $user->country,
            )
            : null;

        $emergencyWhatsapp = $user
            ? CountryDirectory::splitPhoneNumber(
                $user->emergency_contact_whatsapp,
                $user->country,
            )
            : null;

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,

                    'is_active' =>
                        $user->isStudentAccountActive(),

                    'account_status' =>
                        $user->studentAccountStatus(),

                    'irregular_activity_count' => (int) (
                        $user->irregular_activity_count ?? 0
                    ),

                    'access_tier_id' =>
                        $user->access_tier_id,

                    'access_tier' => $user->accessTier ? [
                        'id' =>
                            $user->accessTier->id,

                        'name' =>
                            $user->accessTier->name,

                        'slug' =>
                            $user->accessTier->slug,

                        'description' =>
                            $user->accessTier->description,

                        'is_active' =>
                            $user->accessTier->is_active,

                        'has_full_standing_dialog_access' =>
                            $user
                                ->accessTier
                                ->has_full_standing_dialog_access,

                        'has_full_floor_dialog_access' =>
                            $user
                                ->accessTier
                                ->has_full_floor_dialog_access,
                    ] : null,

                    'email' =>
                        $user->email,

                    'first_name' =>
                        $user->first_name,

                    'last_name' =>
                        $user->last_name,

                    /*
                     * Student WhatsApp.
                     */
                    'whatsapp' =>
                        $user->whatsapp,

                    'whatsapp_country_code' =>
                        $whatsapp['country_code'],

                    'whatsapp_number' =>
                        $whatsapp['local_number'],

                    /*
                     * MasterClass emergency contact.
                     */
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

                    'profile_photo' =>
                        $this->protectedMediaUrl(
                            'user',
                            $user->id,
                            'profile_photo',
                            $user->profile_photo,
                            versionSeed: $user->updated_at,
                        ),

                    'profile_photo_path' =>
                        $user->profile_photo,

                    'instagram' =>
                        $user->instagram,

                    'country' =>
                        $user->country,

                    'birth_date' => optional(
                        $user->birth_date,
                    )->toDateString(),

                    'gender' =>
                        $user->gender,

                    /*
                     * MasterClass T-shirt and favourite song.
                     */
                    'tshirt_size' =>
                        $user->tshirt_size,

                    'favorite_song' =>
                        $user->favorite_song,

                    /*
                     * MasterClass medical history.
                     */
                    'has_medical_issues' =>
                        $user->has_medical_issues,

                    'medical_issues_details' =>
                        $user->medical_issues_details,

                    'is_taking_medication' =>
                        $user->is_taking_medication,

                    'medication_details' =>
                        $user->medication_details,

                    'practicing_yoga_for' =>
                        StudentProfileValue::
                            normalizePracticingYogaFor(
                                $user->practicing_yoga_for,
                            ),

                    'yoga_sequence_experience' =>
                        StudentProfileValue::
                            normalizeYogaSequenceExperience(
                                $user->yoga_sequence_experience,
                            ),

                    'hours_per_week' =>
                        StudentProfileValue::
                            normalizeHoursPerWeek(
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
                        StudentProfileValue::
                            normalizeHowDidYouFindUs(
                                $user->how_did_you_find_us,
                            ),

                    'profile_is_complete' =>
                        $user->hasCompletedStudentProfile(),

                    'missing_profile_fields' =>
                        $user->missingStudentProfileFields(),
                ] : null,
            ],

            'impersonation' => Impersonation::active() ? [
                'active' => true,

                'admin_name' => optional(
                    User::find(Impersonation::adminId()),
                )->name,

                'student_name' =>
                    $user?->name,
            ] : [
                'active' => false,
            ],

            'flash' => [
                'success' => fn () =>
                    $request
                        ->session()
                        ->get('success'),

                'error' => fn () =>
                    $request
                        ->session()
                        ->get('error'),

                'status' => fn () =>
                    $request
                        ->session()
                        ->get('status'),
            ],

            'directory' => [
                'countries' =>
                    CountryDirectory::countryOptions(),

                'phone_country_codes' =>
                    CountryDirectory::
                        phoneCountryCodeOptions(),
            ],

            'supportContact' =>
                $supportSettingService->publicPayload(),

            'appDownload' => [
                ...$appDownloadPayload,

                'qr_image_url' =>
                    $appDownloadPayload['has_any_link']
                        ? $this->protectedMediaUrl(
                            'link-control-setting',
                            $linkControlSetting->id,
                            'qr_image',
                            $linkControlSetting->qr_image,
                            versionSeed:
                                $linkControlSetting->updated_at,
                        )
                        : null,
            ],
        ];
    }
}