<?php

namespace App\Models;

use Database\Factories\AccommodationBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_number',
    'accommodation_id',
    'accommodation_room_type_id',
    'user_id',
    'guest_name',
    'guest_email',
    'guest_phone',
    'check_in_date',
    'check_out_date',
    'nights',
    'price_per_night',
    'total_amount',
    'currency_code',
    'status',
    'paypal_order_id',
    'hold_expires_at',
    'paid_at',
    'cancelled_at',
])]
class AccommodationBooking extends Model
{
    /** @use HasFactory<AccommodationBookingFactory> */
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'nights' => 'integer',
            'price_per_night' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(AccommodationRoomType::class, 'accommodation_room_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActiveHold(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isFuture();
    }
}
