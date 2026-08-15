<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Force-move an active seat from one session to another (remove then assign, one transaction).
 */
class ReassignApplicantConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ConsultationSession $fromSession,
        ConsultationAssignment $assignment,
        ConsultationSession $toSession,
        User $actor,
        ?string $reason = null,
    ): ConsultationAssignment {
        return DB::transaction(function () use ($fromSession, $assignment, $toSession, $actor, $reason): ConsultationAssignment {
            $fromSession = ConsultationSession::query()->lockForUpdate()->findOrFail($fromSession->id);
            $toSession = ConsultationSession::query()->lockForUpdate()->findOrFail($toSession->id);
            $assignment = ConsultationAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $assignment->consultation_session_id !== (int) $fromSession->id) {
                throw new RuntimeException('Assignment does not belong to this session.');
            }

            if (! $assignment->isActive()) {
                throw new RuntimeException('Assignment is not active.');
            }

            if ((int) $fromSession->id === (int) $toSession->id) {
                throw new RuntimeException('Choose a different consultation session for reassignment.');
            }

            if (in_array($fromSession->status, [ConsultationSession::STATUS_COMPLETED, ConsultationSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Cannot reassign from a terminal session.');
            }

            if (in_array($toSession->status, [ConsultationSession::STATUS_COMPLETED, ConsultationSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Cannot reassign to a terminal session.');
            }

            if ((int) $fromSession->academic_cycle_id !== (int) $toSession->academic_cycle_id) {
                throw new RuntimeException('Target session must be in the same academic year.');
            }

            $activeOnTarget = ConsultationAssignment::query()
                ->active()
                ->where('consultation_session_id', $toSession->id)
                ->count();

            $capacity = (int) $toSession->room()->value('capacity');
            if ($activeOnTarget >= $capacity) {
                throw new RuntimeException('Target consultation session is full.');
            }

            $endReason = trim((string) $reason);
            if ($endReason === '') {
                $endReason = 'Reassigned by staff';
            }

            $attemptId = (int) $assignment->admission_attempt_id;
            $fromId = (int) $fromSession->id;
            $toId = (int) $toSession->id;

            $assignment->update([
                'status' => ConsultationAssignment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $endReason,
                'version' => $assignment->version + 1,
            ]);

            $newAssignment = ConsultationAssignment::query()->create([
                'consultation_session_id' => $toId,
                'admission_attempt_id' => $attemptId,
                'status' => ConsultationAssignment::STATUS_ASSIGNED,
                'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
                'assigned_by_user_id' => $actor->id,
            ]);

            $this->recordAuditEvent->handle('consultation.assignment_reassigned', [
                'assignment_id' => $newAssignment->id,
                'prior_assignment_id' => $assignment->id,
                'from_session_id' => $fromId,
                'to_session_id' => $toId,
                'attempt_id' => $attemptId,
                'reason' => $endReason,
            ], (string) $actor->id);

            return $newAssignment;
        });
    }
}
