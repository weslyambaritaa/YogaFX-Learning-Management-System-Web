<?php

namespace App\Http\Requests\Admin;

use App\Models\AccessTier;
use App\Models\Accommodation;
use App\Support\UploadConstraints;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => strtolower(trim((string) $this->input('slug'))),
            ]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accommodation = $this->route('accommodation');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(Accommodation::class, 'slug')->ignore($accommodation?->id),
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'image', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'currency_code' => ['required', 'string', Rule::in(AccessTier::CURRENCY_OPTIONS)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
            'image.max' => 'The image must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).'.',
        ];
    }
}
