import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function SupportSettingsShow({ supportSetting, status }) {
    const { data, setData, patch, processing, errors } = useForm({
        support_whatsapp: supportSetting.support_whatsapp ?? '',
        support_email: supportSetting.support_email ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.support-settings.update'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Contact Support
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage the WhatsApp number and support email used by suspended-account notices.
                    </p>
                </div>
            }
        >
            <Head title="Contact Support" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'support-setting-updated' ? (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Contact support settings have been updated.
                        </div>
                    ) : null}

                    <div className="rounded-[5px] bg-white p-6 shadow-sm">
                        <form onSubmit={submit} className="space-y-6">
                            <div>
                                <label
                                    htmlFor="support_whatsapp"
                                    className="text-sm font-medium text-gray-700"
                                >
                                    Support WhatsApp
                                </label>
                                <input
                                    id="support_whatsapp"
                                    value={data.support_whatsapp}
                                    onChange={(event) =>
                                        setData('support_whatsapp', event.target.value)
                                    }
                                    placeholder="6281234567890"
                                    className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    Use international format so the WhatsApp button can open directly.
                                </p>
                                {errors.support_whatsapp ? (
                                    <div className="mt-2 text-sm text-rose-600">
                                        {errors.support_whatsapp}
                                    </div>
                                ) : null}
                            </div>

                            <div>
                                <label
                                    htmlFor="support_email"
                                    className="text-sm font-medium text-gray-700"
                                >
                                    Support Email
                                </label>
                                <input
                                    id="support_email"
                                    type="email"
                                    value={data.support_email}
                                    onChange={(event) =>
                                        setData('support_email', event.target.value)
                                    }
                                    placeholder="support@yogafx.com"
                                    className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {errors.support_email ? (
                                    <div className="mt-2 text-sm text-rose-600">
                                        {errors.support_email}
                                    </div>
                                ) : null}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                            >
                                Save Contact Support
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
