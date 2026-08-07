<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExamSession;
use App\Notifications\ExamSessionReminder;
use Illuminate\Console\Command;

class SendExamReminders extends Command
{
    protected $signature = 'notifications:exam-reminder {--days=1}';

    protected $description = 'Send exam reminders to applicants with sessions in N days';

    public function handle(): int
    {
        $daysList = collect(explode(',', env('EXAM_REMINDER_DAYS', '1,3,7')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->values();

        if ($this->option('days') !== null) {
            $daysList = collect([(int) $this->option('days')]);
        }

        $totalNotified = 0;
        foreach ($daysList as $days) {
            $targetDate = now()->addDays($days)->startOfDay();
            $endDate = $targetDate->copy()->endOfDay();

            $sessions = ExamSession::whereBetween('date', [$targetDate, $endDate])
                ->where('status', ExamSession::STATUS_PUBLISHED)
                ->with('applicants')
                ->get();

            foreach ($sessions as $session) {
                foreach ($session->applicants as $applicant) {
                    $applicant->notify(new ExamSessionReminder($session, $days));
                    $totalNotified++;
                }
            }
        }

        $this->info("Sent {$totalNotified} reminders for sessions in {$daysList->implode(',')} day(s).");

        return Command::SUCCESS;
    }
}
