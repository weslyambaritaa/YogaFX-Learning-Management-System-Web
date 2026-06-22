<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Support\CountryDirectory;
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_active' => $user->isStudentAccountActive(),
                    'access_tier_id' => $user->access_tier_id,
                    'access_tier' => $user->accessTier ? [
                        'id' => $user->accessTier->id,
                        'name' => $user->accessTier->name,
                        'slug' => $user->accessTier->slug,
                        'is_active' => $user->accessTier->is_active,
                    ] : null,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'whatsapp' => $user->whatsapp,
                    'whatsapp_country_code' => CountryDirectory::splitPhoneNumber($user->whatsapp, $user->country)['country_code'],
                    'whatsapp_number' => CountryDirectory::splitPhoneNumber($user->whatsapp, $user->country)['local_number'],
                    'profile_photo' => $this->protectedMediaUrl(
                        'user',
                        $user->id,
                        'profile_photo',
                        $user->profile_photo,
                        versionSeed: $user->updated_at,
                    ),
                    'profile_photo_path' => $user->profile_photo,
                    'instagram' => $user->instagram,
                    'country' => $user->country,
                    'birth_date' => optional($user->birth_date)->toDateString(),
                    'gender' => $user->gender,
                    'practicing_yoga_for' => StudentProfileValue::normalizePracticingYogaFor($user->practicing_yoga_for),
                    'yoga_sequence_experience' => StudentProfileValue::normalizeYogaSequenceExperience($user->yoga_sequence_experience),
                    'hours_per_week' => StudentProfileValue::normalizeHoursPerWeek($user->hours_per_week),
                    'current_fitness_level' => $user->current_fitness_level,
                    'flexibility_rating' => $user->flexibility_rating,
                    'motivation' => $user->motivation,
                    'why_yogafx' => $user->why_yogafx,
                    'how_did_you_find_us' => StudentProfileValue::normalizeHowDidYouFindUs($user->how_did_you_find_us),
                    'profile_is_complete' => $user->hasCompletedStudentProfile(),
                    'missing_profile_fields' => $user->missingStudentProfileFields(),
                ] : null,
            ],
            'directory' => [
                'countries' => CountryDirectory::countryOptions(),
                'phone_country_codes' => CountryDirectory::phoneCountryCodeOptions(),
            ],
        ];
    }
}
