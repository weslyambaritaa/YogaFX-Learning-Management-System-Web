<?php

namespace App\Http\Requests\Admin;

use App\Models\AccessTier;
use App\Models\Package;
use App\Support\UploadConstraints;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('slug')),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $package = $this->route('package');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(Package::class, 'slug')->ignore($package?->id),
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'image', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'price' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', Rule::in(AccessTier::CURRENCY_OPTIONS)],
            'is_active' => ['required', 'boolean'],
            'installment_enabled' => ['required', 'boolean'],
            'billing_interval_unit' => ['nullable', 'string', 'max:50'],
            'billing_interval_count' => ['nullable', 'integer', 'min:1'],
            'fixed_billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'allowed_billing_days' => ['nullable', 'array'],
            'allowed_billing_days.*' => ['integer', Rule::in([1, 15])],
            'installment_deadline_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'installment_deadline_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'access_tier_id' => ['nullable', Rule::exists(AccessTier::class, 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $installmentEnabled = filter_var($this->input('installment_enabled'), FILTER_VALIDATE_BOOL);
                $allowedBillingDays = collect($this->input('allowed_billing_days', []))
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->all();

                if ($installmentEnabled && $allowedBillingDays === []) {
                    $validator->errors()->add(
                        'allowed_billing_days',
                        'Select at least one allowed billing day when installment is enabled.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'The image must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).'.',
        ];
    }
}
