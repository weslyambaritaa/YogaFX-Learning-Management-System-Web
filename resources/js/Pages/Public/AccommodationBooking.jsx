import AccommodationBookingPanel from "@/Components/public/AccommodationBookingPanel";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";

export default function AccommodationBooking({
    accommodation,
    roomTypes,
    prefill,
    availabilityUrl,
    ordersUrl,
    paypal,
}) {
    return (
        <PublicFlowLayout
            title={accommodation.title}
            eyebrow="Book Your Stay"
            heading={accommodation.title}
            description={accommodation.description}
        >
            <AccommodationBookingPanel
                accommodation={accommodation}
                roomTypes={roomTypes}
                prefill={prefill}
                availabilityUrl={availabilityUrl}
                ordersUrl={ordersUrl}
                paypal={paypal}
            />
        </PublicFlowLayout>
    );
}
