<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Student/Modules/Index', [
            'modules' => Module::query()
                ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user?->access_tier_id))
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Module $module) => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'url_slug' => $module->url_slug,
                    'sort_order' => $module->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'module',
                        $module->id,
                        'thumbnail',
                        $module->thumbnail,
                        versionSeed: $module->updated_at,
                    ),
                ]),
        ]);
    }

    public function show(Request $request, Module $module): Response
    {
        $user = $request->user();
        abort_unless(
            $user
            && $module->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        return Inertia::render('Student/Modules/Show', [
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'url_slug' => $module->url_slug,
                'sort_order' => $module->sort_order,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
                'lessons' => Lesson::query()
                    ->where('module_id', $module->id)
                    ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->get()
                    ->map(fn (Lesson $lesson) => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'sort_order' => $lesson->sort_order,
                        'has_workbook' => $lesson->workbook !== null,
                        'has_video' => $lesson->video !== null,
                        'has_audio' => $lesson->audio !== null,
                        'has_content' => $lesson->content !== null,
                    ]),
            ],
        ]);
    }
}
