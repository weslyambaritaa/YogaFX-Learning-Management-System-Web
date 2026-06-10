import { useRef, useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import CkeditorField from '@/Components/CkeditorField';
import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const statusMessages = {
    'email-template-saved': 'Email template has been saved.',
    'email-template-test-sent': 'Test email has been sent and logged.',
};

export default function EmailNotificationShow({
    notificationType,
    notificationLabel,
    notificationDescription,
    notificationTrigger,
    template,
    availableMergeTags,
    status,
}) {
    const errors = usePage().props.errors;
    const adminEditorRef = useRef(null);
    const userEditorRef = useRef(null);
    const adminMediaInputRef = useRef(null);
    const userMediaInputRef = useRef(null);
    const [mediaState, setMediaState] = useState({
        admin: { processing: false, message: '' },
        user: { processing: false, message: '' },
    });

    const templateForm = useForm({
        notification_type: notificationType,
        is_enabled: template.is_enabled,
        admin_recipients: template.admin_recipients,
        subject_admin: template.subject_admin,
        body_admin: template.body_admin,
        subject_user: template.subject_user,
        body_user: template.body_user,
    });

    const testForm = useForm({
        notification_type: notificationType,
        send_to: '',
    });

    const submitTemplate = (event) => {
        event.preventDefault();
        templateForm.patch(route('admin.email-notifications.update', notificationType));
    };

    const submitTest = (event) => {
        event.preventDefault();
        testForm.post(route('admin.email-notifications.send-test', notificationType));
    };

    const readXsrfToken = () => {
        const xsrfCookie = document.cookie
            .split('; ')
            .find((item) => item.startsWith('XSRF-TOKEN='));

        return xsrfCookie ? decodeURIComponent(xsrfCookie.split('=').slice(1).join('=')) : '';
    };

    const normalizeMediaUrl = (result) => {
        const candidate = [
            result?.public_url,
            result?.file_url,
            result?.original_url,
            result?.preview_url,
            result?.url,
            result?.path,
        ].find((value) => typeof value === 'string' && value.trim() !== '');

        if (!candidate) {
            return '';
        }

        try {
            return new URL(candidate, window.location.origin).href;
        } catch {
            return '';
        }
    };

    const escapeHtml = (value) =>
        String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

    const buildMediaHtml = (result) => {
        const publicUrl = normalizeMediaUrl(result);

        if (!publicUrl) {
            return '';
        }

        const fileName = String(result?.file_name ?? 'Download file');
        const label = fileName.replace(/\.[^.]+$/, '').replaceAll(/[-_]+/g, ' ').trim() || 'Media';

        if (result?.kind === 'image') {
            return `<p><img src="${escapeHtml(publicUrl)}" alt="${escapeHtml(label)}"></p>`;
        }

        return `<p><a href="${escapeHtml(publicUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(fileName)}</a></p>`;
    };

    const setMediaFeedback = (target, nextState) => {
        setMediaState((current) => ({
            ...current,
            [target]: {
                ...current[target],
                ...nextState,
            },
        }));
    };

    const openMediaPicker = (target) => {
        if (target === 'admin') {
            adminMediaInputRef.current?.click();
            return;
        }

        userMediaInputRef.current?.click();
    };

    const handleMediaSelected = async (target, event) => {
        const file = event.target.files?.[0] ?? null;
        event.target.value = '';

        if (!file) {
            return;
        }

        setMediaFeedback(target, {
            processing: true,
            message: '',
        });

        const payload = new FormData();
        payload.append('media', file);

        try {
            const response = await fetch(
                route('admin.email-notifications.media', notificationType),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': readXsrfToken(),
                    },
                    body: payload,
                    credentials: 'same-origin',
                },
            );

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                const uploadError =
                    result?.message ??
                    result?.errors?.media?.[0] ??
                    'Media upload failed. Please try again.';

                throw new Error(uploadError);
            }

            const mediaHtml = buildMediaHtml(result);

            if (!mediaHtml) {
                throw new Error('Media upload succeeded, but the server did not return a valid public URL.');
            }

            const inserted = target === 'admin'
                ? adminEditorRef.current?.insertHtml(mediaHtml)
                : userEditorRef.current?.insertHtml(mediaHtml);

            if (!inserted) {
                throw new Error('Editor is not ready yet. Please try again.');
            }

            setMediaFeedback(target, {
                processing: false,
                message: `${result.file_name ?? 'Media'} inserted into the template.`,
            });
        } catch (error) {
            setMediaFeedback(target, {
                processing: false,
                message: error instanceof Error
                    ? error.message
                    : 'Media upload failed. Please try again.',
            });
        }
    };

    const renderMediaControls = (target) => (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => openMediaPicker(target)}
                    disabled={mediaState[target].processing || templateForm.processing}
                >
                    {mediaState[target].processing ? 'Uploading...' : 'Add Media'}
                </Button>

                <p className="text-xs text-slate-500">
                    Upload an image or document, then insert it into the email body as HTML.
                </p>
            </div>

            <input
                ref={target === 'admin' ? adminMediaInputRef : userMediaInputRef}
                type="file"
                accept="image/*,.pdf,.doc,.docx,.txt"
                className="hidden"
                onChange={(event) => handleMediaSelected(target, event)}
            />

            {mediaState[target].message && (
                <p
                    className={`text-xs ${
                        mediaState[target].message.includes('inserted')
                            ? 'text-emerald-600'
                            : 'text-rose-600'
                    }`}
                >
                    {mediaState[target].message}
                </p>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {notificationLabel}
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        {notificationDescription}
                    </p>
                </div>
            }
        >
            <Head title={notificationLabel} />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}

                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {Object.values(errors)[0]}
                        </div>
                    )}

                    <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                        <div className="space-y-6">
                            <form
                                onSubmit={submitTemplate}
                                className="space-y-6 rounded-lg bg-white p-6 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 className="text-lg font-semibold text-slate-900">
                                            Email Notification
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-500">
                                            Save the template used by automated
                                            triggers and operational emails for this
                                            notification type.
                                        </p>
                                    </div>

                                    <Badge
                                        variant={
                                            templateForm.data.is_enabled
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {templateForm.data.is_enabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </div>

                                <label className="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        checked={templateForm.data.is_enabled}
                                        onChange={(event) =>
                                            templateForm.setData(
                                                'is_enabled',
                                                event.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                                    />
                                    <div>
                                        <div className="text-sm font-medium text-slate-900">
                                            Enable notification
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            Automated trigger will only send when
                                            this notification is enabled.
                                        </div>
                                    </div>
                                </label>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        Admin recipients
                                    </label>
                                    <Textarea
                                        value={templateForm.data.admin_recipients}
                                        onChange={(event) =>
                                            templateForm.setData(
                                                'admin_recipients',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="admin@yogafx.test, ops@yogafx.test"
                                        className="min-h-24"
                                    />
                                    <p className="text-xs text-slate-500">
                                        Separate multiple addresses with commas,
                                        spaces, or new lines.
                                    </p>
                                </div>

                                <div className="space-y-6">
                                    <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                                        <div>
                                            <h4 className="font-medium text-slate-900">
                                                Admin Email
                                            </h4>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Sent to admin recipients when admin
                                                copy is configured.
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-sm font-medium text-slate-700">
                                                Admin subject
                                            </label>
                                            <Input
                                                value={templateForm.data.subject_admin}
                                                onChange={(event) =>
                                                    templateForm.setData(
                                                        'subject_admin',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Admin subject"
                                            />
                                        </div>

                                        <div className="space-y-3">
                                            <label className="text-sm font-medium text-slate-700">
                                                Admin body
                                            </label>
                                            {renderMediaControls('admin')}
                                            <CkeditorField
                                                ref={adminEditorRef}
                                                value={templateForm.data.body_admin}
                                                onChange={(value) =>
                                                    templateForm.setData(
                                                        'body_admin',
                                                        value,
                                                    )
                                                }
                                                disabled={templateForm.processing}
                                                invalid={Boolean(errors.body_admin)}
                                            />
                                            <InputError message={errors.body_admin} />
                                        </div>
                                    </section>

                                    <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                                        <div>
                                            <h4 className="font-medium text-slate-900">
                                                User Email
                                            </h4>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Sent to the user when user-facing
                                                content is configured.
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-sm font-medium text-slate-700">
                                                User subject
                                            </label>
                                            <Input
                                                value={templateForm.data.subject_user}
                                                onChange={(event) =>
                                                    templateForm.setData(
                                                        'subject_user',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="User subject"
                                            />
                                        </div>

                                        <div className="space-y-3">
                                            <label className="text-sm font-medium text-slate-700">
                                                User body
                                            </label>
                                            {renderMediaControls('user')}
                                            <CkeditorField
                                                ref={userEditorRef}
                                                value={templateForm.data.body_user}
                                                onChange={(value) =>
                                                    templateForm.setData(
                                                        'body_user',
                                                        value,
                                                    )
                                                }
                                                disabled={templateForm.processing}
                                                invalid={Boolean(errors.body_user)}
                                            />
                                            <InputError message={errors.body_user} />
                                        </div>
                                    </section>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={templateForm.processing}
                                        className="min-w-36 bg-slate-950 text-white shadow-lg shadow-slate-950/20 hover:bg-slate-800"
                                    >
                                        Save Changes
                                    </Button>
                                </div>
                            </form>

                            <div className="rounded-lg bg-white p-6 shadow-sm">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 className="text-lg font-semibold text-slate-900">
                                            Trigger Context
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {notificationTrigger}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg bg-white p-6 shadow-sm">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Available Merge Tags
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Use these placeholders inside subject and
                                    body fields.
                                </p>

                                <div className="mt-4 flex flex-wrap gap-2">
                                    {availableMergeTags.map((mergeTag) => (
                                        <Badge key={mergeTag} variant="outline">
                                            {mergeTag}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-6">
                            <div className="rounded-lg bg-white p-6 shadow-sm xl:sticky xl:top-24">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Send Test
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Send the current template to one email
                                    address using sample data.
                                </p>

                                <form onSubmit={submitTest} className="mt-4 space-y-4">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700">
                                            Send To
                                        </label>
                                        <Input
                                            type="email"
                                            value={testForm.data.send_to}
                                            onChange={(event) =>
                                                testForm.setData('send_to', event.target.value)
                                            }
                                            placeholder="test@example.com"
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={testForm.processing}
                                        className="w-full bg-slate-900 text-white shadow-lg shadow-slate-900/20 hover:bg-slate-800"
                                    >
                                        Send Test
                                    </Button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
