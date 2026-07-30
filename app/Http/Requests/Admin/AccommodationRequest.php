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

        if ($this->has('installment_enabled')) {
            $this->merge([
                'installment_enabled' => filter_var($this->input('installment_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('installment_count_mode')) {
            $mode = strtolower(trim((string) $this->input('installment_count_mode')));

            $this->merge([
                'installment_count_mode' => $mode === '' ? null : $mode,
            ]);
        }

        if ($this->has('installment_fixed_count')) {
            $fixedCount = $this->input('installment_fixed_count');

            $this->merge([
                'installment_fixed_count' => $fixedCount === null || $fixedCount === ''
                    ? null
                    : (int) $fixedCount,
            ]);
        }

        if (! $this->boolean('installment_enabled')) {
            $this->merge([
                'installment_count_mode' => null,
                'installment_fixed_count' => null,
            ]);
        } elseif ($this->input('installment_count_mode') !== Accommodation::INSTALLMENT_COUNT_MODE_FIXED) {
            $this->merge([
                'installment_fixed_count' => null,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accommodation = $this->route('accommodation');
        $installmentEnabled = $this->boolean('installment_enabled');
        $usesFixedCount = $installmentEnabled && $this->input('installment_count_mode') === Accommodation::INSTALLMENT_COUNT_MODE_FIXED;

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
            'installment_enabled' => ['required', 'boolean'],
            'installment_count_mode' => [
                $installmentEnabled ? 'required' : 'nullable',
                'string',
                Rule::in([
                    Accommodation::INSTALLMENT_COUNT_MODE_FLEX,
                    Accommodation::INSTALLMENT_COUNT_MODE_FIXED,
                ]),
            ],
            'installment_fixed_count' => [
                $usesFixedCount ? 'required' : 'nullable',
                'integer',
                'min:'.Accommodation::MIN_INSTALLMENT_COUNT,
                'max:'.Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
            'image.max' => 'The image must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).'.',
            'installment_count_mode.required' => 'Please choose whether the installment count is flex or fixed.',
            'installment_count_mode.in' => 'Installment count mode must be flex or fixed.',
            'installment_fixed_count.required' => 'Fixed installment count is required when the mode is fixed.',
            'installment_fixed_count.min' => 'Installment count must be at least '.Accommodation::MIN_INSTALLMENT_COUNT.'.',
            'installment_fixed_count.max' => 'Installment count cannot be greater than '.Accommodation::MAX_PROVIDER_INSTALLMENT_COUNT.'.',
        ];
    }
}
