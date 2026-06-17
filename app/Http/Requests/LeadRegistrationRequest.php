<?php

namespace App\Http\Requests;

use App\Models\AccessTier;
use App\Models\User;
use App\Support\CountryDirectory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'access_tier_id' => [
                'required',
                'integer',
                Rule::exists(AccessTier::class, 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phoneCountryCode = (string) $this->input('phone_country_code', CountryDirectory::dialCodeForCountry((string) $this->input('country')));
        $phoneNumber = (string) $this->input('phone_number', '');

        $this->merge([
            'phone' => CountryDirectory::formatPhoneNumber($phoneCountryCode, $phoneNumber),
        ]);
    }
}
