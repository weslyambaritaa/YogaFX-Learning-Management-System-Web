import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";

const CURRENCY_OPTIONS = ["IDR", "USD", "GBP", "EUR"];

export default function AccommodationForm({
    data,
    setData,
    errors = {},
    processing = false,
    onSubmit,
    submitLabel = "Save Accommodation",
    currentImageUrl = null,
    publicBaseUrl = "",
}) {
    const normalizedSlug = String(data.slug ?? "").trim();
    const publicLink =
        normalizedSlug !== ""
            ? `${String(publicBaseUrl).replace(/\/$/, "")}/${normalizedSlug}`
            : "";

    const copyPublicLink = async () => {
        if (publicLink === "") {
            return;
        }

        await navigator.clipboard.writeText(publicLink);
    };

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="title" value="Hotel Title" />
                    <TextInput
                        id="title"
                        className="mt-1 block w-full"
                        value={data.title}
                        onChange={(event) => setData("title", event.target.value)}
                        isFocused
                    />
                    <InputError className="mt-2" message={errors.title} />
                </div>

                <div>
                    <InputLabel htmlFor="slug" value="Slug" />
                    <TextInput
                        id="slug"
                        className="mt-1 block w-full"
                        value={data.slug}
                        onChange={(event) => setData("slug", event.target.value)}
                    />
                    <p className="mt-2 text-xs text-gray-500">
                        Lowercase letters, numbers, and dashes only. Used in the public link below.
                    </p>
                    <InputError className="mt-2" message={errors.slug} />
                </div>
            </div>

            {publicLink !== "" && (
                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <span className="break-all text-xs text-slate-600">{publicLink}</span>
                    <button
                        type="button"
                        onClick={copyPublicLink}
                        className="inline-flex rounded-md border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                    >
                        Copy Public Link
                    </button>
                </div>
            )}

            <div>
                <InputLabel htmlFor="description" value="Description" />
                <textarea
                    id="description"
                    rows="4"
                    value={data.description}
                    onChange={(event) => setData("description", event.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div>
                <InputLabel htmlFor="image" value="Hotel Image" />
                <input
                    id="image"
                    type="file"
                    accept="image/*"
                    onChange={(event) =>
                        setData("image", event.target.files?.[0] ?? null)
                    }
                    className="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p className="mt-2 text-xs text-gray-500">Maximum file size: 10 MB.</p>
                <InputError className="mt-2" message={errors.image} />

                {currentImageUrl && (
                    <div className="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                        <img
                            src={currentImageUrl}
                            alt="Current accommodation"
                            className="h-44 w-full object-cover"
                        />
                    </div>
                )}
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="currency_code" value="Currency" />
                    <select
                        id="currency_code"
                        value={data.currency_code}
                        onChange={(event) => setData("currency_code", event.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                    >
                        {CURRENCY_OPTIONS.map((currency) => (
                            <option key={currency} value={currency}>
                                {currency}
                            </option>
                        ))}
                    </select>
                    <p className="mt-2 text-xs text-gray-500">
                        All room types under this hotel are priced in this currency.
                    </p>
                    <InputError className="mt-2" message={errors.currency_code} />
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
                    <p className="mt-2 text-xs text-gray-500">
                        Inactive hotels are not reachable from the public booking link.
                    </p>
                    <InputError className="mt-2" message={errors.is_active} />
                </div>
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
