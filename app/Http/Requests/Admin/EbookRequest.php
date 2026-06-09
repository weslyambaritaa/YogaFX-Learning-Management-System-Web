<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ebook = $this->route('ebook');
        $fileRule = $ebook ? ['nullable'] : ['required'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => [...$fileRule, 'file', 'mimes:pdf', 'max:10240'],
            'access_tier_id' => ['required', Rule::exists('access_tiers', 'id')],
        ];
    }
}
