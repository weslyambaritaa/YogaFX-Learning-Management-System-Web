<?php

namespace App\Http\Requests\Admin;

use App\Models\AccessTier;
use App\Models\Module;
use App\Support\UploadConstraints;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'thumbnail' => [...$thumbnailRule, 'image', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'workbook' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.UploadConstraints::MAX_FILE_SIZE_KB],
            'video' => ['nullable', 'string', 'max:2048'],
            'audio' => ['nullable', 'string', 'max:2048'],
            'content' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'thumbnail.max' => 'The thumbnail must not be larger than 10 MB.',
            'workbook.max' => 'The workbook file must not be larger than 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('module_id') || $validator->errors()->has('access_tier_ids')) {
                return;
            }

            $moduleId = $this->integer('module_id');
            $selectedTierIds = collect($this->input('access_tier_ids', []))
                ->map(fn ($tierId) => (int) $tierId)
                ->unique()
                ->values();

            if ($moduleId === 0 || $selectedTierIds->isEmpty()) {
                return;
            }

            $module = Module::query()
                ->with('accessTiers:id,name')
                ->find($moduleId);

            if (! $module) {
                return;
            }

            $allowedTierIds = $module->accessTiers
                ->pluck('id')
                ->map(fn ($tierId) => (int) $tierId);

            $invalidTierIds = $selectedTierIds->diff($allowedTierIds)->values();

            if ($invalidTierIds->isEmpty()) {
                return;
            }

            $invalidTierNames = AccessTier::query()
                ->whereIn('id', $invalidTierIds->all())
                ->orderBy('name')
                ->pluck('name')
                ->all();

            $allowedTierNames = $module->accessTiers
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            $validator->errors()->add(
                'access_tier_ids',
                sprintf(
                    'The selected lesson tier(s) are not allowed for module "%s". Invalid tier(s): %s. Allowed tier(s): %s.',
                    $module->title,
                    implode(', ', $invalidTierNames),
                    implode(', ', $allowedTierNames),
                ),
            );
        });
    }
}
