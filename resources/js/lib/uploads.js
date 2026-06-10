export const MAX_UPLOAD_SIZE_BYTES = 10 * 1024 * 1024;
export const MAX_UPLOAD_SIZE_MB = 10;
export const DEFAULT_MAX_UPLOAD_SIZE_LABEL = `${MAX_UPLOAD_SIZE_MB} MB`;

export function maxUploadSizeMessage(
    fieldLabel = 'file',
    maxUploadSizeLabel = DEFAULT_MAX_UPLOAD_SIZE_LABEL,
) {
    return `The ${fieldLabel} must not be larger than ${maxUploadSizeLabel}.`;
}

export function validateUploadSize(
    file,
    fieldLabel = 'file',
    {
        maxUploadSizeBytes = MAX_UPLOAD_SIZE_BYTES,
        maxUploadSizeLabel = DEFAULT_MAX_UPLOAD_SIZE_LABEL,
    } = {},
) {
    if (!file) {
        return null;
    }

    return file.size > maxUploadSizeBytes
        ? maxUploadSizeMessage(fieldLabel, maxUploadSizeLabel)
        : null;
}
