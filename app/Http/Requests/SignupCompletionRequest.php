<?php

namespace App\Http\Requests;

use App\Models\OnboardingState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignupCompletionRequest extends FormRequest
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
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
