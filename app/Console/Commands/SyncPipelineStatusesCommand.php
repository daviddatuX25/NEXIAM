<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ConsultationSummary;
use App\Models\ExamSession;
use App\Services\ApplicationPipelineService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncPipelineStatusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pipeline:sync-statuses
                            {--dry-run : Print what would change without writing to DB}
                            {--chunk=100 : Number of applications to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill pipeline_status and pipeline_milestones for all applications. Safe to re-run.';

    /**
     * Execute the console command.
     */
    public function handle(ApplicationPipelineService $pipeline): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes will be written.');
        }

        $this->info('Syncing pipeline_status for all applications…');
        $count = 0;
        $changed = 0;

        Application::with([
            'applicant.examSessions',
            'applicant.applicantScores',
            'applicant.consultationSummary',
        ])->chunkById($chunkSize, function ($applications) use ($pipeline, $isDryRun, &$count, &$changed) {
            foreach ($applications as $app) {
                [$status, $milestones] = $pipeline->computeExpected($app);
                $count++;

                if ($isDryRun) {
                    $this->line("  [{$app->id}] {$app->pipeline_status} → {$status}");
                } else {
                    $oldStatus = $app->pipeline_status;
                    $pipeline->forceSet($app, $status, $milestones);
                    if ($app->pipeline_status !== $oldStatus) {
                        $changed++;
                    }
                }
            }
        });

        $this->info("Done. Processed {$count} applications".($isDryRun ? ' (dry run).' : ", updated {$changed}."));

        return Command::SUCCESS;
    }
}
