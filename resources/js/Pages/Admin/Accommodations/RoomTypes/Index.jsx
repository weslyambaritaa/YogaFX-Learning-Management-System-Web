import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { formatCurrency } from '@/lib/currency';
import { Head, Link, usePage } from '@inertiajs/react';

export default function AccommodationRoomTypesIndex({ accommodation, roomTypes, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Room Types — {accommodation.title}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage room types and nightly pricing for this hotel.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Link
                            href={route('admin.accommodations.index')}
                            className="rounded-md border border-slate-200 bg-white px-4 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Back to Accommodations
                        </Link>
                        <Link
                            href={route('admin.accommodations.room-types.create', accommodation.id)}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Create Room Type
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Room Types — ${accommodation.title}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'room-type-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Room type has been created.
                        </div>
                    )}
                    {status === 'room-type-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Room type has been updated.
                        </div>
                    )}
                    {status === 'room-type-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Room type has been deleted.
                        </div>
                    )}
                    {errors.room_type && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.room_type}
                        </div>
                    )}

                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Room Type</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Price / Night</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Total Rooms</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Bookings</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {roomTypes.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-6 text-center text-sm text-gray-500">
                                                No room types yet for this hotel.
                                            </td>
                                        </tr>
                                    )}
                                    {roomTypes.map((roomType) => (
                                        <tr key={roomType.id}>
                                            <td className="px-4 py-3 font-medium text-gray-900">
                                                {roomType.title}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {formatCurrency(roomType.price, accommodation.currency_code)}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{roomType.total_rooms}</td>
                                            <td className="px-4 py-3 text-gray-700">{roomType.bookings_count}</td>
                                            <td className="px-4 py-3">
                                                <span className={roomType.is_active ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700' : 'rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700'}>
                                                    {roomType.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-4">
                                                    <Link
                                                        href={route('admin.accommodations.room-types.edit', {
                                                            accommodation: accommodation.id,
                                                            roomType: roomType.id,
                                                        })}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <DeleteConfirmationDialog
                                                        href={route('admin.accommodations.room-types.destroy', {
                                                            accommodation: accommodation.id,
                                                            roomType: roomType.id,
                                                        })}
                                                        title="Delete room type?"
                                                        description={`This will permanently delete "${roomType.title}". This is blocked if it already has booking history.`}
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
