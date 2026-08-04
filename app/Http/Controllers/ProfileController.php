<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Models\AccessTier;
use App\Http\Requests\ProfileUpdateRequest;
use App\Support\StudentProfileValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use HandlesLocalUploads;

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $currentLevel = (int) ($user?->accessTier?->level ?? 0);

        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
            'upgradeOptions' => AccessTier::query()
                ->where('is_active', true)
                ->where('level', '>', $currentLevel)
                ->orderBy('level')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'description' => $accessTier->description,
                    'price' => (float) $accessTier->price,
                    'currency_code' => $accessTier->currency_code,
                    'level' => $accessTier->level,
                    'upgrade_url' => route('student.upgrades.show', $accessTier),
                ]),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (app()->environment(['local', 'development'])) {
            logger()->info('Profile update validated debug', [
                'user_id' => $user?->id,
                'validated_gender' => $validated['gender'] ?? null,
                'validated_hours_per_week' => $validated['hours_per_week'] ?? null,
                'gender_before' => $user?->getOriginal('gender'),
            ]);
        }

        unset(
    $validated['profile_photo'],
    $validated['whatsapp_country_code'],
    $validated['whatsapp_number'],
    $validated['emergency_contact_country_code'],
    $validated['emergency_contact_number'],
);
        $validated['yoga_sequence_experience'] = StudentProfileValue::encodeMultiSelect($validated['yoga_sequence_experience'] ?? null);
        $validated['how_did_you_find_us'] = StudentProfileValue::encodeMultiSelect($validated['how_did_you_find_us'] ?? null);

        $user->fill($validated);
        $user->birth_date = $validated['birth_date'] ?? $request->input('birth_date') ?? $user->birth_date;
        $user->syncDisplayName();

        $user->profile_photo = $this->storeUploadedFileToBunny(
            $request->file('profile_photo'),
            'users/profile-photos',
            $user->profile_photo,
        );

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (app()->environment(['local', 'development'])) {
            logger()->info('Profile update saved debug', [
                'user_id' => $user->id,
                'gender_after' => $user->fresh()->gender,
                'hours_per_week_after' => $user->fresh()->hours_per_week,
            ]);
        }

        return Redirect::route('profile.edit')
            ->with('status', 'profile-updated')
            ->with('success', 'Profile updated successfully.');
    }
}
