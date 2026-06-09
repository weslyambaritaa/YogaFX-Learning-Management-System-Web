export const MAX_UPLOAD_SIZE_BYTES = 10 * 1024 * 1024;
export const MAX_UPLOAD_SIZE_MB = 10;

export function maxUploadSizeMessage(fieldLabel = 'file') {
    return `The ${fieldLabel} must not be larger than ${MAX_UPLOAD_SIZE_MB} MB.`;
}

export function validateUploadSize(file, fieldLabel = 'file') {
    if (!file) {
        return null;
    }

    return file.size > MAX_UPLOAD_SIZE_BYTES
        ? maxUploadSizeMessage(fieldLabel)
        : null;
}
