<?php

namespace App\Services\Mobile\V1;

use App\Models\Certificate;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Services\CertificateDownloadTrackingService;
use App\Services\Certificates\CertificateEligibilityService;
use App\Support\BunnyAssetPath;
use App\Support\MobileMediaPayload;
use App\Support\MobileSignedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentCertificateApiService
{
    public function __construct(
        private readonly CertificateEligibilityService $certificateEligibilityService,
        private readonly BunnyStorageService $bunnyStorageService,
        private readonly CertificateDownloadTrackingService $certificateDownloadTrackingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listForUser(User $user): array
    {
        $summary = $this->certificateEligibilityService->summaryForStudent($user);
        $certificates = $this->generatedCertificatesForUser($user, $summary['available_types'] ?? []);

        return [
            'summary' => [
                'learning_eligible' => (bool) ($summary['learning_eligible'] ?? false),
                'has_required_name' => (bool) ($summary['has_required_name'] ?? false),
                'message' => $summary['message'] ?? null,
                'tier' => $summary['tier'] ?? null,
                'available_types' => $summary['available_types'] ?? [],
                'requirements' => $summary['requirements'] ?? [],
                'generated_count' => $certificates->count(),
            ],
            'items' => $certificates
                ->map(fn (Certificate $certificate) => $this->certificatePayload($certificate))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detailForUser(User $user, Certificate $certificate): ?array
    {
        if ($certificate->user_id !== $user->id) {
            return null;
        }

        $summary = $this->certificateEligibilityService->summaryForStudent($user);

        return [
            'certificate' => $this->certificatePayload($certificate),
            'summary' => [
                'learning_eligible' => (bool) ($summary['learning_eligible'] ?? false),
                'has_required_name' => (bool) ($summary['has_required_name'] ?? false),
                'message' => $summary['message'] ?? null,
                'tier' => $summary['tier'] ?? null,
            ],
        ];
    }

    public function downloadResponseForUser(User $user, Certificate $certificate): RedirectResponse|BinaryFileResponse|StreamedResponse|null
    {
        if ($certificate->user_id !== $user->id) {
            return null;
        }

        $this->certificateDownloadTrackingService->record($user, $certificate);

        if (BunnyAssetPath::isBunnyPath($certificate->file_path)) {
            $url = $this->bunnyStorageService->url($certificate->file_path);

            if (! filled($url)) {
                return null;
            }

            return redirect()->away($url);
        }

        if (! Storage::disk('local')->exists($certificate->file_path)) {
            return null;
        }

        /** @var BinaryFileResponse|StreamedResponse $response */
        $response = Storage::disk('local')->download($certificate->file_path, $certificate->file_name);

        return $response;
    }

    /**
     * @param  array<int, string>  $availableTypes
     * @return Collection<int, Certificate>
     */
    private function generatedCertificatesForUser(User $user, array $availableTypes): Collection
    {
        return $this->certificateEligibilityService
            ->latestCertificatesByType($user, $availableTypes)
            ->sortByDesc(fn (Certificate $certificate) => sprintf(
                '%010d-%010d',
                $certificate->generated_at?->getTimestamp() ?? 0,
                $certificate->id,
            ))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function certificatePayload(Certificate $certificate): array
    {
        $openUrl = $this->signedCertificateRoute('mobile.api.v1.certificates.media.open', $certificate);
        $downloadUrl = $this->signedCertificateRoute('mobile.api.v1.certificates.media.download', $certificate);

        return [
            'id' => $certificate->id,
            'type' => $certificate->certificate_type,
            'type_label' => $certificate->typeLabel(),
            'file_name' => $certificate->file_name,
            'version' => $certificate->version,
            'generated_at' => $certificate->generated_at?->toIso8601String(),
            'generated_by' => $certificate->generator?->name,
            'download_url' => $downloadUrl,
            'open_url' => $openUrl,
            'file' => MobileMediaPayload::file(
                openUrl: $openUrl,
                downloadUrl: $downloadUrl,
                previewUrl: $openUrl,
                fileName: $certificate->file_name,
                mimeType: 'application/pdf',
                previewSupported: true,
                previewMessage: null,
                isAvailable: filled($certificate->file_path),
            ),
        ];
    }

    private function signedCertificateRoute(string $routeName, Certificate $certificate): string
    {
        return MobileSignedUrl::temporarySignedRoute($routeName, now()->addHour(), [
            'certificate' => $certificate->id,
            'student' => $certificate->user_id,
        ]);
    }
}
