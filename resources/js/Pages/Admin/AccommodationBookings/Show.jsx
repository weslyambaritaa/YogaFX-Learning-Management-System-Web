import { Badge } from '@/Components/ui/badge';
import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { Head, Link, router, usePage } from '@inertiajs/react';

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

function Row({ label, value }) {
    return (
        <div className="flex items-center justify-between border-b border-slate-100 py-3 text-sm last:border-b-0">
            <span className="text-slate-500">{label}</span>
            <span className="font-medium text-slate-900">{value ?? '—'}</span>
        </div>
    );
}

export default function AccommodationBookingShow({ booking, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Booking {booking.booking_number}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {booking.accommodation?.title ?? 'Unknown hotel'}
                        </p>
                    </div>

                    <Link
                        href={route('admin.accommodation-bookings.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Bookings
                    </Link>
                </div>
            }
        >
            <Head title={`Booking ${booking.booking_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'booking-cancelled' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Booking has been cancelled.
                        </div>
                    )}
                    {errors.booking && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.booking}
                        </div>
                    )}

                    <div className="flex items-center justify-between rounded-lg bg-white p-6 shadow-sm">
                        <div>
                            <div className="text-sm text-slate-500">Status</div>
                            <div className="mt-1">
                                <Badge variant={statusBadgeVariant(booking.status)}>
                                    {statusLabel(booking.status)}
                                </Badge>
                            </div>
                        </div>

                        {booking.status === 'confirmed' && (
                            <DeleteConfirmationDialog
                                onConfirm={({ onFinish }) =>
                                    router.post(
                                        route('admin.accommodation-bookings.cancel', booking.id),
                                        {},
                                        { onFinish },
                                    )
                                }
                                title="Cancel this booking?"
                                description="This marks the booking as cancelled and immediately frees the room for other guests. PayPal does NOT get refunded automatically — you must process the refund manually in the PayPal dashboard."
                                triggerLabel="Cancel Booking"
                                confirmLabel="Yes, Cancel Booking"
                            />
                        )}
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Stay Details
                        </h3>
                        <Row label="Hotel" value={booking.accommodation?.title} />
                        <Row label="Room Type" value={booking.room_type?.title} />
                        <Row label="Check-in" value={booking.check_in_date} />
                        <Row label="Check-out" value={booking.check_out_date} />
                        <Row label="Nights" value={booking.nights} />
                        <Row
                            label="Price per Night"
                            value={formatCurrency(booking.price_per_night, booking.currency_code)}
                        />
                        <Row
                            label="Total Amount"
                            value={formatCurrency(booking.total_amount, booking.currency_code)}
                        />
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Guest
                        </h3>
                        <Row label="Name" value={booking.guest_name} />
                        <Row label="Email" value={booking.guest_email} />
                        <Row label="Phone" value={booking.guest_phone} />
                        <Row
                            label="Linked Student Account"
                            value={booking.user ? `${booking.user.name} (${booking.user.email})` : 'None — guest booking'}
                        />
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Payment
                        </h3>
                        <Row label="PayPal Order ID" value={booking.paypal_order_id} />
                        <Row label="Hold Expires At" value={booking.hold_expires_at} />
                        <Row label="Paid At" value={booking.paid_at} />
                        <Row label="Cancelled At" value={booking.cancelled_at} />
                        <Row label="Created At" value={booking.created_at} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
