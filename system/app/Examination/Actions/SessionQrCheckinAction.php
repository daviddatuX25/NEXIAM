<?php

namespace App\Examination\Actions;

use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\ApplicantReferenceResolver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SessionQrCheckinAction
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * Verify applicant QR token, assert assignment belongs to session, and mark
     * attendance as present atomically.
     *
     * @return array{status: string, candidate?: array<string, mixed>, room?: string, session_code?: string, starts_at?: string}
     */
    public function handle(ExamSession $session, User $actor, string $qrCode, string $idempotencyKey): array
    {
        $code = trim($qrCode);
        $attempt = null;
        try {
            $attemptId = ApplicantQrCode::verify(ApplicantQrCode::normalize($code));
            $attempt = AdmissionAttempt::query()->forWorkspace()->with('identity')->find($attemptId);
        } catch (RuntimeException) {
            $session->loadMissing('academicCycle');
            $attemptQuery = AdmissionAttempt::query()
                ->forWorkspace()
                ->with('identity');
            if ($session->academicCycle !== null) {
                $attemptQuery->where('academic_cycle_id', $session->academic_cycle_id);
            }
            ApplicantReferenceResolver::applyMatch($attemptQuery, $code, $session->academicCycle);
            $attempt = $attemptQuery->first()
                ?? AdmissionAttempt::query()->forWorkspace()->with('identity')->whereRaw('UPPER(reference_code) = ?', [strtoupper($code)])->first();
        }

        if ($attempt === null) {
            throw new RuntimeException('Applicant code or reference number could not be resolved.');
        }

        // Assert attempt belongs to actor's institution and active academic cycle.
        $activeCycle = AcademicCycle::active();
        if ($activeCycle === null || (int) $attempt->academic_cycle_id !== $activeCycle->id) {
            throw new RuntimeException('Applicant does not belong to the active academic cycle.');
        }

        // Find assignment in this specific session.
        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $attempt->id)
            ->active()
            ->first();

        if ($assignment === null) {
            // Check if the applicant is assigned to a different session.
            $otherAssignment = ExamAssignment::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('exam_session_id', '!=', $session->id)
                ->active()
                ->with('examSession.room')
                ->first();

            if ($otherAssignment !== null) {
                $otherSession = $otherAssignment->examSession;

                return [
                    'status' => 'mismatch',
                    'room' => $otherSession->room?->name,
                    'session_code' => $otherSession->code,
                    'starts_at' => $otherSession->starts_at?->toIso8601String(),
                ];
            }

            throw new RuntimeException('Applicant has no active assignment for any exam session.');
        }

        // Mark attendance as present atomically.
        return DB::transaction(function () use ($assignment, $session, $actor, $attempt, $idempotencyKey): array {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $session->attendanceIsOpen()) {
                throw new RuntimeException('Attendance requires a started session that has not closed.');
            }
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ((int) $assignment->exam_session_id !== (int) $session->id || ! $assignment->isActive()) {
                throw new RuntimeException('Attendance requires an active assignment in this session.');
            }

            // Idempotency check.
            $existing = ExamAttendanceRecord::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->successPayload($assignment, $attempt, $session, 'already_present');
            }

            $existing = $assignment->attendanceRecord;
            if ($existing !== null) {
                if ($existing->outcome === ExamAttendanceRecord::OUTCOME_PRESENT) {
                    return $this->successPayload($assignment, $attempt, $session, 'already_present');
                }

                return [
                    'status' => 'conflict',
                    'message' => "Applicant is already recorded as {$existing->outcome}. Use the roster actions menu for corrections.",
                    'candidate' => [
                        'reference_number' => $attempt->reference_code,
                        'name' => trim(($attempt->identity?->first_name ?? '').' '.($attempt->identity?->last_name ?? '')),
                    ],
                ];
            }

            ExamAttendanceRecord::query()->create([
                'exam_assignment_id' => $assignment->id,
                'recorded_by_user_id' => $actor->id,
                'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
                'idempotency_key' => $idempotencyKey,
                'recorded_at' => now(),
            ]);

            $this->recordAuditEvent->handle('exam.qr_checkin_recorded', [
                'assignment_id' => $assignment->id,
                'admission_attempt_id' => $attempt->id,
                'exam_session_id' => $session->id,
            ], (string) $actor->id);

            return $this->successPayload($assignment, $attempt, $session);
        });
    }

    /**
     * @return array{status: string, candidate: array<string, mixed>}
     */
    private function successPayload(ExamAssignment $assignment, AdmissionAttempt $attempt, ExamSession $session, string $status = 'ok'): array
    {
        $assignment->load(['activeSeatAllocation.sessionSeat', 'activeLogisticsSeatAllocation.seat']);
        $session->loadMissing('currentLogisticsPlan', 'currentMaterialSnapshot');
        if ($session->currentLogisticsPlan !== null) {
            $isPhysicalSeating = $session->currentLogisticsPlan->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING;
            $seatLabel = $assignment->activeLogisticsSeatAllocation?->seat?->seat_label;
        } else {
            $isPhysicalSeating = $session->currentMaterialSnapshot?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING;
            $seatLabel = $assignment->activeSeatAllocation?->sessionSeat?->seat_label;
        }

        return [
            'status' => $status,
            'candidate' => [
                'reference_number' => $attempt->reference_code,
                'name' => trim(($attempt->identity?->first_name ?? '').' '.($attempt->identity?->last_name ?? '')),
                'desk_label' => $isPhysicalSeating ? $seatLabel : null,
            ],
        ];
    }
}
