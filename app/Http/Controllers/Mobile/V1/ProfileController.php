<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\ProfileResource;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request)
    {
        return MobileApiResponse::success(
            new ProfileResource($request->user()->loadMissing('accessTier')),
            'Authenticated student profile retrieved successfully.',
        );
    }
}
