<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\CurrentStudentResource;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        return MobileApiResponse::success(
            new CurrentStudentResource($request->user()),
            'Authenticated student retrieved successfully.',
        );
    }
}
