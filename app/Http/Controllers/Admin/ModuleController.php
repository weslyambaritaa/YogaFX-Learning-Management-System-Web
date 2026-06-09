<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModuleRequest;
use App\Models\AccessTier;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Modules/Index', [
            'modules' => Module::query()
                ->with(['accessTier'])
                ->withCount('lessons')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Module $module) => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'url_slug' => $module->url_slug,
                    'sort_order' => $module->sort_order,
                    'thumbnail_url' => route('media.show', ['entity' => 'module', 'id' => $module->id, 'field' => 'thumbnail']),
                    'access_tier' => $module->accessTier?->name,
                    'lessons_count' => $module->lessons_count,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Modules/Create', [
            'accessTiers' => $this->accessTierOptions(),
        ]);
    }

    public function store(ModuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile($request->file('thumbnail'), 'modules/thumbnails');

        $module = Module::query()->create($data);

        return redirect()
            ->route('admin.modules.edit', $module)
            ->with('status', 'module-created');
    }

    public function edit(Module $module): Response
    {
        return Inertia::render('Admin/Modules/Edit', [
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'url_slug' => $module->url_slug,
                'access_tier_id' => $module->access_tier_id,
                'sort_order' => $module->sort_order,
                'thumbnail_url' => route('media.show', ['entity' => 'module', 'id' => $module->id, 'field' => 'thumbnail']),
            ],
            'accessTiers' => $this->accessTierOptions(),
            'status' => session('status'),
        ]);
    }

    public function update(ModuleRequest $request, Module $module): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile(
            $request->file('thumbnail'),
            'modules/thumbnails',
            $module->thumbnail,
        );

        $module->update($data);

        return redirect()
            ->route('admin.modules.edit', $module)
            ->with('status', 'module-updated');
    }

    public function destroy(Module $module): RedirectResponse
    {
        if ($module->lessons()->exists()) {
            return redirect()
                ->route('admin.modules.index')
                ->withErrors([
                    'module' => 'This module cannot be deleted because it still contains lessons.',
                ]);
        }

        $this->deleteUploadedFile($module->thumbnail);
        $module->delete();

        return redirect()
            ->route('admin.modules.index')
            ->with('status', 'module-deleted');
    }

    private function accessTierOptions(): array
    {
        return AccessTier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (AccessTier $accessTier) => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'is_active' => $accessTier->is_active,
            ])
            ->all();
    }
}
