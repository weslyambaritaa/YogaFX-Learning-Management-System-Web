import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/lib/currency';

export default function PackageForm({
    data,
    setData,
    accessTiers,
    errors,
    processing,
    onSubmit,
    submitLabel = 'Save Package',
    currentImageUrl = null,
    packagePublicBaseUrl = '',
}) {
    const normalizedSlug = String(data.slug ?? '').trim();
    const packagePublicLink = normalizedSlug !== ''
        ? `${String(packagePublicBaseUrl).replace(/\/$/, '')}/${normalizedSlug}`
        : '';

    const copyPackageLink = async () => {
        if (packagePublicLink === '') {
            return;
        }

        await navigator.clipboard.writeText(packagePublicLink);
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

            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-sm font-medium text-slate-900">Direct Package Public Link</div>
                <div className="mt-2 break-all text-sm text-slate-700">
                    {packagePublicLink || 'Add a package slug to generate the direct package link.'}
                </div>
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <a
                        href={packagePublicLink || '#'}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`inline-flex rounded-md border px-3 py-2 text-sm font-medium transition ${packagePublicLink ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'pointer-events-none border-slate-200 bg-white text-slate-400'}`}
                    >
                        Open Package Link
                    </a>
                    <button
                        type="button"
                        onClick={copyPackageLink}
                        disabled={packagePublicLink === ''}
                        className={`inline-flex rounded-md border px-3 py-2 text-sm font-medium transition ${packagePublicLink ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100' : 'cursor-not-allowed border-slate-200 bg-white text-slate-400'}`}
                    >
                        Copy Package Link
                    </button>
                </div>
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
                            Enable this when the package should expose installment checkout using the package billing configuration.
                        </p>
                    </div>
                    <select
                        id="installment_enabled"
                        value={data.installment_enabled ? '1' : '0'}
                        onChange={(event) => setData('installment_enabled', event.target.value === '1')}
                        className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                    >
                        <option value="0">Disabled</option>
                        <option value="1">Enabled</option>
                    </select>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="billing_interval_unit" value="Billing Interval Unit" />
                        <TextInput
                            id="billing_interval_unit"
                            className="mt-1 block w-full"
                            value={data.billing_interval_unit}
                            onChange={(event) => setData('billing_interval_unit', event.target.value)}
                            placeholder="MONTH"
                        />
                        <InputError className="mt-2" message={errors.billing_interval_unit} />
                    </div>

                    <div>
                        <InputLabel htmlFor="billing_interval_count" value="Billing Interval Count" />
                        <TextInput
                            id="billing_interval_count"
                            type="number"
                            min="1"
                            step="1"
                            className="mt-1 block w-full"
                            value={data.billing_interval_count}
                            onChange={(event) => setData('billing_interval_count', event.target.value)}
                        />
                        <InputError className="mt-2" message={errors.billing_interval_count} />
                    </div>

                    <div>
                        <InputLabel htmlFor="installment_deadline_month" value="Installment Deadline Month" />
                        <TextInput
                            id="installment_deadline_month"
                            type="number"
                            min="1"
                            max="12"
                            step="1"
                            className="mt-1 block w-full"
                            value={data.installment_deadline_month}
                            onChange={(event) => setData('installment_deadline_month', event.target.value)}
                        />
                        <InputError className="mt-2" message={errors.installment_deadline_month} />
                    </div>

                    <div>
                        <InputLabel htmlFor="installment_deadline_day" value="Installment Deadline Day" />
                        <TextInput
                            id="installment_deadline_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                            className="mt-1 block w-full"
                            value={data.installment_deadline_day}
                            onChange={(event) => setData('installment_deadline_day', event.target.value)}
                        />
                        <InputError className="mt-2" message={errors.installment_deadline_day} />
                    </div>
                </div>

                <div className="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    Billing day options are now part of the public checkout experience.
                    Monthly installment packages keep using their stored checkout rules, while
                    pay-full and non-monthly package flows do not expose a billing-day picker here.
                </div>
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
