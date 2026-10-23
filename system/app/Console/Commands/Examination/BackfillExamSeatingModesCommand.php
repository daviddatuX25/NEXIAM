<?php

namespace App\Console\Commands\Examination;

use App\Examination\Support\ExamSnapshotInterpretation;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackfillExamSeatingModesCommand extends Command
{
    protected $signature = 'exams:backfill-seating-mode {--dry-run : Report eligible enrichment without writing}';

    protected $description = 'Enrich only historically proven, previously unknown frozen seating metadata';

    public function handle(ExamSnapshotInterpretation $interpretation, RecordAuditEvent $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $unresolved = 0;
        foreach (ExamSessionMaterialSnapshot::query()->lazyById(100) as $candidate) {
            try {
                $changed = DB::transaction(function () use ($candidate, $interpretation, $audit, $dryRun): bool {
                    $session = ExamSession::query()->lockForUpdate()->findOrFail($candidate->exam_session_id);
                    $snapshot = ExamSessionMaterialSnapshot::query()->lockForUpdate()->findOrFail($candidate->id);
                    if ($snapshot->seating_mode !== null) {
                        if ($snapshot->is_current && $session->seating_mode === null && in_array($snapshot->seating_mode, ExamSession::SEATING_MODES, true) && ! $dryRun) {
                            $session->update(['seating_mode' => $snapshot->seating_mode, 'version' => $session->version + 1]);
                        }
                        if (! in_array($snapshot->seating_mode, ExamSession::SEATING_MODES, true)) {
                            throw new RuntimeException('Existing unknown non-null mode cannot be overwritten.');
                        }

                        return false;
                    }
                    $classification = $interpretation->classify($snapshot);
                    if ($classification['mode'] === null) {
                        throw new RuntimeException($classification['reason']);
                    }
                    if ($snapshot->capacity_limit !== null || $snapshot->required_set_codes !== null) {
                        throw new RuntimeException('Existing capacity or Set metadata must not be overwritten.');
                    }
                    if (! $dryRun) {
                        $interpretation->enrich($snapshot, $classification['mode']);
                        if ($snapshot->is_current && $session->seating_mode === null) {
                            $session->update(['seating_mode' => $classification['mode'], 'version' => $session->version + 1]);
                        }
                        $audit->handle('exam.snapshot_interpretation_resolved', [
                            'exam_session_id' => $session->id,
                            'snapshot_id' => $snapshot->id,
                            'snapshot_revision' => $snapshot->revision,
                            'seating_mode' => $classification['mode'],
                            'reason' => $classification['reason'],
                            'provenance_notes' => 'Retained frozen manifest and arrangement publication metadata; exams:backfill-seating-mode.',
                        ]);
                    }
                    $this->info(($dryRun ? 'Would enrich' : 'Enriched')." snapshot {$snapshot->id} revision {$snapshot->revision}: {$classification['mode']}");

                    return true;
                });
                $updated += (int) $changed;
            } catch (RuntimeException $exception) {
                $unresolved++;
                $this->warn("Skipped snapshot {$candidate->id} revision {$candidate->revision}: {$exception->getMessage()}");
            }
        }
        $this->table([$dryRun ? 'Eligible snapshots' : 'Enriched snapshots', 'Unresolved'], [[$updated, $unresolved]]);

        return self::SUCCESS;
    }
}
