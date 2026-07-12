<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LinkControlSettingUpdateRequest;
use App\Services\AppDownloadQrService;
use App\Services\LinkControlSettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LinkControlSettingController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly LinkControlSettingService $linkControlSettingService,
        private readonly AppDownloadQrService $appDownloadQrService,
    ) {}

    public function show(): Response
    {
        $setting = $this->linkControlSettingService->current();

        return Inertia::render('Admin/LinkControl/Show', [
            'linkControlSetting' => [
                'qr_image_url' => $this->protectedMediaUrl(
                    'link-control-setting',
                    $setting->id,
                    'qr_image',
                    $setting->qr_image,
                    versionSeed: $setting->updated_at,
                ),
                'google_play_url' => $setting->google_play_url,
                'app_store_url' => $setting->app_store_url,
                'download_page_url' => $this->appDownloadQrService->publicUrl(),
            ],
            'status' => session('status'),
            'warning' => session('warning'),
        ]);
    }

    public function update(LinkControlSettingUpdateRequest $request): RedirectResponse
    {
        $setting = $this->linkControlSettingService->current();
        $data = $request->validated();
        $status = 'link-control-updated';
        $warning = null;

        $setting->update($data);

        $qrResult = $this->appDownloadQrService->regenerate(
            $setting->qr_image,
        );
        $qrPath = $qrResult['path'] ?? $setting->qr_image;

        if ($qrPath !== $setting->qr_image) {
            $setting->update([
                'qr_image' => $qrPath,
            ]);
        }

        if (filled($qrResult['warning'] ?? null)) {
            $status = 'link-control-updated-with-warning';
            $warning = $qrResult['warning'];
        }

        return redirect()
            ->route('admin.link-control.show')
            ->with('status', $status)
            ->with('warning', $warning);
    }
}
