<?php

namespace App\Models;

use Database\Factories\AccommodationRoomTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'accommodation_id',
    'title',
    'price',
    'total_rooms',
    'is_active',
    'sort_order',
])]
class AccommodationRoomType extends Model
{
    /** @use HasFactory<AccommodationRoomTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'total_rooms' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AccommodationBooking::class);
    }
}
