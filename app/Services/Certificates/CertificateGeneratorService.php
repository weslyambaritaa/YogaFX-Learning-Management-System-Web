<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CertificateGeneratorService
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorage,
    ) {}

    public function generate(User $student, string $certificateType, ?int $generatedByUserId = null): Certificate
    {
        $studentName = $this->resolveStudentCertificateName($student);

        if ($studentName === '') {
            throw new RuntimeException('Student primary account name is required before certificate generation.');
        }

        $template = config("certificates.templates.{$certificateType}");

        if (! is_array($template)) {
            throw new RuntimeException('Certificate template configuration is missing.');
        }

        $timestamp = now();
        $templateBytes = $this->loadTemplateBytes($template);
        $pdfBinary = $this->buildPdfFromTemplate(
            $templateBytes,
            $student,
            $studentName,
            $template['placement'] ?? [],
            $template['date_placement'] ?? [],
            $template['profile_photo_placement'] ?? [],
            $timestamp,
        );

        $existing = Certificate::query()
            ->where('user_id', $student->id)
            ->where('certificate_type', $certificateType)
            ->latest('generated_at')
            ->latest('id')
            ->first();

        $previousPath = $existing?->file_path;
        $nextVersion = max(1, (int) ($existing?->version ?? 0) + 1);
        $fileName = sprintf(
            '%s-%s.pdf',
            Str::slug($studentName),
            $template['file_name_suffix'] ?? Str::slug($certificateType),
        );
        $objectKey = trim((string) config('certificates.output_directory', 'certificates'), '/')
            .'/'.$student->id
            .'/'.$certificateType
            .'/v'.$nextVersion.'-'.$timestamp->format('YmdHis').'-'.Str::uuid()->toString().'.pdf';

        $storedPath = $this->bunnyStorage->uploadContents($pdfBinary, $objectKey, 'application/pdf');

        $certificate = $existing ?? new Certificate([
            'user_id' => $student->id,
            'certificate_type' => $certificateType,
        ]);

        $certificate->fill([
            'file_path' => $storedPath,
            'file_name' => $fileName,
            'version' => $nextVersion,
            'generated_by_user_id' => $generatedByUserId,
            'generated_at' => $timestamp,
        ]);
        $certificate->save();

        if ($previousPath && $previousPath !== $storedPath) {
            $this->bunnyStorage->delete($previousPath);
        }

        return $certificate->fresh(['generator:id,name']);
    }

    private function renderCertificateTemplate(
        string $templateBytes,
        User $student,
        string $studentName,
        array $placement,
        array $datePlacement,
        array $profilePhotoPlacement,
        Carbon $generatedAt,
    ): string
    {
        $image = imagecreatefromstring($templateBytes);

        if (! $image) {
            throw new RuntimeException('Unable to read the JPG certificate template.');
        }

        $this->drawText($image, $studentName, $placement, 'Unable to calculate certificate name placement using the configured font.');

        if ($datePlacement !== []) {
            $this->drawText(
                $image,
                $generatedAt->format('F j, Y'),
                $datePlacement,
                'Unable to calculate certificate date placement using the configured font.',
            );
        }

        if ($profilePhotoPlacement !== [] && filled($student->profile_photo)) {
            $this->drawCircularProfilePhoto($image, $student, $profilePhotoPlacement);
        }

        $outputPath = storage_path('app/tmp/'.Str::uuid()->toString().'.jpg');
        $outputDirectory = dirname($outputPath);

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        imagejpeg($image, $outputPath, 100);
        imagedestroy($image);

        return $outputPath;
    }

    private function drawCircularProfilePhoto($certificateImage, User $student, array $placement): void
    {
        $photoBytes = $this->loadProfilePhotoBytes((string) $student->profile_photo);

        if ($photoBytes === null) {
            return;
        }

        $source = imagecreatefromstring($photoBytes);

        if (! $source) {
            return;
        }

        $diameter = max(40, (int) ($placement['diameter'] ?? 180));
        $borderWidth = max(0, (int) ($placement['border_width'] ?? 6));
        $canvasSize = $diameter + ($borderWidth * 2);

        $canvas = imagecreatetruecolor($canvasSize, $canvasSize);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        $resized = imagecreatetruecolor($diameter, $diameter);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, $transparent);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $square = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $square) / 2);
        $sourceY = (int) floor(($sourceHeight - $square) / 2);

        imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $diameter,
            $diameter,
            $square,
            $square,
        );

        $radius = $diameter / 2;

        for ($x = 0; $x < $diameter; $x++) {
            for ($y = 0; $y < $diameter; $y++) {
                $dx = $x - $radius;
                $dy = $y - $radius;

                if (($dx * $dx) + ($dy * $dy) > ($radius * $radius)) {
                    imagesetpixel($resized, $x, $y, $transparent);
                }
            }
        }

        imagecopy($canvas, $resized, $borderWidth, $borderWidth, 0, 0, $diameter, $diameter);

        $borderColorRgb = $this->parseHexColor((string) ($placement['border_color'] ?? '#FFFFFF'));
        $borderColor = imagecolorallocate(
            $canvas,
            $borderColorRgb['red'],
            $borderColorRgb['green'],
            $borderColorRgb['blue'],
        );

        imagealphablending($canvas, true);

        for ($i = 0; $i < $borderWidth; $i++) {
            imageellipse(
                $canvas,
                (int) round($canvasSize / 2),
                (int) round($canvasSize / 2),
                $diameter + ($borderWidth * 2) - (2 * $i) - 1,
                $diameter + ($borderWidth * 2) - (2 * $i) - 1,
                $borderColor,
            );
        }

        $certificateWidth = imagesx($certificateImage);
        $x = isset($placement['x'])
            ? (int) $placement['x']
            : max(0, $certificateWidth - (int) ($placement['right'] ?? 100) - $canvasSize);
        $y = (int) ($placement['top'] ?? ($placement['y'] ?? 100));

        imagecopy($certificateImage, $canvas, $x, $y, 0, 0, $canvasSize, $canvasSize);

        imagedestroy($source);
        imagedestroy($resized);
        imagedestroy($canvas);
    }

    private function drawText($image, string $text, array $placement, string $errorMessage): void
    {
        $fontPath = $this->resolveFontPath((string) ($placement['font_family'] ?? 'dejavu_sans'));
        $fontSize = (float) ($placement['font_size'] ?? 42);
        $minFontSize = (float) ($placement['min_font_size'] ?? max(12, $fontSize - 16));
        $maxWidth = isset($placement['max_width']) ? (int) $placement['max_width'] : null;
        $alignment = (string) ($placement['alignment'] ?? 'center');
        $verticalAlignment = (string) ($placement['vertical_alignment'] ?? 'baseline');
        $x = $this->resolvePlacementCoordinate(
            $placement['x'] ?? 0,
            imagesx($image),
            'center',
        );
        $y = $this->resolvePlacementCoordinate(
            $placement['y'] ?? 0,
            imagesy($image),
            'middle',
        );
        $rgb = $this->parseHexColor((string) ($placement['font_color'] ?? '#000000'));
        $color = imagecolorallocate($image, $rgb['red'], $rgb['green'], $rgb['blue']);
        $boundingBox = $this->measureTextBox($fontSize, $fontPath, $text);

        if ($boundingBox === false) {
            imagedestroy($image);
            throw new RuntimeException($errorMessage);
        }

        while (
            $maxWidth !== null
            && $boundingBox['width'] > $maxWidth
            && $fontSize > $minFontSize
        ) {
            $fontSize -= 1;
            $boundingBox = $this->measureTextBox($fontSize, $fontPath, $text);

            if ($boundingBox === false) {
                imagedestroy($image);
                throw new RuntimeException($errorMessage);
            }
        }

        $baselineX = match ($alignment) {
            'left' => $x - $boundingBox['min_x'],
            'right' => $x - $boundingBox['max_x'],
            default => $x - (int) round(($boundingBox['min_x'] + $boundingBox['max_x']) / 2),
        };

        $baselineY = match ($verticalAlignment) {
            'top' => $y - $boundingBox['min_y'],
            'middle', 'center' => $y - (int) round(($boundingBox['min_y'] + $boundingBox['max_y']) / 2),
            'bottom' => $y - $boundingBox['max_y'],
            default => $y,
        };

        imagettftext($image, $fontSize, 0, $baselineX, $baselineY, $color, $fontPath, $text);
    }

    private function resolveStudentCertificateName(User $student): string
    {
        $candidates = [
            trim((string) $student->name),
            trim(implode(' ', array_filter([
                $student->first_name,
                $student->last_name,
            ]))),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
            }
        }

        return '';
    }

    private function loadTemplateBytes(array $template): string
    {
        $templateFile = (string) ($template['template_file'] ?? '');
        $templateSourceUrl = (string) ($template['template_source_url'] ?? '');
        $objectKey = trim((string) config('certificates.template_output_directory', 'certificates/templates'), '/').'/'.$templateFile;
        $bunnyPath = BunnyAssetPath::fromObjectKey($objectKey);
        $bunnyUrl = $this->bunnyStorage->url($bunnyPath);

        if (filled($bunnyUrl)) {
            try {
                $bunnyResponse = Http::timeout(30)->get($bunnyUrl);

                if ($bunnyResponse->successful() && $bunnyResponse->body() !== '') {
                    return $bunnyResponse->body();
                }
            } catch (Throwable) {
                // Fall through to the configured source URL when Bunny CDN is unavailable.
            }
        }

        if ($templateSourceUrl === '') {
            throw new RuntimeException('Certificate JPG template source URL is missing.');
        }

        try {
            $sourceResponse = Http::timeout(60)->get($templateSourceUrl);
        } catch (Throwable $exception) {
            throw new RuntimeException('Certificate JPG template could not be loaded from the configured source.', previous: $exception);
        }

        if (! $sourceResponse->successful() || $sourceResponse->body() === '') {
            throw new RuntimeException('Certificate JPG template could not be loaded from the configured source.');
        }

        $templateBytes = $sourceResponse->body();
        $mimeType = $sourceResponse->header('Content-Type') ?: 'image/jpeg';
        $uploadedPath = $this->bunnyStorage->uploadContents($templateBytes, $objectKey, $mimeType);

        if ($uploadedPath !== $bunnyPath) {
            throw new RuntimeException('Certificate JPG template could not be stored in Bunny Storage.');
        }

        return $templateBytes;
    }

    private function buildPdfFromTemplate(
        string $templateBytes,
        User $student,
        string $studentName,
        array $placement,
        array $datePlacement,
        array $profilePhotoPlacement,
        Carbon $generatedAt,
    ): string {
        $templateMeta = $this->detectImageMeta($templateBytes);
        $pageWidthPx = $templateMeta['width'];
        $pageHeightPx = $templateMeta['height'];
        $pageWidthPt = $this->pixelsToPoints($pageWidthPx);
        $pageHeightPt = $this->pixelsToPoints($pageHeightPx);
        $templateDataUri = $this->dataUri($templateBytes, $templateMeta['mime']);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $html = sprintf(
            '<html><head><style>%s</style></head><body><div class="page">%s%s%s%s</div></body></html>',
            $this->certificatePdfCss($pageWidthPx, $pageHeightPx, $pageWidthPt, $pageHeightPt),
            '<img class="background" src="'.$templateDataUri.'" alt="Certificate template">',
            $this->buildTextOverlayHtml($studentName, $placement, $pageWidthPx),
            $datePlacement !== []
                ? $this->buildTextOverlayHtml($generatedAt->format('F j, Y'), $datePlacement, $pageWidthPx)
                : '',
            $this->buildProfilePhotoOverlayHtml($student, $profilePhotoPlacement, $pageWidthPx),
        );

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $pageWidthPt, $pageHeightPt]);
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildPdfFromRenderedImage(string $renderedImagePath): string
    {
        [$width, $height] = getimagesize($renderedImagePath);
        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $imageData = base64_encode((string) file_get_contents($renderedImagePath));
        $paperWidth = $width * 0.75;
        $paperHeight = $height * 0.75;

        $html = sprintf(
            '<html><head><style>@page { margin: 0; size: %1$.4Fpt %2$.4Fpt; } html, body { margin: 0; padding: 0; width: %1$.4Fpt; height: %2$.4Fpt; overflow: hidden; font-size: 0; line-height: 0; } .page { width: %1$.4Fpt; height: %2$.4Fpt; overflow: hidden; } .page img { display: block; width: %1$.4Fpt; height: %2$.4Fpt; }</style></head><body><div class="page"><img src="data:image/jpeg;base64,%3$s" alt="Certificate"></div></body></html>',
            $paperWidth,
            $paperHeight,
            $imageData,
        );

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $paperWidth, $paperHeight]);
        $dompdf->render();

        return $dompdf->output();
    }

    private function certificatePdfCss(
        int $pageWidthPx,
        int $pageHeightPx,
        float $pageWidthPt,
        float $pageHeightPt,
    ): string {
        return sprintf(
            '@page { margin: 0; size: %.4Fpt %.4Fpt; } html, body { margin: 0; padding: 0; width: %dpx; height: %dpx; } body { font-family: DejaVu Sans, sans-serif; } .page { position: relative; width: %dpx; height: %dpx; overflow: hidden; } .background { position: absolute; inset: 0; width: %dpx; height: %dpx; display: block; } .overlay-text { position: absolute; white-space: nowrap; line-height: 1; } .profile-photo-frame { position: absolute; overflow: hidden; box-sizing: border-box; border-radius: 9999px; } .profile-photo-frame img { display: block; width: 100%%; height: 100%%; object-fit: cover; }',
            $pageWidthPt,
            $pageHeightPt,
            $pageWidthPx,
            $pageHeightPx,
            $pageWidthPx,
            $pageHeightPx,
            $pageWidthPx,
            $pageHeightPx,
        );
    }

    private function buildTextOverlayHtml(string $text, array $placement, int $pageWidthPx): string
    {
        if ($text === '') {
            return '';
        }

        $fontSize = max(8, (float) ($placement['font_size'] ?? 42));
        $fontFamily = (string) ($placement['font_family'] ?? 'dejavu_sans');
        $fontWeight = str_contains(strtolower($fontFamily), 'bold') ? '700' : '400';
        $color = $this->normalizeHexColor((string) ($placement['font_color'] ?? '#000000'));
        $alignment = strtolower((string) ($placement['alignment'] ?? 'center'));
        $verticalAlignment = strtolower((string) ($placement['vertical_alignment'] ?? 'baseline'));
        $top = $this->resolveTextTopPx($placement, $fontSize, $verticalAlignment);
        $styles = [
            'top: '.$top.'px',
            'font-size: '.$fontSize.'px',
            'font-weight: '.$fontWeight,
            'color: '.$color,
            'text-align: '.$this->normalizeTextAlign($alignment),
        ];

        $maxWidth = isset($placement['max_width']) ? max(1, (int) $placement['max_width']) : null;
        $resolvedX = $this->resolvePlacementX($placement['x'] ?? 0, $pageWidthPx);

        if ($alignment === 'center') {
            $width = $maxWidth ?? $pageWidthPx;
            $left = $maxWidth !== null
                ? (int) round($resolvedX - ($width / 2))
                : 0;

            $styles[] = 'left: '.$left.'px';
            $styles[] = 'width: '.$width.'px';
        } elseif ($alignment === 'right') {
            $width = $maxWidth ?? $resolvedX;
            $left = max(0, $resolvedX - $width);

            $styles[] = 'left: '.$left.'px';
            $styles[] = 'width: '.$width.'px';
        } else {
            $styles[] = 'left: '.$resolvedX.'px';

            if ($maxWidth !== null) {
                $styles[] = 'width: '.$maxWidth.'px';
            }
        }

        return '<div class="overlay-text" style="'.$this->implodeStyles($styles).'">'
            .htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            .'</div>';
    }

    private function buildProfilePhotoOverlayHtml(User $student, array $placement, int $pageWidthPx): string
    {
        if ($placement === [] || ! filled($student->profile_photo)) {
            return '';
        }

        $photoBytes = $this->loadProfilePhotoBytes((string) $student->profile_photo);

        if ($photoBytes === null) {
            return '';
        }

        $photoMeta = $this->detectImageMeta($photoBytes, 'image/jpeg');
        $diameter = max(40, (int) ($placement['diameter'] ?? 180));
        $borderWidth = max(0, (int) ($placement['border_width'] ?? 6));
        $frameSize = $diameter + ($borderWidth * 2);
        $x = isset($placement['x'])
            ? (int) $placement['x']
            : max(0, $pageWidthPx - (int) ($placement['right'] ?? 100) - $frameSize);
        $y = (int) ($placement['top'] ?? ($placement['y'] ?? 100));
        $borderColor = $this->normalizeHexColor((string) ($placement['border_color'] ?? '#FFFFFF'));

        return '<div class="profile-photo-frame" style="'.$this->implodeStyles([
            'left: '.$x.'px',
            'top: '.$y.'px',
            'width: '.$frameSize.'px',
            'height: '.$frameSize.'px',
            'border: '.$borderWidth.'px solid '.$borderColor,
            'background: transparent',
        ]).'">'
            .'<img src="'.$this->dataUri($photoBytes, $photoMeta['mime']).'" alt="Student profile photo">'
            .'</div>';
    }

    /**
     * @return array{width:int, height:int, mime:string}
     */
    private function detectImageMeta(string $bytes, string $fallbackMime = 'image/jpeg'): array
    {
        $size = function_exists('getimagesizefromstring')
            ? @getimagesizefromstring($bytes)
            : false;

        if (is_array($size) && isset($size[0], $size[1])) {
            return [
                'width' => (int) $size[0],
                'height' => (int) $size[1],
                'mime' => is_string($size['mime'] ?? null) && $size['mime'] !== ''
                    ? (string) $size['mime']
                    : $this->detectMimeType($bytes, $fallbackMime),
            ];
        }

        return [
            'width' => 1920,
            'height' => 1485,
            'mime' => $this->detectMimeType($bytes, $fallbackMime),
        ];
    }

    private function detectMimeType(string $bytes, string $fallbackMime = 'application/octet-stream'): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($bytes);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return $fallbackMime;
    }

    private function dataUri(string $bytes, string $mime): string
    {
        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function pixelsToPoints(int $pixels): float
    {
        return $pixels * 0.75;
    }

    private function resolvePlacementX(mixed $value, int $pageWidthPx): int
    {
        if (is_string($value) && strtolower(trim($value)) === 'center') {
            return (int) round($pageWidthPx / 2);
        }

        return (int) round((float) $value);
    }

    private function resolveTextTopPx(array $placement, float $fontSize, string $verticalAlignment): int
    {
        $y = (float) ($placement['y'] ?? 0);

        return match ($verticalAlignment) {
            'top' => (int) round($y),
            'middle', 'center' => (int) round($y - ($fontSize * 0.55)),
            'bottom' => (int) round($y - ($fontSize * 1.05)),
            default => (int) round($y - ($fontSize * 0.85)),
        };
    }

    private function normalizeTextAlign(string $alignment): string
    {
        return match ($alignment) {
            'left', 'right' => $alignment,
            default => 'center',
        };
    }

    private function normalizeHexColor(string $hex): string
    {
        $normalized = ltrim($hex, '#');

        if (strlen($normalized) === 3) {
            $normalized = preg_replace('/(.)/', '$1$1', $normalized) ?? '000000';
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $normalized = '000000';
        }

        return '#'.strtoupper($normalized);
    }

    private function implodeStyles(array $styles): string
    {
        return implode('; ', array_filter($styles, fn (mixed $style) => is_string($style) && $style !== '')).';';
    }

    private function loadProfilePhotoBytes(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (BunnyAssetPath::isBunnyPath($path)) {
            $url = $this->bunnyStorage->url($path);

            if (! filled($url)) {
                return null;
            }

            $response = Http::timeout(30)->get($url);

            return $response->successful() && $response->body() !== ''
                ? $response->body()
                : null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(30)->get($path);

            return $response->successful() && $response->body() !== ''
                ? $response->body()
                : null;
        }

        if (Storage::disk('local')->exists($path)) {
            $contents = Storage::disk('local')->get($path);

            return $contents !== ''
                ? $contents
                : null;
        }

        return null;
    }

    private function resolveFontPath(string $fontFamily): string
    {
        $fontPath = config("certificates.font_families.{$fontFamily}");

        if (! is_string($fontPath) || ! is_file($fontPath)) {
            throw new RuntimeException('Configured certificate font file is missing.');
        }

        return $fontPath;
    }

    private function parseHexColor(string $hex): array
    {
        $normalized = ltrim($hex, '#');

        if (strlen($normalized) === 3) {
            $normalized = preg_replace('/(.)/', '$1$1', $normalized) ?? '000000';
        }

        if (strlen($normalized) !== 6) {
            $normalized = '000000';
        }

        return [
            'red' => hexdec(substr($normalized, 0, 2)),
            'green' => hexdec(substr($normalized, 2, 2)),
            'blue' => hexdec(substr($normalized, 4, 2)),
        ];
    }

    private function measureTextBox(float $fontSize, string $fontPath, string $text): array|false
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        if ($box === false) {
            return false;
        }

        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $minX = (int) min($xs);
        $maxX = (int) max($xs);
        $minY = (int) min($ys);
        $maxY = (int) max($ys);

        return [
            'box' => $box,
            'min_x' => $minX,
            'max_x' => $maxX,
            'min_y' => $minY,
            'max_y' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

    private function resolvePlacementCoordinate(mixed $value, int $dimension, string $keyword): int
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === $keyword) {
                return (int) round($dimension / 2);
            }

            if (is_numeric($normalized)) {
                return (int) round((float) $normalized);
            }
        }

        return (int) round((float) $value);
    }
}
