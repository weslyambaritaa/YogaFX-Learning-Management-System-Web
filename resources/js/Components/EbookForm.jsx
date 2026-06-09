import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function EbookForm({
    data,
    setData,
    errors,
    processing,
    accessTiers,
    onSubmit,
    submitLabel = 'Save Ebook',
    currentFileUrl = null,
}) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="title" value="Title" />
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
                    <InputLabel htmlFor="access_tier_id" value="Access Tier" />
                    <select
                        id="access_tier_id"
                        value={data.access_tier_id}
                        onChange={(event) =>
                            setData('access_tier_id', Number(event.target.value))
                        }
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Select access tier</option>
                        {accessTiers.map((accessTier) => (
                            <option key={accessTier.id} value={accessTier.id}>
                                {accessTier.name}
                                {!accessTier.is_active ? ' (Inactive)' : ''}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.access_tier_id} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="file" value="Ebook File" />
                <input
                    id="file"
                    type="file"
                    accept=".pdf"
                    onChange={(event) =>
                        setData('file', event.target.files?.[0] ?? null)
                    }
                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                />
                <InputError className="mt-2" message={errors.file} />
                {currentFileUrl && (
                    <a
                        href={currentFileUrl}
                        className="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        target="_blank"
                        rel="noreferrer"
                    >
                        Open current ebook file
                    </a>
                )}
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
