<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Mobile\V1\StudentLessonApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class LessonController extends Controller
{
    public function __construct(
        private readonly StudentLessonApiService $studentLessonApiService,
    ) {}

    public function show(Request $request, Lesson $lesson)
    {
        $detail = $this->studentLessonApiService->lessonDetailForUser($request->user(), $lesson);

        if (! $detail) {
            return MobileApiResponse::error(
                'Lesson not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($detail['is_locked'] ?? false) {
            return MobileApiResponse::error(
                'This lesson is still locked for the authenticated student.',
                Response::HTTP_FORBIDDEN,
                [
                    'lesson_id' => $lesson->id,
                    'lock_reason' => $detail['lock_reason'] ?? null,
                ],
            );
        }

        return MobileApiResponse::success(
            $detail,
            'Mobile lesson detail retrieved successfully.',
        );
    }

    public function updateProgress(Request $request, Lesson $lesson)
    {
        $validator = Validator::make($request->all(), [
            'watch_progress' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error(
                'The lesson progress payload is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $result = $this->studentLessonApiService->updateProgressForUser(
            $request->user(),
            $lesson,
            (float) $validator->validated()['watch_progress'],
        );

        if (! $result) {
            return MobileApiResponse::error(
                'Lesson not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if (($result['is_locked'] ?? false) === true) {
            return MobileApiResponse::error(
                'This lesson is still locked for the authenticated student.',
                Response::HTTP_FORBIDDEN,
                [
                    'lesson_id' => $lesson->id,
                    'lock_reason' => $result['lock_reason'] ?? null,
                ],
            );
        }

        return MobileApiResponse::success(
            $result,
            'Mobile lesson progress updated successfully.',
        );
    }
}
