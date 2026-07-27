<?php

namespace App\Console\Commands;

use App\Services\ExamSessionAutoCloser;
use Illuminate\Console\Command;

class AutoCloseExamSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:auto-close';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close exam sessions that have passed their scheduled end time';

    /**
     * Execute the console command.
     */
    public function handle(ExamSessionAutoCloser $closer): void
    {
        $closer->run();
        $this->info('Auto-close sweep complete.');
    }
}
