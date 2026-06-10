import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function AccessTiersIndex({ accessTiers, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Access Tiers
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage membership tiers before they are enforced in
                            future learning domains.
                        </p>
                    </div>

                    <Link
                        href={route('admin.access-tiers.create')}
                        className="w-full rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700 sm:w-auto"
                    >
                        Create Access Tier
                    </Link>
                </div>
            }
        >
            <Head title="Access Tiers" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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

                    {status === 'access-tier-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Access tier has been deleted.
                        </div>
                    )}

                    {errors.access_tier && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.access_tier}
                        </div>
                    )}

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Tier
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Slug
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Students
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {accessTiers.map((accessTier) => (
                                        <tr key={accessTier.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-gray-900">
                                                    {accessTier.name}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {accessTier.description}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {accessTier.slug}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={
                                                        accessTier.is_active
                                                            ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700'
                                                            : 'rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700'
                                                    }
                                                >
                                                    {accessTier.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {accessTier.users_count}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <Link
                                                        href={route(
                                                            'admin.access-tiers.edit',
                                                            accessTier.id,
                                                        )}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>

                                                    <Link
                                                        href={route(
                                                            'admin.access-tiers.destroy',
                                                            accessTier.id,
                                                        )}
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
