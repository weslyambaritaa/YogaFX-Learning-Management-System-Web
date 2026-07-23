<?php

namespace App\Services;

use App\Models\LinkControlSetting;
use App\Services\AppDownloadQrService;

class LinkControlSettingService
{
    public function __construct(
        private readonly AppDownloadQrService $appDownloadQrService,
    ) {}

    public function current(): LinkControlSetting
    {
        return LinkControlSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'qr_image' => null,
                'google_play_url' => null,
                'app_store_url' => null,
            ],
        );
    }

    /**
     * @return array{
     *     google_play_url: string|null,
     *     app_store_url: string|null,
     *     has_any_link: bool,
     *     download_page_url: string
     * }
     */
    public function publicPayload(): array
    {
        $setting = $this->current();
        $googlePlayUrl = $this->normalizeUrl($setting->google_play_url);
        $appStoreUrl = $this->normalizeUrl($setting->app_store_url);

        return [
            'google_play_url' => $googlePlayUrl,
            'app_store_url' => $appStoreUrl,
            'has_any_link' => filled($googlePlayUrl) || filled($appStoreUrl),
            'download_page_url' => $this->appDownloadQrService->publicUrl(),
        ];
    }

    private function normalizeUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
