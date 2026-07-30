<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccommodationRequest;
use App\Models\Accommodation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccommodationController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Accommodations/Index', [
            'accommodations' => Accommodation::query()
                ->withCount('roomTypes')
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get()
                ->map(fn (Accommodation $accommodation) => $this->presentAccommodation($accommodation)),
            'publicBaseUrl' => url('/stay'),
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Accommodations/Create', [
            'publicBaseUrl' => url('/stay'),
        ]);
    }

    public function store(AccommodationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['image'] = $this->storeUploadedFileToBunny(
            $request->file('image'),
            'accommodations/images',
        );

        Accommodation::query()->create($data);

        return redirect()
            ->route('admin.accommodations.index')
            ->with('status', 'accommodation-created');
    }

    public function edit(Accommodation $accommodation): Response
    {
        $accommodation->loadCount('roomTypes');

        return Inertia::render('Admin/Accommodations/Edit', [
            'accommodation' => $this->presentAccommodation($accommodation),
            'status' => session('status'),
        ]);
    }

    public function update(AccommodationRequest $request, Accommodation $accommodation): RedirectResponse
    {
        $data = $request->validated();
        $data['image'] = $this->storeUploadedFileToBunny(
            $request->file('image'),
            'accommodations/images',
            $accommodation->image,
        );

        $accommodation->update($data);

        return redirect()
            ->route('admin.accommodations.index')
            ->with('status', 'accommodation-updated');
    }

    public function destroy(Accommodation $accommodation): RedirectResponse
    {
        if ($accommodation->roomTypes()->exists()) {
            return redirect()
                ->route('admin.accommodations.index')
                ->withErrors([
                    'accommodation' => 'This accommodation cannot be deleted because it still has room types. Delete the room types first.',
                ]);
        }

        $this->deleteUploadedFileFromAnyStorage($accommodation->image);
        $accommodation->delete();

        return redirect()
            ->route('admin.accommodations.index')
            ->with('status', 'accommodation-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAccommodation(Accommodation $accommodation): array
    {
        return [
            'id' => $accommodation->id,
            'title' => $accommodation->title,
            'slug' => $accommodation->slug,
            'public_link' => url('/stay/'.$accommodation->slug),
            'description' => $accommodation->description,
            'image_url' => $this->protectedMediaUrl(
                'accommodation',
                $accommodation->id,
                'image',
                $accommodation->image,
                versionSeed: $accommodation->updated_at,
            ),
            'currency_code' => $accommodation->currency_code,
            'is_active' => $accommodation->is_active,
            'installment_enabled' => $accommodation->installment_enabled,
            'installment_count_mode' => $accommodation->installment_count_mode,
            'installment_fixed_count' => $accommodation->installment_fixed_count,
            'room_types_count' => $accommodation->room_types_count,
        ];
    }
}
