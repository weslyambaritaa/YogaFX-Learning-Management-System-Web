<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\V1\LoginRequest;
use App\Http\Resources\Mobile\V1\CurrentStudentResource;
use App\Models\User;
use App\Services\StudentSessionTrackingService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly StudentSessionTrackingService $studentSessionTrackingService,
    ) {}

    public function store(LoginRequest $request)
    {
        $user = $request->authenticateStudent();

        if (! $user->isStudent()) {
            return MobileApiResponse::error(
                'This mobile API is only available to student accounts.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (! $user->isStudentAccountActive()) {
            return MobileApiResponse::error(
                'Your student account is inactive.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $token = $user->createToken($request->deviceName());

        $this->studentSessionTrackingService->startMobileSession($user, $token->accessToken->id);

        return MobileApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new CurrentStudentResource($user->load('accessTier')),
        ], 'Login successful.', Response::HTTP_OK);
    }

    public function destroy(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $this->studentSessionTrackingService->endMobileSession($request, $user);
        $request->user()?->currentAccessToken()?->delete();

        return MobileApiResponse::success([
            'user_id' => $user->id,
        ], 'Logout successful.');
    }
}
