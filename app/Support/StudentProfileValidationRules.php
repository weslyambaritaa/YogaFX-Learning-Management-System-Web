<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

class StudentProfileValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function make(?int $ignoreUserId = null): array
    {
        $wordLimit = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (str_word_count(strip_tags((string) $value)) > 50) {
                $fail('Use 50 words or less.');
            }
        };

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($ignoreUserId),
            ],
            'whatsapp_country_code' => ['required', 'string', 'max:10'],
            'whatsapp_number' => ['required', 'string', 'max:50'],
            'whatsapp' => ['required', 'string', 'max:50'],
            'profile_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['female', 'male'])],
            'practicing_yoga_for' => ['required', 'string', Rule::in([
                'beginner',
                '0_to_3_years',
                '4_to_6_years',
                '6_plus_years',
            ])],
            'yoga_sequence_experience' => ['required', 'array', 'min:1'],
            'yoga_sequence_experience.*' => ['string', Rule::in([
                'bikram',
                'hatha',
                'astanga',
                'vinyasa',
                'yin',
                'iyengar',
                'pilates',
                'other',
            ])],
            'hours_per_week' => ['required', 'string', Rule::in([
                '0_3',
                '4_7',
                '7_10',
                '10_plus',
            ])],
            'current_fitness_level' => ['required', 'string', Rule::in(['poor', 'average', 'good'])],
            'flexibility_rating' => ['required', 'string', Rule::in(['poor', 'average', 'good'])],
            'motivation' => ['required', 'string', 'max:2000', $wordLimit],
            'why_yogafx' => ['required', 'string', 'max:2000', $wordLimit],
            'how_did_you_find_us' => ['required', 'array', 'min:1'],
            'how_did_you_find_us.*' => ['string', Rule::in([
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
            ])],
        ];
    }
}
