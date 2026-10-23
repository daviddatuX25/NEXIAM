<?php

namespace App\Consultation\Actions;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChangeApplicantConsultationSlot
{
    public function __construct(
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly IndividualScoringReadiness $scoringReadiness,
    ) {}

    public function handle(
        ConsultationSession $targetSession,
        AdmissionAttempt $attempt,
        User $actor,
        int $expectedAssignmentId,
        int $expectedAssignmentVersion,
        string $idempotencyKey,
    ): ConsultationAssignment {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $attempt->academic_cycle_id,
            function () use ($targetSession, $attempt, $actor, $expectedAssignmentId, $expectedAssignmentVersion, $idempotencyKey): ConsultationAssignment {
                return DB::transaction(function () use ($targetSession, $attempt, $actor, $expectedAssignmentId, $expectedAssignmentVersion, $idempotencyKey): ConsultationAssignment {
                    $currentAssignment = ConsultationAssignment::query()
                        ->active()
                        ->where('admission_attempt_id', $attempt->id)
                        ->firstOrFail();

                    $sourceSessionId = (int) $currentAssignment->consultation_session_id;
                    $targetSessionId = (int) $targetSession->id;

                    if ($sourceSessionId === $targetSessionId) {
                        throw new RuntimeException('You are already assigned to this Release Day appointment.');
                    }

                    $firstId = min($sourceSessionId, $targetSessionId);
                    $secondId = max($sourceSessionId, $targetSessionId);
                    $first = ConsultationSession::query()->lockForUpdate()->findOrFail($firstId);
                    $second = ConsultationSession::query()->lockForUpdate()->findOrFail($secondId);
                    $lockedSource = $sourceSessionId === $firstId ? $first : $second;
                    $lockedTarget = $targetSessionId === $firstId ? $first : $second;

                    $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
                    if (! $this->scoringReadiness->forAttempt($lockedAttempt)['ready']) {
                        throw new RuntimeException('Release Day appointment changes require complete individual scoring.');
                    }
                    $lockedAssignment = ConsultationAssignment::query()->lockForUpdate()->findOrFail($currentAssignment->id);

                    if ((int) $lockedAssignment->id !== $expectedAssignmentId || (int) $lockedAssignment->version !== $expectedAssignmentVersion) {
                        throw new RuntimeException('Your current Release Day appointment has been modified. Please refresh.');
                    }

                    if ($lockedSource->status !== ConsultationSession::STATUS_DRAFT) {
                        throw new RuntimeException('Your current Release Day appointment has been published and cannot be changed.');
                    }

                    if ($lockedTarget->status !== ConsultationSession::STATUS_DRAFT || ! $lockedTarget->isOpenForReservation()) {
                        throw new RuntimeException('Selected Release Day appointment is not open for reservation.');
                    }

                    if ((int) $lockedSource->academic_cycle_id !== (int) $lockedTarget->academic_cycle_id) {
                        throw new RuntimeException('Target Release Day session belongs to a different academic cycle.');
                    }

                    $capacity = (int) ($lockedTarget->room?->capacity ?? 0);
                    $assigned = ConsultationAssignment::query()
                        ->active()
                        ->where('consultation_session_id', $lockedTarget->id)
                        ->count();

                    if ($assigned >= $capacity) {
                        throw new RuntimeException('The selected Release Day appointment is full.');
                    }

                    $lockedAssignment->update([
                        'status' => ConsultationAssignment::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'cancel_reason' => 'Changed by applicant',
                        'version' => $lockedAssignment->version + 1,
                    ]);

                    $newAssignment = ConsultationAssignment::query()->create([
                        'consultation_session_id' => $lockedTarget->id,
                        'admission_attempt_id' => $lockedAttempt->id,
                        'status' => ConsultationAssignment::STATUS_ASSIGNED,
                        'source' => ConsultationAssignment::SOURCE_APPLICANT_RESERVED,
                        'origin' => ConsultationAssignment::ORIGIN_RESERVATION,
                        'reserved_by_user_id' => $actor->id,
                        'version' => 1,
                    ]);

                    $lockedSource->increment('version');
                    $lockedTarget->increment('version');

                    $this->recordAuditEvent->handle('consultation.slot_switched', [
                        'attempt_id' => $lockedAttempt->id,
                        'from_assignment_id' => $lockedAssignment->id,
                        'to_assignment_id' => $newAssignment->id,
                        'from_session_id' => $lockedSource->id,
                        'to_session_id' => $lockedTarget->id,
                        'idempotency_key' => $idempotencyKey,
                    ], (string) $actor->id);

                    return $newAssignment;
                });
            },
        );
    }
}
