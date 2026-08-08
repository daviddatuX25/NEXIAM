<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateDraftExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        ExamSession $session,
        AcademicCycle $cycle,
        Room $room,
        string $code,
        string $startsAt,
        string $effectiveEndAt,
        User $actor,
        int $expectedVersion,
    ): ExamSession {
        return DB::transaction(function () use ($session, $cycle, $room, $code, $startsAt, $effectiveEndAt, $actor, $expectedVersion): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft sessions may be edited.');
            }

            if ($session->isOpenForReservation()) {
                throw new RuntimeException('Close reservation before editing schedule fields.');
            }

            $overlap = ExamSession::query()
                ->where('room_id', $room->id)
                ->where('id', '!=', $session->id)
                ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                ->where('starts_at', '<', $effectiveEndAt)
                ->where('effective_end_at', '>', $startsAt)
                ->exists();

            if ($overlap) {
                throw new RuntimeException('Room scheduling conflict.');
            }

            $session->update([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'starts_at' => $startsAt,
                'effective_end_at' => $effectiveEndAt,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_updated', [
                'exam_session_id' => $session->id,
                'code' => $code,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }
}
