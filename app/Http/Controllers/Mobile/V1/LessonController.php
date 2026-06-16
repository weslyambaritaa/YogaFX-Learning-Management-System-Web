<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Mobile\V1\StudentLessonApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
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
}
