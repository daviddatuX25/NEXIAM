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
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        ExamAssignment $assignment,
        User $actor,
        string $outcome,
        string $idempotencyKey,
    ): ExamAttendanceRecord {
        $existing = ExamAttendanceRecord::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($assignment, $actor, $outcome, $idempotencyKey): ExamAttendanceRecord {
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $session = ExamSession::query()->findOrFail($assignment->exam_session_id);

            if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                throw new RuntimeException('Attendance is not allowed for this session state.');
            }

            if (now()->lt($session->starts_at) || now()->gt($session->effective_end_at)) {
                throw new RuntimeException('Attendance is outside the approved time window.');
            }

            if ($assignment->attendanceRecord()->exists()) {
                throw new RuntimeException('Attendance already recorded for assignment.');
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
