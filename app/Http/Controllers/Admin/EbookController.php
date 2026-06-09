<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EbookRequest;
use App\Models\AccessTier;
use App\Models\Ebook;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EbookController extends Controller
{
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Ebooks/Index', [
            'ebooks' => Ebook::query()
                ->with('accessTiers')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (Ebook $ebook) => [
                    'id' => $ebook->id,
                    'title' => $ebook->title,
                    'access_tiers' => $ebook->accessTiers->pluck('name')->all(),
                    'sort_order' => $ebook->sort_order,
                    'file_url' => route('media.show', ['entity' => 'ebook', 'id' => $ebook->id, 'field' => 'file']),
                ]),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Ebooks/Create', [
            'accessTiers' => $this->accessTierOptions(),
        ]);
    }

    public function store(EbookRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['file'] = $this->storeUploadedFile($request->file('file'), 'ebooks/files');
        unset($data['access_tier_ids']);

        $ebook = Ebook::query()->create($data);
        $ebook->accessTiers()->sync($request->validated('access_tier_ids'));

        return redirect()
            ->route('admin.ebooks.index')
            ->with('status', 'ebook-created');
    }

    public function edit(Ebook $ebook): Response
    {
        return Inertia::render('Admin/Ebooks/Edit', [
            'ebook' => [
                'id' => $ebook->id,
                'title' => $ebook->title,
                'access_tier_ids' => $ebook->accessTiers()->pluck('access_tiers.id')->all(),
                'file_url' => route('media.show', ['entity' => 'ebook', 'id' => $ebook->id, 'field' => 'file']),
            ],
            'accessTiers' => $this->accessTierOptions(),
            'status' => session('status'),
        ]);
    }

    public function update(EbookRequest $request, Ebook $ebook): RedirectResponse
    {
        $data = $request->validated();
        $data['file'] = $this->storeUploadedFile(
            $request->file('file'),
            'ebooks/files',
            $ebook->file,
        );
        unset($data['access_tier_ids']);

        $ebook->update($data);
        $ebook->accessTiers()->sync($request->validated('access_tier_ids'));

        return redirect()
            ->route('admin.ebooks.index')
            ->with('status', 'ebook-updated');
    }

    public function destroy(Ebook $ebook): RedirectResponse
    {
        $this->deleteUploadedFile($ebook->file);
        $ebook->delete();

        return redirect()
            ->route('admin.ebooks.index')
            ->with('status', 'ebook-deleted');
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
