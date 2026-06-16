<?php

namespace App\Http\Requests;

use App\Support\StudentProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
}
