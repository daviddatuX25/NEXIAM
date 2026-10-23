<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordExamAttendance
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ExamAssignment $assignment,
        User $actor,
        string $outcome,
        string $idempotencyKey,
    ): ExamAttendanceRecord {
        if (! in_array($outcome, [
            ExamAttendanceRecord::OUTCOME_PRESENT,
            ExamAttendanceRecord::OUTCOME_ABSENT,
            ExamAttendanceRecord::OUTCOME_VOID,
        ], true)) {
            throw new RuntimeException('Invalid attendance outcome.');
        }

        $existing = ExamAttendanceRecord::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($assignment, $actor, $outcome, $idempotencyKey): ExamAttendanceRecord {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($assignment->exam_session_id);
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $assignment->exam_session_id !== (int) $session->id || ! $assignment->isActive()) {
                throw new RuntimeException('Attendance requires an active assignment in this session.');
            }

            if (! $session->attendanceIsOpen()) {
                throw new RuntimeException('Attendance requires a started session that has not closed.');
            }

            $record = $assignment->attendanceRecord()->lockForUpdate()->first();
            if ($record !== null) {
                $record->update([
                    'outcome' => $outcome,
                    'recorded_by_user_id' => $actor->id,
                    'recorded_at' => now(),
                ]);

                $this->recordAuditEvent->handle('exam.attendance_updated', [
                    'record_id' => $record->id,
                    'assignment_id' => $assignment->id,
                    'outcome' => $outcome,
                ], (string) $actor->id);

                return $record;
            }
            $record = ExamAttendanceRecord::query()->create([
                'exam_assignment_id' => $assignment->id,
                'recorded_by_user_id' => $actor->id,
                'outcome' => $outcome,
                'idempotency_key' => $idempotencyKey,
                'recorded_at' => now(),
            ]);

            $this->recordAuditEvent->handle('exam.attendance_recorded', [
                'record_id' => $record->id,
                'assignment_id' => $assignment->id,
                'outcome' => $outcome,
            ], (string) $actor->id);

            return $record;
        });
    }
}
