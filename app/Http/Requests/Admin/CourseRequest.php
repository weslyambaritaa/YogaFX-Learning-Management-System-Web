<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use App\Services\BunnyStreamService;
use App\Support\UploadConstraints;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('url_slug')) {
            $this->merge([
                'url_slug' => Str::slug((string) $this->input('url_slug')),
            ]);
        }

        $videoId = $this->input('video');

        if (is_string($videoId)) {
            $normalizedVideoId = trim($videoId);

            $this->merge([
                'video' => $normalizedVideoId === '' ? null : $normalizedVideoId,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $course = $this->route('course');
        $thumbnailRule = $course ? ['nullable'] : ['required'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'url_slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(Course::class, 'url_slug')->ignore($course?->id),
            ],
            'access_tier_ids' => ['required', 'array', 'min:1'],
            'access_tier_ids.*' => ['integer', Rule::exists('access_tiers', 'id')],
            'description' => ['required', 'string', 'max:5000'],
            'thumbnail' => [...$thumbnailRule, 'image', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'video' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $normalizedValue = trim($value);

                    if (filter_var($normalizedValue, FILTER_VALIDATE_URL)) {
                        $fail('Lecturer Video ID must use the Bunny Stream video ID only, not a full URL.');

                        return;
                    }

                    if (! Str::isUuid($normalizedValue)) {
                        $fail('Lecturer Video ID must be a valid Bunny Stream video ID in UUID format.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'thumbnail.max' => 'The thumbnail must not be larger than 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('video')) {
                return;
            }

            $videoId = $this->input('video');

            if (! is_string($videoId) || trim($videoId) === '') {
                return;
            }

            $inspection = app(BunnyStreamService::class)->inspectVideoId($videoId);

            if ($inspection['error_code'] === 'invalid_format') {
                $validator->errors()->add(
                    'video',
                    'Lecturer Video ID must be a valid Bunny Stream video ID in UUID format.',
                );

                return;
            }

            if ($inspection['is_verified'] && ! $inspection['is_found']) {
                $validator->errors()->add(
                    'video',
                    'Lecturer Video ID was not found in the configured Bunny Stream library. Paste the Bunny Stream video GUID from the same library used by this environment.',
                );
            }
        });
    }
}
