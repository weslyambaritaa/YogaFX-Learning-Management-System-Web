<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Mobile\V1\StudentLessonApiService;
use App\Services\StudentIrregularActivityService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class LessonController extends Controller
{
    public function __construct(
        private readonly StudentLessonApiService $studentLessonApiService,
        private readonly StudentIrregularActivityService $studentIrregularActivityService,
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
            'watch_time_increment_seconds' => ['nullable', 'integer', 'min:0', 'max:36000'],
            'video_duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
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
            (int) ($validator->validated()['watch_time_increment_seconds'] ?? 0),
            isset($validator->validated()['video_duration_seconds'])
                ? (int) round((float) $validator->validated()['video_duration_seconds'])
                : null,
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

    public function triggerWorkbook(Request $request, Lesson $lesson)
    {
        $result = $this->studentLessonApiService->triggerWorkbookForUser(
            $request->user(),
            $lesson,
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
            'Mobile workbook trigger completed successfully.',
        );
    }

    public function recordIrregularActivity(Request $request, Lesson $lesson)
    {
        $validator = Validator::make($request->all(), [
            'watch_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'watch_time_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'video_duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'lesson_completed' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The irregular activity payload is invalid.',
                'blocked' => false,
                'violation_count' => 0,
                'lesson_id' => $lesson->id,
                'user_id' => (int) $request->user()->id,
                'errors' => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->studentIrregularActivityService->recordLessonExitAttempt(
            $request->user(),
            $lesson,
            (float) $validator->validated()['watch_progress'],
            (int) $validator->validated()['watch_time_seconds'],
            (int) $validator->validated()['video_duration_seconds'],
            (bool) $validator->validated()['lesson_completed'],
        );

        $status = ($result['blocked'] ?? false) ? Response::HTTP_FORBIDDEN : Response::HTTP_OK;

        return response()->json($result, $status);
    }
}
