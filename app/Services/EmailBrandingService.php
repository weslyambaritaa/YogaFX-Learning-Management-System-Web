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
                'logo_html' => EmailBrandingDefaults::logoHtml(),
                'email_header_html' => EmailBrandingDefaults::headerHtml(),
                'email_signature_html' => EmailBrandingDefaults::footerHtml(),
                'pdf_header_html' => EmailBrandingDefaults::pdfHeaderHtml(),
                'pdf_footer_html' => EmailBrandingDefaults::pdfFooterHtml(),
                'watermark_html' => EmailBrandingDefaults::watermarkHtml(),
                'header_html' => EmailBrandingDefaults::headerHtml(),
                'footer_html' => EmailBrandingDefaults::footerHtml(),
            ],
        );
    }

    public function currentBrandingPayload(): array
    {
        $branding = $this->findOrCreateBranding();

        return [
            'logo_html' => $this->normalizeHtml(
                $branding->logo_html,
                $this->legacyLogoHtml($branding) ?? EmailBrandingDefaults::logoHtml(),
            ),
            'email_header_html' => $this->normalizeHtml(
                $branding->email_header_html ?: $branding->header_html,
                EmailBrandingDefaults::headerHtml(),
            ),
            'email_signature_html' => $this->normalizeHtml(
                $branding->email_signature_html ?: $branding->footer_html,
                EmailBrandingDefaults::footerHtml(),
            ),
            'pdf_header_html' => $this->normalizeHtml(
                $branding->pdf_header_html,
                EmailBrandingDefaults::pdfHeaderHtml(),
            ),
            'pdf_footer_html' => $this->normalizeOptionalHtml(
                $branding->pdf_footer_html,
            ),
            'watermark_html' => $this->normalizeHtml(
                $branding->watermark_html,
                EmailBrandingDefaults::watermarkHtml(),
            ),
            'app_name' => (string) config('app.name', 'YogaFX LMS'),
        ];
    }

    public function currentPdfBrandingPayload(): array
    {
        $branding = $this->findOrCreateBranding();

        return [
            'logo_html' => $this->logoEditorHtml($branding),
            'pdf_header_html' => $this->normalizeHtml(
                $branding->pdf_header_html,
                EmailBrandingDefaults::pdfHeaderHtml(),
            ),
            'pdf_footer_html' => $this->normalizeOptionalHtml(
                $branding->pdf_footer_html,
            ),
            'watermark_html' => $this->normalizeHtml(
                $branding->watermark_html,
                EmailBrandingDefaults::watermarkHtml(),
            ),
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

    public function logoEditorHtml(?EmailBranding $branding = null): string
    {
        $branding ??= $this->findOrCreateBranding();

        return $this->normalizeHtml(
            $branding->logo_html,
            $this->legacyLogoHtml($branding) ?? EmailBrandingDefaults::logoHtml(),
        );
    }

    public function emailHeaderEditorHtml(?EmailBranding $branding = null): string
    {
        $branding ??= $this->findOrCreateBranding();

        return $this->normalizeHtml(
            $branding->email_header_html ?: $branding->header_html,
            EmailBrandingDefaults::headerHtml(),
        );
    }

    public function emailSignatureEditorHtml(?EmailBranding $branding = null): string
    {
        $branding ??= $this->findOrCreateBranding();

        return $this->normalizeHtml(
            $branding->email_signature_html ?: $branding->footer_html,
            EmailBrandingDefaults::footerHtml(),
        );
    }

    public function pdfHeaderEditorHtml(?EmailBranding $branding = null): string
    {
        $branding ??= $this->findOrCreateBranding();

        return $this->normalizeHtml(
            $branding->pdf_header_html,
            EmailBrandingDefaults::pdfHeaderHtml(),
        );
    }

    public function watermarkEditorHtml(?EmailBranding $branding = null): string
    {
        $branding ??= $this->findOrCreateBranding();

        return $this->normalizeHtml(
            $branding->watermark_html,
            EmailBrandingDefaults::watermarkHtml(),
        );
    }

    public function pdfFooterEditorHtml(?EmailBranding $branding = null): string
    {
        $branding ??= $this->findOrCreateBranding();

        return $this->normalizeOptionalHtml($branding->pdf_footer_html);
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

    private function normalizeOptionalHtml(?string $value): string
    {
        return trim((string) $value);
    }

    private function legacyLogoHtml(EmailBranding $branding): ?string
    {
        $logoUrl = $this->logoPreviewUrl($branding);

        if (! is_string($logoUrl) || $logoUrl === '') {
            return null;
        }

        $alt = e((string) config('app.name', 'YogaFX LMS'));

        return '<p style="margin: 0;"><img src="'.e($logoUrl).'" alt="'.$alt.'" style="display: block; max-width: 180px; width: auto; height: auto;"></p>';
    }
}
