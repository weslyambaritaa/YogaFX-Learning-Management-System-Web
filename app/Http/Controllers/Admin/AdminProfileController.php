<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Admin/Profile/Edit', [
            'status' => session('status'),
        ]);
    }

    public function update(AdminProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $validated = $request->safe()->only([
            'first_name',
            'last_name',
            'email',
            'password',
        ]);

        $user->fill($validated);
        $user->syncDisplayName();

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', 'admin-profile-updated');
    }
}
