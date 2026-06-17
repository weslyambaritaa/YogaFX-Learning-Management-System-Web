<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use App\Services\Mobile\V1\StudentEbookApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EbookController extends Controller
{
    public function __construct(
        private readonly StudentEbookApiService $studentEbookApiService,
    ) {}

    public function index(Request $request)
    {
        return MobileApiResponse::success(
            $this->studentEbookApiService->listForUser($request->user()),
            'Mobile ebook list retrieved successfully.',
        );
    }

    public function show(Request $request, Ebook $ebook)
    {
        $payload = $this->studentEbookApiService->detailForUser($request->user(), $ebook);

        if (! $payload) {
            return MobileApiResponse::error(
                'Ebook not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return MobileApiResponse::success(
            $payload,
            'Mobile ebook detail retrieved successfully.',
        );
    }
}
