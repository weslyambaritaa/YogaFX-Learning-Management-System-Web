<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Support\MobileApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PasswordRecoveryController extends Controller
{
    public function forgot(Request $request)
    {
        $validator = validator($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error(
                'The forgot password payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $status = Password::sendResetLink($validator->validated());

        if ($status === Password::RESET_LINK_SENT) {
            return MobileApiResponse::success(
                [
                    'email' => $validator->validated()['email'],
                ],
                'Password reset email sent successfully.',
            );
        }

        return MobileApiResponse::error(
            'Unable to send password reset email.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'email' => [trans($status)],
            ],
        );
    }

    public function reset(Request $request)
    {
        $validator = validator($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error(
                'The reset password payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function ($user) use ($validated): void {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return MobileApiResponse::success(
                [
                    'email' => $validated['email'],
                    'password_reset' => true,
                ],
                'Password reset successfully.',
            );
        }

        return MobileApiResponse::error(
            'Unable to reset password.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'email' => [trans($status)],
            ],
        );
    }
}
