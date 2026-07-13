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

        /*
        |--------------------------------------------------------------------------
        | Normalize boolean inputs
        |--------------------------------------------------------------------------
        |
        | Inertia/React biasanya mengirim boolean dengan benar, tetapi normalisasi
        | ini membuat request tetap aman jika suatu saat nilainya dikirim sebagai
        | "1", "0", "true", atau "false".
        |
        */
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

        if ($this->has('installment_calculation_method')) {
            $this->merge([
                'installment_calculation_method' => strtolower(trim((string) $this->input('installment_calculation_method'))),
            ]);
        }

        if ($this->has('installment_count_mode')) {
            $mode = strtolower(trim((string) $this->input('installment_count_mode')));

            $this->merge([
                'installment_count_mode' => $mode === '' ? null : $mode,
            ]);
        }

        if ($this->has('installment_count')) {
            $installmentCount = $this->input('installment_count');

            $this->merge([
                'installment_count' => $installmentCount === null || $installmentCount === ''
                    ? null
                    : (int) $installmentCount,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize allowed billing days
        |--------------------------------------------------------------------------
        |
        | Field baru ini berasal dari checkbox admin:
        | - [1]
        | - [15]
        | - [1, 15]
        |
        | Jika tidak ada yang dicentang, frontend bisa mengirim null/empty string.
        | Kita normalisasi menjadi array agar validasi konsisten.
        |
        */
        if ($this->has('allowed_billing_days')) {
            $allowedBillingDays = $this->input('allowed_billing_days');

            if (is_string($allowedBillingDays)) {
                $decoded = json_decode($allowedBillingDays, true);
                $allowedBillingDays = is_array($decoded) ? $decoded : [$allowedBillingDays];
            }

            if (! is_array($allowedBillingDays)) {
                $allowedBillingDays = [];
            }

            $this->merge([
                'allowed_billing_days' => collect($allowedBillingDays)
                    ->map(fn ($day) => (int) $day)
                    ->filter(fn (int $day) => in_array($day, Package::CUSTOMER_BILLING_DAY_OPTIONS, true))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ]);
        }

        if (! $this->boolean('installment_enabled')) {
            $this->merge([
                'installment_calculation_method' => Package::INSTALLMENT_CALCULATION_DATE,
                'installment_count_mode' => null,
                'installment_count' => null,
                'installment_deadline_date' => null,
                'allowed_billing_days' => null,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $package = $this->route('package');
        $installmentEnabled = $this->boolean('installment_enabled');
        $method = $this->input('installment_calculation_method', Package::INSTALLMENT_CALCULATION_DATE);
        $isDateMethod = $installmentEnabled && $method === Package::INSTALLMENT_CALCULATION_DATE;
        $isNumberMethod = $installmentEnabled && $method === Package::INSTALLMENT_CALCULATION_NUMBER;

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
            'installment_calculation_method' => [
                $installmentEnabled ? 'required' : 'nullable',
                'string',
                Rule::in([
                    Package::INSTALLMENT_CALCULATION_DATE,
                    Package::INSTALLMENT_CALCULATION_NUMBER,
                ]),
            ],
            'installment_count_mode' => [
                $isNumberMethod ? 'required' : 'nullable',
                'string',
                Rule::in([
                    Package::INSTALLMENT_COUNT_MODE_FLEX,
                    Package::INSTALLMENT_COUNT_MODE_FIXED,
                ]),
            ],
            'installment_count' => [
                $isNumberMethod ? 'required' : 'nullable',
                'integer',
                'min:'.Package::MIN_INSTALLMENT_COUNT,
                'max:'.Package::MAX_INSTALLMENT_COUNT,
            ],

            /*
            |--------------------------------------------------------------------------
            | New installment rules
            |--------------------------------------------------------------------------
            |
            | Flow baru:
            | - Admin menentukan deadline akhir installment melalui date.
            | - Admin mengaktifkan tanggal billing 1 dan/atau 15.
            | - Calon student nanti hanya memilih salah satu tanggal yang tersedia.
            |
            */
            'installment_deadline_date' => [
                $isDateMethod ? 'required' : 'nullable',
                'date',
                'after_or_equal:today',
            ],
            'allowed_billing_days' => [
                $installmentEnabled ? 'required' : 'nullable',
                ...($installmentEnabled ? ['array', 'min:1'] : []),
            ],
            'allowed_billing_days.*' => [
                'integer',
                Rule::in(Package::CUSTOMER_BILLING_DAY_OPTIONS),
            ],

            /*
            |--------------------------------------------------------------------------
            | Legacy installment fields
            |--------------------------------------------------------------------------
            |
            | Field lama tidak lagi dipakai oleh form admin baru, tetapi tetap dibuat
            | nullable agar request lama atau data lama tidak langsung menyebabkan error.
            | Nanti field ini bisa dihapus setelah seluruh service checkout/installment
            | sudah pindah ke installment_deadline_date + allowed_billing_days.
            |
            */
            'billing_interval_unit' => ['nullable', 'string', 'max:50'],
            'billing_interval_count' => ['nullable', 'integer', 'min:1'],
            'fixed_billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'installment_deadline_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'installment_deadline_day' => ['nullable', 'integer', 'min:1', 'max:31'],

            'access_tier_id' => ['nullable', Rule::exists(AccessTier::class, 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'The image must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::MAX_FILE_SIZE_MB).'.',

            'installment_deadline_date.required' => 'The installment deadline date is required when installment is enabled.',
            'installment_deadline_date.date' => 'The installment deadline date must be a valid date.',
            'installment_deadline_date.after_or_equal' => 'The installment deadline date must be today or a future date.',
            'installment_calculation_method.required' => 'Please choose how installment count should be determined.',
            'installment_calculation_method.in' => 'Installment calculation method must be date or number.',
            'installment_count_mode.required' => 'Please choose whether installment count is flex or fixed.',
            'installment_count_mode.in' => 'Installment count mode must be flex or fixed.',
            'installment_count.required' => 'Installment count is required for number-based installment.',
            'installment_count.integer' => 'Installment count must be a whole number.',
            'installment_count.min' => 'Installment count must be at least '.Package::MIN_INSTALLMENT_COUNT.'.',
            'installment_count.max' => 'Installment count cannot be greater than '.Package::MAX_INSTALLMENT_COUNT.'.',

            'allowed_billing_days.required' => 'Please enable at least one billing day for installment.',
            'allowed_billing_days.array' => 'The allowed billing days must be a valid list.',
            'allowed_billing_days.min' => 'Please enable at least one billing day for installment.',
            'allowed_billing_days.*.in' => 'Allowed billing days can only be day 1 or day 15.',
        ];
    }
}
