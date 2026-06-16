<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\Mobile\V1\StudentAssignmentApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly StudentAssignmentApiService $studentAssignmentApiService,
    ) {}

    public function show(Request $request, Assignment $assignment)
    {
        $payload = $this->studentAssignmentApiService->detailForUser($request->user(), $assignment);

        if (! $payload) {
            return MobileApiResponse::error(
                'Assignment not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if (($payload['is_locked'] ?? false) === true) {
            return MobileApiResponse::error(
                'This assignment is still locked for the authenticated student.',
                Response::HTTP_FORBIDDEN,
                [
                    'assignment_id' => $assignment->id,
                    'lock_reason' => $payload['lock_reason'] ?? null,
                ],
            );
        }

        return MobileApiResponse::success(
            $payload,
            'Mobile assignment detail retrieved successfully.',
        );
    }

    public function submit(Request $request, Assignment $assignment)
    {
        try {
            $payload = $this->studentAssignmentApiService->submitForUser($request, $request->user(), $assignment);
        } catch (ValidationException $exception) {
            return MobileApiResponse::error(
                'The assignment submission payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->errors(),
            );
        }

        if (! $payload) {
            return MobileApiResponse::error(
                'Assignment not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if (($payload['is_locked'] ?? false) === true) {
            return MobileApiResponse::error(
                'This assignment is still locked for the authenticated student.',
                Response::HTTP_FORBIDDEN,
                [
                    'assignment_id' => $assignment->id,
                    'lock_reason' => $payload['lock_reason'] ?? null,
                ],
            );
        }

        return MobileApiResponse::success(
            $payload,
            'Mobile assignment submitted successfully.',
        );
    }
}
