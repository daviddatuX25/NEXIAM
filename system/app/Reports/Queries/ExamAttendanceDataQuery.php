<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;

final class ExamAttendanceDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;

        return $this->forXiamSafe($institutionId, $cycleId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   total_sessions: int,
     *   total_registered: int,
     *   total_present: int,
     *   total_absent: int,
     *   overall_attendance_rate_percent: float,
     *   sessions: list<array{
     *     session_code: string,
     *     room_name: string,
     *     starts_at: ?string,
     *     capacity: int,
     *     registered_count: int,
     *     present_count: int,
     *     absent_count: int,
     *     attendance_rate_percent: float,
     *     status: string
     *   }>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycle = $cycleId === null
            ? AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->first()
            : AcademicCycle::query()->where('institution_id', $institutionId)->where('id', $cycleId)->first();
        $cycleId = $cycle?->id;

        $sessions = $cycleId === null
            ? collect()
            : ExamSession::query()
                ->forPracticeSet(null)
                ->where('academic_cycle_id', $cycleId)
                ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
                ->with(['room', 'assignments.attendanceRecord'])
                ->orderBy('starts_at')
                ->get();

        $sessionRows = [];
        $totalRegistered = 0;
        $totalPresent = 0;
        $totalAbsent = 0;

        foreach ($sessions as $session) {
            $assignments = $session->assignments;
            $registered = $assignments->count();
            $present = $assignments
                ->filter(fn (ExamAssignment $assignment) => $assignment->attendanceRecord?->outcome === 'present')
                ->count();
            $absent = $assignments
                ->filter(fn (ExamAssignment $assignment) => in_array($assignment->attendanceRecord?->outcome, ['absent', 'no_show', null], true) && $session->status === 'completed')
                ->count();

            $totalRegistered += $registered;
            $totalPresent += $present;
            $totalAbsent += $absent;

            $sessionRows[] = [
                'session_code' => (string) ($session->code ?? "SES-{$session->id}"),
                'room_name' => (string) ($session->room?->name ?? 'Unassigned Room'),
                'starts_at' => $session->starts_at?->toIso8601String(),
                'capacity' => (int) ($session->capacity ?? $session->room?->capacity ?? 0),
                'registered_count' => $registered,
                'present_count' => $present,
                'absent_count' => $absent,
                'attendance_rate_percent' => $registered > 0 ? round(($present / $registered) * 100, 1) : 0.0,
                'status' => (string) $session->status,
            ];
        }

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $cycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_sessions' => count($sessionRows),
            'total_registered' => $totalRegistered,
            'total_present' => $totalPresent,
            'total_absent' => $totalAbsent,
            'overall_attendance_rate_percent' => $totalRegistered > 0 ? round(($totalPresent / $totalRegistered) * 100, 1) : 0.0,
            'sessions' => $sessionRows,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $sessions = $cycleId === null ? collect() : ExamSession::query()->forPracticeSet(null)
            ->with(['room', 'assignments.attendanceRecord'])
            ->where('academic_cycle_id', $cycleId)
            ->orderBy('starts_at')
            ->get();

        $sessionRows = [];
        $totalRegistered = 0;
        $totalPresent = 0;
        $totalAbsent = 0;

        foreach ($sessions as $index => $session) {
            $assignments = $session->assignments;
            $registered = $assignments->count();
            $present = $assignments->filter(fn (ExamAssignment $a) => $a->attendanceRecord?->outcome === 'present')->count();
            $absent = $assignments->filter(fn (ExamAssignment $a) => in_array($a->attendanceRecord?->outcome, ['absent', 'no_show', null], true) && $session->status === 'completed')->count();
            $rate = $registered > 0 ? number_format(($present / $registered) * 100, 1).'%' : '0.0%';

            $totalRegistered += $registered;
            $totalPresent += $present;
            $totalAbsent += $absent;

            $sessionRows[] = [
                'row_no' => $index + 1,
                'session_code' => $this->escapeText($session->code ?? "SES-{$session->id}"),
                'session_title' => $this->escapeText($session->title ?? 'Exam Session'),
                'room_name' => $this->escapeText($session->room?->name ?? 'Unassigned Room'),
                'exam_date' => $session->starts_at ? $session->starts_at->format('Y-m-d') : '',
                'time_slot' => $session->starts_at ? $session->starts_at->format('g:i A').' – '.$session->ends_at?->format('g:i A') : '',
                'capacity' => $session->capacity ?? ($session->room?->capacity ?? 0),
                'registered_count' => $registered,
                'present_count' => $present,
                'absent_count' => $absent,
                'attendance_rate' => $rate,
                'status' => ucfirst((string) $session->status),
            ];
        }

        $overallRate = $totalRegistered > 0
            ? number_format(($totalPresent / $totalRegistered) * 100, 1).'%'
            : '0.0%';

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_sessions' => count($sessionRows),
            'total_registered' => $totalRegistered,
            'total_present' => $totalPresent,
            'total_absent' => $totalAbsent,
            'overall_attendance_rate' => $overallRate,
            'sessions' => $sessionRows,
            'prepared_by' => 'Guidance Staff',
            'verified_by' => 'Chief Test Administrator',
            'approved_by' => 'Head of Guidance & Testing',
        ];
    }

    private function escapeText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
