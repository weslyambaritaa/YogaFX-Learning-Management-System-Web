<?php

namespace App\Http\Resources\Mobile\V1;

use App\Services\Mobile\V1\Concerns\BuildsMobileSignedContentImageUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentStudentResource extends JsonResource
{
    use BuildsMobileSignedContentImageUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->mobileSignedContentImageUrl(
                $this->resource,
                'user',
                $this->id,
                'profile_photo',
                $this->profile_photo,
                $this->updated_at,
            ),
            'profile_completed' => $this->hasCompletedStudentProfile(),
            'access_tier' => $this->accessTier
                ? [
                    'id' => $this->accessTier->id,
                    'name' => $this->accessTier->name,
                    'slug' => $this->accessTier->slug,
                ]
                : null,
        ];
    }
}
