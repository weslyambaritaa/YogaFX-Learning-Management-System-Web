<?php

namespace App\Http\Requests\Admin;

use App\Models\AccessTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccessTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $accessTier = $this->route('accessTier');
        $generatedSlug = $accessTier?->slug;

        if ($generatedSlug === null || $generatedSlug === '') {
            $generatedSlug = AccessTier::canonicalSlug((string) $this->input('name', ''));
        }

        $this->merge([
            'slug' => $generatedSlug,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accessTier = $this->route('accessTier');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique(AccessTier::class, 'slug')->ignore($accessTier?->id),
            ],
            'description' => ['required', 'string', 'max:2000'],
            'level' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'has_full_standing_dialog_access' => ['required', 'boolean'],
            'has_full_floor_dialog_access' => ['required', 'boolean'],
        ];
    }
}
