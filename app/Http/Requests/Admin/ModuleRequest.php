<?php

namespace App\Http\Requests\Admin;

use App\Models\Module;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('url_slug')) {
            $this->merge([
                'url_slug' => Str::slug((string) $this->input('url_slug')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $module = $this->route('module');
        $thumbnailRule = $module ? ['nullable'] : ['required'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'url_slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(Module::class, 'url_slug')->ignore($module?->id),
            ],
            'thumbnail' => [...$thumbnailRule, 'image', 'max:2048'],
            'access_tier_ids' => ['required', 'array', 'min:1'],
            'access_tier_ids.*' => ['integer', Rule::exists('access_tiers', 'id')],
        ];
    }
}
