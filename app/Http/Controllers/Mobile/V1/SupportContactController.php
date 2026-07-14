<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Services\SupportSettingService;
use App\Support\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class SupportContactController extends Controller
{
    public function __construct(
        private readonly SupportSettingService $supportSettingService,
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            $payload = $this->supportSettingService->publicPayload();

            return MobileApiResponse::success([
                'whatsapp' => $payload['whatsapp'],
                'email' => $payload['email'],
            ], 'Support contact fetched successfully');
        } catch (Throwable $exception) {
            report($exception);

            return MobileApiResponse::error(
                'Unable to fetch support contact.',
                500,
            );
        }
    }
}
