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
        $rawHeaderHtml = trim($this->branding['email_header_html'] ?? '');
        $rawFooterHtml = trim($this->branding['email_signature_html'] ?? '');
        $headerHtml = $rawHeaderHtml === '' ? '' : $this->prepareBrandingSectionHtml($rawHeaderHtml, 'header');
        $footerHtml = $rawFooterHtml === '' ? '' : $this->prepareBrandingSectionHtml($rawFooterHtml, 'footer');
        $contentHtml = $this->constrainImages(
    $this->htmlFragment($this->bodyHtml),
    'max-width:100%; height:auto; box-sizing:border-box;'
);

        $headerSection = $headerHtml === '' ? '' : <<<HTML
        <div style="padding: 24px 24px 16px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);">
            {$headerHtml}
        </div>
HTML;
        $footerSection = $footerHtml === '' ? '' : <<<HTML
        <div style="padding: 16px 24px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
            {$footerHtml}
        </div>
HTML;

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
        {$headerSection}
        <div style="padding: 24px; overflow: hidden;">
    {$contentHtml}
</div>
        {$footerSection}
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

    private function prepareBrandingSectionHtml(string $content, string $section): string
    {
        $html = $this->htmlFragment($content);

        return match ($section) {
            'logo' => $this->wrapBrandingSection(
                $this->constrainImages($html, 'display:block; max-width:220px; width:auto; height:auto;'),
                'margin: 0 0 14px; text-align: left;',
            ),
            'header' => $this->wrapBrandingSection(
                $this->constrainImages($html, 'display:block; width:100%; max-width:100%; height:auto;'),
                'margin: 0; text-align: left;',
            ),
            'footer' => $this->wrapBrandingSection(
                $this->constrainImages($html, 'display:block; width:100%; max-width:100%; height:auto;'),
                'margin: 0; text-align: left;',
            ),
            default => $html,
        };
    }

    private function wrapBrandingSection(string $html, string $style): string
    {
        return '<div style="'.$style.'">'.$html.'</div>';
    }

    private function constrainImages(string $html, string $requiredStyle): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function (array $matches) use ($requiredStyle): string {
                $tag = $matches[0];

                if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $tag, $styleMatch) === 1) {
                    $mergedStyle = rtrim(trim($styleMatch[2]), ';');

                    if ($mergedStyle !== '') {
                        $mergedStyle .= '; ';
                    }

                    $mergedStyle .= $requiredStyle;

                    return preg_replace(
                        '/\sstyle=(["\'])(.*?)\1/i',
                        ' style="'.$mergedStyle.'"',
                        $tag,
                        1,
                    ) ?? $tag;
                }

                return preg_replace(
                    '/<img\b/i',
                    '<img style="'.$requiredStyle.'"',
                    $tag,
                    1,
                ) ?? $tag;
            },
            $html,
        ) ?? $html;
    }

    private function resolveEmbeddableImagePath(string $src): ?string
    {
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
