<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Mobile\V1\StudentCourseApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseController extends Controller
{
    public function __construct(
        private readonly StudentCourseApiService $studentCourseApiService,
    ) {}

    public function index(Request $request)
    {
        return MobileApiResponse::success(
            $this->studentCourseApiService->listForUser($request->user()),
            'Mobile course list retrieved successfully.',
        );
    }

    public function show(Request $request, Course $course)
    {
        $payload = $this->studentCourseApiService->detailForUser($request->user(), $course);

        if (! $payload) {
            return MobileApiResponse::error(
                'Course not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return MobileApiResponse::success(
            $payload,
            'Mobile course detail retrieved successfully.',
        );
    }
}
