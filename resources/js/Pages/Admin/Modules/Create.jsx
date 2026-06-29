import ModuleForm from '@/Components/ModuleForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateModule({ accessTiers, nextSortOrder }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        title: '',
        description: '',
        sort_order: String(nextSortOrder ?? 1),
        url_slug: '',
        certificate_enabled: false,
        ebook_enabled: false,
        video_lecturer_enabled: false,
        thumbnail: null,
        access_tier_ids: [],
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.modules.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Module" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-end">
                        <Link
                            href={route('admin.modules.index')}
                            className="inline-flex items-center rounded-md border border-gray-900 bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-700"
                        >
                            Back to Modules
                        </Link>
                    </div>
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <ModuleForm
                            data={data}
                            setData={setData}
                            setError={setError}
                            clearErrors={clearErrors}
                            errors={errors}
                            processing={processing}
                            accessTiers={accessTiers}
                            onSubmit={submit}
                            submitLabel="Create Module"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
