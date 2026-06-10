<?php

namespace App\Http\Requests\Admin;

use App\Support\UploadConstraints;
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
            'file' => [...$fileRule, 'file', 'mimes:pdf', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'access_tier_ids' => ['required', 'array', 'min:1'],
            'access_tier_ids.*' => ['integer', Rule::exists('access_tiers', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The ebook file must not be larger than 10 MB.',
        ];
    }
}
