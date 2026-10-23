<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;

class AssignApplicantToConsultationSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
    ) {}

    public function handle(ConsultationSession $session, AdmissionAttempt $attempt, User $actor): ConsultationAssignment
    {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $session->academic_cycle_id,
            function () use ($session, $attempt, $actor): ConsultationAssignment {
                $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
                $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $this->invariant->validateManualConsultationAssignment($session, $attempt);

                $assignment = ConsultationAssignment::query()->create([
                    'consultation_session_id' => $session->id,
                    'admission_attempt_id' => $attempt->id,
                    'status' => ConsultationAssignment::STATUS_ASSIGNED,
                    'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
                    'origin' => ConsultationAssignment::ORIGIN_MANUAL,
                    'assigned_by_user_id' => $actor->id,
                ]);

                $this->recordAuditEvent->handle('consultation.assignment_created', [
                    'assignment_id' => $assignment->id,
                    'consultation_session_id' => $session->id,
                    'attempt_id' => $attempt->id,
                    'source' => $assignment->source,
                ], (string) $actor->id);

                return $assignment;
            },
        );
    }
}
