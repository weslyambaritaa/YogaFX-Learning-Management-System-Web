<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\MobileSignedUrl;
use App\Services\CertificateDownloadTrackingService;
use App\Support\BunnyAssetPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateMediaController extends Controller
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
        private readonly CertificateDownloadTrackingService $certificateDownloadTrackingService,
    ) {}

    public function open(Request $request, Certificate $certificate): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless(MobileSignedUrl::hasValidSignature($request), 403);

        $student = $this->resolveSignedStudent($request);
        abort_unless($certificate->user_id === $student->id, 403);

        return $this->serveCertificate((string) $certificate->file_path, (string) $certificate->file_name, false);
    }

    public function download(Request $request, Certificate $certificate): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless(MobileSignedUrl::hasValidSignature($request), 403);

        $student = $this->resolveSignedStudent($request);
        abort_unless($certificate->user_id === $student->id, 403);

        $this->certificateDownloadTrackingService->record($student, $certificate);

        return $this->serveCertificate((string) $certificate->file_path, (string) $certificate->file_name, true);
    }

    private function resolveSignedStudent(Request $request): User
    {
        $studentId = (int) $request->integer('student');
        abort_unless($studentId > 0, 403);

        /** @var User $student */
        $student = User::query()->findOrFail($studentId);
        abort_unless($student->isStudent(), 403);

        return $student;
    }

    private function serveCertificate(string $path, string $fileName, bool $download): Response|StreamedResponse|BinaryFileResponse
    {
        if (BunnyAssetPath::isBunnyPath($path)) {
            $url = $this->bunnyStorageService->url($path);
            abort_unless(filled($url), 404);

            return redirect()->away($url);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        abort_unless(Storage::disk('local')->exists($path), 404);

        if ($download) {
            return Storage::disk('local')->download($path, $fileName);
        }

        return Storage::disk('local')->response($path);
    }
}
