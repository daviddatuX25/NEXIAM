<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        AcademicCycle $cycle,
        Room $room,
        string $code,
        string $startsAt,
        string $effectiveEndAt,
        User $actor,
    ): ExamSession {
        return DB::transaction(function () use ($cycle, $room, $code, $startsAt, $effectiveEndAt, $actor): ExamSession {
            $overlap = ExamSession::query()
                ->where('room_id', $room->id)
                ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                ->where('starts_at', '<', $effectiveEndAt)
                ->where('effective_end_at', '>', $startsAt)
                ->exists();

            if ($overlap) {
                throw new RuntimeException('Room scheduling conflict.');
            }

            $session = ExamSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'status' => ExamSession::STATUS_DRAFT,
                'starts_at' => $startsAt,
                'effective_end_at' => $effectiveEndAt,
            ]);

            $this->recordAuditEvent->handle('exam.session_created', [
                'exam_session_id' => $session->id,
                'code' => $code,
            ], (string) $actor->id);

            return $session;
        });
    }
}
