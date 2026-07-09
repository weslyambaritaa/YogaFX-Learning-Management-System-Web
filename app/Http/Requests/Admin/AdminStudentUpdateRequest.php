<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
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
            'account_status' => ['required', 'string', Rule::in([
                User::ACCOUNT_STATUS_AVAILABLE,
                User::ACCOUNT_STATUS_INACTIVE,
                User::ACCOUNT_STATUS_SUSPENDED,
            ])],
        ];
    }
}
