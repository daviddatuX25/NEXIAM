<?php

namespace App\Console\Commands;

use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\Jobs\PrecomputeApplicantCmiJob;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PrecomputeAdmissionSupportSnapshotsCommand extends Command
{
    protected $signature = 'admission-support:precompute-cmi {--cycle= : The academic cycle ID or code} {--limit=100 : Maximum attempts to process}';

    protected $description = 'Queue admission support evaluations for result-ready applicants';

    public function handle(ConsultationScoreProjection $scoreProjection): int
    {
        $cycleOption = $this->option('cycle');
        $cycle = null;
        if ($cycleOption !== null && $cycleOption !== '') {
            $cycle = AcademicCycle::query()
                ->whereKey(is_numeric($cycleOption) ? (int) $cycleOption : 0)
                ->when(! is_numeric($cycleOption), fn (Builder $query) => $query->orWhere('code', (string) $cycleOption))
                ->first();

            if ($cycle === null) {
                $this->error('The requested academic cycle was not found.');

                return self::FAILURE;
            }
        }

        $limit = max(1, (int) $this->option('limit'));
        $queued = 0;
        $skipped = 0;
        $query = AdmissionAttempt::query()
            ->when($cycle !== null, fn (Builder $query) => $query->where('academic_cycle_id', $cycle->id))
            ->orderBy('id');

        foreach ($query->cursor() as $attempt) {
            if ($queued >= $limit) {
                break;
            }

            $projection = $scoreProjection->forAttempt($attempt);
            $hasScoreRecords = count(array_filter(
                $projection['series'] ?? [],
                static fn (mixed $score): bool => is_array($score)
                    && (($score['score'] ?? null) !== null || ($score['percentile'] ?? null) !== null),
            )) > 0;
            if (! (bool) $attempt->getAttribute('result_ready')
                && ! AttemptResultReadiness::isReady($attempt)
                && ! $hasScoreRecords) {
                $skipped++;

                continue;
            }

            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $attempt->academic_cycle_id);
            if ($activation === null) {
                $skipped++;

                continue;
            }

            $input = AdmissionInputPayload::fromAttempt(
                $attempt,
                ConsultationScoreProjection::admissionInputSeries($projection),
            );
            $latest = AdmissionSupportSnapshot::latestForAttempt((int) $attempt->id);
            if ($latest !== null
                && $latest->engine_slot === 'tabfm_pairwise'
                && ($latest->payload['status'] ?? null) === 'evaluated'
                && json_encode($latest->payload['assessment_context'] ?? null) === json_encode($projection['assessment_context'])
                && $latest->input_digest === $input->digest()) {
                $skipped++;

                continue;
            }

            PrecomputeApplicantCmiJob::dispatch((int) $attempt->id)->afterCommit();
            $queued++;
            $this->line("Queued attempt {$attempt->id}.");
        }

        $this->info("Precomputation queueing complete: {$queued} queued, {$skipped} skipped; limit {$limit}.");

        return self::SUCCESS;
    }
}
