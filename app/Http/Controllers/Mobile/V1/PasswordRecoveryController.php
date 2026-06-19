<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Services\PasswordChangeFlowService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PasswordRecoveryController extends Controller
{
    public function __construct(
        private readonly PasswordChangeFlowService $passwordChangeFlowService,
    ) {}

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
            'otp_code' => ['required', 'digits:6'],
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

        $result = $this->passwordChangeFlowService->resetWithOtp(
            $validated['email'],
            $validated['token'],
            $validated['otp_code'],
            $validated['password'],
            (string) $request->input('password_confirmation'),
        );

        $status = $result['status'];

        if ($status === Password::PASSWORD_RESET) {
            return MobileApiResponse::success(
                [
                    'email' => $validated['email'],
                    'password_reset' => true,
                    'mobile_origin' => $result['password_change_request']->isMobileOrigin(),
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
