<?php

namespace App\Console\Commands\Examination;

use App\Examination\Support\ExamSnapshotInterpretation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ReportExamSeatingModesCommand extends Command
{
    protected $signature = 'exams:seating-mode-report';

    protected $description = 'Report evidence-based historical seating interpretations without changing data';

    public function handle(ExamSnapshotInterpretation $interpretation): int
    {
        $counts = ['total' => 0, 'simple_capacity' => 0, 'assigned_seating' => 0, 'unresolved' => 0];
        foreach (ExamSessionMaterialSnapshot::query()->lazyById(100) as $snapshot) {
            $counts['total']++;
            $classification = in_array($snapshot->seating_mode, ExamSession::SEATING_MODES, true)
                ? ['mode' => $snapshot->seating_mode, 'reason' => 'Already resolved.']
                : $interpretation->classify($snapshot);
            $counts[$classification['mode'] ?? 'unresolved']++;
            if ($classification['mode'] === null) {
                $this->warn("Snapshot {$snapshot->id} revision {$snapshot->revision}: {$classification['reason']}");
            }
        }
        $this->table(['Snapshots total', 'Simple', 'Assigned', 'Unresolved'], [array_values($counts)]);
        $sessionCounts = ['total' => 0, 'simple_capacity' => 0, 'assigned_seating' => 0, 'unresolved' => 0];
        foreach (ExamSession::query()->where(function (Builder $query): void {
            $query->whereNull('seating_mode')->orWhereNotIn('seating_mode', ExamSession::SEATING_MODES);
        })->lazyById(100) as $session) {
            $sessionCounts['total']++;
            $snapshot = ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->where('is_current', true)->first();
            $classification = $snapshot === null ? ['mode' => null, 'reason' => 'No retained current snapshot provenance.']
                : (in_array($snapshot->seating_mode, ExamSession::SEATING_MODES, true)
                    ? ['mode' => $snapshot->seating_mode, 'reason' => 'Current frozen snapshot.']
                    : $interpretation->classify($snapshot));
            $sessionCounts[$classification['mode'] ?? 'unresolved']++;
            if ($classification['mode'] === null) {
                $this->warn("Session {$session->id}: {$classification['reason']}");
            }
        }
        $this->table(['Unknown sessions total', 'Simple', 'Assigned', 'Unresolved'], [array_values($sessionCounts)]);

        return self::SUCCESS;
    }
}
