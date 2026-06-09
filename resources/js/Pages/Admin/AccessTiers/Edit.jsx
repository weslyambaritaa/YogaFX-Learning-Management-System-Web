import AccessTierForm from '@/Components/AccessTierForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditAccessTier({ accessTier, status }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: accessTier.name ?? '',
        slug: accessTier.slug ?? '',
        description: accessTier.description ?? '',
        is_active: accessTier.is_active ?? true,
    });

    const submit = (event) => {
        event.preventDefault();

        patch(route('admin.access-tiers.update', accessTier.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Edit Access Tier
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Update tier metadata and availability status.
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
            <Head title="Edit Access Tier" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">
                                Assigned Students
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {accessTier.users_count}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">Status</div>
                            <div className="mt-2">
                                <span
                                    className={
                                        accessTier.is_active
                                            ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700'
                                            : 'rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700'
                                    }
                                >
                                    {accessTier.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {status === 'access-tier-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Access tier has been created.
                        </div>
                    )}

                    {status === 'access-tier-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Access tier has been updated.
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AccessTierForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Access Tier"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
