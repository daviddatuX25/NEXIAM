<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Application;
use Illuminate\Console\Command;

class ExpireSeasonApplications extends Command
{
    protected $signature = 'seasons:expire-applications';

    protected $description = 'Mark pending applications as dismissed when their season application window has ended.';

    public function handle(): int
    {
        $today = now()->toDateString();

        $academicYearIds = AcademicYear::query()
            ->whereNotNull('application_end_date')
            ->whereDate('application_end_date', '<', $today)
            ->pluck('id');

        if ($academicYearIds->isEmpty()) {
            $this->info('No seasons with a closed application window found.');

            return self::SUCCESS;
        }

        $affected = Application::query()
            ->whereIn('academic_year_id', $academicYearIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'dismissed',
                'rejection_reason' => 'Application window closed',
            ]);

        $this->info("Dismissed {$affected} pending applications.");

        return self::SUCCESS;
    }
}
