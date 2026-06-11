<?php

namespace App\Http\Requests\Admin;

use App\Models\AccessTier;
use App\Models\Module;
use App\Services\BunnyStreamService;
use App\Support\UploadConstraints;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LessonRequest extends FormRequest
{
    private const SUPPORTED_THUMBNAIL_MIME_TYPES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/webp',
        'image/svg+xml',
        'image/avif',
        'image/heic',
        'image/heif',
    ];

    private const SUPPORTED_AUDIO_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/x-mpeg',
        'application/octet-stream',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $lessonVideoId = $this->input('lesson_video_id');

        if (! is_string($lessonVideoId)) {
            return;
        }

        $normalizedVideoId = trim($lessonVideoId);

        $this->merge([
            'lesson_video_id' => $normalizedVideoId === '' ? null : $normalizedVideoId,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lesson = $this->route('lesson');
        $thumbnailRule = $lesson ? ['nullable'] : ['required'];

        return [
            'module_id' => ['required', Rule::exists('modules', 'id')],
            'access_tier_ids' => ['required', 'array', 'min:1'],
            'access_tier_ids.*' => ['integer', Rule::exists('access_tiers', 'id')],
            'assessment_id' => [
                'nullable',
                Rule::exists('assessments', 'id'),
                Rule::unique('lessons', 'assessment_id')->ignore($lesson?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'thumbnail' => [
                ...$thumbnailRule,
                'file',
                'max:'.UploadConstraints::MAX_FILE_SIZE_KB,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $detectedMimeType = $value->getMimeType();
                    $clientMimeType = $value->getClientMimeType();
                    $mimeType = $detectedMimeType ?: $clientMimeType;

                    if ($mimeType && in_array($mimeType, self::SUPPORTED_THUMBNAIL_MIME_TYPES, true)) {
                        return;
                    }

                    if ($mimeType && str_starts_with($mimeType, 'image/')) {
                        return;
                    }

                    $fail('The thumbnail must be a valid image file in a supported format, such as JPG, PNG, GIF, BMP, WebP, SVG, AVIF, HEIC, or HEIF.');
                },
            ],
            'workbook' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'audio' => [
                'nullable',
                'file',
                'max:'.UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_KB,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    if (! $this->isReadableUploadedFile($value)) {
                        return;
                    }

                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if ($extension !== 'mp3') {
                        $fail('The audio file must use the .mp3 extension.');

                        return;
                    }

                    $detectedMimeType = $value->getMimeType();
                    $clientMimeType = $value->getClientMimeType();
                    $mimeTypes = array_filter([$detectedMimeType, $clientMimeType]);

                    if ($mimeTypes === []) {
                        return;
                    }

                    foreach ($mimeTypes as $mimeType) {
                        if (in_array($mimeType, self::SUPPORTED_AUDIO_MIME_TYPES, true) || str_starts_with($mimeType, 'audio/')) {
                            return;
                        }
                    }

                    $fail('The audio file must be a valid MP3 upload.');
                },
            ],
            'lesson_video_id' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $normalizedValue = trim($value);

                    if (filter_var($normalizedValue, FILTER_VALIDATE_URL)) {
                        $fail('Lesson Video ID must use the Bunny Stream video ID only, not a full URL.');

                        return;
                    }

                    if (! Str::isUuid($normalizedValue)) {
                        $fail('Lesson Video ID must be a valid Bunny Stream video ID in UUID format.');
                    }
                },
            ],
            'content' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        $messages = [
            'thumbnail.file' => 'The thumbnail upload is invalid.',
            'thumbnail.uploaded' => 'The thumbnail could not be uploaded. Please make sure the file is not larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).' and try again.',
            'thumbnail.max' => 'The thumbnail must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).'.',
            'workbook.uploaded' => 'The workbook file could not be uploaded. Please make sure the file is not larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).' and try again.',
            'workbook.max' => 'The workbook file must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).'.',
            'audio.max' => 'The audio file must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_MB).'.',
        ];

        if (! config('app.debug')) {
            $messages['audio.uploaded'] = 'The audio file could not be received by the server. Please make sure it is an MP3 and no larger than '.UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_MB).'.';
        }

        return $messages;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->logAudioUploadIssues($validator);

            if (config('app.debug') && $validator->errors()->has('audio')) {
                throw new RuntimeException($this->debugAudioValidationMessage());
            }

            if ($validator->errors()->has('module_id') || $validator->errors()->has('access_tier_ids')) {
                return;
            }

            if (! $validator->errors()->has('lesson_video_id')) {
                $this->validateLessonVideoIdAgainstBunny($validator);
            }

            $moduleId = $this->integer('module_id');
            $selectedTierIds = collect($this->input('access_tier_ids', []))
                ->map(fn ($tierId) => (int) $tierId)
                ->unique()
                ->values();

            if ($moduleId === 0 || $selectedTierIds->isEmpty()) {
                return;
            }

            $module = Module::query()
                ->with('accessTiers:id,name')
                ->find($moduleId);

            if (! $module) {
                return;
            }

            $allowedTierIds = $module->accessTiers
                ->pluck('id')
                ->map(fn ($tierId) => (int) $tierId);

            $invalidTierIds = $selectedTierIds->diff($allowedTierIds)->values();

            if ($invalidTierIds->isEmpty()) {
                return;
            }

            $invalidTierNames = AccessTier::query()
                ->whereIn('id', $invalidTierIds->all())
                ->orderBy('name')
                ->pluck('name')
                ->all();

            $allowedTierNames = $module->accessTiers
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            $validator->errors()->add(
                'access_tier_ids',
                sprintf(
                    'The selected lesson tier(s) are not allowed for module "%s". Invalid tier(s): %s. Allowed tier(s): %s.',
                    $module->title,
                    implode(', ', $invalidTierNames),
                    implode(', ', $allowedTierNames),
                ),
            );
        });
    }

    private function validateLessonVideoIdAgainstBunny(Validator $validator): void
    {
        $lessonVideoId = $this->input('lesson_video_id');

        if (! is_string($lessonVideoId) || trim($lessonVideoId) === '') {
            return;
        }

        /** @var BunnyStreamService $bunnyStream */
        $bunnyStream = app(BunnyStreamService::class);
        $inspection = $bunnyStream->inspectVideoId($lessonVideoId);

        if ($inspection['is_verified'] && ! $inspection['is_found']) {
            $validator->errors()->add(
                'lesson_video_id',
                'Lesson Video ID was not found in the configured Bunny Stream library. Paste the Bunny Stream video GUID from the same library used by this environment.',
            );
        }
    }

    private function logAudioUploadIssues(Validator $validator): void
    {
        if (! $validator->errors()->has('audio')) {
            return;
        }

        $audioFile = $this->hasFile('audio') ? $this->file('audio') : null;
        $isUploadedFile = $audioFile instanceof UploadedFile;
        $isValidUpload = $isUploadedFile ? $audioFile->isValid() : false;
        $realPath = $isUploadedFile ? $audioFile->getRealPath() : false;
        $isReadable = is_string($realPath) && $realPath !== '' && is_readable($realPath);
        $contentLengthBytes = $this->serverBytes('CONTENT_LENGTH');
        $phpUploadMaxBytes = $this->iniSizeToBytes((string) ini_get('upload_max_filesize'));
        $phpPostMaxBytes = $this->iniSizeToBytes((string) ini_get('post_max_size'));
        $likelyRootCause = $this->detectAudioUploadRootCause(
            hasAudioFile: $this->hasFile('audio'),
            isUploadedFile: $isUploadedFile,
            uploadErrorCode: $isUploadedFile ? $audioFile->getError() : null,
            contentLengthBytes: $contentLengthBytes,
            phpUploadMaxBytes: $phpUploadMaxBytes,
            phpPostMaxBytes: $phpPostMaxBytes,
        );

        Log::error('Lesson audio validation failed.', [
            'messages' => $validator->errors()->get('audio'),
            'has_audio_file' => $this->hasFile('audio'),
            'audio_input_type' => get_debug_type($this->input('audio')),
            'audio_is_uploaded_file' => $isUploadedFile,
            'audio_is_valid_upload' => $isValidUpload,
            'audio_real_path' => $realPath ?: null,
            'audio_is_readable' => $isReadable,
            'audio_original_name' => $isUploadedFile ? $audioFile->getClientOriginalName() : null,
            'audio_client_mime' => $isUploadedFile ? $audioFile->getClientMimeType() : null,
            'audio_detected_mime' => ($isUploadedFile && $isValidUpload && $isReadable) ? $audioFile->getMimeType() : null,
            'audio_size_bytes' => $isUploadedFile ? $audioFile->getSize() : null,
            'audio_upload_error_code' => $isUploadedFile ? $audioFile->getError() : null,
            'content_length' => $this->server('CONTENT_LENGTH'),
            'content_length_bytes' => $contentLengthBytes,
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_upload_max_filesize_bytes' => $phpUploadMaxBytes,
            'php_post_max_size' => ini_get('post_max_size'),
            'php_post_max_size_bytes' => $phpPostMaxBytes,
            'lesson_audio_limit_mb' => UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_MB,
            'recommended_php_upload_max_filesize_mb' => UploadConstraints::LESSON_AUDIO_SERVER_UPLOAD_MAX_FILE_SIZE_MB,
            'recommended_php_post_max_size_mb' => UploadConstraints::LESSON_AUDIO_SERVER_POST_MAX_SIZE_MB,
            'likely_root_cause' => $likelyRootCause,
            'route' => $this->route()?->getName(),
        ]);
    }

    public function audioUpload(): ?UploadedFile
    {
        $audioFile = $this->hasFile('audio') ? $this->file('audio') : null;

        if (! $audioFile instanceof UploadedFile) {
            return null;
        }

        return $this->isReadableUploadedFile($audioFile) ? $audioFile : null;
    }

    private function isReadableUploadedFile(UploadedFile $file): bool
    {
        if (! $file->isValid()) {
            return false;
        }

        $realPath = $file->getRealPath();

        return is_string($realPath) && $realPath !== '' && is_readable($realPath);
    }

    private function debugAudioValidationMessage(): string
    {
        $audioFile = $this->hasFile('audio') ? $this->file('audio') : null;
        $isUploadedFile = $audioFile instanceof UploadedFile;
        $uploadErrorCode = $isUploadedFile ? $audioFile->getError() : null;
        $realPath = $isUploadedFile ? $audioFile->getRealPath() : false;
        $contentLengthBytes = $this->serverBytes('CONTENT_LENGTH');
        $phpUploadMax = (string) ini_get('upload_max_filesize');
        $phpPostMax = (string) ini_get('post_max_size');
        $phpUploadMaxBytes = $this->iniSizeToBytes($phpUploadMax);
        $phpPostMaxBytes = $this->iniSizeToBytes($phpPostMax);
        $likelyRootCause = $this->detectAudioUploadRootCause(
            hasAudioFile: $this->hasFile('audio'),
            isUploadedFile: $isUploadedFile,
            uploadErrorCode: $uploadErrorCode,
            contentLengthBytes: $contentLengthBytes,
            phpUploadMaxBytes: $phpUploadMaxBytes,
            phpPostMaxBytes: $phpPostMaxBytes,
        );

        if ($likelyRootCause === 'php_upload_max_filesize_too_small') {
            return sprintf(
                'Lesson audio upload did not reach Laravel as a valid UploadedFile. Likely root cause: PHP upload_max_filesize is too small for this request. hasFile=%s; uploadedFile=%s; contentLength=%s bytes; php_upload_max_filesize=%s; php_post_max_size=%s; recommended_upload_max_filesize>=%s; recommended_post_max_size>=%s.',
                $this->hasFile('audio') ? 'true' : 'false',
                $isUploadedFile ? 'true' : 'false',
                $contentLengthBytes === null ? 'unknown' : (string) $contentLengthBytes,
                $phpUploadMax,
                $phpPostMax,
                UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_SERVER_UPLOAD_MAX_FILE_SIZE_MB),
                UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_SERVER_POST_MAX_SIZE_MB),
            );
        }

        if ($likelyRootCause === 'php_post_max_size_exceeded') {
            return sprintf(
                'Lesson audio upload exceeded PHP post_max_size before Laravel could read the file. contentLength=%s bytes; php_post_max_size=%s; php_upload_max_filesize=%s; recommended_post_max_size>=%s.',
                $contentLengthBytes === null ? 'unknown' : (string) $contentLengthBytes,
                $phpPostMax,
                $phpUploadMax,
                UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_SERVER_POST_MAX_SIZE_MB),
            );
        }

        if ($likelyRootCause === 'php_upload_error_ini_size') {
            return sprintf(
                'Lesson audio upload hit PHP upload error UPLOAD_ERR_INI_SIZE. php_upload_max_filesize=%s; php_post_max_size=%s; recommended_upload_max_filesize>=%s.',
                $phpUploadMax,
                $phpPostMax,
                UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_SERVER_UPLOAD_MAX_FILE_SIZE_MB),
            );
        }

        return sprintf(
            'Lesson audio validation failed. hasFile=%s; inputType=%s; uploadedFile=%s; isValid=%s; uploadError=%s; realPath=%s; readable=%s; contentLength=%s; php_upload_max_filesize=%s; php_post_max_size=%s; likely_root_cause=%s.',
            $this->hasFile('audio') ? 'true' : 'false',
            get_debug_type($this->input('audio')),
            $isUploadedFile ? 'true' : 'false',
            ($isUploadedFile && $audioFile->isValid()) ? 'true' : 'false',
            $uploadErrorCode === null ? 'null' : (string) $uploadErrorCode,
            is_string($realPath) && $realPath !== '' ? $realPath : 'null',
            (is_string($realPath) && $realPath !== '' && is_readable($realPath)) ? 'true' : 'false',
            $contentLengthBytes === null ? 'unknown' : (string) $contentLengthBytes,
            $phpUploadMax,
            $phpPostMax,
            $likelyRootCause ?? 'unknown',
        );
    }

    private function detectAudioUploadRootCause(
        bool $hasAudioFile,
        bool $isUploadedFile,
        ?int $uploadErrorCode,
        ?int $contentLengthBytes,
        ?int $phpUploadMaxBytes,
        ?int $phpPostMaxBytes,
    ): ?string {
        if ($uploadErrorCode === UPLOAD_ERR_INI_SIZE) {
            return 'php_upload_error_ini_size';
        }

        if (! $hasAudioFile && ! $isUploadedFile && $contentLengthBytes !== null) {
            if ($phpPostMaxBytes !== null && $contentLengthBytes > $phpPostMaxBytes) {
                return 'php_post_max_size_exceeded';
            }

            if ($phpUploadMaxBytes !== null && $contentLengthBytes > $phpUploadMaxBytes) {
                return 'php_upload_max_filesize_too_small';
            }
        }

        return null;
    }

    private function serverBytes(string $key): ?int
    {
        $value = $this->server($key);

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function iniSizeToBytes(string $value): ?int
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^(?<size>\d+)(?<unit>[KMG]?)$/i', $normalized, $matches)) {
            return is_numeric($normalized) ? (int) $normalized : null;
        }

        $size = (int) $matches['size'];
        $unit = strtolower($matches['unit']);

        return match ($unit) {
            'g' => $size * 1024 * 1024 * 1024,
            'm' => $size * 1024 * 1024,
            'k' => $size * 1024,
            default => $size,
        };
    }
}
