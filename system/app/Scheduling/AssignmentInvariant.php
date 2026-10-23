<?php

namespace App\Scheduling;

use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Examination\Support\ExamSnapshotInterpretation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

final class AssignmentInvariant
{
    public function __construct(private readonly IndividualScoringReadiness $scoringReadiness) {}

    /** @return array<int, array<string, mixed>> */
    public function individualScoringForAttempts(Collection $attempts): array
    {
        return $this->scoringReadiness->forAttempts($attempts);
    }

    public const DOMAIN_EXAM = 'exam';

    public const DOMAIN_CONSULTATION = 'consultation';

    public function validateManualExamAssignment(ExamSession $session, AdmissionAttempt $attempt): void
    {
        if ($session->status !== ExamSession::STATUS_DRAFT) {
            throw new RuntimeException('Published or finalized session rosters are locked for ordinary scheduling. Exam Day walk-in and early-traveler changes must use AccommodateApplicantExamSession on Monitoring.');
        }

        if ($session->practice_fixture_set_id !== $attempt->practice_fixture_set_id) {
            throw new RuntimeException('The applicant and exam session must belong to the same operational or practice workspace.');
        }
        if ((int) $attempt->academic_cycle_id !== (int) $session->academic_cycle_id) {
            throw new RuntimeException('Attempt is not in the same academic year as this session.');
        }

        if (! $attempt->isExamEligible()) {
            throw new RuntimeException('Only exam-eligible (accepted) attempts may be assigned.');
        }

        if (in_array($session->status, [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED], true)) {
            throw new RuntimeException('Cannot assign to a terminal exam session.');
        }

        $hasActiveAssignment = ExamAssignment::query()
            ->blockingScheduling()
            ->where('admission_attempt_id', $attempt->id)
            ->exists();

        if ($hasActiveAssignment) {
            throw new RuntimeException('Attempt already has an active exam assignment.');
        }

        if ($this->remainingCapacity($session) <= 0) {
            throw new RuntimeException('Exam session capacity exceeded.');
        }
    }

