<?php

namespace App\Console\Commands\Admission;

use App\Admission\Actions\ReconcileApplicationOpeningAlerts;
use Illuminate\Console\Command;

final class ReconcileApplicationOpeningAlertsCommand extends Command
{
    protected $signature = 'admission:reconcile-opening-alerts';

    protected $description = 'Detect effective application openings and create durable one-time alert deliveries.';

    public function handle(ReconcileApplicationOpeningAlerts $reconcile): int
    {
        $processed = $reconcile->handle();
        $this->info("Processed {$processed} opening-alert subscription(s).");

        return self::SUCCESS;
    }
}
