<?php

namespace App\Support;

use App\Models\AccessTier;
use App\Models\Package;
use App\Services\BunnyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicPageMeta
{
    private const DEFAULT_SITE_NAME = 'YogaFX';
    private const DEFAULT_DESCRIPTION = 'YogaFX adalah platform pembelajaran premium dengan pengalaman belajar yoga yang tenang, terpandu, dan content-first.';
    private const DEFAULT_TYPE = 'website';
    private const DEFAULT_TWITTER_CARD = 'summary_large_image';

    public function defaults(Request $request, array $overrides = []): array
    {
        return array_merge([
            'title' => self::DEFAULT_SITE_NAME,
            'description' => self::DEFAULT_DESCRIPTION,
            'image' => $this->defaultImageUrl(),
            'url' => PublicUrl::current($request),
            'type' => self::DEFAULT_TYPE,
            'site_name' => self::DEFAULT_SITE_NAME,
            'twitter_card' => self::DEFAULT_TWITTER_CARD,
        ], $overrides);
    }

    public function forPackage(Request $request, Package $package): array
    {
        $tierName = $package->accessTier?->name;
        $description = $this->normalizeDescription(
            $package->description
            ?: ($tierName ? sprintf('Join %s di YogaFX dan lanjutkan perjalanan belajar yoga Anda dengan alur yang tenang dan terpandu.', $tierName) : null)
        );

        return $this->defaults($request, [
            'title' => trim($package->title.' | '.self::DEFAULT_SITE_NAME),
            'description' => $description,
            'image' => $this->resolvePackageImageUrl($package) ?? $this->defaultImageUrl(),
            'url' => PublicUrl::current($request),
        ]);
    }

    public function forScoreboard(Request $request): array
    {
        return $this->defaults($request, [
            'title' => self::DEFAULT_SITE_NAME.' | Premium Yoga Learning Platform',
            'description' => self::DEFAULT_DESCRIPTION,
        ]);
    }

    public function defaultImageUrl(): string
    {
        return PublicUrl::fromRelativePath('social-preview-default.svg');
    }

    private function resolvePackageImageUrl(Package $package): ?string
    {
        $imageUrl = $this->publicAssetUrl(
            entity: 'package',
            id: $package->id,
            field: 'image',
            path: $package->image,
            versionSeed: $package->updated_at,
        );

        if ($imageUrl) {
            return $imageUrl;
        }

        if ($package->accessTier instanceof AccessTier) {
            return $this->publicAssetUrl(
                entity: 'access-tier',
                id: $package->accessTier->id,
                field: 'thumbnail',
                path: $package->accessTier->thumbnail,
                versionSeed: $package->accessTier->updated_at,
            );
        }

        return null;
    }

    private function publicAssetUrl(
        string $entity,
        int $id,
        string $field,
        ?string $path,
        mixed $versionSeed = null,
    ): ?string {
        if (! filled($path)) {
            return null;
        }

        if (BunnyAssetPath::isBunnyPath($path)) {
            return app(BunnyStorageService::class)->url($path);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $relativeUrl = route('public-media.show', [
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'v' => sha1(implode('|', [
                $entity,
                $id,
                $field,
                (string) $path,
                Storage::disk('local')->exists($path)
                    ? (string) Storage::disk('local')->lastModified($path)
                    : '',
                (string) $versionSeed,
            ])),
        ], false);

        return PublicUrl::fromUri($relativeUrl);
    }

    private function normalizeDescription(?string $description): string
    {
        $plainText = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $description)));

        if ($plainText === '') {
            return self::DEFAULT_DESCRIPTION;
        }

        return mb_strimwidth($plainText, 0, 200, '...');
    }
}
