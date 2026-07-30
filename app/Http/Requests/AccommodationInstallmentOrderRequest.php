<?php

namespace App\Http\Requests;

use App\Models\Accommodation;
use App\Models\AccommodationRoomType;
use App\Support\CountryDirectory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationInstallmentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors LeadRegistrationRequest::prepareForValidation(): first_name +
     * last_name merge into guest_name, phone_country_code + phone_number
     * merge into guest_phone (via the same CountryDirectory helper) — both
     * merged values are still validated below, same as Package's `phone`.
     */
    protected function prepareForValidation(): void
    {
        $firstName = trim((string) $this->input('first_name', ''));
        $lastName = trim((string) $this->input('last_name', ''));
        $phoneCountryCode = (string) $this->input(
            'phone_country_code',
            CountryDirectory::dialCodeForCountry((string) $this->input('country')),
        );
        $phoneNumber = (string) $this->input('phone_number', '');

        $this->merge([
            'guest_name' => trim($firstName.' '.$lastName),
            'guest_phone' => CountryDirectory::formatPhoneNumber($phoneCountryCode, $phoneNumber),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accommodation = $this->route('accommodation');

        return [
            'room_type_id' => [
                'required',
                'integer',
                Rule::exists(AccommodationRoomType::class, 'id')
                    ->where('accommodation_id', $accommodation?->id)
                    ->where('is_active', true),
            ],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'phone_country_code' => ['required', 'string', 'max:10'],
            'phone_number' => ['required', 'string', 'max:50'],
            'guest_phone' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:255'],
            'installment_count' => ['nullable', 'integer', 'min:'.Accommodation::MIN_INSTALLMENT_COUNT],
        ];
    }

    public function messages(): array
    {
        return [
            'room_type_id.exists' => 'The selected room type is not available for this hotel.',
            'check_in_date.after_or_equal' => 'Check-in date cannot be in the past.',
            'check_out_date.after' => 'Check-out date must be after the check-in date.',
        ];
    }
}
