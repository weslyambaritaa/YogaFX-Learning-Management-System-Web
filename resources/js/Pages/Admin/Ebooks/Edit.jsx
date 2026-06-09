import EbookForm from '@/Components/EbookForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditEbook({ ebook, accessTiers, status }) {
    const { data, setData, patch, processing, errors, setError, clearErrors } = useForm({
        title: ebook.title ?? '',
        file: null,
        access_tier_ids: ebook.access_tier_ids ?? [],
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.ebooks.update', ebook.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Edit Ebook
                    </h2>
                    <Link
                        href={route('admin.ebooks.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Ebooks
                    </Link>
                </div>
            }
        >
            <Head title="Edit Ebook" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {status === 'ebook-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Ebook has been created.
                        </div>
                    )}
                    {status === 'ebook-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Ebook has been updated.
                        </div>
                    )}
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <EbookForm
                            data={data}
                            setData={setData}
                            setError={setError}
                            clearErrors={clearErrors}
                            errors={errors}
                            processing={processing}
                            accessTiers={accessTiers}
                            onSubmit={submit}
                            submitLabel="Save Ebook"
                            currentFileUrl={ebook.preview_url}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
