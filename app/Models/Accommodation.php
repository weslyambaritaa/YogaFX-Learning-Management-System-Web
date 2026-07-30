<?php

namespace App\Models;

use Database\Factories\AccommodationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'description',
    'image',
    'currency_code',
    'is_active',
    'installment_enabled',
    'installment_count_mode',
    'installment_fixed_count',
])]
class Accommodation extends Model
{
    /** @use HasFactory<AccommodationFactory> */
    use HasFactory;

    public const INSTALLMENT_COUNT_MODE_FLEX = 'flex';

    public const INSTALLMENT_COUNT_MODE_FIXED = 'fixed';

    public const MIN_INSTALLMENT_COUNT = 2;

    public const MAX_PROVIDER_INSTALLMENT_COUNT = 15;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'installment_enabled' => 'boolean',
            'installment_fixed_count' => 'integer',
        ];
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(AccommodationRoomType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AccommodationBooking::class);
    }
}
