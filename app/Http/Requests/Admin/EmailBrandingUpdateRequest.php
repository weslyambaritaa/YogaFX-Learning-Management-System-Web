<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EmailBrandingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'logo_html' => ['nullable', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
        ];
    }
}
