import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AccommodationsIndex({ accommodations, status }) {
    const errors = usePage().props.errors;
    const [copyToast, setCopyToast] = useState('');

    const copyPublicLink = async (publicLink) => {
        try {
            await navigator.clipboard.writeText(publicLink);
            setCopyToast('Public link copied successfully.');
        } catch (error) {
            setCopyToast('Unable to copy the public link. Please try again.');
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Accommodations
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage hotels available for public booking.
                        </p>
                    </div>

                    <Link
                        href={route('admin.accommodations.create')}
                        className="w-full rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700 sm:w-auto"
                    >
                        Create Accommodation
                    </Link>
                </div>
            }
        >
            <Head title="Accommodations" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {copyToast && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {copyToast}
                        </div>
                    )}
                    {status === 'accommodation-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Accommodation has been created.
                        </div>
                    )}
                    {status === 'accommodation-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Accommodation has been updated.
                        </div>
                    )}
                    {status === 'accommodation-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Accommodation has been deleted.
                        </div>
                    )}
                    {errors.accommodation && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.accommodation}
                        </div>
                    )}

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-4 py-4 sm:px-6">
                            <h3 className="text-lg font-semibold text-gray-900">
                                Hotel Directory
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Each hotel has its own room types and public booking link.
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Image</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Hotel</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Public Link</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Currency</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Room Types</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {accommodations.map((accommodation) => (
                                        <tr key={accommodation.id}>
                                            <td className="px-4 py-3">
                                                {accommodation.image_url ? (
                                                    <img
                                                        src={accommodation.image_url}
                                                        alt={accommodation.title}
                                                        className="h-14 w-20 rounded-md object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-14 w-20 items-center justify-center rounded-md border border-dashed border-gray-300 bg-gray-50 text-[11px] uppercase tracking-[0.18em] text-gray-400">
                                                        No image
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-gray-900">{accommodation.title}</div>
                                                <div className="text-xs text-gray-500">{accommodation.slug}</div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                <div className="space-y-2">
                                                    <div className="break-all text-xs text-gray-500">
                                                        {accommodation.public_link}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => copyPublicLink(accommodation.public_link)}
                                                        className="inline-flex rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                                    >
                                                        Copy Public Link
                                                    </button>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{accommodation.currency_code}</td>
                                            <td className="px-4 py-3 text-gray-700">{accommodation.room_types_count}</td>
                                            <td className="px-4 py-3">
                                                <span className={accommodation.is_active ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700' : 'rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700'}>
                                                    {accommodation.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-4">
                                                    <Link
                                                        href={route('admin.accommodations.room-types.index', accommodation.id)}
                                                        className="text-sm font-medium text-slate-700 hover:text-slate-900"
                                                    >
                                                        Room Types
                                                    </Link>
                                                    <Link
                                                        href={route('admin.accommodations.edit', accommodation.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <DeleteConfirmationDialog
                                                        href={route('admin.accommodations.destroy', accommodation.id)}
                                                        title="Delete accommodation?"
                                                        description={`This will permanently delete "${accommodation.title}". This is blocked if it still has room types.`}
                                                    />
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
