<?php

namespace App\Http\Requests;

use App\Models\AccessTier;
use App\Support\CountryDirectory;
use App\Support\StudentProfileValidationRules;
use App\Support\StudentProfileValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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

        return $currentUser->isAdmin()
            || $currentUser->is($targetUser);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUser = $this->route('student')
            ?? $this->user();

        return StudentProfileValidationRules::make(
            $targetUser?->id,
            [
                'require_master_class_fields' =>
                    $this->requiresMasterClassFields(),

                'has_medical_issues' =>
                    $this->input('has_medical_issues') === true,

                'is_taking_medication' =>
                    $this->input('is_taking_medication') === true,
            ],
        );
    }

    protected function prepareForValidation(): void
    {
        $country = (string) $this->input('country');

        $whatsappCountryCode = (string) $this->input(
            'whatsapp_country_code',
            CountryDirectory::dialCodeForCountry($country),
        );

        $whatsappNumber = (string) $this->input(
            'whatsapp_number',
            '',
        );

        $emergencyCountryCode = (string) $this->input(
            'emergency_contact_country_code',
            CountryDirectory::dialCodeForCountry($country),
        );

        $emergencyNumber = (string) $this->input(
            'emergency_contact_number',
            '',
        );

        $birthDate = $this->normalizeBirthDate(
            $this->input('birth_date'),
        );

        $hasMedicalIssues = $this->normalizeYesNo(
            $this->input('has_medical_issues'),
        );

        $isTakingMedication = $this->normalizeYesNo(
            $this->input('is_taking_medication'),
        );

        $this->merge([
            'whatsapp' => CountryDirectory::formatPhoneNumber(
                $whatsappCountryCode,
                $whatsappNumber,
            ),

            'emergency_contact_whatsapp' =>
                CountryDirectory::formatPhoneNumber(
                    $emergencyCountryCode,
                    $emergencyNumber,
                ),

            'birth_date' => $birthDate,

            'gender' => StudentProfileValue::normalizeGender(
                $this->input('gender'),
            ),

            'practicing_yoga_for' =>
                StudentProfileValue::normalizePracticingYogaFor(
                    $this->input('practicing_yoga_for'),
                ),

            'yoga_sequence_experience' =>
                StudentProfileValue::normalizeYogaSequenceExperience(
                    $this->input('yoga_sequence_experience'),
                ),

            'hours_per_week' =>
                StudentProfileValue::normalizeHoursPerWeek(
                    $this->input('hours_per_week'),
                ),

            'current_fitness_level' =>
                StudentProfileValue::normalizeFitnessLevel(
                    $this->input('current_fitness_level'),
                ),

            'flexibility_rating' =>
                StudentProfileValue::normalizeFitnessLevel(
                    $this->input('flexibility_rating'),
                ),

            'how_did_you_find_us' =>
                StudentProfileValue::normalizeHowDidYouFindUs(
                    $this->input('how_did_you_find_us'),
                ),

            'has_medical_issues' => $hasMedicalIssues,

            'medical_issues_details' =>
                $hasMedicalIssues === true
                    ? trim((string) $this->input(
                        'medical_issues_details',
                        '',
                    ))
                    : null,

            'is_taking_medication' => $isTakingMedication,

            'medication_details' =>
                $isTakingMedication === true
                    ? trim((string) $this->input(
                        'medication_details',
                        '',
                    ))
                    : null,
        ]);
    }

    private function requiresMasterClassFields(): bool
    {
        $currentUser = $this->user();
        $targetUser = $this->route('student')
            ?? $currentUser;

        /*
         * Ketika admin mengubah access tier, gunakan tier
         * yang sedang dipilih di form.
         */
        if (
            $currentUser?->isAdmin()
            && $this->has('access_tier_id')
        ) {
            $accessTierId = $this->input('access_tier_id');

            if (! filled($accessTierId)) {
                return false;
            }

            $accessTierSlug = (string) AccessTier::query()
                ->whereKey($accessTierId)
                ->value('slug');

            return AccessTier::canonicalSlug($accessTierSlug)
                === AccessTier::SLUG_MASTER_CLASS;
        }

        $targetUser?->loadMissing('accessTier');

        $accessTierSlug = (string) (
            $targetUser?->accessTier?->slug ?? ''
        );

        return AccessTier::canonicalSlug($accessTierSlug)
            === AccessTier::SLUG_MASTER_CLASS;
    }

    private function normalizeYesNo(mixed $value): mixed
    {
        return match ($value) {
            true, 1, '1', 'true', 'yes' => true,
            false, 0, '0', 'false', 'no' => false,
            default => $value,
        };
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

        foreach (
            ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y']
            as $format
        ) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $trimmed,
                )->format('Y-m-d');
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

    protected function failedValidation(
        Validator $validator,
    ): void {
        parent::failedValidation($validator);
    }
}