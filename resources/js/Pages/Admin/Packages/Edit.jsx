import PackageForm from "@/Components/PackageForm";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function EditPackage({
    package: pkg,
    accessTiers,
    status,
    packagePublicBaseUrl,
}) {
    const { data, setData, patch, processing, errors } = useForm({
        title: pkg.title ?? "",
        slug: pkg.slug ?? "",
        description: pkg.description ?? "",
        payment_type: pkg.payment_type ?? "paid",
        image: null,
        price: pkg.price ?? "",
        minimum_donation_amount: pkg.minimum_donation_amount ?? "",
        suggested_donation_amount: pkg.suggested_donation_amount ?? "",
        currency_code: pkg.currency_code ?? "IDR",
        is_active: pkg.is_active ?? true,
        installment_enabled: pkg.installment_enabled ?? false,
        setup_fee: pkg.setup_fee ?? "",
        installment_calculation_method:
            pkg.installment_calculation_method ?? "date",
        installment_count_mode: pkg.installment_count_mode ?? "",
        installment_count: pkg.installment_count ?? "",
        installment_deadline_date: pkg.installment_deadline_date ?? "",
        allowed_billing_days: pkg.allowed_billing_days ?? [],
        access_tier_id: pkg.access_tier_id ?? "",
    });

    const submit = (event) => {
        event.preventDefault();

        patch(route("admin.packages.update", pkg.id), {
            forceFormData: true,
        });
    };

    const copyPackageLink = async () => {
        await navigator.clipboard.writeText(pkg.public_link);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Edit Package
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Update package pricing, assignment, and public offer
                            details safely.
                        </p>
                    </div>

                    <Link
                        href={route("admin.packages.index")}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Packages
                    </Link>
                </div>
            }
        >
            <Head title="Edit Package" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">
                                Pending Registrations
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {pkg.pending_registrations_count}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">
                                Invoices
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {pkg.invoices_count}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm md:col-span-2">
                            <div className="text-sm text-gray-500">
                                Direct Package Public Link
                            </div>
                            <div className="mt-2 break-all text-sm font-medium text-gray-900">
                                {pkg.public_link}
                            </div>
                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <a
                                    href={pkg.public_link}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                                >
                                    Open Package Link
                                </a>
                                <button
                                    type="button"
                                    onClick={copyPackageLink}
                                    className="inline-flex rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                                >
                                    Copy Package Link
                                </button>
                            </div>
                        </div>
                    </div>

                    {status === "package-created" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Package has been created.
                        </div>
                    )}
                    {status === "package-updated" && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Package has been updated.
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <PackageForm
                            data={data}
                            setData={setData}
                            accessTiers={accessTiers}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Package"
                            currentImageUrl={pkg.image_url}
                            packagePublicBaseUrl={packagePublicBaseUrl}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
