<?php

namespace App\Jobs\Ai;

use App\Ai\Policy\UsageBudgetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileNexiUsageJob implements ShouldQueue
{
    use Queueable;

    public function handle(UsageBudgetService $budgets): void
    {
        $budgets->markUnknownStaleReservations();
    }
}
