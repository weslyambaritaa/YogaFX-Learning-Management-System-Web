import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck, Plus } from 'lucide-react';

export default function AccessTiersIndex({ accessTiers, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Access Tiers</h2>
                    <p className="text-sm text-gray-500">Manage membership tiers before they are enforced in future learning domains.</p>
                </div>
            }
        >
            <Head title="Access Tiers" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                    {status === 'access-tier-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Access tier has been created.</div>
                    )}
                    {status === 'access-tier-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Access tier has been updated.</div>
                    )}
                    {status === 'access-tier-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Access tier has been deleted.</div>
                    )}
                    {errors.access_tier && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{errors.access_tier}</div>
                    )}

                    {/* Card */}
                    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                        {/* Card header */}
                        <div className="flex items-center justify-between px-5 py-4">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-gray-500 shrink-0" />
                                <span className="text-base font-semibold text-gray-800">Access Tier List</span>
                            </div>
                            <Link
                                href={route('admin.access-tiers.create')}
                                className="flex h-10 items-center gap-1.5 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-700 whitespace-nowrap"
                            >
                                <Plus className="h-4 w-4" />
                                Create Access Tier
                            </Link>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full table-fixed text-sm">
                                <colgroup>
                                    <col style={{ width: '30%' }} />
                                    <col style={{ width: '20%' }} />
                                    <col style={{ width: '120px' }} />
                                    <col style={{ width: '100px' }} />
                                    <col style={{ width: '130px' }} />
                                </colgroup>
                                <thead>
                                    <tr className="bg-gray-900 text-white">
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Tier</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Slug</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Students</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {accessTiers.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-10 text-center text-sm text-gray-400">No access tiers yet.</td>
                                        </tr>
                                    ) : (
                                        accessTiers.map((accessTier) => (
                                            <tr key={accessTier.id} className="align-middle hover:bg-gray-50" style={{ height: '60px' }}>
                                                {/* Tier name + description */}
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-gray-900">{accessTier.name}</div>
                                                    {accessTier.description && (
                                                        <div className="text-xs text-gray-400 mt-0.5 line-clamp-1">{accessTier.description}</div>
                                                    )}
                                                </td>
                                                {/* Slug */}
                                                <td className="px-4 py-3 text-gray-600">{accessTier.slug}</td>
                                                {/* Status badge */}
                                                <td className="px-4 py-3">
                                                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                        accessTier.is_active
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-gray-100 text-gray-600'
                                                    }`}>
                                                        {accessTier.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {/* Students count */}
                                                <td className="px-4 py-3 text-gray-600">{accessTier.users_count}</td>
                                                {/* Action */}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <Link
                                                            href={route('admin.access-tiers.edit', accessTier.id)}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <Link
                                                            href={route('admin.access-tiers.destroy', accessTier.id)}
                                                            method="delete"
                                                            as="button"
                                                            className="text-sm font-medium text-rose-600 hover:text-rose-800"
                                                        >
                                                            Delete
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Footer */}
                        <div className="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">
                            {accessTiers.length} tier{accessTiers.length !== 1 ? 's' : ''} total
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}