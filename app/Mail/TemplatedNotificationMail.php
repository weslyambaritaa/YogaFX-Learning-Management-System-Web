<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class TemplatedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public string $variantLabel,
        public array $attachmentPayloads = [],
        public array $branding = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function build()
    {
        $initialHtml = $this->buildEmailHtml();
        $mail = $this->subject($this->subjectLine)
            ->html($initialHtml);

        foreach ($this->attachmentPayloads as $attachment) {
            if (isset($attachment['data'], $attachment['name'])) {
                $mail->attachData(
                    $attachment['data'],
                    $attachment['name'],
                    ['mime' => $attachment['mime'] ?? 'application/octet-stream'],
                );
            }
        }

        return $mail
            ->withSymfonyMessage(function (Email $message) use ($initialHtml): void {
                $message->html(
                    $this->htmlContainsEmbeddableImage($initialHtml)
                        ? $this->buildEmailHtml($message)
                        : $initialHtml,
                );
            });
    }

    public function previewHtml(): string
    {
        return $this->buildEmailHtml();
    }

    private function buildEmailHtml(?Email $message = null): string
    {
        $logoHtml = $this->buildLogoHtml();
        $headerHtml = $this->htmlFragment($this->branding['header_html'] ?? '');
        $footerHtml = $this->htmlFragment($this->branding['footer_html'] ?? '');
        $contentHtml = $this->htmlFragment($this->bodyHtml);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->escapedSubjectLine()}</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f8fafc; font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <div style="margin: 0 auto; max-width: 680px; overflow: hidden; border: 1px solid #e2e8f0; border-radius: 18px; background: #ffffff;">
        <div style="padding: 32px 32px 20px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);">
            {$logoHtml}
            {$headerHtml}
        </div>
        <div style="padding: 32px;">
            {$contentHtml}
        </div>
        <div style="padding: 20px 32px 28px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
            {$footerHtml}
        </div>
    </div>
</body>
</html>
HTML;

        if ($message !== null && $this->htmlContainsEmbeddableImage($html)) {
            $html = preg_replace_callback(
                '/<img\b([^>]*)\bsrc=(["\'])(.*?)\2([^>]*)>/i',
                function (array $matches) use ($message): string {
                    $src = $matches[3] ?? '';
                    $localPath = $this->resolveEmbeddableImagePath($src);

                    if ($localPath === null) {
                        return $matches[0];
                    }

                    $part = DataPart::fromPath(
                        $localPath,
                        basename($localPath),
                        mime_content_type($localPath) ?: 'application/octet-stream',
                    )->asInline();

                    $message->addPart($part);

                    $cid = 'cid:'.$part->getContentId();

                    return sprintf(
                        '<img%s src="%s"%s>',
                        $matches[1] ?? '',
                        e($cid),
                        $matches[4] ?? '',
                    );
                },
                $html,
            ) ?? $html;
        }

        return $html;
    }

    private function escapedSubjectLine(): string
    {
        return e($this->subjectLine);
    }

    private function resolveEmbeddableImagePath(string $src): ?string
    {
        if ($src === 'branding-logo://inline') {
            $brandingLogoPath = $this->branding['logo_path'] ?? null;

            if (! is_string($brandingLogoPath) || ! is_file($brandingLogoPath) || ! is_readable($brandingLogoPath)) {
                return null;
            }

            $mimeType = mime_content_type($brandingLogoPath) ?: '';

            return str_starts_with($mimeType, 'image/') ? $brandingLogoPath : null;
        }

        $path = parse_url($src, PHP_URL_PATH);

        if (is_string($path) && str_starts_with($path, '/storage/email-notifications/media/')) {
            $relativePath = Str::after($path, '/storage/');
            $absolutePath = storage_path('app/public/'.$relativePath);

            if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
                return null;
            }

            $mimeType = mime_content_type($absolutePath) ?: '';

            return str_starts_with($mimeType, 'image/') ? $absolutePath : null;
        }

        if (! filter_var($src, FILTER_VALIDATE_URL)) {
            return null;
        }

        $response = Http::timeout(30)->get($src);

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        $mimeType = $response->header('Content-Type') ?: '';

        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            'image/bmp' => 'bmp',
            default => 'img',
        };
        $temporaryPath = tempnam(sys_get_temp_dir(), 'yogafx-email-');

        if ($temporaryPath === false) {
            return null;
        }

        $imagePath = $temporaryPath.'.'.$extension;

        if (! @rename($temporaryPath, $imagePath)) {
            $imagePath = $temporaryPath;
        }

        if (file_put_contents($imagePath, $response->body()) === false) {
            @unlink($imagePath);

            return null;
        }

        register_shutdown_function(static function () use ($imagePath): void {
            @unlink($imagePath);
        });

        return $imagePath;
    }

    private function buildLogoHtml(): string
    {
        $hasLocalLogo = is_string($this->branding['logo_path'] ?? null) && $this->branding['logo_path'] !== '';
        $logoUrl = is_string($this->branding['logo_url'] ?? null) && $this->branding['logo_url'] !== ''
            ? $this->branding['logo_url']
            : ($hasLocalLogo ? 'branding-logo://inline' : '');

        if ($logoUrl === '') {
            return '';
        }

        $alt = e((string) ($this->branding['app_name'] ?? config('app.name', 'YogaFX LMS')));

        return '<div style="margin-bottom: 20px;"><img src="'.e($logoUrl).'" alt="'.$alt.'" style="display: block; max-width: 180px; width: auto; height: auto;"></div>';
    }

    private function htmlFragment(string $content): string
    {
        if (! Str::contains($content, '<')) {
            return '<div style="white-space: pre-line;">'.e($content).'</div>';
        }

        return $content;
    }

    private function htmlContainsEmbeddableImage(string $html): bool
    {
        return Str::contains($html, '<img', true);
    }
}
