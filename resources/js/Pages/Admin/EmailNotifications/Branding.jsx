import CkeditorField from '@/Components/CkeditorField';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function EmailBranding({ branding, statusMessage, statusTone }) {
    const errors = usePage().props.errors;

    const form = useForm({
        logo_html: branding.logo_html ?? '',
        email_header_html: branding.email_header_html ?? '',
        email_signature_html: branding.email_signature_html ?? '',
        pdf_header_html: branding.pdf_header_html ?? '',
        watermark_html: branding.watermark_html ?? '',
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
                                Manage shared branding blocks for email and PDF output from one place.
                            </p>
                        </div>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Logo</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears above the header and can contain image HTML or branded text.
                                </p>
                            </div>

                            {branding.logo_html ? (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4">
                                    <div
                                        className="prose prose-sm max-w-none"
                                        dangerouslySetInnerHTML={{ __html: branding.logo_html }}
                                    />
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    No logo content saved yet. Emails will still send safely using the global header and footer.
                                </div>
                            )}

                            <CkeditorField
                                value={form.data.logo_html}
                                onChange={(value) => form.setData('logo_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.logo_html)}
                            />
                            <InputError message={errors.logo_html} />
                        </section>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Email Header</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears above the per-notification email body.
                                </p>
                            </div>

                            <CkeditorField
                                value={form.data.email_header_html}
                                onChange={(value) => form.setData('email_header_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.email_header_html)}
                            />
                            <InputError message={errors.email_header_html} />
                        </section>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Email Signature</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears below the per-notification email body.
                                </p>
                            </div>

                            <CkeditorField
                                value={form.data.email_signature_html}
                                onChange={(value) => form.setData('email_signature_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.email_signature_html)}
                            />
                            <InputError message={errors.email_signature_html} />
                        </section>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">PDF Header</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears at the top of invoice and confirmation PDFs.
                                </p>
                            </div>

                            <CkeditorField
                                value={form.data.pdf_header_html}
                                onChange={(value) => form.setData('pdf_header_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.pdf_header_html)}
                            />
                            <InputError message={errors.pdf_header_html} />
                        </section>

                        <section className="space-y-4 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="font-medium text-slate-900">Watermark</h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    This content appears behind the PDF body as the watermark layer.
                                </p>
                            </div>

                            <CkeditorField
                                value={form.data.watermark_html}
                                onChange={(value) => form.setData('watermark_html', value)}
                                disabled={form.processing}
                                invalid={Boolean(errors.watermark_html)}
                            />
                            <InputError message={errors.watermark_html} />
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
