<?php

namespace App\Console\Commands\Notifications;

use App\Models\Examination\ExamSession;
use App\Notifications\ExamReminderDispatcher;
use Illuminate\Console\Command;

class SendExamRemindersCommand extends Command
{
    protected $signature = 'notifications:send-exam-reminders {--window= : Reminder window (72h, 24h, 2h, 1h). Omit to scan all configured windows}';

    protected $description = 'Send scheduled exam reminders to applicants assigned to upcoming published sessions';

    public function handle(ExamReminderDispatcher $dispatcher): int
    {
        $option = $this->option('window');
        $windows = $option === null
            ? (array) config('examination.thresholds.reminder_windows', ['72h', '24h', '2h'])
            : [strtolower((string) $option)];
        if ($option !== null && ! isset(ExamReminderDispatcher::WINDOW_RANGES[$windows[0]])) {
            $this->error('Invalid --window. Supported windows: '.implode(', ', array_keys(ExamReminderDispatcher::WINDOW_RANGES)));

            return self::FAILURE;
        }
        $total = 0;
        foreach ($windows as $window) {
            if (! isset(ExamReminderDispatcher::WINDOW_RANGES[$window])) {
                $this->warn("Skipping unknown window [{$window}] from configuration.");

                continue;
            }
            [$start, $end] = ExamReminderDispatcher::range($window);
            $sessions = ExamSession::query()
                ->whereNull('practice_fixture_set_id')
                ->where('status', ExamSession::STATUS_PUBLISHED)
                ->whereBetween('starts_at', [$start, $end])
                ->get();
            $this->info("Window [{$window}]: scanning {$sessions->count()} upcoming operational session(s)...");
            foreach ($sessions as $session) {
                $total += $dispatcher->dispatch($session, $window)->count();
            }
        }
        $this->info("Completed. Created {$total} reminder delivery record(s) across ".count($windows).' window(s).');

        return self::SUCCESS;
    }
}
