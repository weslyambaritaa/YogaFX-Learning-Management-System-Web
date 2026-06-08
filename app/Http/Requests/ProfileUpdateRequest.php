<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore(($this->route('student') ?? $this->user())->id),
            ],
            'whatsapp' => ['required', 'string', 'max:50'],
            'preferred_certificate_picture' => ['nullable', 'string', 'max:2048'],
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
