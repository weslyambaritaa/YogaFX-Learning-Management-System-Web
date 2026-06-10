import EbookForm from '@/Components/EbookForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateEbook({ accessTiers }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        title: '',
        file: null,
        access_tier_ids: [],
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.ebooks.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Ebook
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
            <Head title="Create Ebook" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
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
                            submitLabel="Create Ebook"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
