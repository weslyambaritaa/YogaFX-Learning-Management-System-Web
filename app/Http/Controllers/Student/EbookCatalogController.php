<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EbookCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Student/Ebooks/Index', [
            'ebooks' => Ebook::query()
                ->where('access_tier_id', $user?->access_tier_id)
                ->orderBy('title')
                ->get()
                ->map(fn (Ebook $ebook) => [
                    'id' => $ebook->id,
                    'title' => $ebook->title,
                    'file_url' => route('media.show', [
                        'entity' => 'ebook',
                        'id' => $ebook->id,
                        'field' => 'file',
                        'download' => 1,
                    ]),
                ]),
        ]);
    }
}
