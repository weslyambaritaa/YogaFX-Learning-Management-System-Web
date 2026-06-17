<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Validation\Rule;

class AdminStudentUpdateRequest extends ProfileUpdateRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'access_tier_id' => ['nullable', Rule::exists('access_tiers', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
