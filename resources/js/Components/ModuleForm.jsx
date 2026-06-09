import AccessTierMultiSelect from '@/Components/AccessTierMultiSelect';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function ModuleForm({
    data,
    setData,
    errors,
    processing,
    accessTiers,
    onSubmit,
    submitLabel = 'Save Module',
    currentThumbnailUrl = null,
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
                    <InputLabel htmlFor="url_slug" value="URL Slug" />
                    <TextInput
                        id="url_slug"
                        className="mt-1 block w-full"
                        value={data.url_slug}
                        onChange={(event) => setData('url_slug', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.url_slug} />
                </div>
            </div>

            <AccessTierMultiSelect
                value={data.access_tier_ids}
                onChange={(value) => setData('access_tier_ids', value)}
                accessTiers={accessTiers}
                error={errors.access_tier_ids}
            />

            <div>
                <InputLabel htmlFor="thumbnail" value="Thumbnail" />
                <input
                    id="thumbnail"
                    type="file"
                    accept="image/*"
                    onChange={(event) =>
                        setData('thumbnail', event.target.files?.[0] ?? null)
                    }
                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                />
                <InputError className="mt-2" message={errors.thumbnail} />
                {currentThumbnailUrl && (
                    <img
                        src={currentThumbnailUrl}
                        alt="Current module thumbnail"
                        className="mt-4 h-36 w-full rounded-lg object-cover md:w-64"
                    />
                )}
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
