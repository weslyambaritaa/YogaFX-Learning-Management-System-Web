<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CertificateGeneratorService
{
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

        $disk = Storage::disk((string) config('certificates.storage_disk', 'local'));
        $templatePath = $disk->path(trim((string) config('certificates.template_directory', 'certificate-templates'), '/').'/'.$template['template_file']);

        if (! is_file($templatePath)) {
            throw new RuntimeException('Certificate JPG template is missing from internal storage.');
        }

        $renderedImagePath = $this->renderStudentNameToTemplate($templatePath, $studentName, $template['placement'] ?? []);
        $pdfBinary = $this->buildPdfFromRenderedImage($renderedImagePath);

        $fileName = sprintf(
            '%s-%s.pdf',
            Str::slug($studentName),
            $template['file_name_suffix'] ?? Str::slug($certificateType)
        );
        $relativePath = trim((string) config('certificates.output_directory', 'certificates'), '/').'/'.$student->id.'/'.$fileName;
        $timestamp = now();

        $existing = Certificate::query()
            ->where('user_id', $student->id)
            ->where('certificate_type', $certificateType)
            ->latest('generated_at')
            ->latest('id')
            ->first();

        $previousPath = $existing?->file_path;
        $nextVersion = max(1, (int) ($existing?->version ?? 0) + 1);

        $disk->put($relativePath, $pdfBinary);

        if ($previousPath && $previousPath !== $relativePath && $disk->exists($previousPath)) {
            $disk->delete($previousPath);
        }

        @unlink($renderedImagePath);

        $certificate = $existing ?? new Certificate([
            'user_id' => $student->id,
            'certificate_type' => $certificateType,
        ]);

        $certificate->fill([
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'version' => $nextVersion,
            'generated_by_user_id' => $generatedByUserId,
            'generated_at' => $timestamp,
        ]);
        $certificate->save();

        return $certificate->fresh(['generator:id,name']);
    }

    private function renderStudentNameToTemplate(string $templatePath, string $studentName, array $placement): string
    {
        $image = imagecreatefromjpeg($templatePath);

        if (! $image) {
            throw new RuntimeException('Unable to read the JPG certificate template.');
        }

        $fontPath = $this->resolveFontPath((string) ($placement['font_family'] ?? 'dejavu_sans'));
        $fontSize = (float) ($placement['font_size'] ?? 42);
        $alignment = (string) ($placement['alignment'] ?? 'center');
        $x = (int) ($placement['x'] ?? 0);
        $y = (int) ($placement['y'] ?? 0);
        $angle = 0;
        $rgb = $this->parseHexColor((string) ($placement['font_color'] ?? '#000000'));
        $color = imagecolorallocate($image, $rgb['red'], $rgb['green'], $rgb['blue']);

        $boundingBox = imagettfbbox($fontSize, $angle, $fontPath, $studentName);

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

        imagettftext($image, $fontSize, $angle, $x, $y, $color, $fontPath, $studentName);

        $outputPath = storage_path('app/tmp/'.Str::uuid()->toString().'.jpg');
        $outputDirectory = dirname($outputPath);

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        imagejpeg($image, $outputPath, 100);
        imagedestroy($image);

        return $outputPath;
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
            '<html><body style="margin:0;padding:0;"><img src="data:image/jpeg;base64,%s" style="display:block;width:100%%;height:auto;" alt="Certificate"></body></html>',
            $imageData
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
