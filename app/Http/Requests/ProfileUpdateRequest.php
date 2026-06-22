<?php

namespace App\Http\Requests;

use App\Support\CountryDirectory;
use App\Support\StudentProfileValidationRules;
use App\Support\StudentProfileValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
        $birthDate = $this->normalizeBirthDate($this->input('birth_date'));

        $this->merge([
            'whatsapp' => CountryDirectory::formatPhoneNumber($whatsappCountryCode, $whatsappNumber),
            'birth_date' => $birthDate,
            'practicing_yoga_for' => StudentProfileValue::normalizePracticingYogaFor(
                $this->input('practicing_yoga_for'),
            ),
            'yoga_sequence_experience' => StudentProfileValue::normalizeYogaSequenceExperience(
                $this->input('yoga_sequence_experience'),
            ),
            'how_did_you_find_us' => StudentProfileValue::normalizeHowDidYouFindUs(
                $this->input('how_did_you_find_us'),
            ),
        ]);
    }

    private function normalizeBirthDate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $trimmed)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }
}
