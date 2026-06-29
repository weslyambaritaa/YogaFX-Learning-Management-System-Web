<?php

namespace App\Console\Commands;

use App\Services\Payments\OverdueInstallmentService;
use Illuminate\Console\Command;

class SyncOverdueInstallmentsCommand extends Command
{
    protected $signature = 'installments:sync-overdue-status';

    protected $description = 'Deactivate overdue installment accounts after grace deadline and notify admins once.';

    public function handle(OverdueInstallmentService $service): int
    {
        $result = $service->sync();

        $this->info(sprintf(
            'Processed: %d | Deactivated: %d | Notifications sent: %d | Skipped: %d',
            $result['processed'],
            $result['deactivated'],
            $result['notifications_sent'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
