<?php

namespace App\Http\Controllers;

use App\Services\AppDownloadQrService;
use App\Services\LinkControlSettingService;
use App\Support\PublicPageMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicAppDownloadController extends Controller
{
    public function __construct(
        private readonly LinkControlSettingService $linkControlSettingService,
        private readonly AppDownloadQrService $appDownloadQrService,
        private readonly PublicPageMeta $publicPageMeta,
    ) {}

    public function __invoke(Request $request): Response
    {
        $response = Inertia::render('Public/AppDownload', [
            'appDownload' => $this->linkControlSettingService->publicPayload(),
        ]);

        return $response->withViewData([
            'meta' => $this->publicPageMeta->defaults($request, [
                'title' => 'Download App | YogaFX',
                'description' => 'Open the YogaFX mobile download page to choose the App Store or Google Play link.',
                'url' => $this->appDownloadQrService->publicUrl(),
            ]),
        ]);
    }
}
