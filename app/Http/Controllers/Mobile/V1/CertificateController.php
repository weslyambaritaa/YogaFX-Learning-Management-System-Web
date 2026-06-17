<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\Mobile\V1\StudentCertificateApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    public function __construct(
        private readonly StudentCertificateApiService $studentCertificateApiService,
    ) {}

    public function index(Request $request)
    {
        return MobileApiResponse::success(
            $this->studentCertificateApiService->listForUser($request->user()),
            'Mobile certificate list retrieved successfully.',
        );
    }

    public function show(Request $request, Certificate $certificate)
    {
        $payload = $this->studentCertificateApiService->detailForUser($request->user(), $certificate);

        if (! $payload) {
            return MobileApiResponse::error(
                'Certificate not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return MobileApiResponse::success(
            $payload,
            'Mobile certificate detail retrieved successfully.',
        );
    }

    public function download(Request $request, Certificate $certificate)
    {
        $response = $this->studentCertificateApiService->downloadResponseForUser($request->user(), $certificate);

        if (! $response) {
            return MobileApiResponse::error(
                'Certificate file not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $response;
    }
}
