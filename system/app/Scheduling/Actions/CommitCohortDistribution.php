<?php

namespace App\Scheduling\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use App\Scheduling\CandidatePoolQuery;
use App\Scheduling\CohortPlanner;
use App\Scheduling\Exceptions\StalePlanConflictException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class CommitCohortDistribution
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly CandidatePoolQuery $poolQuery,
        private readonly CohortPlanner $planner,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<int>  $targetSessionIds
     * @param  array<int, int>  $expectedSessionVersions
     * @return array<string, mixed>
     */
    public function handle(
        string $domain,
        int $academicCycleId,
        array $targetSessionIds,
        string $sortStrategy,
        string $packingStrategy,
        string $expectedPlanHash,
        array $expectedSessionVersions,
        User $actor,
        bool $autoAssignEnabled = false,
    ): array {
        $this->authorize($domain, $actor);

        if ($academicCycleId <= 0) {
            throw new InvalidArgumentException('Academic cycle ID must be positive.');
        }

        if ($targetSessionIds === []) {
            throw new InvalidArgumentException('At least one target session ID is required.');
        }

        if (trim($expectedPlanHash) === '') {
            throw new InvalidArgumentException('Expected plan hash is required.');
        }

        return $this->planningMutex->run($domain, $academicCycleId, function () use (
            $domain,
            $academicCycleId,
            $targetSessionIds,
            $sortStrategy,
            $packingStrategy,
            $expectedPlanHash,
            $expectedSessionVersions,
            $actor,
            $autoAssignEnabled,
        ): array {
            $sortedIds = collect($targetSessionIds)->unique()->sort()->values()->all();
            $sessionClass = $domain === AssignmentInvariant::DOMAIN_EXAM ? ExamSession::class : ConsultationSession::class;

            $sessions = $sessionClass::query()
                ->when($domain === AssignmentInvariant::DOMAIN_EXAM, fn ($query) => $query->forWorkspace())
                ->whereIn('id', $sortedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('room')
                ->get();

            if ($sessions->count() !== count($sortedIds)) {
                throw new StalePlanConflictException('One or more selected target sessions no longer exist.');
            }

            $staleSessionIds = [];
            foreach ($sessions as $session) {
                $expectedVersion = $expectedSessionVersions[$session->id] ?? null;
                if ($expectedVersion === null || (int) $session->version !== (int) $expectedVersion) {
                    $staleSessionIds[] = (int) $session->id;
                }
            }

            if (! empty($staleSessionIds)) {
                throw new StalePlanConflictException(
                    'Target session version mismatch. One or more sessions were modified since preview.',
                    $staleSessionIds,
                    ['expected' => $expectedSessionVersions]
                );
            }

            foreach ($sessions as $session) {
                try {
                    $this->invariant->validateTargetSessionEligible($session, $academicCycleId);
                } catch (RuntimeException $exception) {
                    throw new StalePlanConflictException(
                        "Target session [{$session->code}] is no longer eligible: {$exception->getMessage()}",
                        [(int) $session->id]
                    );
                }
            }

            $institutionId = (int) AcademicCycle::query()->whereKey($academicCycleId)->value('institution_id');
            $candidates = $domain === AssignmentInvariant::DOMAIN_EXAM
                ? $this->poolQuery->forExam($academicCycleId, $institutionId)
                : $this->poolQuery->forConsultation($academicCycleId, $institutionId);

            $plan = $this->planner->plan(
                $domain,
                $academicCycleId,
                $candidates,
                $sessions,
                $sortStrategy,
                $packingStrategy,
                $this->invariant,
            );

            if ($plan['plan_hash'] !== $expectedPlanHash) {
                throw new StalePlanConflictException(
                    'Candidate pool or target capacities have changed since preview. Please re-preview the distribution.',
                    [],
                    ['expected_hash' => $expectedPlanHash, 'actual_hash' => $plan['plan_hash']]
                );
            }

            $plannedAttemptIds = collect($plan['assignments'])
                ->pluck('admission_attempt_id')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $attempts = AdmissionAttempt::query()->forWorkspace()
                ->whereIn('id', $plannedAttemptIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $individualScoringByAttempt = $domain === AssignmentInvariant::DOMAIN_CONSULTATION
                ? $this->invariant->individualScoringForAttempts($attempts)
                : [];

            if ($attempts->count() !== count($plannedAttemptIds)) {
                throw new StalePlanConflictException('One or more planned candidates no longer exist.');
            }

            foreach ($attempts as $attempt) {
                if ($domain === AssignmentInvariant::DOMAIN_EXAM) {
                    if (! $attempt->isExamEligible()) {
                        throw new StalePlanConflictException("Applicant [{$attempt->reference_code}] is no longer exam-eligible.");
                    }
                    ExamAssignment::releaseTerminalMissedAssignmentsForAttempt(
                        (int) $attempt->id,
                        'Reassigned via cohort auto-distribution after prior absence',
                    );
                    $hasActive = ExamAssignment::query()->blockingScheduling()->where('admission_attempt_id', $attempt->id)->exists();
                    if ($hasActive) {
                        throw new StalePlanConflictException("Applicant [{$attempt->reference_code}] already has an active exam assignment.");
                    }
                } else {
                    if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
                        || $this->invariant->consultationResultReadyDetails($attempt, $individualScoringByAttempt[$attempt->id]) === null) {
                        throw new StalePlanConflictException("Applicant [{$attempt->reference_code}] no longer has a current individually frozen printed result.");
                    }
                    $hasActive = ConsultationAssignment::query()->active()->where('admission_attempt_id', $attempt->id)->exists();
                    if ($hasActive) {
                        throw new StalePlanConflictException("Applicant [{$attempt->reference_code}] already has an active consultation assignment.");
                    }
                }
            }

            $operationId = (string) Str::uuid();
            $now = now();

            if ($domain === AssignmentInvariant::DOMAIN_EXAM) {
                $assignmentRows = [];
                foreach ($plan['assignments'] as $assignment) {
                    $assignmentRows[] = [
                        'exam_session_id' => $assignment['session_id'],
                        'admission_attempt_id' => $assignment['admission_attempt_id'],
                        'status' => ExamAssignment::STATUS_ASSIGNED,
                        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                        'origin' => ExamAssignment::ORIGIN_BATCH,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (! empty($assignmentRows)) {
                    ExamAssignment::query()->insert($assignmentRows);
                }
            } else {
                $assignmentRows = [];
                foreach ($plan['assignments'] as $assignment) {
                    $assignmentRows[] = [
                        'consultation_session_id' => $assignment['session_id'],
                        'admission_attempt_id' => $assignment['admission_attempt_id'],
                        'status' => ConsultationAssignment::STATUS_ASSIGNED,
                        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
                        'origin' => ConsultationAssignment::ORIGIN_BATCH,
                        'assigned_by_user_id' => $actor->id,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (! empty($assignmentRows)) {
                    ConsultationAssignment::query()->insert($assignmentRows);
                }
            }

            $assignmentCountsBySessionId = collect($plan['assignments'])
                ->countBy(static fn (array $assignment): int => (int) $assignment['session_id']);
            foreach ($sessions as $session) {
                $updates = [];
                $wasAutoAssignEnabled = (bool) $session->auto_assign_enabled;
                if ($assignmentCountsBySessionId->get((int) $session->id, 0) > 0
                    || ($autoAssignEnabled && ! $wasAutoAssignEnabled)) {
                    $updates['version'] = (int) $session->version + 1;
                }
                if ($autoAssignEnabled) {
                    $updates['auto_assign_enabled'] = true;
                }
                if (! empty($updates)) {
                    $session->update($updates);
                }
            }

            $auditEventType = $domain === AssignmentInvariant::DOMAIN_EXAM
                ? 'exam.batch_distribution_committed'
                : 'consultation.batch_distribution_committed';

            $auditPayload = [
                'operation_id' => $operationId,
                'domain' => $domain,
                'academic_cycle_id' => $academicCycleId,
                'plan_hash' => $plan['plan_hash'],
                'sort_strategy' => $sortStrategy,
                'packing_strategy' => $packingStrategy,
                'target_session_ids' => $sortedIds,
                'assigned_count' => count($plan['assignments']),
                'unassigned_count' => $plan['unassigned_count'],
                'session_counts' => collect($plan['session_mappings'])->mapWithKeys(
                    fn (array $m): array => [$m['session_code'] => $m['allocated_count']]
                )->all(),
            ];

            $this->recordAuditEvent->handle($auditEventType, $auditPayload, (string) $actor->id);

            return [
                'operation_id' => $operationId,
                'domain' => $domain,
                'academic_cycle_id' => $academicCycleId,
                'plan_hash' => $plan['plan_hash'],
                'assigned_count' => count($plan['assignments']),
                'unassigned_count' => $plan['unassigned_count'],
                'session_mappings' => $plan['session_mappings'],
            ];
        });
    }

    private function authorize(string $domain, User $actor): void
    {
        $capability = match ($domain) {
            AssignmentInvariant::DOMAIN_EXAM => CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            AssignmentInvariant::DOMAIN_CONSULTATION => CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            default => throw new InvalidArgumentException("Unsupported distribution domain [{$domain}]."),
        };

        if (! $this->authorizer->allows($actor, $capability)) {
            throw new AuthorizationException("Unauthorized for [{$capability}].");
        }
    }
}
