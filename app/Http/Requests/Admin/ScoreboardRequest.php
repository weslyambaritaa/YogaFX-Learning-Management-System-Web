<?php

namespace App\Http\Requests\Admin;

use App\Models\Assessment;
use App\Support\UploadConstraints;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScoreboardRequest extends FormRequest
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
        /** @var \App\Models\Assessment|null $assessment */
        $assessment = $this->route('assessment');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assessments', 'slug')->ignore($assessment?->id),
            ],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'status' => ['required', Rule::in([
                Assessment::STATUS_DRAFT,
                Assessment::STATUS_LIVE,
                Assessment::STATUS_ARCHIVED,
            ])],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'scoring_mode' => ['required', 'string', 'max:100'],
            'result_mode' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'show_progress_bar' => ['required', 'boolean'],
            'allow_back_navigation' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'thumbnail.max' => 'The thumbnail must not be larger than 10 MB.',
        ];
    }
}
