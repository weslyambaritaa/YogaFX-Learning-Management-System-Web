<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Models\AccessTier;
use App\Http\Requests\ProfileUpdateRequest;
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
        unset($validated['profile_photo'], $validated['whatsapp_country_code'], $validated['whatsapp_number']);

        $user->fill($validated);
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

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
