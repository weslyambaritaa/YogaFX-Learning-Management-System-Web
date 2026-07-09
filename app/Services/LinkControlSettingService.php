<?php

namespace App\Services;

use App\Models\LinkControlSetting;

class LinkControlSettingService
{
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
     *     has_any_link: bool
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
