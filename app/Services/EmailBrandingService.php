<?php

namespace App\Services;

use App\Models\EmailBranding;
use App\Support\EmailBrandingDefaults;
use Illuminate\Support\Facades\Storage;

class EmailBrandingService
{
    public function findOrCreateBranding(): EmailBranding
    {
        return EmailBranding::query()->firstOrCreate(
            ['singleton_key' => EmailBranding::GLOBAL_KEY],
            [
                'header_html' => EmailBrandingDefaults::headerHtml(),
                'footer_html' => EmailBrandingDefaults::footerHtml(),
            ],
        );
    }

    public function currentBrandingPayload(): array
    {
        $branding = $this->findOrCreateBranding();

        return [
            'logo_path' => $this->logoAbsolutePath($branding),
            'logo_url' => $this->logoPreviewUrl($branding),
            'header_html' => $this->normalizeHtml(
                $branding->header_html,
                EmailBrandingDefaults::headerHtml(),
            ),
            'footer_html' => $this->normalizeHtml(
                $branding->footer_html,
                EmailBrandingDefaults::footerHtml(),
            ),
            'app_name' => (string) config('app.name', 'YogaFX LMS'),
        ];
    }

    public function logoPreviewUrl(?EmailBranding $branding = null): ?string
    {
        $branding ??= $this->findOrCreateBranding();

        if (! filled($branding->logo_path)) {
            return null;
        }

        return route('media.show', [
            'entity' => 'email-branding',
            'id' => $branding->id,
            'field' => 'logo_path',
        ]);
    }

    private function logoAbsolutePath(EmailBranding $branding): ?string
    {
        $path = (string) ($branding->logo_path ?? '');

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    private function normalizeHtml(?string $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }
}
