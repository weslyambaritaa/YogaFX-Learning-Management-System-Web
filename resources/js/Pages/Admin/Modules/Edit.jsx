import ModuleForm from '@/Components/ModuleForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditModule({ module, accessTiers, status }) {
    const { data, setData, patch, processing, errors, setError, clearErrors } = useForm({
        title: module.title ?? '',
        url_slug: module.url_slug ?? '',
        thumbnail: null,
        access_tier_ids: module.access_tier_ids ?? [],
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.modules.update', module.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Edit Module
                    </h2>
                    <Link
                        href={route('admin.modules.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Modules
                    </Link>
                </div>
            }
        >
            <Head title="Edit Module" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {status === 'module-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been created.
                        </div>
                    )}
                    {status === 'module-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been updated.
                        </div>
                    )}
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
                            submitLabel="Save Module"
                            currentThumbnailUrl={module.thumbnail_url}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
