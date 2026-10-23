<?php

namespace App\Console\Commands\Admission;

use App\Admission\Actions\PurgeApplicationOpeningAlertData;
use Illuminate\Console\Command;

final class PurgeApplicationOpeningAlertsCommand extends Command
{
    protected $signature = 'admission:purge-opening-alerts';

    protected $description = 'Apply approved retention and address-erasure rules to opening-alert records.';

    public function handle(PurgeApplicationOpeningAlertData $purge): int
    {
        $changed = $purge->handle();
        $this->info("Applied opening-alert retention to {$changed} subscription(s).");

        return self::SUCCESS;
    }
}
