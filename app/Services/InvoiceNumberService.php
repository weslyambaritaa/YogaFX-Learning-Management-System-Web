<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceNumberService
{
    public function nextNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'INV-'.$year.'-';

        $latestInvoiceNumber = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $nextSequence = 1;

        if (is_string($latestInvoiceNumber) && preg_match('/^INV-\d{4}-(\d{4})$/', $latestInvoiceNumber, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $prefix, $nextSequence);
    }
}
