<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RelocateExamAssignmentSeat
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ExamAssignment $assignment,
        ExamSessionSeat $targetSeat,
        User $actor,
        string $reason,
    ): ExamAssignmentSeatAllocation {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Seat relocation requires a reason.');
        }

        return DB::transaction(function () use ($assignment, $targetSeat, $actor, $reason): ExamAssignmentSeatAllocation {
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $session = ExamSession::query()->lockForUpdate()->findOrFail($assignment->exam_session_id);
            if (! in_array($session->status, [ExamSession::STATUS_DRAFT, ExamSession::STATUS_PUBLISHED], true)
                || ($session->starts_at !== null && $session->starts_at->isPast())) {
                throw new RuntimeException('Scoring-seat relocation is allowed only before the session starts.');
            }

            $targetSeat = ExamSessionSeat::query()
                ->with('materialSnapshot')
                ->lockForUpdate()
                ->findOrFail($targetSeat->id);
            if ((int) $targetSeat->materialSnapshot->exam_session_id !== (int) $session->id
                || ! $targetSeat->materialSnapshot->is_current) {
                throw new RuntimeException('Select a seat from the current session manifest.');
            }

            $current = ExamAssignmentSeatAllocation::query()
                ->where('exam_assignment_id', $assignment->id)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->sole();
            if ((int) $current->exam_session_seat_id === (int) $targetSeat->id) {
                return $current;
            }

            $occupied = ExamAssignmentSeatAllocation::query()
                ->where('exam_session_seat_id', $targetSeat->id)
                ->whereNull('effective_to')
                ->where('exam_assignment_id', '<>', $assignment->id)
                ->lockForUpdate()
                ->exists();
            if ($occupied) {
                throw new RuntimeException('The selected session seat is already occupied.');
            }

            $effectiveSecond = now()->floorSecond();
            $fromSecond = $current->effective_from?->copy()->floorSecond();
            $closeTime = ($fromSecond !== null && $fromSecond->gte($effectiveSecond))
                ? $fromSecond->copy()->addSecond()
                : $effectiveSecond;

            $current->update(['effective_to' => $closeTime]);
            $replacement = ExamAssignmentSeatAllocation::query()->create([
                'exam_assignment_id' => $assignment->id,
                'exam_session_seat_id' => $targetSeat->id,
                'effective_from' => $closeTime,
                'reason' => $reason,
                'assigned_by_user_id' => $actor->id,
            ]);

            $this->recordAuditEvent->handle('exam.assignment_seat_relocated', [
                'exam_assignment_id' => $assignment->id,
                'from_allocation_id' => $current->id,
                'to_allocation_id' => $replacement->id,
                'from_session_seat_id' => $current->exam_session_seat_id,
                'to_session_seat_id' => $targetSeat->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $replacement->fresh('sessionSeat');
        });
    }
}
