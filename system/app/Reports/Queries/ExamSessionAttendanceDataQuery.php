<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;

final class ExamSessionAttendanceDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id']) ? (int) $input['academic_cycle_id'] : null;
        $sessionId = isset($input['session_id']) && is_numeric($input['session_id']) ? (int) $input['session_id'] : null;

        return $this->forXiamSafe($institutionId, $cycleId, $sessionId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   session_id: int,
     *   session_code: string,
     *   session_title: string,
     *   room_name: string,
     *   starts_at: ?string,
     *   capacity: int,
     *   registered_count: int,
     *   present_count: int,
     *   absent_count: int,
     *   attendance_rate_percent: float,
     *   status: string,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null, ?int $sessionId = null): array
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->when($cycleId !== null, fn ($query) => $query->whereKey($cycleId))
            ->latest('id')
            ->first();

        $session = $cycle === null ? null : ExamSession::query()
            ->forPracticeSet(null)
            ->with('room')
            ->where('institution_id', $institutionId)
            ->where('academic_cycle_id', $cycle->id)
            ->when($sessionId !== null, fn ($query) => $query->whereKey($sessionId))
            ->when($sessionId === null, fn ($query) => $query->orderByDesc('starts_at')->orderByDesc('id'))
            ->first();

        $assignments = $session === null
            ? collect()
            : ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->with('attendanceRecord')
                ->get();
        $registeredCount = $assignments->count();
        $presentCount = $assignments->filter(
            fn (ExamAssignment $assignment): bool => $assignment->attendanceRecord?->outcome === 'present'
        )->count();
        $absentCount = $assignments->filter(
            fn (ExamAssignment $assignment): bool => in_array($assignment->attendanceRecord?->outcome, ['absent', 'no_show'], true)
                || ($session?->status === 'completed' && $assignment->attendanceRecord === null)
        )->count();

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $cycle?->id,
            'session_id' => $session?->id ?? 0,
            'session_code' => (string) ($session?->code ?? ''),
            'session_title' => (string) ($session?->label ?? $session?->code ?? ''),
            'room_name' => (string) ($session?->room?->name ?? ''),
            'starts_at' => $session?->starts_at?->toISOString(),
            'capacity' => (int) ($session?->capacity ?? $session?->room?->capacity ?? 0),
            'registered_count' => $registeredCount,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'attendance_rate_percent' => $registeredCount > 0 ? round(($presentCount / $registeredCount) * 100, 1) : 0.0,
            'status' => (string) ($session?->status ?? 'unassigned'),
            'as_of' => now()->toISOString(),
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

        $sessionId = isset($filters['session_id']) && is_numeric($filters['session_id'])
            ? (int) $filters['session_id']
            : null;

        $sessionQuery = ExamSession::query()->forPracticeSet(null)
            ->with(['room', 'assignments.admissionAttempt.identity', 'assignments.attendanceRecord']);

        if ($sessionId !== null) {
            $session = $sessionQuery->find($sessionId);
        } else {
            $session = $sessionQuery
                ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
                ->orderByDesc('starts_at')
                ->first();
        }

        $sessionCode = $session?->code ?? 'SES-UNASSIGNED';
        $sessionTitle = $session?->label ?? $session?->code ?? 'Exam Session';
        $roomName = $session?->room?->name ?? 'Unassigned Room';
        $examDate = $session?->starts_at ? $session->starts_at->format('F j, Y') : now()->format('F j, Y');
        $timeSlot = $session?->starts_at
            ? $session->starts_at->format('g:i A').' – '.($session->effective_end_at ? $session->effective_end_at->format('g:i A') : '')
            : 'Scheduled Window';

        $assignments = $session?->assignments ?? collect();
        $examineeRows = [];
        $presentCount = 0;
        $absentCount = 0;

        foreach ($assignments->values() as $index => $assignment) {
            $attempt = $assignment->admissionAttempt;
            $identity = $attempt?->identity;
            $applicantName = trim(implode(' ', array_filter([
                $identity?->first_name,
                $identity?->middle_name,
                $identity?->last_name,
                $identity?->suffix,
            ], static fn (?string $part): bool => $part !== null && $part !== '')));

            $attendance = $assignment->attendanceRecord;
            $outcome = $attendance?->outcome ?? ($session?->status === 'completed' ? 'absent' : 'pending');
            $statusLabel = match ($outcome) {
                'present' => 'Present',
                'absent', 'no_show' => 'Absent / No-Show',
                default => 'Scheduled',
            };

            if ($outcome === 'present') {
                $presentCount++;
            } elseif (in_array($outcome, ['absent', 'no_show'], true)) {
                $absentCount++;
            }

            $examineeRows[] = [
                'row_no' => $index + 1,
                'seat_number' => 'Seat '.($index + 1),
                'reference_code' => $this->escapeText((string) ($attempt?->reference_code ?? 'N/A')),
                'applicant_name' => $this->escapeText($applicantName !== '' ? $applicantName : 'Unknown'),
                'sex' => ucfirst((string) ($identity?->sex ?? '—')),
                'program_code' => $this->escapeText((string) ($attempt?->preferred_program_code ?: $attempt?->program_preference_1_code ?: '—')),
                'attendance_status' => $statusLabel,
                'check_in_time' => $attendance?->recorded_at ? $attendance->recorded_at->format('g:i A') : '—',
                'signature' => '___________________',
            ];
        }

        $totalAssigned = count($examineeRows);
        $turnoutRate = $totalAssigned > 0
            ? number_format(($presentCount / $totalAssigned) * 100, 1).'%'
            : '0.0%';

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'session_code' => $this->escapeText($sessionCode),
            'session_title' => $this->escapeText($sessionTitle),
            'room_name' => $this->escapeText($roomName),
            'exam_date' => $examDate,
            'time_slot' => $timeSlot,
            'total_assigned' => $totalAssigned,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'turnout_rate' => $turnoutRate,
            'examinees' => $examineeRows,
            'prepared_by' => 'Test Administrator',
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
