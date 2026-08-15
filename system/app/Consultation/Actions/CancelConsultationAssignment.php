<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelConsultationAssignment
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ConsultationSession $session,
        ConsultationAssignment $assignment,
        User $actor,
        ?string $reason = null,
    ): ConsultationAssignment {
        return DB::transaction(function () use ($session, $assignment, $actor, $reason): ConsultationAssignment {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
            $assignment = ConsultationAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $assignment->consultation_session_id !== (int) $session->id) {
                throw new RuntimeException('Assignment does not belong to this session.');
            }

            if (! $assignment->isActive()) {
                throw new RuntimeException('Assignment is not active.');
            }

            if (in_array($session->status, [ConsultationSession::STATUS_COMPLETED, ConsultationSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Cannot cancel assignments on a terminal session.');
            }

            $assignment->update([
                'status' => ConsultationAssignment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason ?: 'Cancelled by staff',
                'version' => $assignment->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.assignment_cancelled', [
                'assignment_id' => $assignment->id,
                'consultation_session_id' => $session->id,
                'attempt_id' => $assignment->admission_attempt_id,
                'reason' => $assignment->cancel_reason,
            ], (string) $actor->id);

            return $assignment->fresh();
        });
    }
}
