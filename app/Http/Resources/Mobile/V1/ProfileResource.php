<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'whatsapp' => $this->whatsapp,
            'profile_photo' => $this->profile_photo,
            'instagram' => $this->instagram,
            'country' => $this->country,
            'birth_date' => optional($this->birth_date)->format('Y-m-d'),
            'gender' => $this->gender,
            'practicing_yoga_for' => $this->practicing_yoga_for,
            'yoga_sequence_experience' => $this->yoga_sequence_experience,
            'hours_per_week' => $this->hours_per_week,
            'current_fitness_level' => $this->current_fitness_level,
            'flexibility_rating' => $this->flexibility_rating,
            'motivation' => $this->motivation,
            'why_yogafx' => $this->why_yogafx,
            'how_did_you_find_us' => $this->how_did_you_find_us,
            'profile_completed' => $this->hasCompletedStudentProfile(),
            'access_tier' => $this->accessTier
                ? [
                    'id' => $this->accessTier->id,
                    'name' => $this->accessTier->name,
                    'slug' => $this->accessTier->slug,
                    'is_active' => $this->accessTier->is_active,
                ]
                : null,
        ];
    }
}
