import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { MAX_UPLOAD_SIZE_MB, validateUploadSize } from '@/lib/uploads';

export default function CourseForm({
    data,
    setData,
    setError,
    clearErrors,
    errors,
    processing,
    accessTiers,
    onSubmit,
    submitLabel = 'Save Course',
    currentThumbnailUrl = null,
}) {
    const handleThumbnailChange = (event) => {
        const file = event.target.files?.[0] ?? null;
        const errorMessage = validateUploadSize(file, 'thumbnail');

        if (errorMessage) {
            setError?.('thumbnail', errorMessage);
            setData('thumbnail', null);
            event.target.value = '';

            return;
        }

        clearErrors?.('thumbnail');
        setData('thumbnail', file);
    };

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

            <div className="grid gap-6 md:grid-cols-2">
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

                <div>
                    <InputLabel htmlFor="video" value="Video URL / Reference" />
                    <TextInput
                        id="video"
                        className="mt-1 block w-full"
                        value={data.video}
                        onChange={(event) => setData('video', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.video} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="description" value="Description" />
                <textarea
                    id="description"
                    rows="6"
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div>
                <InputLabel htmlFor="thumbnail" value="Thumbnail" />
                <input
                    id="thumbnail"
                    type="file"
                    accept="image/*"
                    onChange={handleThumbnailChange}
                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                />
                <p className="mt-2 text-xs text-gray-500">
                    Maximum file size: {MAX_UPLOAD_SIZE_MB} MB.
                </p>
                <InputError className="mt-2" message={errors.thumbnail} />
                {currentThumbnailUrl && (
                    <img
                        src={currentThumbnailUrl}
                        alt="Current course thumbnail"
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
