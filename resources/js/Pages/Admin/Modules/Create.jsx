import ModuleForm from '@/Components/ModuleForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateModule({ accessTiers }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        title: '',
        url_slug: '',
        thumbnail: null,
        access_tier_ids: [],
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.modules.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Module
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
            <Head title="Create Module" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
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
