<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Services\Mobile\V1\StudentAssessmentApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly StudentAssessmentApiService $studentAssessmentApiService,
    ) {}

    public function intro(Request $request, Lesson $lesson)
    {
        $payload = $this->studentAssessmentApiService->introForUser($request->user(), $lesson);

        if (! $payload) {
            return MobileApiResponse::error('Assessment not found for this lesson.', Response::HTTP_NOT_FOUND);
        }

        return MobileApiResponse::success($payload, 'Mobile assessment intro retrieved successfully.');
    }

    public function start(Request $request, Lesson $lesson)
    {
        try {
            $payload = $this->studentAssessmentApiService->startForUser($request->user(), $lesson);
        } catch (ValidationException $exception) {
            return MobileApiResponse::error(
                'Unable to start this assessment.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->errors(),
            );
        }

        if (! $payload) {
            return MobileApiResponse::error('Assessment not found for this lesson.', Response::HTTP_NOT_FOUND);
        }

        return MobileApiResponse::success($payload, 'Mobile assessment attempt prepared successfully.');
    }

    public function show(Request $request, Lesson $lesson, AssessmentAttempt $attempt)
    {
        $payload = $this->studentAssessmentApiService->attemptForUser($request->user(), $lesson, $attempt);

        if (! $payload) {
            return MobileApiResponse::error('Assessment attempt not found for the authenticated student.', Response::HTTP_NOT_FOUND);
        }

        return MobileApiResponse::success($payload, 'Mobile assessment attempt retrieved successfully.');
    }

    public function storeAnswer(Request $request, Lesson $lesson, AssessmentAttempt $attempt)
    {
        try {
            $payload = $this->studentAssessmentApiService->storeAnswerForUser($request, $request->user(), $lesson, $attempt);
        } catch (ValidationException $exception) {
            return MobileApiResponse::error(
                'The assessment answer payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->errors(),
            );
        }

        if (! $payload) {
            return MobileApiResponse::error('Assessment attempt not found for the authenticated student.', Response::HTTP_NOT_FOUND);
        }

        return MobileApiResponse::success($payload, 'Mobile assessment answer saved successfully.');
    }

    public function back(Request $request, Lesson $lesson, AssessmentAttempt $attempt)
    {
        $payload = $this->studentAssessmentApiService->backForUser($request->user(), $lesson, $attempt);

        if (! $payload) {
            return MobileApiResponse::error('Assessment attempt not found for the authenticated student.', Response::HTTP_NOT_FOUND);
        }

        return MobileApiResponse::success($payload, 'Mobile assessment navigation updated successfully.');
    }

    public function result(Request $request, Lesson $lesson, AssessmentAttempt $attempt)
    {
        $payload = $this->studentAssessmentApiService->resultForUser($request->user(), $lesson, $attempt);

        if (! $payload) {
            return MobileApiResponse::error('Assessment attempt not found for the authenticated student.', Response::HTTP_NOT_FOUND);
        }

        return MobileApiResponse::success($payload, 'Mobile assessment result retrieved successfully.');
    }
}
