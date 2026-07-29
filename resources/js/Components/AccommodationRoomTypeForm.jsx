import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import { formatCurrency } from "@/lib/currency";

export default function AccommodationRoomTypeForm({
    data,
    setData,
    errors = {},
    processing = false,
    onSubmit,
    submitLabel = "Save Room Type",
    currencyCode = "USD",
}) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div>
                <InputLabel htmlFor="title" value="Room Type Title" />
                <TextInput
                    id="title"
                    className="mt-1 block w-full"
                    value={data.title}
                    onChange={(event) => setData("title", event.target.value)}
                    isFocused
                />
                <InputError className="mt-2" message={errors.title} />
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="price" value={`Price per Night (${currencyCode})`} />
                    <TextInput
                        id="price"
                        type="number"
                        min="0.01"
                        step="0.01"
                        className="mt-1 block w-full"
                        value={data.price}
                        onChange={(event) => setData("price", event.target.value)}
                    />
                    {data.price !== "" && !Number.isNaN(Number(data.price)) && (
                        <p className="mt-2 text-xs text-gray-500">
                            {formatCurrency(Number(data.price), currencyCode)} per night
                        </p>
                    )}
                    <InputError className="mt-2" message={errors.price} />
                </div>

                <div>
                    <InputLabel htmlFor="total_rooms" value="Total Rooms" />
                    <TextInput
                        id="total_rooms"
                        type="number"
                        min="1"
                        step="1"
                        className="mt-1 block w-full"
                        value={data.total_rooms}
                        onChange={(event) => setData("total_rooms", event.target.value)}
                    />
                    <p className="mt-2 text-xs text-gray-500">
                        Physical room count. Availability per date is calculated from bookings, not stored separately.
                    </p>
                    <InputError className="mt-2" message={errors.total_rooms} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="is_active" value="Status" />
                <select
                    id="is_active"
                    value={data.is_active ? "1" : "0"}
                    onChange={(event) =>
                        setData("is_active", event.target.value === "1")
                    }
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <InputError className="mt-2" message={errors.is_active} />
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
