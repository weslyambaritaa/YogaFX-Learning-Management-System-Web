import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { useRestoreIndexFilters } from '@/lib/useIndexPageMemory';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

function statusBadgeVariant(status) {
    if (status === 'confirmed') {
        return 'secondary';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    return 'outline';
}

function statusLabel(status) {
    const labels = {
        pending_payment: 'Pending Payment',
        confirmed: 'Confirmed',
        cancelled: 'Cancelled',
        expired: 'Expired',
    };

    return labels[status] ?? status;
}

function StatusMessage({ status }) {
    if (status !== 'booking-cancelled') {
        return null;
    }

    return (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Booking has been cancelled.
        </div>
    );
}

function Pagination({ paginator }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-4">
            <p className="text-sm text-slate-500">
                Showing {paginator.from ?? 0} to {paginator.to ?? 0} of {paginator.total} bookings
            </p>

            {paginator.last_page > 1 ? (
                <div className="flex flex-wrap items-center gap-2">
                    {paginator.links.map((link, index) => (
                        <button
                            key={`${link.label}-${index}`}
                            type="button"
                            disabled={!link.url}
                            onClick={() =>
                                link.url
                                    ? router.visit(link.url, {
                                          preserveScroll: true,
                                          preserveState: true,
                                      })
                                    : null
                            }
                            className={[
                                'rounded-md border px-3 py-1.5 text-sm transition',
                                link.active
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : '',
                            ].join(' ')}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export default function AccommodationBookingsIndex({ bookings, accommodations, filters, status }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? 'all');
    const [accommodationFilter, setAccommodationFilter] = useState(filters.accommodation_id ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [perPage, setPerPage] = useState(String(filters.per_page ?? 15));

    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusFilter(filters.status ?? 'all');
        setAccommodationFilter(filters.accommodation_id ?? '');
        setDateFrom(filters.date_from ?? '');
        setDateTo(filters.date_to ?? '');
        setPerPage(String(filters.per_page ?? 15));
    }, [filters]);

    useRestoreIndexFilters('admin-accommodation-bookings-index', {
        search,
        status: statusFilter,
        accommodation_id: accommodationFilter,
        date_from: dateFrom,
        date_to: dateTo,
        per_page: perPage,
        page: bookings.current_page,
    });

    const applyFilters = (overrides = {}) => {
        const query = {
            search,
            status: statusFilter,
            accommodation_id: accommodationFilter,
            date_from: dateFrom,
            date_to: dateTo,
            per_page: perPage,
            ...overrides,
        };

        router.get(route('admin.accommodation-bookings.index'), query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters();
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Accommodation Bookings
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        All hotel bookings across every accommodation.
                    </p>
                </div>
            }
        >
            <Head title="Accommodation Bookings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <StatusMessage status={status} />

                    <div className="overflow-hidden rounded-[5px] bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-5">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Booking Directory
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    {bookings.total} booking{bookings.total === 1 ? '' : 's'} found.
                                </p>
                            </div>

                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                <form onSubmit={submitSearch} className="relative sm:col-span-2">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Search name, email, booking number..."
                                        className="h-10 rounded-[5px] pl-9"
                                    />
                                </form>

                                <select
                                    value={statusFilter}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setStatusFilter(value);
                                        applyFilters({ status: value, page: 1 });
                                    }}
                                    className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                >
                                    <option value="all">All Status</option>
                                    <option value="pending_payment">Pending Payment</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="expired">Expired</option>
                                </select>

                                <select
                                    value={accommodationFilter}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setAccommodationFilter(value);
                                        applyFilters({ accommodation_id: value, page: 1 });
                                    }}
                                    className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                >
                                    <option value="">All Hotels</option>
                                    {accommodations.map((accommodation) => (
                                        <option key={accommodation.id} value={accommodation.id}>
                                            {accommodation.title}
                                        </option>
                                    ))}
                                </select>

                                <input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setDateFrom(value);
                                        applyFilters({ date_from: value, page: 1 });
                                    }}
                                    className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    aria-label="Check-in from"
                                />

                                <input
                                    type="date"
                                    value={dateTo}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setDateTo(value);
                                        applyFilters({ date_to: value, page: 1 });
                                    }}
                                    className="h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    aria-label="Check-out until"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Booking</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Hotel</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Room Type</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Guest</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Check-in</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Check-out</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Total</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-left font-medium text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {bookings.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="px-4 py-10 text-center text-sm text-slate-500">
                                                No bookings matched the current filters.
                                            </td>
                                        </tr>
                                    ) : (
                                        bookings.data.map((booking) => (
                                            <tr key={booking.id}>
                                                <td className="px-4 py-4 font-medium text-slate-900">
                                                    {booking.booking_number}
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {booking.accommodation_title}
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {booking.room_type_title}
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    <div>{booking.guest_name}</div>
                                                    <div className="text-xs text-slate-500">{booking.guest_email}</div>
                                                </td>
                                                <td className="px-4 py-4 text-slate-700">{booking.check_in_date}</td>
                                                <td className="px-4 py-4 text-slate-700">{booking.check_out_date}</td>
                                                <td className="px-4 py-4 text-slate-700">
                                                    {formatCurrency(booking.total_amount, booking.currency_code)}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Badge variant={statusBadgeVariant(booking.status)}>
                                                        {statusLabel(booking.status)}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Link
                                                        href={route('admin.accommodation-bookings.show', booking.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination paginator={bookings} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
