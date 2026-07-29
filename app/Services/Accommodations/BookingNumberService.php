<?php

namespace App\Services\Accommodations;

use App\Models\AccommodationBooking;

class BookingNumberService
{
    /**
     * Must be called from inside a DB::transaction() alongside the row insert
     * that consumes this number — the lockForUpdate() below is only
     * meaningful within an open transaction (mirrors InvoiceNumberService).
     */
    public function nextNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'BOOK-'.$year.'-';

        $latestBookingNumber = AccommodationBooking::query()
            ->where('booking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('booking_number')
            ->value('booking_number');

        $nextSequence = 1;

        if (is_string($latestBookingNumber) && preg_match('/^BOOK-\d{4}-(\d{6})$/', $latestBookingNumber, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%06d', $prefix, $nextSequence);
    }
}
