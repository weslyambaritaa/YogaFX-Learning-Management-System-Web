<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceConfirmationPdfService;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function __construct(
        private readonly InvoiceConfirmationPdfService $invoiceConfirmationPdfService,
    ) {}

    public function preview(Invoice $invoice): Response
    {
        $attachment = $this->invoiceConfirmationPdfService->makeAttachment($invoice);

        return response($attachment['data'], 200, [
            'Content-Type' => $attachment['mime'],
            'Content-Disposition' => 'inline; filename="'.$attachment['name'].'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        $attachment = $this->invoiceConfirmationPdfService->makeAttachment($invoice);

        return response($attachment['data'], 200, [
            'Content-Type' => $attachment['mime'],
            'Content-Disposition' => 'attachment; filename="'.$attachment['name'].'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
