<?php

namespace App\Consultation\Actions;

use App\Consultation\Support\AttemptResultReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignApplicantToConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ConsultationSession $session, AdmissionAttempt $attempt, User $actor): ConsultationAssignment
    {
        return DB::transaction(function () use ($session, $attempt, $actor): ConsultationAssignment {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            // The one extra rule on top of the exam mirror: result-ready only.
            if (! AttemptResultReadiness::isReady($attempt)) {
                throw new RuntimeException('Assignments require the attempt to be result-ready (grading finalized + result document prepared).');
            }

            if (in_array($session->status, [ConsultationSession::STATUS_COMPLETED, ConsultationSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Cannot assign to a terminal consultation session.');
            }

            $hasActiveAssignment = ConsultationAssignment::query()
                ->active()
                ->where('admission_attempt_id', $attempt->id)
                ->exists();

            if ($hasActiveAssignment) {
                throw new RuntimeException('Attempt already has an active consultation assignment.');
            }

            $activeAssignments = ConsultationAssignment::query()
                ->active()
                ->where('consultation_session_id', $session->id)
                ->count();

            $room = $session->room()->firstOrFail();

            if ($activeAssignments >= $room->capacity) {
                throw new RuntimeException('Consultation session capacity exceeded.');
            }

            $assignment = ConsultationAssignment::query()->create([
                'consultation_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
                'status' => ConsultationAssignment::STATUS_ASSIGNED,
                'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
                'assigned_by_user_id' => $actor->id,
            ]);

            $this->recordAuditEvent->handle('consultation.assignment_created', [
                'assignment_id' => $assignment->id,
                'consultation_session_id' => $session->id,
                'attempt_id' => $attempt->id,
                'source' => $assignment->source,
            ], (string) $actor->id);

            return $assignment;
        });
    }
}
