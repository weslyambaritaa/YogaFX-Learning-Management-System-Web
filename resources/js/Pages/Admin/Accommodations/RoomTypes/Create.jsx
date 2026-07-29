import AccommodationRoomTypeForm from "@/Components/AccommodationRoomTypeForm";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function CreateAccommodationRoomType({ accommodation }) {
    const { data, setData, post, processing, errors } = useForm({
        title: "",
        price: "",
        total_rooms: "1",
        is_active: true,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route("admin.accommodations.room-types.store", accommodation.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Room Type — {accommodation.title}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add a bookable room type for this hotel.
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
            <Head title={`Create Room Type — ${accommodation.title}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AccommodationRoomTypeForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Create Room Type"
                            currencyCode={accommodation.currency_code}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
