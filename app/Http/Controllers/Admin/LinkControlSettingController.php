<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LinkControlSettingUpdateRequest;
use App\Services\LinkControlSettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LinkControlSettingController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function __construct(
        private readonly LinkControlSettingService $linkControlSettingService,
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
            ],
            'status' => session('status'),
        ]);
    }

    public function update(LinkControlSettingUpdateRequest $request): RedirectResponse
    {
        $setting = $this->linkControlSettingService->current();
        $data = $request->validated();

        $data['qr_image'] = $this->storeUploadedFileToBunnyWithLocalFallback(
            $request->file('qr_image'),
            'link-control/qr-images',
            $setting->qr_image,
        );

        $setting->update($data);

        return redirect()
            ->route('admin.link-control.show')
            ->with('status', 'link-control-updated');
    }
}
