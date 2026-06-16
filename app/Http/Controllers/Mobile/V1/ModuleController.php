<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Mobile\V1\StudentModuleApiService;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function show(Request $request, Module $module)
    {
        $detail = $this->studentModuleApiService->moduleDetailForUser($request->user(), $module->id);

        if (! $detail) {
            return MobileApiResponse::error(
                'Module not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if (! ($detail['is_visible'] ?? true)) {
            return MobileApiResponse::error(
                'This module is still locked for the authenticated student.',
                Response::HTTP_FORBIDDEN,
                [
                    'module_id' => $module->id,
                    'lock_reason' => $detail['lock_reason'] ?? null,
                ],
            );
        }

        return MobileApiResponse::success(
            $detail,
            'Mobile module detail retrieved successfully.',
        );
    }
}
