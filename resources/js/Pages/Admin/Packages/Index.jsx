import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { Head, Link, usePage } from '@inertiajs/react';

export default function PackagesIndex({ packages, status }) {
    const errors = usePage().props.errors;

    const copyPackageLink = async (publicLink) => {
        await navigator.clipboard.writeText(publicLink);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Packages
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage public commercial offers separately from access entitlements.
                        </p>
                    </div>

                    <Link
                        href={route('admin.packages.create')}
                        className="w-full rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700 sm:w-auto"
                    >
                        Create Package
                    </Link>
                </div>
            }
        >
            <Head title="Packages" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'package-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Package has been created.
                        </div>
                    )}
                    {status === 'package-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Package has been updated.
                        </div>
                    )}
                    {status === 'package-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Package has been deleted.
                        </div>
                    )}
                    {errors.package && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.package}
                        </div>
                    )}

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-4 py-4 sm:px-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900">
                                        Package Directory
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Keep public commercial offers organized by pricing,
                                        assigned tier, and checkout readiness.
                                    </p>
                                </div>

                                <Button
                                    asChild
                                    className="w-full rounded-[5px] bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 sm:w-auto"
                                >
                                    <Link href={route('admin.packages.create')}>
                                        Add Package
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Image</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Package</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Slug</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Price</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Public Link</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Assigned Tier</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Usage</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {packages.map((pkg) => (
                                        <tr key={pkg.id}>
                                            <td className="px-4 py-3">
                                                {pkg.image_url ? (
                                                    <img
                                                        src={pkg.image_url}
                                                        alt={pkg.title}
                                                        className="h-14 w-20 rounded-md object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-14 w-20 items-center justify-center rounded-md border border-dashed border-gray-300 bg-gray-50 text-[11px] uppercase tracking-[0.18em] text-gray-400">
                                                        No image
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-gray-900">{pkg.title}</div>
                                                <div className="text-xs text-gray-500">{pkg.description || 'No description yet.'}</div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{pkg.slug}</td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {formatCurrency(pkg.price, pkg.currency_code)}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                <div className="space-y-2">
                                                    <div className="break-all text-xs text-gray-500">
                                                        {pkg.public_link}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        <a
                                                            href={pkg.public_link}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100"
                                                        >
                                                            Open Package Link
                                                        </a>
                                                        <button
                                                            type="button"
                                                            onClick={() => copyPackageLink(pkg.public_link)}
                                                            className="inline-flex rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                                        >
                                                            Copy Package Link
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {pkg.access_tier?.name ?? 'None'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col gap-2">
                                                    <span className={pkg.is_active ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700' : 'rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700'}>
                                                        {pkg.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                    <span className={pkg.installment_enabled ? 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700' : 'rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700'}>
                                                        {pkg.installment_enabled ? 'Installment Ready' : 'One-time Only'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                <div>Pending registrations: {pkg.pending_registrations_count}</div>
                                                <div>Invoices: {pkg.invoices_count}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-4">
                                                    <Link
                                                        href={route('admin.packages.edit', pkg.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <Link
                                                        href={route('admin.packages.destroy', pkg.id)}
                                                        method="delete"
                                                        as="button"
                                                        className="text-sm font-medium text-rose-600 hover:text-rose-800"
                                                    >
                                                        Delete
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
