<?php

namespace App\Http\Requests;

use App\Models\AccommodationRoomType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accommodation = $this->route('accommodation');

        return [
            'room_type_id' => [
                'required',
                'integer',
                Rule::exists(AccommodationRoomType::class, 'id')
                    ->where('accommodation_id', $accommodation?->id)
                    ->where('is_active', true),
            ],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'installment_count' => ['nullable', 'integer', 'min:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_type_id.exists' => 'The selected room type is not available for this hotel.',
            'check_in_date.after_or_equal' => 'Check-in date cannot be in the past.',
            'check_out_date.after' => 'Check-out date must be after the check-in date.',
        ];
    }
}
