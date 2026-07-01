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
                    Str::contains($this->bodyHtml, '<img', true)
                        ? $this->buildEmailHtml($message)
                        : $initialHtml,
                );
            });
    }

    private function buildEmailHtml(?Email $message = null): string
    {
        $renderedBody = $this->bodyHtml;

        if ($message !== null && Str::contains($renderedBody, '<img', true)) {
            $renderedBody = preg_replace_callback(
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
                $renderedBody,
            ) ?? $renderedBody;
        }

        $content = Str::contains($renderedBody, '<')
            ? $renderedBody
            : '<div style="white-space: pre-line;">'.e($renderedBody).'</div>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$this->escapedSubjectLine()}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    {$content}
</body>
</html>
HTML;
    }

    private function escapedSubjectLine(): string
    {
        return e($this->subjectLine);
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
}
