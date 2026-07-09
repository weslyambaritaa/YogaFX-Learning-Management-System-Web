<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupportSettingUpdateRequest;
use App\Services\SupportSettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupportSettingController extends Controller
{
    public function __construct(
        private readonly SupportSettingService $supportSettingService,
    ) {}

    public function show(): Response
    {
        $setting = $this->supportSettingService->current();

        return Inertia::render('Admin/SupportSettings/Show', [
            'supportSetting' => [
                'support_whatsapp' => $setting->support_whatsapp,
                'support_email' => $setting->support_email,
            ],
            'status' => session('status'),
        ]);
    }

    public function update(SupportSettingUpdateRequest $request): RedirectResponse
    {
        $setting = $this->supportSettingService->current();
        $setting->update($request->validated());

        return redirect()
            ->route('admin.support-settings.show')
            ->with('status', 'support-setting-updated');
    }
}
