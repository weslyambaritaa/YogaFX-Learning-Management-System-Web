<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
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
                    'profile_is_complete' => $user->hasCompletedStudentProfile(),
                ] : null,
            ],
        ];
    }
}
