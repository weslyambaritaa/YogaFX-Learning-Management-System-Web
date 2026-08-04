<?php

namespace App\Console\Commands;

use App\Services\Payments\ExpiredUnpaidCheckoutPurgeService;
use Illuminate\Console\Command;

class PurgeExpiredUnpaidCheckoutsCommand extends Command
{
    protected $signature = 'checkouts:purge-expired-unpaid';

    protected $description = 'Delete initial checkout invoices left unpaid for more than 72 hours, along with their pending registration once no invoice remains for it.';

    public function handle(ExpiredUnpaidCheckoutPurgeService $service): int
    {
        $result = $service->purge();

        $this->info(sprintf(
            'Processed: %d | Invoices deleted: %d | Pending registrations deleted: %d',
            $result['processed'],
            $result['invoices_deleted'],
            $result['pending_registrations_deleted'],
        ));

        return self::SUCCESS;
    }
}
