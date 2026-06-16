<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\V1\StudentModuleApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function __construct(
        private readonly StudentModuleApiService $studentModuleApiService,
    ) {}

    public function index(Request $request)
    {
        $moduleItems = $this->studentModuleApiService->moduleItemsForUser($request->user());

        return MobileApiResponse::success([
            'items' => $moduleItems->values()->all(),
            'summary' => [
                'total' => $moduleItems->count(),
                'visible' => $moduleItems->where('is_visible', true)->count(),
                'completed' => $moduleItems->where('status', 'completed')->count(),
                'active' => $moduleItems->where('status', 'active')->count(),
            ],
        ], 'Mobile modules retrieved successfully.');
    }
}
