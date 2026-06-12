<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModuleRequest;
use App\Models\AccessTier;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function index(): Response
    {
        $this->normalizeModuleSortOrder();

        return Inertia::render('Admin/Modules/Index', [
            'modules' => Module::query()
                ->with(['accessTiers'])
                ->withCount('lessons')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Module $module) => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'url_slug' => $module->url_slug,
                    'sort_order' => $module->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'module',
                        $module->id,
                        'thumbnail',
                        $module->thumbnail,
                        versionSeed: $module->updated_at,
                    ),
                    'access_tiers' => $module->accessTiers->pluck('name')->all(),
                    'lessons_count' => $module->lessons_count,
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        $this->normalizeModuleSortOrder();

        return Inertia::render('Admin/Modules/Create', [
            'accessTiers' => $this->accessTierOptions(),
            'nextSortOrder' => ((int) Module::query()->count()) + 1,
        ]);
    }

    public function store(ModuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['thumbnail'] = $this->storeUploadedFile($request->file('thumbnail'), 'modules/thumbnails');
        unset($data['access_tier_ids']);
        $requestedSortOrder = (int) ($data['sort_order'] ?? 0);

        $module = DB::transaction(function () use ($data, $request, $requestedSortOrder) {
            unset($data['sort_order']);

            $module = Module::query()->create($data);
            $this->moveModuleToSortOrder($module, $requestedSortOrder);
            $module->accessTiers()->sync($request->validated('access_tier_ids'));

            return $module;
        });

        return redirect()
            ->route('admin.modules.index')
            ->with('status', 'module-created');
    }

    public function edit(Module $module): Response
    {
        $this->normalizeModuleSortOrder();

        $module->refresh();

        return Inertia::render('Admin/Modules/Edit', [
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'sort_order' => $module->sort_order,
                'url_slug' => $module->url_slug,
                'access_tier_ids' => $module->accessTiers()->pluck('access_tiers.id')->all(),
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
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
        unset($data['access_tier_ids']);
        $requestedSortOrder = (int) ($data['sort_order'] ?? $module->sort_order);

        DB::transaction(function () use ($data, $module, $request, $requestedSortOrder): void {
            unset($data['sort_order']);

            $module->update($data);
            $this->moveModuleToSortOrder($module, $requestedSortOrder);
            $module->accessTiers()->sync($request->validated('access_tier_ids'));
        });

        return redirect()
            ->route('admin.modules.index')
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
        $this->normalizeModuleSortOrder();

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

    private function normalizeModuleSortOrder(): void
    {
        Module::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (Module $module, int $index): void {
                $expectedOrder = $index + 1;

                if ((int) $module->sort_order !== $expectedOrder) {
                    $module->updateQuietly([
                        'sort_order' => $expectedOrder,
                    ]);
                }
            });
    }

    private function moveModuleToSortOrder(Module $module, int $requestedSortOrder): void
    {
        $this->normalizeModuleSortOrder();
        $module->refresh();

        $moduleCount = (int) Module::query()->count();
        $targetOrder = max(1, min($requestedSortOrder > 0 ? $requestedSortOrder : $moduleCount, $moduleCount));
        $currentOrder = (int) $module->sort_order;

        if ($currentOrder === $targetOrder) {
            return;
        }

        if ($targetOrder < $currentOrder) {
            Module::query()
                ->whereKeyNot($module->id)
                ->whereBetween('sort_order', [$targetOrder, $currentOrder - 1])
                ->increment('sort_order');
        } else {
            Module::query()
                ->whereKeyNot($module->id)
                ->whereBetween('sort_order', [$currentOrder + 1, $targetOrder])
                ->decrement('sort_order');
        }

        $module->updateQuietly([
            'sort_order' => $targetOrder,
        ]);

        $this->normalizeModuleSortOrder();
    }
}
