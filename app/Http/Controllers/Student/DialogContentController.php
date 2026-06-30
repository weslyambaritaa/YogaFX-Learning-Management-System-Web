<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DialogContent;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DialogContentController extends Controller
{
    public function standing(): Response
    {
        $this->authorizeDialogAccess(request()->user(), 'has_full_standing_dialog_access');

        return $this->renderDialogPage(
            DialogContent::KEY_FULL_STANDING,
            'Full Standing Series Dialogue',
        );
    }

    public function floor(): Response
    {
        $this->authorizeDialogAccess(request()->user(), 'has_full_floor_dialog_access');

        return $this->renderDialogPage(
            DialogContent::KEY_FULL_FLOOR,
            'Full Floor Series Dialogue',
        );
    }

    private function authorizeDialogAccess(?User $user, string $tierAttribute): void
    {
        abort_unless(
            $user?->isStudent()
            && $user->accessTier
            && (bool) $user->accessTier->{$tierAttribute},
            403,
        );
    }

    private function renderDialogPage(string $key, string $fallbackTitle): Response
    {
        $dialog = DialogContent::query()->where('key', $key)->first();

        return Inertia::render('Student/Dialogs/Show', [
            'dialog' => [
                'key' => $key,
                'title' => $dialog?->title ?? $fallbackTitle,
                'content' => $dialog?->content ?? '',
            ],
        ]);
    }
}
