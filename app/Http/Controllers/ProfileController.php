<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
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
        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
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
