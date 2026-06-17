<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DialogContent;
use Inertia\Inertia;
use Inertia\Response;

class DialogContentController extends Controller
{
    public function standing(): Response
    {
        return $this->renderDialogPage(
            DialogContent::KEY_FULL_STANDING,
            'Full Standing Series Dialogue',
        );
    }

    public function floor(): Response
    {
        return $this->renderDialogPage(
            DialogContent::KEY_FULL_FLOOR,
            'Full Floor Series Dialogue',
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
