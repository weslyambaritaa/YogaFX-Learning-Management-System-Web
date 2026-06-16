<?php

namespace App\Http\Requests;

use App\Models\OnboardingState;
use App\Support\StudentProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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

        return StudentProfileValidationRules::make($onboardingState?->user_id);
    }
}
