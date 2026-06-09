import AccessTierMultiSelect from '@/Components/AccessTierMultiSelect';
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

            <AccessTierMultiSelect
                value={data.access_tier_ids}
                onChange={(value) => setData('access_tier_ids', value)}
                accessTiers={accessTiers}
                error={errors.access_tier_ids}
            />

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
                    >
                        Preview current ebook file
                    </a>
                )}
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
