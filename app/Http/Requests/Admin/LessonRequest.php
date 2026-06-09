<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
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
        $lesson = $this->route('lesson');
        $thumbnailRule = $lesson ? ['nullable'] : ['required'];

        return [
            'module_id' => ['required', Rule::exists('modules', 'id')],
            'access_tier_ids' => ['required', 'array', 'min:1'],
            'access_tier_ids.*' => ['integer', Rule::exists('access_tiers', 'id')],
            'assessment_id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'thumbnail' => [...$thumbnailRule, 'image', 'max:2048'],
            'workbook' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'video' => ['nullable', 'string', 'max:2048'],
            'audio' => ['nullable', 'string', 'max:2048'],
            'content' => ['nullable', 'string'],
        ];
    }
}
