<?php

namespace App\Services\Mobile\V1;

use App\Models\Ebook;
use App\Models\User;
use App\Support\BunnyAssetPath;
use App\Support\MobileMediaPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class StudentEbookApiService
{
    /**
     * @return array<string, mixed>
     */
    public function listForUser(User $user): array
    {
        $ebooks = $this->ebooksForUser($user);

        return [
            'items' => $ebooks
                ->map(fn (Ebook $ebook) => $this->ebookPayload($user, $ebook, includeDetail: false))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detailForUser(User $user, Ebook $ebook): ?array
    {
        if (! $this->isAccessibleToUser($user, $ebook)) {
            return null;
        }

        return $this->ebookPayload($user, $ebook, includeDetail: true);
    }

    /**
     * @return Collection<int, Ebook>
     */
    private function ebooksForUser(User $user): Collection
    {
        return Ebook::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    private function isAccessibleToUser(User $user, Ebook $ebook): bool
    {
        if (! $user->isStudent() || $user->access_tier_id === null) {
            return false;
        }

        return $ebook->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function ebookPayload(User $user, Ebook $ebook, bool $includeDetail): array
    {
        [$previewSupported, $mimeType] = $this->previewMetadata($ebook);
        $openUrl = $this->signedEbookRoute('mobile.api.v1.ebooks.media.open', $user, $ebook);
        $downloadUrl = $this->signedEbookRoute('mobile.api.v1.ebooks.media.download', $user, $ebook);

        return [
            'id' => $ebook->id,
            'title' => $ebook->title,
            'sort_order' => $ebook->sort_order,
            'file_name' => basename((string) $ebook->file),
            'preview_url' => $previewSupported ? $openUrl : null,
            'download_url' => $downloadUrl,
            'file' => MobileMediaPayload::file(
                openUrl: $openUrl,
                downloadUrl: $downloadUrl,
                previewUrl: $previewSupported ? $openUrl : null,
                fileName: basename((string) $ebook->file),
                mimeType: $mimeType,
                previewSupported: $previewSupported,
                previewMessage: $previewSupported
                    ? null
                    : 'This ebook file cannot be previewed in the browser yet. You can still download it.',
                isAvailable: filled($ebook->file),
            ),
            'preview_supported' => $previewSupported,
            'preview_message' => $previewSupported
                ? null
                : 'This ebook file cannot be previewed in the browser yet. You can still download it.',
            'mime_type' => $mimeType,
            'detail_ready' => $includeDetail,
        ];
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function previewMetadata(Ebook $ebook): array
    {
        $mimeType = null;

        if ($ebook->file && ! BunnyAssetPath::isBunnyPath($ebook->file) && ! filter_var($ebook->file, FILTER_VALIDATE_URL) && Storage::disk('local')->exists($ebook->file)) {
            $mimeType = Storage::disk('local')->mimeType($ebook->file);
        }

        $isPdf = str($ebook->file)->lower()->endsWith('.pdf')
            || $mimeType === 'application/pdf';

        return [$isPdf, $mimeType];
    }

    private function signedEbookRoute(string $routeName, User $user, Ebook $ebook): string
    {
        return URL::temporarySignedRoute($routeName, now()->addHour(), [
            'ebook' => $ebook->id,
            'student' => $user->id,
        ]);
    }
}
