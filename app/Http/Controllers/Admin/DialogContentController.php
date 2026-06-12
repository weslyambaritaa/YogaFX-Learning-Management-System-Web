<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DialogContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DialogContentController extends Controller
{
    public function edit(): Response
    {
        $dialogs = DialogContent::query()
            ->whereIn('key', [
                DialogContent::KEY_FULL_STANDING,
                DialogContent::KEY_FULL_FLOOR,
            ])
            ->get()
            ->keyBy('key');

        return Inertia::render('Admin/Dialogs/Edit', [
            'dialogs' => [
                'full_standing' => [
                    'title' => $dialogs->get(DialogContent::KEY_FULL_STANDING)?->title
                        ?? 'Full Standing Series Dialogue',
                    'content' => $dialogs->get(DialogContent::KEY_FULL_STANDING)?->content ?? '',
                ],
                'full_floor' => [
                    'title' => $dialogs->get(DialogContent::KEY_FULL_FLOOR)?->title
                        ?? 'Full Floor Series Dialogue',
                    'content' => $dialogs->get(DialogContent::KEY_FULL_FLOOR)?->content ?? '',
                ],
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_standing_title' => ['required', 'string', 'max:255'],
            'full_standing_content' => ['nullable', 'string'],
            'full_floor_title' => ['required', 'string', 'max:255'],
            'full_floor_content' => ['nullable', 'string'],
        ]);

        DialogContent::query()->updateOrCreate(
            ['key' => DialogContent::KEY_FULL_STANDING],
            [
                'title' => $validated['full_standing_title'],
                'content' => $validated['full_standing_content'] ?? '',
            ],
        );

        DialogContent::query()->updateOrCreate(
            ['key' => DialogContent::KEY_FULL_FLOOR],
            [
                'title' => $validated['full_floor_title'],
                'content' => $validated['full_floor_content'] ?? '',
            ],
        );

        return redirect()
            ->route('admin.dialogs.edit')
            ->with('status', 'dialog-content-saved');
    }
}
