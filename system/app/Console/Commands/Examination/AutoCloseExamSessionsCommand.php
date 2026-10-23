<?php

namespace App\Console\Commands\Examination;

use App\Examination\Actions\ReconcileOverdueExamSessions;
use Illuminate\Console\Command;

class AutoCloseExamSessionsCommand extends Command
{
    protected $signature = 'sessions:auto-close {--grace= : Grace period in minutes after effective_end_at (default from config)}';

    protected $description = 'Automatically close or cancel overdue exam sessions past their grace period per D-040';

    public function handle(ReconcileOverdueExamSessions $reconcileOverdue): int
    {
        $graceOption = $this->option('grace');
        $graceMinutes = $graceOption !== null
            ? max(0, (int) $graceOption)
            : $reconcileOverdue->defaultGraceMinutes();

        $cutoff = now()->subMinutes($graceMinutes);
        $result = $reconcileOverdue->handle($graceMinutes, null, true);

        $this->info("Scanning overdue in-progress sessions ended before {$cutoff->toDateTimeString()} (found {$result['scanned']})...");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        $errorCount = count($result['errors']);
        $summary = "Auto-close sweep complete: {$result['closed']} session(s) closed (grading opened), {$result['cancelled']} session(s) cancelled (zero attendance).";
        if ($errorCount > 0) {
            $summary .= " {$errorCount} session(s) errored.";
        }
        $this->info($summary);

        return self::SUCCESS;
    }
}
