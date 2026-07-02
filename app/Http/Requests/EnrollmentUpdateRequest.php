<?php

namespace App\Http\Requests;

use App\Models\OnboardingState;
use App\Support\CountryDirectory;
use App\Support\StudentProfileValidationRules;
use App\Support\StudentProfileValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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

        $rules = StudentProfileValidationRules::make($onboardingState?->user_id, [
            'require_profile_photo' => ! filled($onboardingState?->user?->profile_photo),
            'require_instagram' => true,
        ]);

        $rules['terms_accepted'] = ['required', 'accepted'];
        $rules['recaptcha_confirmed'] = ['required', 'accepted'];

        return $rules;
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
            'gender' => StudentProfileValue::normalizeGender(
                $this->input('gender'),
            ),
            'practicing_yoga_for' => StudentProfileValue::normalizePracticingYogaFor(
                $this->input('practicing_yoga_for'),
            ),
            'yoga_sequence_experience' => StudentProfileValue::normalizeYogaSequenceExperience(
                $this->input('yoga_sequence_experience'),
            ),
            'hours_per_week' => StudentProfileValue::normalizeHoursPerWeek(
                $this->input('hours_per_week'),
            ),
            'current_fitness_level' => StudentProfileValue::normalizeFitnessLevel(
                $this->input('current_fitness_level'),
            ),
            'flexibility_rating' => StudentProfileValue::normalizeFitnessLevel(
                $this->input('flexibility_rating'),
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
