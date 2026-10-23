<?php

namespace App\Scheduling;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Release\ResultDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CandidatePoolQuery implements ContextualReadToolHandler
{
    public function __construct(private readonly AssignmentInvariant $invariant) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $profile = $actor->staffProfile;

        if ((string) $actor->getKey() !== $context->actorId
            || $context->actorType !== 'staff'
            || $profile === null
            || ! $profile->isActive()) {
            return ['page_state' => 'denied', 'reason' => 'staff_actor_required'];
        }

        $institutionId = $context->institutionId;
        if ($institutionId === null || $institutionId < 1
            || (int) $profile->institution_id !== $institutionId
            || ! Institution::query()->whereKey($institutionId)->exists()) {
            return ['page_state' => 'denied', 'reason' => 'institution_context_mismatch'];
        }

        $cycleId = $input['academic_cycle_id'] ?? null;
        $cycleQuery = AcademicCycle::query()->where('institution_id', $institutionId);

        if ($cycleId === null) {
            $cycle = $cycleQuery->where('is_active', true)->first();
        } elseif (is_numeric($cycleId)) {
            $cycle = (clone $cycleQuery)->whereKey((int) $cycleId)->first()
                ?? (clone $cycleQuery)->where('is_active', true)->first();
        } else {
            $cycle = (clone $cycleQuery)->where('code', (string) $cycleId)->first()
                ?? (clone $cycleQuery)->where('is_active', true)->first();
        }

        if ($cycle === null) {
            return ['page_state' => 'unavailable', 'reason' => 'academic_cycle_unavailable'];
        }

        $limit = $input['limit'] ?? 20;
        if (! is_int($limit) || $limit < 1) {
            return ['page_state' => 'denied', 'reason' => 'limit_invalid'];
        }

