<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LinkControlSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'qr_image' => ['nullable', 'image', 'max:10240'],
            'google_play_url' => ['nullable', 'url', 'max:2048'],
            'app_store_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
