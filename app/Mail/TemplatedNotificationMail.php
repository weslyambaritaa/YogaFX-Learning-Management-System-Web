<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
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

        return $this->subject($this->subjectLine)
            ->html($initialHtml)
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

        if (! is_string($path) || ! str_starts_with($path, '/storage/email-notifications/media/')) {
            return null;
        }

        $relativePath = Str::after($path, '/storage/');
        $absolutePath = storage_path('app/public/'.$relativePath);

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $mimeType = mime_content_type($absolutePath) ?: '';

        return str_starts_with($mimeType, 'image/') ? $absolutePath : null;
    }
}