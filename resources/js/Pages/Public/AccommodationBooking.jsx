import AccommodationBookingPanel from "@/Components/public/AccommodationBookingPanel";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";

// Font/heading style copied from Scoreboard.jsx's PublicFlowLayout usage.
// The copy itself is adapted for accommodation, not copied verbatim — see
// the phase report for why ("We Are Thrilled...Joining Our Masterclass" is
// Package-specific, and accommodation has no fixed price to quote upfront
// the way a package does).
const FONT_FAMILY = "'Montserrat', sans-serif";

export default function AccommodationBooking({
    accommodation,
    roomTypes,
    prefill,
    availabilityUrl,
    ordersUrl,
    installmentsUrl,
    paypal,
}) {
    return (
        <PublicFlowLayout
            title={accommodation.title}
            eyebrow="Book Your Stay"
            heading={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "clamp(26px, 3.4vw, 34px)",
                        fontWeight: 700,
                        lineHeight: 1.2,
                    }}
                >
                    {`We Are Thrilled You Will Be Staying At ${accommodation.title}`}
                </span>
            }
            description={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "18px",
                        fontWeight: 500,
                        lineHeight: 1.6,
                    }}
                >
                    Please Complete Your Booking Details Below.
                </span>
            }
        >
            <AccommodationBookingPanel
                accommodation={accommodation}
                roomTypes={roomTypes}
                prefill={prefill}
                availabilityUrl={availabilityUrl}
                ordersUrl={ordersUrl}
                installmentsUrl={installmentsUrl}
                paypal={paypal}
            />
        </PublicFlowLayout>
    );
}
