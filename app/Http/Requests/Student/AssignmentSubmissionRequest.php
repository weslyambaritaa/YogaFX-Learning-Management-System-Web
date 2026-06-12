<?php

namespace App\Http\Requests\Student;

use App\Support\UploadConstraints;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class AssignmentSubmissionRequest extends FormRequest
{
    private const SUPPORTED_VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'video/x-msvideo',
        'video/x-m4v',
        'application/octet-stream',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->isStudent();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'max:'.UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_KB,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if (! in_array($extension, ['mp4', 'mov', 'webm', 'avi', 'm4v'], true)) {
                        $fail('Assignment video must use a supported video extension such as MP4, MOV, WEBM, AVI, or M4V.');

                        return;
                    }

                    $mimeType = $value->getMimeType() ?: $value->getClientMimeType();

                    if ($mimeType && (in_array($mimeType, self::SUPPORTED_VIDEO_MIME_TYPES, true) || str_starts_with($mimeType, 'video/'))) {
                        return;
                    }

                    $fail('Assignment video must be uploaded as a supported video file.');
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'video.max' => 'The assignment video must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_MB).'.',
        ];
    }
}
