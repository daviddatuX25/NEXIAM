<?php

use App\Jobs\Ai\ReconcileXiamUsageJob;
use App\Jobs\Assessment\GcOmrCanonicalTempAssetsJob;
use App\Models\Auth\StaffSessionLease;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

if (config('application-opening-alerts.scheduler_only', false)) {
    Schedule::command('admission:reconcile-opening-alerts')->everyMinute()->withoutOverlapping()->onOneServer();
    Schedule::command('admission:purge-opening-alerts')->daily()->withoutOverlapping()->onOneServer();
    Schedule::command('notifications:recover-deliveries --opening-alerts-only --limit=100')->everyMinute()->withoutOverlapping()->onOneServer();

    return;
}

Schedule::job(new ReconcileXiamUsageJob)->hourly();
Schedule::command('omr:recover-stale-runs')->everyMinute()->withoutOverlapping();
Schedule::command('omr:prune-camera-frames --limit=100')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('result-sheet:prune')->daily()->withoutOverlapping();
Schedule::job(new GcOmrCanonicalTempAssetsJob)->daily()->withoutOverlapping();
Schedule::command('notifications:send-exam-reminders --window=72h')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('notifications:send-exam-reminders --window=24h')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('notifications:send-exam-reminders --window=2h')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('notifications:send-exam-reminders --window=1h')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('notifications:recover-deliveries --limit=100')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('admission:reconcile-opening-alerts')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('admission:purge-opening-alerts')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('sessions:auto-close --grace=15')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(function (): void {
    $cutoff = Carbon::now()->subHours(48);

    StaffSessionLease::query()
        ->where(function ($query) use ($cutoff): void {
            $query->where('revoked_at', '<', $cutoff)
                ->orWhere(function ($query) use ($cutoff): void {
                    $query->whereNull('revoked_at')
                        ->where('absolute_expires_at', '<', $cutoff);
                });
        })
        ->delete();
})->name('staff-session-leases:prune')->daily()->withoutOverlapping();
