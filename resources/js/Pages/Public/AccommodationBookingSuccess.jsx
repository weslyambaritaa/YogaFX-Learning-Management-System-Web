import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { formatCurrency } from "@/lib/currency";

function formatDateLabel(dateString) {
    const parsed = new Date(`${dateString}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateString;
    }

    return new Intl.DateTimeFormat("en-US", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(parsed);
}

export default function AccommodationBookingSuccess({ accommodation, booking }) {
    return (
        <PublicFlowLayout
            title="Booking Confirmed"
            eyebrow="Booking Confirmed"
            heading={accommodation.title}
            description={`Booking number ${booking.booking_number}`}
        >
            <div className="space-y-4 rounded-[5px] border-2 border-white/25 bg-black/25 p-6 text-sm text-white">
                <div className="flex items-center justify-between border-b border-white/15 pb-3">
                    <span className="text-white/60">Room Type</span>
                    <span className="font-medium">{booking.room_type_title}</span>
                </div>
                <div className="flex items-center justify-between border-b border-white/15 pb-3">
                    <span className="text-white/60">Guest</span>
                    <span className="font-medium">{booking.guest_name}</span>
                </div>
                <div className="flex items-center justify-between border-b border-white/15 pb-3">
                    <span className="text-white/60">Check-in</span>
                    <span className="font-medium">
                        {formatDateLabel(booking.check_in_date)}
                    </span>
                </div>
                <div className="flex items-center justify-between border-b border-white/15 pb-3">
                    <span className="text-white/60">Check-out</span>
                    <span className="font-medium">
                        {formatDateLabel(booking.check_out_date)}
                    </span>
                </div>
                <div className="flex items-center justify-between border-b border-white/15 pb-3">
                    <span className="text-white/60">
                        {booking.nights} night{booking.nights === 1 ? "" : "s"}
                    </span>
                    <span className="text-base font-semibold">
                        {formatCurrency(booking.total_amount, booking.currency_code)}
                    </span>
                </div>
                <p className="pt-2 text-xs text-white/50">
                    Please save your booking number for reference.
                </p>
            </div>
        </PublicFlowLayout>
    );
}