        return $this->forCandidateListSafe((int) $cycle->id, $institutionId, $limit);
    }

    /**
     * @return array{
     *     summary: array{academic_cycle_id: int, pool_count: int, available_capacity: int, returned_count: int, as_of: string},
     *     candidates: list<array{candidate_ref: string, preferred_program_code: ?string, strand: ?string}>
     * }
     */
    public function forCandidateListSafe(int $academicCycleId, int $institutionId, int $limit = 20): array
    {
        $eligibleAttempts = AdmissionAttempt::query()->forWorkspace()
            ->where('academic_cycle_id', $academicCycleId)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('examAssignments', function (Builder $query): void {
                $query->blockingScheduling();
            })
            ->whereHas('academicCycle', fn (Builder $query) => $query
                ->where('institution_id', $institutionId));

        $poolCount = (clone $eligibleAttempts)->count();
        $candidates = (clone $eligibleAttempts)
            ->select(['reference_code', 'preferred_program_code', 'strand'])
            ->orderBy('id')
            ->limit(max(0, $limit))
            ->get()
            ->map(fn (AdmissionAttempt $attempt): array => [
                'candidate_ref' => (string) $attempt->reference_code,
                'preferred_program_code' => $attempt->preferred_program_code,
                'strand' => $attempt->strand,
            ])
            ->values()
            ->all();

        $availableCapacity = (int) ExamSession::query()->forWorkspace()
            ->whereHas('academicCycle', fn (Builder $query) => $query
                ->where('institution_id', $institutionId))
            ->distributionEligible($academicCycleId)
            ->with('room')
            ->get()
            ->sum(fn (ExamSession $session): int => $this->invariant->remainingCapacity($session));

        return [
            'summary' => [
                'academic_cycle_id' => $academicCycleId,
                'pool_count' => $poolCount,
                'available_capacity' => $availableCapacity,
                'returned_count' => count($candidates),
                'as_of' => now()->toIso8601String(),
            ],
            'candidates' => $candidates,
        ];
    }

    /**
     * Internal planning query for exam cohort distribution.
     * Preserves typed candidate roster for deterministic sorting and fingerprinting.
     *
     * @return Collection<int, array{
     *     id: int,
     *     reference_code: string,
     *     applicant_identity_id: int,
     *     first_name: string,
     *     last_name: string,
     *     preferred_program_code: ?string,
     *     strand: ?string,
     *     program_strand_key: string,
     *     accepted_at: Carbon,
     *     eligibility_timestamp: Carbon,
     * }>
     */
    public function forExam(int $academicCycleId, int $institutionId): Collection
    {
        $query = AdmissionAttempt::query()->forWorkspace()
            ->where('academic_cycle_id', $academicCycleId)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('examAssignments', function (Builder $query): void {
                $query->blockingScheduling();
            })
            ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))
            ->with('identity');

        return $query->get()
            ->map(function (AdmissionAttempt $attempt): array {
                $firstName = trim((string) ($attempt->identity?->first_name ?? $attempt->payload['first_name'] ?? ''));
                $lastName = trim((string) ($attempt->identity?->last_name ?? $attempt->payload['last_name'] ?? ''));
                $programStrandKey = strtoupper(trim((string) ($attempt->preferred_program_code ?: ($attempt->strand ?: 'GENERAL'))));
                $acceptedAt = $attempt->accepted_at !== null ? Carbon::parse($attempt->accepted_at) : Carbon::parse($attempt->created_at);

                return [
                    'id' => (int) $attempt->id,
                    'reference_code' => (string) $attempt->reference_code,
                    'applicant_identity_id' => (int) $attempt->applicant_identity_id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'preferred_program_code' => $attempt->preferred_program_code,
                    'strand' => $attempt->strand,
                    'program_strand_key' => $programStrandKey,
                    'accepted_at' => $acceptedAt,
                    'eligibility_timestamp' => $acceptedAt,
                ];
            })
            ->values();
    }

    /**
     * Internal planning query for consultation cohort distribution.
     * Preserves typed candidate roster for deterministic sorting and fingerprinting.
     *
     * @return Collection<int, array{
     *     id: int,
     *     reference_code: string,
     *     applicant_identity_id: int,
     *     first_name: string,
     *     last_name: string,
     *     preferred_program_code: ?string,
     *     strand: ?string,
     *     program_strand_key: string,
     *     accepted_at: ?Carbon,
     *     eligibility_timestamp: Carbon,
     *     result_ready_at: Carbon,
     *     ready_document_id: int,
     *     ready_document_checksum: string,
     *     ready_document_version: int,
     *     score_evidence_type: string,
     *     result_set_id: ?int,
     *     grading_session_id: int,
     *     administration_id: ?int,
     *     score_locked_at: Carbon,
     *     ready_document_created_at: Carbon,
     *     scoring_fingerprint: string,
     *     print_receipt_id: int,
     *     print_evidence_revision: int,
     *     print_confirmed_at: Carbon,
     * }>
     */
    public function forConsultation(int $academicCycleId, int $institutionId): Collection
    {
        $query = AdmissionAttempt::query()->forWorkspace()
            ->where('academic_cycle_id', $academicCycleId)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('consultationAssignments', function (Builder $query): void {
                $query->where(function (Builder $q): void {
                    $q->active()
                        ->orWhereIn('status', [ConsultationAssignment::STATUS_CONSULTED, 'completed']);
                });
            })
            ->whereDoesntHave('consultationDecisions', function (Builder $query): void {
                $query->whereIn('decision_type', ['approve', 'modify', 'override', 'refer', 'defer']);
            })
            ->whereDoesntHave('examAssignments', function (Builder $query): void {
                $query->whereHas('attendanceRecord', function (Builder $attendanceQuery): void {
                    $attendanceQuery->where('outcome', ExamAttendanceRecord::OUTCOME_ABSENT);
                });
            })
            ->whereHas('examAssignments', fn (Builder $query) => $query->active())
            ->whereHas('resultDocuments', function (Builder $query): void {
                $query->where('status', ResultDocument::STATUS_READY);
            })
            ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))
            ->with('identity');

        $attempts = $query->get();
        $scoringByAttempt = $this->invariant->individualScoringForAttempts($attempts);

        return $attempts
            ->map(function (AdmissionAttempt $attempt) use ($scoringByAttempt): ?array {
                $details = $this->invariant->consultationResultReadyDetails($attempt, $scoringByAttempt[$attempt->id]);
                if ($details === null) {
                    return null;
                }

                $firstName = trim((string) ($attempt->identity?->first_name ?? $attempt->payload['first_name'] ?? ''));
                $lastName = trim((string) ($attempt->identity?->last_name ?? $attempt->payload['last_name'] ?? ''));
                $programStrandKey = strtoupper(trim((string) ($attempt->preferred_program_code ?: ($attempt->strand ?: 'GENERAL'))));
                $acceptedAt = $attempt->accepted_at !== null ? Carbon::parse($attempt->accepted_at) : null;

                return [
                    'id' => (int) $attempt->id,
                    'reference_code' => (string) $attempt->reference_code,
                    'applicant_identity_id' => (int) $attempt->applicant_identity_id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'preferred_program_code' => $attempt->preferred_program_code,
                    'strand' => $attempt->strand,
                    'program_strand_key' => $programStrandKey,
                    'accepted_at' => $acceptedAt,
                    'eligibility_timestamp' => $details['result_ready_at'],
                    'result_ready_at' => $details['result_ready_at'],
                    'ready_document_id' => $details['ready_document_id'],
                    'ready_document_checksum' => $details['ready_document_checksum'],
                    'ready_document_version' => $details['ready_document_version'],
                    'score_evidence_type' => $details['score_evidence_type'],
                    'result_set_id' => $details['result_set_id'],
                    'grading_session_id' => $details['grading_session_id'],
                    'administration_id' => $details['administration_id'],
                    'score_locked_at' => $details['score_locked_at'],
                    'scoring_fingerprint' => $details['scoring_fingerprint'],
                    'ready_document_created_at' => $details['ready_document_created_at'],
                    'print_receipt_id' => $details['print_receipt_id'],
                    'print_evidence_revision' => $details['print_evidence_revision'],
                    'print_confirmed_at' => $details['print_confirmed_at'],
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Aggregate-only summary metrics for AI scheduling tools.
     * Strictly withholds applicant identities, names, and contact details.
     *
     * @return array{
     *     pool_count: int,
     *     available_capacity: int,
     * }
     */
    public function forXiamSafe(int $academicCycleId, int $institutionId, string $stage = 'exam'): array
    {
        $records = $stage === 'consultation'
            ? $this->forConsultation($academicCycleId, $institutionId)
            : $this->forExam($academicCycleId, $institutionId);

        $poolCount = $records->count();

        $availableCapacity = $stage === 'consultation'
            ? (int) ConsultationSession::query()
                ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))
                ->distributionEligible($academicCycleId)
                ->with('room')
                ->get()
                ->sum(fn ($s) => app(AssignmentInvariant::class)->remainingCapacity($s))
            : (int) ExamSession::query()->forWorkspace()
                ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))
                ->distributionEligible($academicCycleId)
                ->with('room')
                ->get()
                ->sum(fn ($s) => app(AssignmentInvariant::class)->remainingCapacity($s));

        return [
            'pool_count' => $poolCount,
            'available_capacity' => $availableCapacity,
        ];
    }
}