    public function validateManualConsultationAssignment(ConsultationSession $session, AdmissionAttempt $attempt): void
    {
        if (AttemptProgramFacts::isAdvised($attempt)) {
            throw new RuntimeException('Attempt is already advised.');
        }

        if ((int) $attempt->academic_cycle_id !== (int) $session->academic_cycle_id) {
            throw new RuntimeException('Attempt is not in the same academic year as this session.');
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED || ! $this->scoringReadiness->forAttempt($attempt)['ready']) {
            throw new RuntimeException('Assignments require an accepted attempt with complete individual scoring.');
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

        $room = $session->room()->firstOrFail();

        $activeAssignments = ConsultationAssignment::query()
            ->active()
            ->where('consultation_session_id', $session->id)
            ->count();

        if ($activeAssignments >= $room->capacity) {
            throw new RuntimeException('Consultation session capacity exceeded.');
        }
    }

    public function isTargetSessionEligible(ExamSession|ConsultationSession $session, int $academicCycleId): bool
    {
        try {
            $this->validateTargetSessionEligible($session, $academicCycleId);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function validateTargetSessionEligible(ExamSession|ConsultationSession $session, int $academicCycleId): void
    {
        if ((int) $session->academic_cycle_id !== $academicCycleId) {
            throw new RuntimeException("Target session [{$session->code}] belongs to a different academic cycle.");
        }

        $statusDraft = $session instanceof ExamSession ? ExamSession::STATUS_DRAFT : ConsultationSession::STATUS_DRAFT;
        if ($session->status !== $statusDraft) {
            throw new RuntimeException("Target session [{$session->code}] is not in draft status.");
        }

        $staffOnlyMode = $session instanceof ExamSession ? ExamSession::BOOKING_STAFF_ONLY : ConsultationSession::BOOKING_STAFF_ONLY;
        if ($session->booking_mode !== $staffOnlyMode) {
            throw new RuntimeException("Target session [{$session->code}] is open for reservation or not staff-only.");
        }

        $room = $session->room;
        if ($room === null || ! $room->is_active) {
            throw new RuntimeException("Target session [{$session->code}] does not have an active room.");
        }

        if ($session instanceof ExamSession && ! $room->isExamRoom()) {
            throw new RuntimeException("Target session [{$session->code}] room is not an exam room.");
        }
        if ($this->remainingCapacity($session) <= 0) {
            throw new RuntimeException("Target session [{$session->code}] has no remaining capacity.");
        }
    }

    public function capacityLimit(ExamSession|ConsultationSession $session): int
    {
        if ($session instanceof ExamSession) {
            $plan = $session->currentLogisticsPlan;
            if ($plan !== null) {
                if ($plan->seating_mode === 'simple_capacity') {
                    return max(0, (int) $plan->capacity_limit);
                }

                return $plan->seats()->count() === (int) $plan->capacity_limit
                    ? max(0, (int) $plan->capacity_limit)
                    : 0;
            }

            if (! $session->hasLogisticsGeneration()) {
                $snapshot = $session->currentMaterialSnapshot;
                if ($snapshot !== null) {
                    if ($snapshot->capacity_limit !== null) {
                        $capacityLimit = max(0, (int) $snapshot->capacity_limit);

                        return $snapshot->seats()->count() === $capacityLimit ? $capacityLimit : 0;
                    }
                    try {
                        return app(ExamSnapshotInterpretation::class)->manifest($snapshot)['capacity_limit'];
                    } catch (RuntimeException) {
                        return 0;
                    }
                }
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                return 0;
            }
        }

        $room = $session->room;
        if ($room === null || ! $room->is_active) {
            return 0;
        }

        $roomCapacity = max(0, (int) $room->capacity);
        if ($session instanceof ExamSession
            && $session->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
            && $session->seating_arrangement_id) {
            $deskCount = (int) ($session->seatingArrangement?->desk_count ?? 0);
            if ($deskCount > 0) {
                return min($roomCapacity, $deskCount);
            }
        }

        return $roomCapacity;
    }

    public function remainingCapacity(ExamSession|ConsultationSession $session): int
    {
        $capacityLimit = $this->capacityLimit($session);
        $activeCount = $session instanceof ExamSession
            ? ExamAssignment::query()->active()->where('exam_session_id', $session->id)->count()
            : ConsultationAssignment::query()->active()->whereHas('admissionAttempt', fn ($query) => $query->forWorkspace())->where('consultation_session_id', $session->id)->count();

        $remainingCapacity = max(0, $capacityLimit - $activeCount);
        if ($session instanceof ExamSession) {
            $plan = $session->currentLogisticsPlan;
            if ($plan !== null && $plan->seating_mode === 'assigned_seating') {
                return min($remainingCapacity, $plan->seats()->whereDoesntHave('activeAllocation')->count());
            }
            if ($plan !== null) {
                return $remainingCapacity;
            }

            if (! $session->hasLogisticsGeneration()) {
                $snapshot = $session->currentMaterialSnapshot;
                if ($snapshot !== null) {
                    return min($remainingCapacity, $snapshot->seats()
                        ->whereDoesntHave('allocations', fn ($query) => $query->whereNull('effective_to'))->count());
                }
            }
        }

        return $remainingCapacity;
    }

    /**
     * Returns the exact current individually frozen result and current physical-print evidence for automation.
     *
     * @return array{result_ready_at: Carbon, score_evidence_type: string, result_set_id: ?int, grading_session_id: int, administration_id: ?int, score_locked_at: Carbon, scoring_fingerprint: string, ready_document_id: int, ready_document_checksum: string, ready_document_version: int, ready_document_created_at: Carbon, print_receipt_id: int, print_evidence_revision: int, print_confirmed_at: Carbon}|null
     */
    public function consultationResultReadyDetails(AdmissionAttempt $attempt, ?array $scoring = null): ?array
    {
        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED || $attempt->current_result_document_id === null) {
            return null;
        }

        $activeExamAssignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        if ($activeExamAssignment === null) {
            return null;
        }

        $activeExamAssignment->loadMissing('attendanceRecord');
        if ($activeExamAssignment->attendanceRecord?->outcome === ExamAttendanceRecord::OUTCOME_ABSENT) {
            return null;
        }
        $scoring ??= $this->scoringReadiness->forAttempt($attempt);
        $resultSetId = $scoring['result_set_id'];
        $isFrozenTypedResult = $scoring['administration_id'] !== null && $resultSetId !== null;
        $isFrozenLegacyLedger = $scoring['administration_id'] === null
            && $resultSetId === null
            && $scoring['state'] === 'frozen'
            && $scoring['grading_session_id'] !== null;
        if (! $scoring['ready'] || ! $scoring['score_locked'] || (! $isFrozenTypedResult && ! $isFrozenLegacyLedger)) {
            return null;
        }

        $scoreLockedAt = $scoring['score_locked_at'];
        if (! is_string($scoreLockedAt) || $scoreLockedAt === ''
            || ! is_string($scoring['current_fingerprint'])
            || $scoring['current_fingerprint'] === '') {
            return null;
        }

        $scoreLockedAt = Carbon::parse($scoreLockedAt);
        $readyDocument = ResultDocument::query()
            ->whereKey($attempt->current_result_document_id)
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->first();

        if ($readyDocument === null || $readyDocument->created_at === null) {
            return null;
        }

        $snapshotResultSetId = $readyDocument->snapshot_payload_json['_assessment_result_set_id'] ?? null;
        if (($isFrozenTypedResult && (! is_int($snapshotResultSetId) || $snapshotResultSetId !== (int) $resultSetId))
            || ($isFrozenLegacyLedger && $snapshotResultSetId !== null)
            || $readyDocument->checksum === null
            || $readyDocument->version < 1) {
            return null;
        }

        $printReceipt = ResultDocumentPrintReceipt::query()
            ->where('institution_id', $attempt->institution_id)
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $readyDocument->id)
            ->where('operation', 'confirmed')
            ->where('is_current', true)
            ->orderByDesc('evidence_revision')
            ->orderByDesc('id')
            ->first();

        if ($printReceipt === null || $printReceipt->confirmed_at === null) {
            return null;
        }

        $docCreatedAt = Carbon::parse($readyDocument->created_at);
        $resultReadyAt = $scoreLockedAt->greaterThan($docCreatedAt) ? $scoreLockedAt : $docCreatedAt;
        $printConfirmedAt = Carbon::parse($printReceipt->confirmed_at);
        if ($printConfirmedAt->greaterThan($resultReadyAt)) {
            $resultReadyAt = $printConfirmedAt;
        }

        return [
            'result_ready_at' => $resultReadyAt,
            'score_evidence_type' => $isFrozenTypedResult ? 'result_set' : 'legacy_ledger',
            'result_set_id' => $resultSetId === null ? null : (int) $resultSetId,
            'grading_session_id' => (int) $scoring['grading_session_id'],
            'administration_id' => $scoring['administration_id'] === null ? null : (int) $scoring['administration_id'],
            'score_locked_at' => $scoreLockedAt,
            'scoring_fingerprint' => $scoring['current_fingerprint'],
            'ready_document_id' => (int) $readyDocument->id,
            'ready_document_checksum' => (string) $readyDocument->checksum,
            'ready_document_version' => (int) $readyDocument->version,
            'ready_document_created_at' => $docCreatedAt,
            'print_receipt_id' => (int) $printReceipt->id,
            'print_evidence_revision' => (int) $printReceipt->evidence_revision,
            'print_confirmed_at' => $printConfirmedAt,
        ];

    }
}
