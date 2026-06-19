<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('profile.password.change.edit', [
            'token' => $request->route('token'),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('profile.password.change.edit', [
            'token' => (string) $request->input('token'),
            'email' => (string) $request->input('email'),
        ]);
    }
}
