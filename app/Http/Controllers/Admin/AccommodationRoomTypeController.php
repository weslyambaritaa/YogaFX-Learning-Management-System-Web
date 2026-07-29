<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccommodationRoomTypeRequest;
use App\Models\Accommodation;
use App\Models\AccommodationRoomType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccommodationRoomTypeController extends Controller
{
    public function index(Accommodation $accommodation): Response
    {
        return Inertia::render('Admin/Accommodations/RoomTypes/Index', [
            'accommodation' => $this->presentAccommodationSummary($accommodation),
            'roomTypes' => $accommodation->roomTypes()
                ->withCount('bookings')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (AccommodationRoomType $roomType) => $this->presentRoomType($roomType)),
            'status' => session('status'),
        ]);
    }

    public function create(Accommodation $accommodation): Response
    {
        return Inertia::render('Admin/Accommodations/RoomTypes/Create', [
            'accommodation' => $this->presentAccommodationSummary($accommodation),
        ]);
    }

    public function store(AccommodationRoomTypeRequest $request, Accommodation $accommodation): RedirectResponse
    {
        $data = $request->validated();
        $data['accommodation_id'] = $accommodation->id;
        $data['sort_order'] = ((int) $accommodation->roomTypes()->max('sort_order')) + 1;

        $accommodation->roomTypes()->create($data);

        return redirect()
            ->route('admin.accommodations.room-types.index', $accommodation)
            ->with('status', 'room-type-created');
    }

    public function edit(Accommodation $accommodation, AccommodationRoomType $roomType): Response
    {
        abort_unless($roomType->accommodation_id === $accommodation->id, 404);

        return Inertia::render('Admin/Accommodations/RoomTypes/Edit', [
            'accommodation' => $this->presentAccommodationSummary($accommodation),
            'roomType' => $this->presentRoomType($roomType),
            'status' => session('status'),
        ]);
    }

    public function update(AccommodationRoomTypeRequest $request, Accommodation $accommodation, AccommodationRoomType $roomType): RedirectResponse
    {
        abort_unless($roomType->accommodation_id === $accommodation->id, 404);

        $roomType->update($request->validated());

        return redirect()
            ->route('admin.accommodations.room-types.index', $accommodation)
            ->with('status', 'room-type-updated');
    }

    public function destroy(Accommodation $accommodation, AccommodationRoomType $roomType): RedirectResponse
    {
        abort_unless($roomType->accommodation_id === $accommodation->id, 404);

        if ($roomType->bookings()->exists()) {
            return redirect()
                ->route('admin.accommodations.room-types.index', $accommodation)
                ->withErrors([
                    'room_type' => 'This room type cannot be deleted because it already has booking history.',
                ]);
        }

        $roomType->delete();

        return redirect()
            ->route('admin.accommodations.room-types.index', $accommodation)
            ->with('status', 'room-type-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAccommodationSummary(Accommodation $accommodation): array
    {
        return [
            'id' => $accommodation->id,
            'title' => $accommodation->title,
            'slug' => $accommodation->slug,
            'currency_code' => $accommodation->currency_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRoomType(AccommodationRoomType $roomType): array
    {
        return [
            'id' => $roomType->id,
            'title' => $roomType->title,
            'price' => (float) $roomType->price,
            'total_rooms' => $roomType->total_rooms,
            'is_active' => $roomType->is_active,
            'sort_order' => $roomType->sort_order,
            'bookings_count' => $roomType->bookings_count,
        ];
    }
}
