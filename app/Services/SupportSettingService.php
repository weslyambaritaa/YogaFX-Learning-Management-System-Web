<?php

namespace App\Services;

use App\Models\SupportSetting;

class SupportSettingService
{
    public function current(): SupportSetting
    {
        return SupportSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'support_whatsapp' => null,
                'support_email' => null,
            ],
        );
    }

    /**
     * @return array{
     *     whatsapp: string|null,
     *     whatsapp_url: string|null,
     *     email: string|null,
     *     email_url: string|null
     * }
     */
    public function publicPayload(): array
    {
        $setting = $this->current();
        $whatsapp = $this->normalizeWhatsapp($setting->support_whatsapp);
        $email = filled($setting->support_email) ? trim((string) $setting->support_email) : null;

        return [
            'whatsapp' => $whatsapp,
            'whatsapp_url' => $whatsapp ? 'https://wa.me/'.$whatsapp : null,
            'email' => $email,
            'email_url' => $email ? 'mailto:'.$email : null,
        ];
    }

    private function normalizeWhatsapp(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $normalized !== '' ? $normalized : null;
    }
}
