<?php

use App\Jobs\Ai\ReconcileNexiUsageJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ReconcileNexiUsageJob)->hourly();
Schedule::command('omr:recover-stale-runs')->everyMinute()->withoutOverlapping();
Schedule::command('result-sheet:prune')->daily()->withoutOverlapping();
