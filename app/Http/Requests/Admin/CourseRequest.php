<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
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
        $course = $this->route('course');
        $thumbnailRule = $course ? ['nullable'] : ['required'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'url_slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(Course::class, 'url_slug')->ignore($course?->id),
            ],
            'access_tier_id' => ['required', Rule::exists('access_tiers', 'id')],
            'description' => ['required', 'string', 'max:5000'],
            'thumbnail' => [...$thumbnailRule, 'image', 'max:2048'],
            'video' => ['required', 'string', 'max:2048'],
        ];
    }
}
