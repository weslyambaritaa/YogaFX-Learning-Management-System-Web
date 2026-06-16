<?php

namespace App\Http\Requests;

use App\Support\CountryDirectory;
use App\Support\StudentProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currentUser = $this->user();
        $targetUser = $this->route('student') ?? $currentUser;

        if (! $currentUser || ! $targetUser) {
            return false;
        }

        return $currentUser->isAdmin() || $currentUser->is($targetUser);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUser = $this->route('student') ?? $this->user();

        return StudentProfileValidationRules::make($targetUser?->id);
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
