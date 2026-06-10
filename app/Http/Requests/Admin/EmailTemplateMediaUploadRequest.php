<?php

namespace App\Http\Requests\Admin;

use App\Support\UploadConstraints;
use Illuminate\Foundation\Http\FormRequest;

class EmailTemplateMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'media' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/svg+xml,image/avif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain',
                'max:'.UploadConstraints::MAX_FILE_SIZE_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'media.required' => 'Please choose a media file first.',
            'media.file' => 'The selected media is invalid.',
            'media.mimetypes' => 'Supported media types are images, PDF, DOC, DOCX, and TXT files.',
            'media.max' => 'The media file must not be larger than '.UploadConstraints::MAX_FILE_SIZE_MB.' MB.',
        ];
    }
}
