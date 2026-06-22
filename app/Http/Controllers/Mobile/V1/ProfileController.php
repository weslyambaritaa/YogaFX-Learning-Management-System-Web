<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Mobile\V1\Concerns\BuildsMobileSignedContentImageUrls;
use App\Support\StudentProfileValue;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    use BuildsMobileSignedContentImageUrls;
    use HandlesLocalUploads;

    public function show(Request $request)
    {
        return MobileApiResponse::success(
            $this->profilePayload($request->user()),
            'Mobile profile retrieved successfully.',
        );
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        unset($validated['profile_photo'], $validated['whatsapp_country_code'], $validated['whatsapp_number']);
        $validated['yoga_sequence_experience'] = StudentProfileValue::encodeMultiSelect($validated['yoga_sequence_experience'] ?? null);
        $validated['how_did_you_find_us'] = StudentProfileValue::encodeMultiSelect($validated['how_did_you_find_us'] ?? null);

        $user->fill($validated);
        $user->birth_date = $validated['birth_date'] ?? $request->input('birth_date') ?? $user->birth_date;
        $user->syncDisplayName();

        $user->profile_photo = $this->storeUploadedFileToBunny(
            $request->file('profile_photo'),
            'users/profile-photos',
            $user->profile_photo,
        );

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return MobileApiResponse::success(
            $this->profilePayload($user->fresh('accessTier')),
            'Mobile profile updated successfully.',
        );
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error(
                'The change password payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $user = $request->user();
        $validated = $validator->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return MobileApiResponse::error(
                'The current password is incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'current_password' => ['The current password is incorrect.'],
                ],
            );
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return MobileApiResponse::success(
            [
                'password_changed' => true,
            ],
            'Mobile password changed successfully.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'whatsapp' => $user->whatsapp,
            'preferred_certificate_picture' => $user->preferred_certificate_picture,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->mobileSignedContentImageUrl(
                $user,
                'user',
                $user->id,
                'profile_photo',
                $user->profile_photo,
                $user->updated_at,
            ),
            'instagram' => $user->instagram,
            'country' => $user->country,
            'birth_date' => $user->birth_date?->toDateString(),
            'gender' => $user->gender,
            'practicing_yoga_for' => $user->practicing_yoga_for,
            'yoga_sequence_experience' => $user->yoga_sequence_experience,
            'hours_per_week' => $user->hours_per_week,
            'current_fitness_level' => $user->current_fitness_level,
            'flexibility_rating' => $user->flexibility_rating,
            'motivation' => $user->motivation,
            'why_yogafx' => $user->why_yogafx,
            'how_did_you_find_us' => $user->how_did_you_find_us,
            'role' => $user->role,
            'profile_completed' => $user->hasCompletedStudentProfile(),
            'access_tier' => $user->accessTier
                ? [
                    'id' => $user->accessTier->id,
                    'name' => $user->accessTier->name,
                    'slug' => $user->accessTier->slug,
                ]
                : null,
        ];
    }
}
