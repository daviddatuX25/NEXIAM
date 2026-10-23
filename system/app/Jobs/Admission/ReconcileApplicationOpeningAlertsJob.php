<?php

namespace App\Jobs\Admission;

use App\Admission\Actions\ReconcileApplicationOpeningAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileApplicationOpeningAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function handle(ReconcileApplicationOpeningAlerts $reconcile): void
    {
        $reconcile->handle();
    }
}
