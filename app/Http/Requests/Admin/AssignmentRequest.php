<?php

namespace App\Http\Requests\Admin;

use App\Models\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(Assignment::STATUSES)],
            'is_required' => ['nullable', 'boolean'],
        ];
    }
}
