<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailTemplateMediaUploadRequest;
use App\Http\Requests\Admin\EmailTemplateSendTestRequest;
use App\Http\Requests\Admin\EmailTemplateUpdateRequest;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailNotificationController extends Controller
{
    public function __construct(private readonly EmailNotificationService $emailNotificationService)
    {
    }

    public function show(string $notificationType): Response
    {
        abort_unless(EmailNotificationTypeRegistry::isValid($notificationType), 404);

        $template = $this->emailNotificationService->findOrCreateTemplate($notificationType);

        return Inertia::render('Admin/EmailNotifications/Show', [
            'notificationType' => $notificationType,
            'notificationLabel' => EmailNotificationTypeRegistry::labelFor($notificationType),
            'notificationDescription' => EmailNotificationTypeRegistry::descriptionFor($notificationType),
            'notificationTrigger' => EmailNotificationTypeRegistry::triggerFor($notificationType),
            'template' => [
                'id' => $template->id,
                'notification_type' => $template->notification_type,
                'notification_name' => $template->notification_name,
                'is_enabled' => $template->is_enabled,
                'admin_recipients' => $template->admin_recipients ?? '',
                'subject_admin' => $template->subject_admin ?? '',
                'body_admin' => $template->body_admin ?? '',
                'subject_user' => $template->subject_user ?? '',
                'body_user' => $template->body_user ?? '',
            ],
            'availableMergeTags' => EmailNotificationTypeRegistry::mergeTagsFor($notificationType),
            'status' => session('status'),
        ]);
    }

    public function update(EmailTemplateUpdateRequest $request, string $notificationType): RedirectResponse
    {
        abort_unless(EmailNotificationTypeRegistry::isValid($notificationType), 404);
        abort_unless($request->validated()['notification_type'] === $notificationType, 422);

        $template = $this->emailNotificationService->findOrCreateTemplate($notificationType);
        $template->update($request->safe()->except('notification_type'));

        return redirect()
            ->route('admin.email-notifications.show', $notificationType)
            ->with('status', 'email-template-saved');
    }

    public function sendTest(EmailTemplateSendTestRequest $request, string $notificationType): RedirectResponse
    {
        abort_unless(EmailNotificationTypeRegistry::isValid($notificationType), 404);
        abort_unless($request->validated()['notification_type'] === $notificationType, 422);

        $this->emailNotificationService->sendTest(
            $notificationType,
            $request->validated()['send_to'],
        );

        return redirect()
            ->route('admin.email-notifications.show', $notificationType)
            ->with('status', 'email-template-test-sent');
    }

    public function uploadMedia(EmailTemplateMediaUploadRequest $request, string $notificationType): JsonResponse
    {
        abort_unless(EmailNotificationTypeRegistry::isValid($notificationType), 404);

        $media = $request->file('media');
        abort_unless($media !== null, 422);

        $path = $media->store('email-notifications/media', 'public');
        $relativeUrl = Storage::disk('public')->url($path);
        $publicUrl = url($relativeUrl);
        $fileName = $media->getClientOriginalName() ?: basename($path);
        $safeLabel = Str::of(pathinfo($fileName, PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->squish()
            ->limit(120, '')
            ->value();
        $label = $safeLabel !== '' ? $safeLabel : 'Download file';
        $isImage = str_starts_with((string) $media->getMimeType(), 'image/');

        return response()->json([
            'path' => $path,
            'url' => $publicUrl,
            'public_url' => $publicUrl,
            'file_url' => $publicUrl,
            'original_url' => $publicUrl,
            'file_name' => $fileName,
            'kind' => $isImage ? 'image' : 'file',
            'html' => $isImage
                ? sprintf(
                    '<p><img src="%s" alt="%s"></p>',
                    e($publicUrl),
                    e($label),
                )
                : sprintf(
                    '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
                    e($publicUrl),
                    e($fileName),
                ),
        ]);
    }
}
