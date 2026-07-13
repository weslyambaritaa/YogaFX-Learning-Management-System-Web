import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/lib/currency';

export default function PackageForm({
    data = {
        title: "",
        slug: "",
        description: "",
        image: null,
        price: "",
        currency_code: "IDR",
        is_active: true,
        installment_enabled: false,
        installment_calculation_method: "date",
        installment_count_mode: "",
        installment_count: "",
        installment_deadline_date: "",
        allowed_billing_days: [],
        access_tier_id: "",
    },
    setData,
    accessTiers = [],
    errors = {},
    processing = false,
    onSubmit,
    submitLabel = "Save Package",
    currentImageUrl = null,
    packagePublicBaseUrl = "",
}) {
    const normalizedSlug = String(data.slug ?? '').trim();
    const packagePublicLink = normalizedSlug !== ''
        ? `${String(packagePublicBaseUrl).replace(/\/$/, '')}/${normalizedSlug}`
        : '';

    const allowedBillingDays = Array.isArray(data.allowed_billing_days)
        ? data.allowed_billing_days.map((day) => Number(day))
        : [];
    const installmentEnabled = Boolean(data.installment_enabled);
    const isDateMethod =
        String(data.installment_calculation_method ?? "date") === "date";
    const isNumberMethod = !isDateMethod;
    const isFixedMode = String(data.installment_count_mode ?? "") === "fixed";

    const copyPackageLink = async () => {
        if (packagePublicLink === '') {
            return;
        }

        await navigator.clipboard.writeText(packagePublicLink);
    };

    const toggleAllowedBillingDay = (day) => {
        const numericDay = Number(day);
        const currentDays = Array.isArray(data.allowed_billing_days)
            ? data.allowed_billing_days.map((value) => Number(value))
            : [];

        const nextDays = currentDays.includes(numericDay)
            ? currentDays.filter((value) => value !== numericDay)
            : [...currentDays, numericDay];

        setData(
            'allowed_billing_days',
            [...new Set(nextDays)]
                .filter((value) => [1, 15].includes(value))
                .sort((a, b) => a - b),
        );
    };

    const handleInstallmentEnabledChange = (event) => {
        const enabled = event.target.value === '1';

        setData((currentData) => ({
            ...currentData,
            installment_enabled: enabled,
            installment_calculation_method: enabled
                ? currentData.installment_calculation_method ?? "date"
                : "date",
            installment_count_mode: enabled
                ? currentData.installment_count_mode ?? ""
                : "",
            installment_count: enabled
                ? currentData.installment_count ?? ""
                : "",
            installment_deadline_date: enabled
                ? currentData.installment_deadline_date ?? ""
                : "",
            allowed_billing_days: enabled
                ? currentData.allowed_billing_days ?? []
                : [],
        }));
    };

    const handleInstallmentMethodChange = (event) => {
        const method = event.target.value === "number" ? "number" : "date";

        setData((currentData) => ({
            ...currentData,
            installment_calculation_method: method,
            installment_deadline_date:
                method === "date"
                    ? currentData.installment_deadline_date ?? ""
                    : "",
            installment_count_mode:
                method === "number"
                    ? currentData.installment_count_mode ?? ""
                    : "",
            installment_count:
                method === "number" ? currentData.installment_count ?? "" : "",
        }));
    };

    const handleInstallmentCountModeChange = (event) => {
        setData("installment_count_mode", event.target.value);
    };

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="title" value="Package Title" />
                    <TextInput
                        id="title"
                        className="mt-1 block w-full"
                        value={data.title}
                        onChange={(event) => setData('title', event.target.value)}
                        isFocused
                    />
                    <InputError className="mt-2" message={errors.title} />
                </div>

                <div>
                    <InputLabel htmlFor="slug" value="Package Slug" />
                    <TextInput
                        id="slug"
                        className="mt-1 block w-full"
                        value={data.slug}
                        onChange={(event) => setData('slug', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.slug} />
                    <p className="mt-2 text-xs text-gray-500">
                        Direct package route uses `/{'{package_slug}'}`.
                    </p>
                </div>

                <div>
                    <InputLabel htmlFor="price" value="Package Price" />
                    <TextInput
                        id="price"
                        type="number"
                        min="0"
                        step="0.01"
                        className="mt-1 block w-full"
                        value={data.price}
                        onChange={(event) => setData('price', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.price} />
                </div>

                <div>
                    <InputLabel htmlFor="currency_code" value="Currency" />
                    <select
                        id="currency_code"
                        value={data.currency_code}
                        onChange={(event) => setData('currency_code', event.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                    >
                        <option value="IDR">Indonesian Rupiah (IDR)</option>
                        <option value="USD">US Dollar (USD)</option>
                        <option value="GBP">British Pound (GBP)</option>
                        <option value="EUR">Euro (EUR)</option>
                    </select>
                    <InputError className="mt-2" message={errors.currency_code} />
                </div>

                <div>
                    <InputLabel htmlFor="access_tier_id" value="Assign to Access Tier" />
                    <select
                        id="access_tier_id"
                        value={data.access_tier_id ?? ''}
                        onChange={(event) => setData('access_tier_id', event.target.value || null)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                    >
                        <option value="">None</option>
                        {accessTiers.map((accessTier) => (
                            <option key={accessTier.id} value={accessTier.id}>
                                {accessTier.name}
                                {!accessTier.is_active ? ' (Inactive)' : ''}
                            </option>
                        ))}
                    </select>
                    <p className="mt-2 text-xs text-gray-500">
                        Assigning this package to a tier will automatically unassign any other package currently attached to that tier.
                    </p>
                    <InputError className="mt-2" message={errors.access_tier_id} />
                </div>

                <div>
                    <InputLabel htmlFor="is_active" value="Status" />
                    <select
                        id="is_active"
                        value={data.is_active ? '1' : '0'}
                        onChange={(event) => setData('is_active', event.target.value === '1')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <InputError className="mt-2" message={errors.is_active} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="description" value="Description" />
                <textarea
                    id="description"
                    rows="4"
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div>
                <InputLabel htmlFor="image" value="Package Image" />
                <input
                    id="image"
                    type="file"
                    accept="image/*"
                    onChange={(event) => setData('image', event.target.files?.[0] ?? null)}
                    className="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError className="mt-2" message={errors.image} />

                {currentImageUrl && (
                    <div className="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                        <img
                            src={currentImageUrl}
                            alt="Current package"
                            className="h-44 w-full object-cover"
                        />
                    </div>
                )}
            </div>

            <div className="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <InputLabel htmlFor="installment_enabled" value="Installment Ready" />
                        <p className="mt-1 text-xs text-gray-500">
                            Enable this when the package should expose installment checkout.
                        </p>
                    </div>
                    <select
                        id="installment_enabled"
                        value={data.installment_enabled ? '1' : '0'}
                        onChange={handleInstallmentEnabledChange}
                        className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                    >
                        <option value="0">Disabled</option>
                        <option value="1">Enabled</option>
                    </select>
                </div>

                {installmentEnabled && (
                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="installment_calculation_method"
                                value="Installment Calculation Method"
                            />
                            <select
                                id="installment_calculation_method"
                                value={data.installment_calculation_method ?? "date"}
                                onChange={handleInstallmentMethodChange}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                            >
                                <option value="date">Date</option>
                                <option value="number">Number</option>
                            </select>
                            <InputError
                                className="mt-2"
                                message={errors.installment_calculation_method}
                            />
                        </div>

                        {isNumberMethod && (
                            <div>
                                <InputLabel
                                    htmlFor="installment_count_mode"
                                    value="Installment Count Mode"
                                />
                                <select
                                    id="installment_count_mode"
                                    value={data.installment_count_mode ?? ""}
                                    onChange={handleInstallmentCountModeChange}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                                >
                                    <option value="">Select mode</option>
                                    <option value="flex">Flex</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                                <InputError
                                    className="mt-2"
                                    message={errors.installment_count_mode}
                                />
                            </div>
                        )}

                        {isDateMethod ? (
                            <div>
                                <InputLabel
                                    htmlFor="installment_deadline_date"
                                    value="Installment Deadline Date"
                                />
                                <TextInput
                                    id="installment_deadline_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.installment_deadline_date ?? ""}
                                    onChange={(event) => setData("installment_deadline_date", event.target.value)}
                                />
                                <p className="mt-2 text-xs text-gray-500">
                                    The final date used to calculate the maximum installment count.
                                </p>
                                <InputError
                                    className="mt-2"
                                    message={errors.installment_deadline_date}
                                />
                            </div>
                        ) : (
                            <div>
                                <InputLabel htmlFor="installment_count" value="Installment Count" />
                                <TextInput
                                    id="installment_count"
                                    type="number"
                                    min="2"
                                    max="99"
                                    step="1"
                                    className="mt-1 block w-full"
                                    value={data.installment_count ?? ""}
                                    onChange={(event) => setData("installment_count", event.target.value)}
                                />
                                <p className="mt-2 text-xs text-gray-500">
                                    {isFixedMode
                                        ? "Exact number of installments that will be applied to every customer."
                                        : "Maximum number of installments the customer can select."}
                                </p>
                                <InputError className="mt-2" message={errors.installment_count} />
                            </div>
                        )}

                        <div>
                        <InputLabel value="Allowed Billing Days" />
                        <div className="mt-2 space-y-3 rounded-md border border-gray-200 bg-white p-4">
                            <label className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    checked={allowedBillingDays.includes(1)}
                                    onChange={() => toggleAllowedBillingDay(1)}
                                    disabled={!installmentEnabled}
                                    className="mt-1 rounded border-gray-300 text-black shadow-sm focus:ring-black disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-gray-900">
                                        Enable billing on day 1
                                    </span>
                                    <span className="block text-xs text-gray-500">
                                        Student can choose the 1st day of the month.
                                    </span>
                                </span>
                            </label>

                            <label className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    checked={allowedBillingDays.includes(15)}
                                    onChange={() => toggleAllowedBillingDay(15)}
                                    disabled={!installmentEnabled}
                                    className="mt-1 rounded border-gray-300 text-black shadow-sm focus:ring-black disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-gray-900">
                                        Enable billing on day 15
                                    </span>
                                    <span className="block text-xs text-gray-500">
                                        Student can choose the 15th day of the month.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <p className="mt-2 text-xs text-gray-500">
                            Admin may enable day 1, day 15, or both. During checkout,
                            the student can select only one billing day.
                        </p>

                        <InputError
                            className="mt-2"
                            message={errors.allowed_billing_days}
                        />
                        <InputError
                            className="mt-2"
                            message={errors['allowed_billing_days.0']}
                        />
                        <InputError
                            className="mt-2"
                            message={errors['allowed_billing_days.1']}
                        />
                    </div>
                    </div>
                )}
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                <p className="text-sm text-gray-500">
                    Preview: {formatCurrency(data.price || 0, data.currency_code || 'IDR')}
                </p>
            </div>
        </form>
    );
}
