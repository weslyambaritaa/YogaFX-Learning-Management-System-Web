<?php

namespace App\Http\Requests;

use App\Models\Package;
use App\Models\User;
use App\Services\PackageResolverService;
use App\Support\CountryDirectory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'phone_country_code' => ['required', 'string', 'max:10'],
            'phone_number' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:255'],
            'package_id' => [
                'required',
                'integer',
                Rule::exists(Package::class, 'id')->where(function ($query) {
                    $query
                        ->where('is_active', true)
                        ->whereNotNull('access_tier_id');
                }),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phoneCountryCode = (string) $this->input('phone_country_code', CountryDirectory::dialCodeForCountry((string) $this->input('country')));
        $phoneNumber = (string) $this->input('phone_number', '');
        $resolvedPackageId = $this->input('package_id');
        $paymentLinkSlug = $this->route('paymentLinkSlug');
        $packageSlug = $this->route('packageSlug');

        if (is_string($paymentLinkSlug) && $paymentLinkSlug !== '') {
            $package = app(PackageResolverService::class)->resolveActivePackageForTierSlug($paymentLinkSlug);
            if (! $package instanceof Package) {
                throw ValidationException::withMessages([
                    'package_id' => 'This package is currently unavailable for checkout.',
                ]);
            }
            $resolvedPackageId = $package?->id;
        } elseif (is_string($packageSlug) && $packageSlug !== '') {
            $package = app(PackageResolverService::class)->resolveActivePackageBySlug($packageSlug);
            if (! $package instanceof Package) {
                throw ValidationException::withMessages([
                    'package_id' => 'This package is currently unavailable for checkout.',
                ]);
            }
            $resolvedPackageId = $package?->id;
        }

        $this->merge([
            'phone' => CountryDirectory::formatPhoneNumber($phoneCountryCode, $phoneNumber),
            'package_id' => $resolvedPackageId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package_id.required' => 'Please select a package before continuing.',
        ];
    }
}
