import PackageForm from '@/Components/PackageForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreatePackage({ accessTiers, packagePublicBaseUrl }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        slug: '',
        description: '',
        image: null,
        price: '',
        currency_code: 'IDR',
        is_active: true,
        installment_enabled: false,
        billing_interval_unit: '',
        billing_interval_count: '',
        installment_deadline_month: '',
        installment_deadline_day: '',
        access_tier_id: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('admin.packages.store'), {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Package
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add a new public commercial offer without changing learning entitlements directly.
                        </p>
                    </div>

                    <Link
                        href={route('admin.packages.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Packages
                    </Link>
                </div>
            }
        >
            <Head title="Create Package" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <PackageForm
                            data={data}
                            setData={setData}
                            accessTiers={accessTiers}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Create Package"
                            currentImageUrl={null}
                            packagePublicBaseUrl={packagePublicBaseUrl}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
