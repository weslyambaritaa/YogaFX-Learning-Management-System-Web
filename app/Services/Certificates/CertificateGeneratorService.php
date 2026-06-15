<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CertificateGeneratorService
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorage,
    ) {}

    public function generate(User $student, string $certificateType, ?int $generatedByUserId = null): Certificate
    {
        $studentName = trim((string) $student->name);

        if ($studentName === '') {
            throw new RuntimeException('Student primary account name is required before certificate generation.');
        }

        $template = config("certificates.templates.{$certificateType}");

        if (! is_array($template)) {
            throw new RuntimeException('Certificate template configuration is missing.');
        }

        $templateBytes = $this->loadTemplateBytes($template);
        $renderedImagePath = $this->renderStudentNameToTemplate($templateBytes, $studentName, $template['placement'] ?? []);
        $pdfBinary = $this->buildPdfFromRenderedImage($renderedImagePath);

        $fileName = sprintf(
            '%s-%s.pdf',
            Str::slug($studentName),
            $template['file_name_suffix'] ?? Str::slug($certificateType),
        );
        $objectKey = trim((string) config('certificates.output_directory', 'certificates'), '/').'/'.$student->id.'/'.$fileName;
        $timestamp = now();

        $existing = Certificate::query()
            ->where('user_id', $student->id)
            ->where('certificate_type', $certificateType)
            ->latest('generated_at')
            ->latest('id')
            ->first();

        $previousPath = $existing?->file_path;
        $nextVersion = max(1, (int) ($existing?->version ?? 0) + 1);

        $storedPath = $this->bunnyStorage->uploadContents($pdfBinary, $objectKey, 'application/pdf');

        @unlink($renderedImagePath);

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

    private function renderStudentNameToTemplate(string $templateBytes, string $studentName, array $placement): string
    {
        $image = imagecreatefromstring($templateBytes);

        if (! $image) {
            throw new RuntimeException('Unable to read the JPG certificate template.');
        }

        $fontPath = $this->resolveFontPath((string) ($placement['font_family'] ?? 'dejavu_sans'));
        $fontSize = (float) ($placement['font_size'] ?? 42);
        $alignment = (string) ($placement['alignment'] ?? 'center');
        $x = (int) ($placement['x'] ?? 0);
        $y = (int) ($placement['y'] ?? 0);
        $rgb = $this->parseHexColor((string) ($placement['font_color'] ?? '#000000'));
        $color = imagecolorallocate($image, $rgb['red'], $rgb['green'], $rgb['blue']);
        $boundingBox = imagettfbbox($fontSize, 0, $fontPath, $studentName);

        if ($boundingBox === false) {
            imagedestroy($image);
            throw new RuntimeException('Unable to calculate certificate name placement using the configured font.');
        }

        $textWidth = abs($boundingBox[4] - $boundingBox[0]);

        if ($alignment === 'center') {
            $x -= (int) round($textWidth / 2);
        } elseif ($alignment === 'right') {
            $x -= $textWidth;
        }

        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $studentName);

        $outputPath = storage_path('app/tmp/'.Str::uuid()->toString().'.jpg');
        $outputDirectory = dirname($outputPath);

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        imagejpeg($image, $outputPath, 100);
        imagedestroy($image);

        return $outputPath;
    }

    private function loadTemplateBytes(array $template): string
    {
        $templateFile = (string) ($template['template_file'] ?? '');
        $templateSourceUrl = (string) ($template['template_source_url'] ?? '');
        $objectKey = trim((string) config('certificates.template_output_directory', 'certificates/templates'), '/').'/'.$templateFile;
        $bunnyPath = BunnyAssetPath::fromObjectKey($objectKey);
        $bunnyUrl = $this->bunnyStorage->url($bunnyPath);

        if (filled($bunnyUrl)) {
            $bunnyResponse = Http::timeout(30)->get($bunnyUrl);

            if ($bunnyResponse->successful() && $bunnyResponse->body() !== '') {
                return $bunnyResponse->body();
            }
        }

        if ($templateSourceUrl === '') {
            throw new RuntimeException('Certificate JPG template source URL is missing.');
        }

        $sourceResponse = Http::timeout(60)->get($templateSourceUrl);

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
}
