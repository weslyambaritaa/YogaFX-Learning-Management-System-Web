import AccommodationForm from "@/Components/AccommodationForm";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function CreateAccommodation({ publicBaseUrl = "" }) {
    const { data, setData, post, processing, errors } = useForm({
        title: "",
        slug: "",
        description: "",
        image: null,
        currency_code: "IDR",
        is_active: true,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route("admin.accommodations.store"), {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Accommodation
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add a new hotel that guests can book from a public link.
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
            <Head title="Create Accommodation" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <AccommodationForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Create Accommodation"
                            currentImageUrl={null}
                            publicBaseUrl={publicBaseUrl}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
