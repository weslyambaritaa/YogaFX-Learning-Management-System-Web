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
            'whatsapp' => ['required', 'string', 'max:50'],
            'preferred_certificate_picture' => ['nullable', 'string', 'max:2048'],
            'profile_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg', 'max:5120'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['female', 'male', 'non_binary', 'prefer_not_to_say'])],
            'practicing_yoga_for' => ['required', 'string', 'max:255'],
            'yoga_sequence_experience' => ['required', 'string', 'max:255'],
            'hours_per_week' => ['required', 'integer', 'min:0', 'max:168'],
            'current_fitness_level' => ['required', 'string', 'max:255'],
            'flexibility_rating' => ['required', 'string', 'max:255'],
            'motivation' => ['required', 'string', 'max:2000'],
            'why_yogafx' => ['required', 'string', 'max:2000'],
            'how_did_you_find_us' => ['required', 'string', 'max:255'],
        ];
    }
}
