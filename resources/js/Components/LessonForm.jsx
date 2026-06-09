import AccessTierMultiSelect from '@/Components/AccessTierMultiSelect';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { MAX_UPLOAD_SIZE_MB, validateUploadSize } from '@/lib/uploads';

export default function LessonForm({
    data,
    setData,
    setError,
    clearErrors,
    errors,
    processing,
    modules,
    accessTiers,
    onSubmit,
    submitLabel = 'Save Lesson',
    currentThumbnailUrl = null,
    currentWorkbookUrl = null,
}) {
    const handleFileChange = (field, label) => (event) => {
        const file = event.target.files?.[0] ?? null;
        const errorMessage = validateUploadSize(file, label);

        if (errorMessage) {
            setError?.(field, errorMessage);
            setData(field, null);
            event.target.value = '';

            return;
        }

        clearErrors?.(field);
        setData(field, file);
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
                    <InputLabel htmlFor="module_id" value="Module" />
                    <select
                        id="module_id"
                        value={data.module_id}
                        onChange={(event) =>
                            setData('module_id', Number(event.target.value))
                        }
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Select module</option>
                        {modules.map((module) => (
                            <option key={module.id} value={module.id}>
                                {module.title}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.module_id} />
                </div>
            </div>

            <div>
                <InputLabel
                    htmlFor="assessment_id"
                    value="Assessment ID (Optional)"
                />
                <TextInput
                    id="assessment_id"
                    type="number"
                    min="1"
                    className="mt-1 block w-full md:max-w-sm"
                    value={data.assessment_id}
                    onChange={(event) => setData('assessment_id', event.target.value)}
                />
                <InputError className="mt-2" message={errors.assessment_id} />
            </div>

            <AccessTierMultiSelect
                value={data.access_tier_ids}
                onChange={(value) => setData('access_tier_ids', value)}
                accessTiers={accessTiers}
                error={errors.access_tier_ids}
            />

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="video" value="Video URL / Reference" />
                    <TextInput
                        id="video"
                        className="mt-1 block w-full"
                        value={data.video ?? ''}
                        onChange={(event) => setData('video', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.video} />
                </div>

                <div>
                    <InputLabel htmlFor="audio" value="Audio URL / Reference" />
                    <TextInput
                        id="audio"
                        className="mt-1 block w-full"
                        value={data.audio ?? ''}
                        onChange={(event) => setData('audio', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.audio} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="content" value="Lesson Content" />
                <textarea
                    id="content"
                    rows="8"
                    value={data.content ?? ''}
                    onChange={(event) => setData('content', event.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError className="mt-2" message={errors.content} />
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="thumbnail" value="Thumbnail" />
                    <input
                        id="thumbnail"
                        type="file"
                        accept="image/*"
                        onChange={handleFileChange('thumbnail', 'thumbnail')}
                        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                    />
                    <p className="mt-2 text-xs text-gray-500">
                        Maximum file size: {MAX_UPLOAD_SIZE_MB} MB.
                    </p>
                    <InputError className="mt-2" message={errors.thumbnail} />
                    {currentThumbnailUrl && (
                        <img
                            src={currentThumbnailUrl}
                            alt="Current lesson thumbnail"
                            className="mt-4 h-36 w-full rounded-lg object-cover md:w-64"
                        />
                    )}
                </div>

                <div>
                    <InputLabel htmlFor="workbook" value="Workbook File" />
                    <input
                        id="workbook"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        onChange={handleFileChange('workbook', 'workbook file')}
                        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                    />
                    <p className="mt-2 text-xs text-gray-500">
                        Maximum file size: {MAX_UPLOAD_SIZE_MB} MB.
                    </p>
                    <InputError className="mt-2" message={errors.workbook} />
                    {currentWorkbookUrl && (
                        <a
                            href={currentWorkbookUrl}
                            className="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Open current workbook
                        </a>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
