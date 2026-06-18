<?php

namespace App\Http\Requests;

use App\Models\OnboardingState;
use App\Support\CountryDirectory;
use App\Support\StudentProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnrollmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('onboardingState') instanceof OnboardingState;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OnboardingState|null $onboardingState */
        $onboardingState = $this->route('onboardingState');

        return StudentProfileValidationRules::make($onboardingState?->user_id);
    }

    protected function prepareForValidation(): void
    {
        $country = (string) $this->input('country');
        $whatsappCountryCode = (string) $this->input('whatsapp_country_code', CountryDirectory::dialCodeForCountry($country));
        $whatsappNumber = (string) $this->input('whatsapp_number', '');

        $this->merge([
            'whatsapp' => CountryDirectory::formatPhoneNumber($whatsappCountryCode, $whatsappNumber),
        ]);
    }
}
