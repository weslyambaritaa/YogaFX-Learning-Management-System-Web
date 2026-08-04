<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

class StudentProfileValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function make(
        ?int $ignoreUserId = null,
        array $options = [],
    ): array {
        $requireProfilePhoto = (bool) (
            $options['require_profile_photo'] ?? false
        );

        $requireInstagram = (bool) (
            $options['require_instagram'] ?? false
        );

        $requireMasterClassFields = (bool) (
            $options['require_master_class_fields'] ?? false
        );

        $hasMedicalIssues = (bool) (
            $options['has_medical_issues'] ?? false
        );

        $isTakingMedication = (bool) (
            $options['is_taking_medication'] ?? false
        );

        $wordLimit = static function (
            string $attribute,
            mixed $value,
            \Closure $fail,
        ): void {
            if (str_word_count(strip_tags((string) $value)) > 50) {
                $fail('Use 50 words or less.');
            }
        };

        $rules = [
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($ignoreUserId),
            ],

            'whatsapp_country_code' => [
                'required',
                'string',
                'max:10',
            ],

            'whatsapp_number' => [
                'required',
                'string',
                'max:50',
            ],

            'whatsapp' => [
                'required',
                'string',
                'max:50',
            ],

            'profile_photo' => array_values(array_filter([
                $requireProfilePhoto ? 'required' : 'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ])),

            'instagram' => array_values(array_filter([
                $requireInstagram ? 'required' : 'nullable',
                'string',
                'max:255',
            ])),

            'country' => [
                'required',
                'string',
                'max:255',
            ],

            'birth_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'gender' => [
                'required',
                'string',
                Rule::in(['female', 'male']),
            ],

            'practicing_yoga_for' => [
                'required',
                'string',
                Rule::in([
                    'beginner',
                    '0_to_3_years',
                    '4_to_6_years',
                    '6_plus_years',
                ]),
            ],

            'yoga_sequence_experience' => [
                'required',
                'array',
                'min:1',
            ],

            'yoga_sequence_experience.*' => [
                'string',
                Rule::in([
                    'bikram',
                    'hatha',
                    'astanga',
                    'vinyasa',
                    'yin',
                    'iyengar',
                    'pilates',
                    'other',
                ]),
            ],

            'hours_per_week' => [
                'required',
                'string',
                Rule::in([
                    '0_3',
                    '4_7',
                    '7_10',
                    '10_plus',
                ]),
            ],

            'current_fitness_level' => [
                'required',
                'string',
                Rule::in([
                    'poor',
                    'average',
                    'good',
                ]),
            ],

            'flexibility_rating' => [
                'required',
                'string',
                Rule::in([
                    'poor',
                    'average',
                    'good',
                ]),
            ],

            'motivation' => [
                'required',
                'string',
                'max:2000',
                $wordLimit,
            ],

            'why_yogafx' => [
                'required',
                'string',
                'max:2000',
                $wordLimit,
            ],

            'how_did_you_find_us' => [
                'required',
                'array',
                'min:1',
            ],

            'how_did_you_find_us.*' => [
                'string',
                Rule::in([
                    'google',
                    'facebook',
                    'instagram',
                    'chatgpt',
                    'gemini',
                    'perplexity',
                    'youtube',
                    'yoga_studio',
                    'word_of_mouth',
                    'other',
                ]),
            ],
        ];

        if (! $requireMasterClassFields) {
            return $rules;
        }

        return [
            ...$rules,

            'tshirt_size' => [
                'required',
                'string',
                Rule::in([
                    'XXL',
                    'XL',
                    'L',
                    'M',
                    'S',
                    'XS',
                ]),
            ],

            'favorite_song' => [
                'required',
                'string',
                'max:2000',
            ],

            'emergency_contact_name' => [
                'required',
                'string',
                'max:255',
            ],

            'emergency_contact_relationship' => [
                'required',
                'string',
                'max:255',
            ],

            'emergency_contact_country_code' => [
                'required',
                'string',
                'max:10',
            ],

            'emergency_contact_number' => [
                'required',
                'string',
                'max:50',
            ],

            'emergency_contact_whatsapp' => [
                'required',
                'string',
                'max:50',
            ],

            'has_medical_issues' => [
                'required',
                'boolean',
            ],

            'medical_issues_details' => [
                Rule::requiredIf($hasMedicalIssues),
                'nullable',
                'string',
                'max:2000',
            ],

            'is_taking_medication' => [
                'required',
                'boolean',
            ],

            'medication_details' => [
                Rule::requiredIf($isTakingMedication),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}