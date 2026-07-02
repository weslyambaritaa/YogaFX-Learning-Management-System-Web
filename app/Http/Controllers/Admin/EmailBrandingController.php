<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailBrandingUpdateRequest;
use App\Services\EmailBrandingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailBrandingController extends Controller
{
    use HandlesLocalUploads;

    public function __construct(
        private readonly EmailBrandingService $emailBrandingService,
    ) {}

    public function show(): Response
    {
        $branding = $this->emailBrandingService->findOrCreateBranding();

        return Inertia::render('Admin/EmailNotifications/Branding', [
            'branding' => [
                'id' => $branding->id,
                'logo_url' => $this->emailBrandingService->logoPreviewUrl($branding),
                'header_html' => $branding->header_html,
                'footer_html' => $branding->footer_html,
            ],
            'statusMessage' => session('status_message'),
            'statusTone' => session('status_tone'),
        ]);
    }

    public function update(EmailBrandingUpdateRequest $request): RedirectResponse
    {
        $branding = $this->emailBrandingService->findOrCreateBranding();
        $validated = $request->validated();

        $logoPath = $this->storeUploadedFile(
            $request->file('logo'),
            'email-branding/logos',
            $branding->logo_path,
        );

        $branding->update([
            'logo_path' => $logoPath,
            'header_html' => $validated['header_html'] ?? null,
            'footer_html' => $validated['footer_html'] ?? null,
        ]);

        return redirect()
            ->route('admin.email-branding.show')
            ->with([
                'status' => 'email-branding-saved',
                'status_message' => 'Email branding has been saved.',
                'status_tone' => 'success',
            ]);
    }
}
