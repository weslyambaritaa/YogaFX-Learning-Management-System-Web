<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Ebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EbookCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Student/Ebooks/Index', [
            'ebooks' => Ebook::query()
                ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user?->access_tier_id))
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Ebook $ebook) => [
                    'id' => $ebook->id,
                    'title' => $ebook->title,
                    'sort_order' => $ebook->sort_order,
                    'file_name' => basename((string) $ebook->file),
                    'preview_url' => route('ebooks.preview', $ebook),
                ]),
        ]);
    }

    public function preview(Request $request, Ebook $ebook): Response
    {
        $user = $request->user();

        abort_unless($user?->isStudent(), 403);
        abort_unless($user?->access_tier_id !== null, 403);
        abort_unless(
            $ebook->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        [$previewSupported, $mimeType] = $this->previewMetadata($ebook);

        return Inertia::render('Ebooks/Preview', [
            'ebook' => [
                'id' => $ebook->id,
                'title' => $ebook->title,
                'preview_url' => $this->protectedMediaUrl(
                    'ebook',
                    $ebook->id,
                    'file',
                    $ebook->file,
                    versionSeed: $ebook->updated_at,
                ),
                'download_url' => $this->protectedMediaUrl(
                    'ebook',
                    $ebook->id,
                    'file',
                    $ebook->file,
                    download: true,
                    versionSeed: $ebook->updated_at,
                ),
                'preview_supported' => $previewSupported,
                'preview_message' => $previewSupported
                    ? null
                    : 'This ebook file cannot be previewed in the browser yet. You can still download it.',
                'mime_type' => $mimeType,
                'file_name' => basename((string) $ebook->file),
            ],
            'backUrl' => route('ebooks.index'),
            'backLabel' => 'Back to Ebooks',
        ]);
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function previewMetadata(Ebook $ebook): array
    {
        $mimeType = $ebook->file
            ? Storage::disk('local')->mimeType($ebook->file)
            : null;
        $isPdf = str($ebook->file)->lower()->endsWith('.pdf')
            || $mimeType === 'application/pdf';

        return [$isPdf, $mimeType];
    }
}
