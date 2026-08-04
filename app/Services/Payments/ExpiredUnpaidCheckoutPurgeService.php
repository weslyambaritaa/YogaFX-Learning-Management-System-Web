<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentSubscriptionEvent;
use App\Models\PendingRegistration;
use Illuminate\Support\Facades\DB;

class ExpiredUnpaidCheckoutPurgeService
{
    public const EXPIRY_HOURS = 72;

    /**
     * @return array{processed:int,invoices_deleted:int,pending_registrations_deleted:int}
     */
    public function purge(): array
    {
        $processed = 0;
        $invoicesDeleted = 0;
        $pendingRegistrationsDeleted = 0;
        $cutoff = now()->subHours(self::EXPIRY_HOURS);

        Invoice::query()
            ->where('type', Invoice::TYPE_INITIAL)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('issued_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$processed, &$invoicesDeleted, &$pendingRegistrationsDeleted): void {
                foreach ($invoices as $invoice) {
                    $processed++;

                    $result = DB::transaction(function () use ($invoice): array {
                        /** @var Invoice|null $lockedInvoice */
                        $lockedInvoice = Invoice::query()
                            ->lockForUpdate()
                            ->find($invoice->id);

                        if (
                            ! $lockedInvoice instanceof Invoice
                            || $lockedInvoice->status !== Invoice::STATUS_UNPAID
                        ) {
                            // Someone paid it (or it was already cleaned up) between the
                            // chunk query and this transaction — leave it alone.
                            return ['invoice_deleted' => false, 'pending_registration_deleted' => false];
                        }

                        $pendingRegistrationId = $lockedInvoice->pending_registration_id;

                        PaymentSubscriptionEvent::query()
                            ->where('invoice_id', $lockedInvoice->id)
                            ->delete();

                        // payment_activities and payment_subscriptions cascade-delete
                        // via their invoice_id foreign key.
                        $lockedInvoice->delete();

                        $pendingRegistrationDeleted = false;

                        if ($pendingRegistrationId !== null) {
                            $hasRemainingInvoices = Invoice::query()
                                ->where('pending_registration_id', $pendingRegistrationId)
                                ->exists();

                            if (! $hasRemainingInvoices) {
                                PendingRegistration::query()->whereKey($pendingRegistrationId)->delete();
                                $pendingRegistrationDeleted = true;
                            }
                        }

                        return [
                            'invoice_deleted' => true,
                            'pending_registration_deleted' => $pendingRegistrationDeleted,
                        ];
                    });

                    if ($result['invoice_deleted']) {
                        $invoicesDeleted++;
                    }

                    if ($result['pending_registration_deleted']) {
                        $pendingRegistrationsDeleted++;
                    }
                }
            });

        return [
            'processed' => $processed,
            'invoices_deleted' => $invoicesDeleted,
            'pending_registrations_deleted' => $pendingRegistrationsDeleted,
        ];
    }
}
