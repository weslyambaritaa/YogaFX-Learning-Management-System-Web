import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
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

    return (
        <AuthenticatedLayout
            header={
                <div>
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
                <div className="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
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

                    <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
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

                            <div className="grid gap-6 lg:grid-cols-2">
                                <div className="space-y-4 rounded-lg border border-slate-200 p-4">
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

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700">
                                            Admin body
                                        </label>
                                        <Textarea
                                            value={templateForm.data.body_admin}
                                            onChange={(event) =>
                                                templateForm.setData(
                                                    'body_admin',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Admin body"
                                            className="min-h-40"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-4 rounded-lg border border-slate-200 p-4">
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

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700">
                                            User body
                                        </label>
                                        <Textarea
                                            value={templateForm.data.body_user}
                                            onChange={(event) =>
                                                templateForm.setData(
                                                    'body_user',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="User body"
                                            className="min-h-40"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={templateForm.processing}>
                                    Save Changes
                                </Button>
                            </div>
                        </form>

                        <div className="space-y-6">
                            <div className="rounded-lg bg-white p-6 shadow-sm">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Trigger Context
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    {notificationTrigger}
                                </p>
                            </div>

                            <div className="rounded-lg bg-white p-6 shadow-sm">
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

                                    <Button type="submit" disabled={testForm.processing}>
                                        Send Test
                                    </Button>
                                </form>
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
