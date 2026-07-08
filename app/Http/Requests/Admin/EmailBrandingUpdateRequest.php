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
            'email_header_html' => ['nullable', 'string'],
            'email_signature_html' => ['nullable', 'string'],
            'pdf_header_html' => ['nullable', 'string'],
            'watermark_html' => ['nullable', 'string'],
        ];
    }
}
