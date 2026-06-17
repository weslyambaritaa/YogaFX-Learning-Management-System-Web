import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function AccessTierForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel = 'Save Access Tier',
    currentThumbnailUrl = null,
}) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name" value="Tier Name" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        isFocused
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="slug" value="Slug" />
                    <TextInput
                        id="slug"
                        className="mt-1 block w-full"
                        value={data.slug}
                        onChange={(event) => setData('slug', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.slug} />
                </div>

                <div>
                    <InputLabel htmlFor="price_amount" value="Program Price" />
                    <TextInput
                        id="price_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        className="mt-1 block w-full"
                        value={data.price_amount}
                        onChange={(event) => setData('price_amount', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.price_amount} />
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
                <InputLabel htmlFor="thumbnail" value="Thumbnail" />
                <input
                    id="thumbnail"
                    type="file"
                    accept="image/*"
                    onChange={(event) => setData('thumbnail', event.target.files?.[0] ?? null)}
                    className="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p className="mt-2 text-xs text-gray-500">
                    Upload an image thumbnail up to 10 MB for this access tier.
                </p>
                <InputError className="mt-2" message={errors.thumbnail} />

                {currentThumbnailUrl && (
                    <div className="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                        <img
                            src={currentThumbnailUrl}
                            alt="Current access tier thumbnail"
                            className="h-44 w-full object-cover"
                        />
                    </div>
                )}
            </div>

            <div>
                <InputLabel htmlFor="is_active" value="Status" />
                <select
                    id="is_active"
                    value={data.is_active ? '1' : '0'}
                    onChange={(event) =>
                        setData('is_active', event.target.value === '1')
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
