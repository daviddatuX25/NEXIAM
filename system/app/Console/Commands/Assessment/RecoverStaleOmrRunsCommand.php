<?php

namespace App\Console\Commands\Assessment;

use App\Jobs\Assessment\AnalyzeOmrCaptureSheetJob;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrProcessingRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recovers OMR processing runs whose queue jobs were lost (worker crash,
 * server kill, manual queue clear). A lost job leaves a CLAIMED run stranded
 * until its lease expires and a PENDING run waiting forever; this sweep
 * converts them back to retryable failures and re-dispatches.
 */
class RecoverStaleOmrRunsCommand extends Command
{
    protected $signature = 'omr:recover-stale-runs {--limit=50}';

    protected $description = 'Re-dispatch OMR runs stranded by lost queue jobs.';

    public function handle(): int
    {
        $now = now();
        $tries = (int) config('omr.retry.tries', 3);
        $limit = max(1, (int) $this->option('limit'));

        $stale = OmrProcessingRun::query()
            ->where(function ($query) use ($now): void {
                $query->where(function ($claimed) use ($now): void {
                    $claimed->where('status', OmrProcessingRun::STATUS_CLAIMED)
                        ->where('claim_expires_at', '<', $now);
                })->orWhere(function ($pending) use ($now): void {
                    $pending->where('status', OmrProcessingRun::STATUS_PENDING)
                        ->where('created_at', '<', $now->copy()->subMinutes(5));
                })->orWhere(function ($retryable) use ($now): void {
                    $retryable->where('status', OmrProcessingRun::STATUS_RETRYABLE_FAILED)
                        ->where('next_retry_at', '<', $now);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $recovered = 0;

        foreach ($stale as $run) {
            $retryable = DB::transaction(function () use ($run, $now, $tries): bool {
                $locked = OmrProcessingRun::query()->lockForUpdate()->find($run->id);

                if ($locked === null || ! $this->isStillStale($locked, $now)) {
                    return false;
                }

                if ((int) $locked->attempts >= $tries) {
                    $locked->update([
                        'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
                        'diagnostic_code' => $locked->diagnostic_code ?: 'retry_exhausted',
                        'claim_token' => null,
                        'claim_expires_at' => null,
                        'next_retry_at' => null,
                    ]);

                    $sheet = OmrCaptureSheet::query()->lockForUpdate()->find($locked->sheet_id);

                    if ($sheet !== null
                        && (int) $sheet->current_processing_run_id === (int) $locked->id
                        && (int) $sheet->version === (int) $locked->expected_sheet_version) {
                        $sheet->update([
                            'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
                            'version' => (int) $sheet->version + 1,
                            'proposal_json' => [
                                'envelope' => (string) ($locked->request_version ?: 'nexiam.omr.detection.v3'),
                                'abstained' => true,
                                'reason' => 'provider_failed',
                                'safe_error' => 'retry_exhausted',
                            ],
                        ]);
                    }

                    return false;
                }

                // Lost-job recovery retries immediately: the provider never failed,
                // only the queue process did. Provider failures keep normal backoff.
                $locked->update([
                    'status' => OmrProcessingRun::STATUS_RETRYABLE_FAILED,
                    'diagnostic_code' => 'claim_lost',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'next_retry_at' => $now,
                ]);

                $sheet = OmrCaptureSheet::query()->lockForUpdate()->find($locked->sheet_id);

                if ($sheet !== null
                    && (int) $sheet->current_processing_run_id === (int) $locked->id
                    && (int) $sheet->version === (int) $locked->expected_sheet_version) {
                    $sheet->update(['status' => OmrCaptureSheet::STATUS_QUEUED]);
                }

                return true;
            });

            if ($retryable) {
                AnalyzeOmrCaptureSheetJob::dispatch($run->id);
                $recovered++;
            }
        }

        if ($recovered > 0) {
            $this->info("Recovered {$recovered} stranded OMR run(s).");
        }

        return self::SUCCESS;
    }

    private function isStillStale(OmrProcessingRun $run, Carbon $now): bool
    {
        return match ($run->status) {
            OmrProcessingRun::STATUS_CLAIMED => $run->claim_expires_at !== null && $run->claim_expires_at->lt($now),
            OmrProcessingRun::STATUS_PENDING => $run->created_at->lt($now->copy()->subMinutes(5)),
            OmrProcessingRun::STATUS_RETRYABLE_FAILED => $run->next_retry_at !== null && $run->next_retry_at->lt($now),
            default => false,
        };
    }
}
