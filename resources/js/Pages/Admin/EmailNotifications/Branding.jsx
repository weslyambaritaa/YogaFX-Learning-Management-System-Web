import CkeditorField from '@/Components/CkeditorField';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function EmailBranding({ branding, statusMessage, statusTone }) {
    const errors = usePage().props.errors;

    const form = useForm({
        logo: null,
        header_html: branding.header_html ?? '',
        footer_html: branding.footer_html ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            _method: 'patch',
        }));

        form.post(route('admin.email-branding.update'), {
            forceFormData: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Email Branding" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessage && (
                        <div
                            className={`rounded-lg px-4 py-3 text-sm ${
                                statusTone === 'success'
                                    ? 'border border-emerald-200 bg-emerald-50 text-emerald-900'
                                    : statusTone === 'warning'
                                      ? 'border border-amber-200 bg-amber-50 text-amber-900'
                                      : 'border border-rose-200 bg-rose-50 text-rose-900'
                            }`}
                        >
                            {statusMessage}
                        </div>
                    )}

                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {Object.values(errors)[0]}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-lg bg-white p-6 shadow-sm"
                    >
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Global Email Branding
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Logo, header, and footer saved here are applied automatically to every
                                Email Notification template and send-test flow.
                            </p>
                        </div>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Logo</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    Upload one global logo for all notification emails.
                                </p>
                            </div>

                            {branding.logo_url ? (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4">
                                    <img
                                        src={branding.logo_url}
                                        alt="Email branding logo"
                                        className="max-h-24 w-auto object-contain"
                                    />
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    No logo uploaded yet. Emails will still send safely using the global header and footer.
                                </div>
                            )}

                            <div className="space-y-2">
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => form.setData('logo', event.target.files?.[0] ?? null)}
                                    className="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800"
                                />
                                <InputError message={errors.logo} />
                            </div>
                        </section>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Header</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears above the per-notification email body.
                                </p>
                            </div>

                            <CkeditorField
                                value={form.data.header_html}
                                onChange={(value) => form.setData('header_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.header_html)}
                            />
                            <InputError message={errors.header_html} />
                        </section>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Footer</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears below the per-notification email body.
                                </p>
                            </div>

                            <CkeditorField
                                value={form.data.footer_html}
                                onChange={(value) => form.setData('footer_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.footer_html)}
                            />
                            <InputError message={errors.footer_html} />
                        </section>

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                                className="min-w-36 bg-slate-950 text-white shadow-lg shadow-slate-950/20 hover:bg-slate-800"
                            >
                                Save Branding
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
