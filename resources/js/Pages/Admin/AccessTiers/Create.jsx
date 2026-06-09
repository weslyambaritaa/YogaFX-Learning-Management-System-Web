import AccessTierForm from '@/Components/AccessTierForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateAccessTier() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        description: '',
        is_active: true,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('admin.access-tiers.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Access Tier
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add a new membership tier for student assignment.
                        </p>
                    </div>

                    <Link
                        href={route('admin.access-tiers.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Access Tiers
                    </Link>
                </div>
            }
        >
            <Head title="Create Access Tier" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AccessTierForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Create Access Tier"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
