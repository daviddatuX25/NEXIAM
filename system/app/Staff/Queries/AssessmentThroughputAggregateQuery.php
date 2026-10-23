<?php

namespace App\Staff\Queries;

use App\Examination\Support\ExamSnapshotInterpretation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Release\ConsultationDecision;
use Illuminate\Support\Facades\DB;

final class AssessmentThroughputAggregateQuery
{
    public function for(?int $cycleId, ?int $institutionId): array
    {
        $sessions = ExamSession::query()->forWorkspace()
            ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($query) => $query->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId)));
        $counts = (clone $sessions)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
        $attendance = ExamAttendanceRecord::query()->whereHas('examAssignment.examSession', function ($query) use ($cycleId, $institutionId): void {
            $query->forWorkspace()->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
                ->when($institutionId !== null, fn ($q) => $q->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId)));
        })->select('outcome', DB::raw('COUNT(*) as total'))->groupBy('outcome')->pluck('total', 'outcome')->all();
        $present = (int) ($attendance['present'] ?? 0);
        $absent = (int) ($attendance['absent'] ?? 0);
        $total = $present + $absent;
        $upcoming = (clone $sessions)->whereIn('status', ['scheduled', 'published', 'open'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(8)->get();
        $sessionList = (clone $sessions)->with([
            'room',
            'currentMaterialSnapshot' => fn ($query) => $query->withCount('seats'),
            'seatingArrangement',
        ])->get();
        $examineeCapacity = 0;
        foreach ($sessionList as $session) {
            $snapshot = $session->currentMaterialSnapshot;
            if ($snapshot !== null) {
                if ($snapshot->capacity_limit !== null) {
                    $capacityLimit = max(0, (int) $snapshot->capacity_limit);
                    $seatsCount = (int) ($snapshot->seats_count ?? 0);
                    $examineeCapacity += $seatsCount === $capacityLimit ? $capacityLimit : 0;

                    continue;
                }

                try {
                    $examineeCapacity += app(ExamSnapshotInterpretation::class)->manifest($snapshot)['capacity_limit'];

                    continue;
                } catch (\RuntimeException) {
                    continue;
                }
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                continue;
            }

            $room = $session->room;
            if ($room === null || ! $room->is_active) {
                continue;
            }

            $roomCapacity = max(0, (int) $room->capacity);
            if ($session->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING && $session->seating_arrangement_id) {
                $deskCount = (int) ($session->seatingArrangement?->desk_count ?? 0);
                if ($deskCount > 0) {
                    $examineeCapacity += min($roomCapacity, $deskCount);

                    continue;
                }
            }

            $examineeCapacity += $roomCapacity;
        }
        $decisions = ConsultationDecision::query()
            ->whereHas('admissionAttempt', function ($aq) use ($cycleId, $institutionId): void {
                $aq->forWorkspace()
                    ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
                    ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId));
            })
            ->count();

        return [
            'kpis' => [
                'scheduled_sessions' => array_sum($counts),
                'completed_sessions' => (int) ($counts['completed'] ?? 0),
                'examinee_capacity' => $examineeCapacity,
                'attendance_rate' => $total > 0 ? round($present / $total * 100, 1) : 0.0,
                'finalized_grading_sessions' => (int) ($counts['finalized'] ?? 0),
                'recorded_consultation_decisions' => $decisions,
                'candidates_tested' => $present,
                'pending_scoring' => (int) ($counts['in_progress'] ?? 0) + (int) ($counts['completed'] ?? 0),
                'turnaround_days' => 0.0,
                'attendance_percentage' => $total > 0 ? round($present / $total * 100, 1) : 0.0,
            ],
            'upcoming_sessions' => $upcoming->map(fn ($session) => ['id' => $session->id, 'name' => $session->name ?? $session->code ?? 'Exam session', 'starts_at' => $session->starts_at?->toIso8601String()])->all(),
            'attendance_breakdown' => [['label' => 'Present', 'value' => $present, 'color' => '#10b981'], ['label' => 'Absent', 'value' => $absent, 'color' => '#ef4444']],
            'attendance_distribution' => [['label' => 'Present', 'value' => $present, 'color' => '#10b981'], ['label' => 'Absent', 'value' => $absent, 'color' => '#ef4444']],
        ];
    }
}
