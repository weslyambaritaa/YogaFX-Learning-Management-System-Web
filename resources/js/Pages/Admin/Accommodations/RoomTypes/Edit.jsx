import AccommodationRoomTypeForm from "@/Components/AccommodationRoomTypeForm";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function EditAccommodationRoomType({ accommodation, roomType }) {
    const { data, setData, patch, processing, errors } = useForm({
        title: roomType.title,
        price: roomType.price,
        total_rooms: roomType.total_rooms,
        is_active: roomType.is_active,
    });

    const submit = (event) => {
        event.preventDefault();

        patch(
            route("admin.accommodations.room-types.update", {
                accommodation: accommodation.id,
                roomType: roomType.id,
            }),
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Edit Room Type — {accommodation.title}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Price changes only apply to new bookings — existing
                            bookings keep their original price snapshot.
                        </p>
                    </div>

                    <Link
                        href={route("admin.accommodations.room-types.index", accommodation.id)}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Room Types
                    </Link>
                </div>
            }
        >
            <Head title={`Edit Room Type — ${accommodation.title}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="text-sm text-gray-500">Bookings on Record</div>
                        <div className="mt-1 text-2xl font-semibold text-gray-900">
                            {roomType.bookings_count}
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AccommodationRoomTypeForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Changes"
                            currencyCode={accommodation.currency_code}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
