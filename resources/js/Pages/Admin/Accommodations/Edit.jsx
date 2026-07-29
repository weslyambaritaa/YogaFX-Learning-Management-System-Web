import AccommodationForm from "@/Components/AccommodationForm";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function EditAccommodation({ accommodation, publicBaseUrl = "" }) {
    const { data, setData, patch, processing, errors } = useForm({
        title: accommodation.title,
        slug: accommodation.slug,
        description: accommodation.description ?? "",
        image: null,
        currency_code: accommodation.currency_code,
        is_active: accommodation.is_active,
    });

    const submit = (event) => {
        event.preventDefault();

        patch(route("admin.accommodations.update", accommodation.id), {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Edit Accommodation
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Update hotel details, currency, and status.
                        </p>
                    </div>

                    <Link
                        href={route("admin.accommodations.index")}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Accommodations
                    </Link>
                </div>
            }
        >
            <Head title="Edit Accommodation" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AccommodationForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Changes"
                            currentImageUrl={accommodation.image_url}
                            publicBaseUrl={publicBaseUrl}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
