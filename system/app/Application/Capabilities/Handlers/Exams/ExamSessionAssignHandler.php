<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

final class ExamSessionAssignHandler
{
    private const CAPABILITY_ID = 'exams.session.assign@1';

    public function __construct(private readonly AssignApplicantToExamSession $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $executionIdentity = $input['_xiam_execution_identity'] ?? null;
        unset($input['_xiam_execution_identity']);
        if ($executionIdentity !== null && ! $executionIdentity instanceof ActionExecutionIdentity) {
            throw new RuntimeException('action_execution_identity_invalid');
        }

        $sessionId = (int) ($input['session_id'] ?? 0);
        $execute = (bool) ($input['execute'] ?? false);
        $session = ExamSession::query()->find($sessionId);
        if ($session === null) {
            throw new RuntimeException('target_not_found');
        }

        $session->loadMissing(['academicCycle', 'room']);
        $sessionInstitutionId = $session->academicCycle?->institution_id;
        $actorInstitutionId = $actor?->actorScope()->institutionId;
        if ($actorInstitutionId !== null && $sessionInstitutionId !== null
            && (int) $actorInstitutionId !== (int) $sessionInstitutionId) {
            return ['mode' => 'denied', 'reason' => 'institution_scope_mismatch'];
        }

        $hasAttemptIds = is_array($input['admission_attempt_ids'] ?? null);
        $hasCandidateRefs = is_array($input['candidate_refs'] ?? null);
        if ($hasAttemptIds && $hasCandidateRefs) {
            throw new RuntimeException('batch_assignment_input_ambiguous');
        }

        if ($hasAttemptIds || $hasCandidateRefs) {
            if ($sessionInstitutionId === null) {
                return ['mode' => 'denied', 'reason' => 'institution_scope_unavailable'];
            }

            $requestedAttempts = $hasAttemptIds
                ? array_map('intval', $input['admission_attempt_ids'])
                : array_map('strval', $input['candidate_refs']);
            if ($requestedAttempts === [] || count($requestedAttempts) !== count(array_unique($requestedAttempts))) {
                throw new RuntimeException('batch_assignment_targets_invalid');
            }

            $attemptsByIdentifier = AdmissionAttempt::query()
                ->with('academicCycle')
                ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $sessionInstitutionId))
                ->when($hasAttemptIds, fn ($query) => $query->whereIn('id', $requestedAttempts))
                ->when($hasCandidateRefs, fn ($query) => $query->whereIn('reference_code', $requestedAttempts))
                ->get()
                ->keyBy($hasAttemptIds ? 'id' : 'reference_code');
            $attempts = collect($requestedAttempts)->map(
                fn (int|string $requested) => $attemptsByIdentifier->get($requested),
            );
            if ($attempts->contains(null)) {
                throw new RuntimeException('target_not_found');
            }

            /** @var Collection<int, AdmissionAttempt> $attempts */
            $attempts = $attempts->values();
            foreach ($attempts as $attempt) {
                if (! $attempt->isExamEligible()) {
                    throw new RuntimeException('attempt_not_exam_eligible');
                }
            }

            if (in_array($session->status, ['completed', 'cancelled', 'closed'], true)) {
                return ['mode' => 'denied', 'reason' => 'session_terminal'];
            }

            $roomCapacity = (int) ($session->room?->capacity ?? 0);
            $assignedCount = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->count();
            $remainingCapacity = $roomCapacity - $assignedCount;
            if ($session->room === null || $attempts->count() > $remainingCapacity) {
                return ['mode' => 'denied', 'reason' => 'session_capacity_unavailable'];
            }

            if (! $execute) {
                $command = [
                    'session_id' => $session->id,
                ];
                if ($hasAttemptIds) {
                    $command['admission_attempt_ids'] = $attempts->pluck('id')->all();
                } else {
                    $command['candidate_refs'] = $attempts->pluck('reference_code')->all();
                }

                $sessionRecord = [
                    'type' => 'record',
                    'label' => (string) $session->code,
                    'context' => array_values(array_filter([
                        $session->room?->name ? "Room: {$session->room->name}" : null,
                        $session->starts_at ? "Starts: {$session->starts_at->setTimezone('Asia/Manila')->format('Y-m-d H:i')}" : null,
                    ])),
                ];

                $applicantsList = [
                    'type' => 'list',
                    'items' => $attempts->map(static fn (AdmissionAttempt $a): array => [
                        'type' => 'record',
                        'label' => (string) $a->reference_code,
                        'context' => array_values(array_filter([(string) $a->applicant_name])),
                    ])->all(),
                    'empty_label' => 'No applicants selected',
                ];

                $fields = [
                    ProposalViewContract::field('session', 'Exam session', ['type' => 'missing'], $sessionRecord, 'context'),
                    ProposalViewContract::field('applicants', 'Assigned applicants', ['type' => 'missing'], $applicantsList, 'choice'),
                    ProposalViewContract::field(
                        'assigned_count',
                        'Applicant count',
                        ['type' => 'missing'],
                        ['type' => 'integer', 'value' => $attempts->count(), 'semantic' => 'count', 'unit' => 'applicants'],
                        'effect',
                    ),
                ];

                $coverage = [
                    'session_id' => ['classification' => 'display', 'field' => 'session'],
                ];
                if ($hasAttemptIds) {
                    $coverage['admission_attempt_ids'] = ['classification' => 'display', 'field' => 'applicants'];
                } else {
                    $coverage['candidate_refs'] = ['classification' => 'display', 'field' => 'applicants'];
                }

                $checks = [
                    ['key' => 'exam_eligible', 'label' => 'All applicants eligible for exam', 'status' => 'pass', 'detail' => 'Applicants meet all exam eligibility criteria'],
                    ['key' => 'session_not_terminal_precheck', 'label' => 'Exam session is active', 'status' => 'pass', 'detail' => 'Session is not completed, closed, or cancelled'],
                    ['key' => 'room_capacity_available', 'label' => 'Room capacity available', 'status' => 'pass', 'detail' => 'Room has remaining capacity for applicants'],
                ];

                $impact = [
                    'session_id' => $session->id,
                    'code' => $session->code,
                    'admission_attempt_ids' => $attempts->pluck('id')->all(),
                    'candidate_refs' => $attempts->pluck('reference_code')->all(),
                    'count' => $attempts->count(),
                    'action' => 'batch_assign',
                    'expected_version' => (int) $session->version,
                ];

                $result = [
                    'mode' => 'propose',
                    'ok' => true,
                    'capability_id' => self::CAPABILITY_ID,
                    'command_input' => $command,
                    'impact' => $impact,
                    'proposal_review' => [
                        'version' => 1,
                        'operation' => 'bulk',
                        'title' => 'Assign applicants to session',
                        'sections' => [
                            ProposalViewContract::fieldsSection('session_batch_assignment', 'Exam session assignment', $fields),
                        ],
                        'preflight' => ProposalViewContract::preflight('pass', $checks),
                        'coverage' => $coverage,
                    ],
                    'evidence' => [
                        'outcome' => 'pass',
                        'checks' => ['exam_eligible', 'session_not_terminal_precheck', 'room_capacity_available'],
                    ],
                ];
                ProposalReview::fromResult(self::CAPABILITY_ID, $result);

                return $result;
            }

            if (! $actor instanceof User) {
                throw new RuntimeException('unauthenticated');
            }
            if ($executionIdentity instanceof ActionExecutionIdentity) {
                throw new RuntimeException('batch_assignment_confirmation_unsupported');
            }

            $assignments = [];
            foreach ($attempts as $attempt) {
                $assignments[] = $this->action->handle($session, $attempt, $actor);
            }

            return [
                'mode' => 'execute',
                'session_id' => $session->id,
                'assignment_ids' => collect($assignments)->pluck('id')->all(),
                'admission_attempt_ids' => $attempts->pluck('id')->all(),
            ];
        }

        $attemptId = (int) ($input['admission_attempt_id'] ?? 0);
        $attempt = AdmissionAttempt::query()->with('academicCycle')->find($attemptId);
        if ($attempt === null) {
            throw new RuntimeException('target_not_found');
        }
        $attemptInstitutionId = $attempt->academicCycle?->institution_id;
        if ($sessionInstitutionId !== null && $attemptInstitutionId !== null
            && (int) $sessionInstitutionId !== (int) $attemptInstitutionId) {
            return ['mode' => 'denied', 'reason' => 'institution_scope_mismatch'];
        }

        $impact = [
            'session_id' => $session->id,
            'code' => $session->code,
            'admission_attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'action' => 'assign',
            'expected_version' => (int) $session->version,
        ];
        if (! $execute) {
            if (! $attempt->isExamEligible()) {
                throw new RuntimeException('attempt_not_exam_eligible');
            }

            if (in_array($session->status, ['completed', 'cancelled', 'closed'], true)) {
                return ['mode' => 'denied', 'reason' => 'session_terminal'];
            }

            $roomCapacity = (int) ($session->room?->capacity ?? 0);
            $assignedCount = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->count();
            if ($session->room === null || $assignedCount >= $roomCapacity) {
                return ['mode' => 'denied', 'reason' => 'session_capacity_unavailable'];
            }

            $command = [
                'session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
            ];

            $sessionRecord = [
                'type' => 'record',
                'label' => (string) $session->code,
                'context' => array_values(array_filter([
                    $session->room?->name ? "Room: {$session->room->name}" : null,
                    $session->starts_at ? "Starts: {$session->starts_at->setTimezone('Asia/Manila')->format('Y-m-d H:i')}" : null,
                ])),
            ];

            $attemptRecord = [
                'type' => 'record',
                'label' => (string) $attempt->reference_code,
                'context' => array_values(array_filter([
                    (string) $attempt->applicant_name,
                ])),
            ];

            $fields = [
                ProposalViewContract::field('session', 'Exam session', ['type' => 'missing'], $sessionRecord, 'context'),
                ProposalViewContract::field('applicant', 'Applicant', ['type' => 'missing'], $attemptRecord, 'choice'),
                ProposalViewContract::field(
                    'action',
                    'Assignment status',
                    ['type' => 'missing'],
                    ['type' => 'enum', 'code' => 'assigned', 'label' => 'Assigned to session'],
                    'effect',
                ),
            ];

            $checks = [
                ['key' => 'exam_eligible', 'label' => 'Applicant eligible for exam', 'status' => 'pass', 'detail' => 'Applicant meets exam eligibility criteria'],
                ['key' => 'session_not_terminal_precheck', 'label' => 'Exam session is active', 'status' => 'pass', 'detail' => 'Session is not completed, closed, or cancelled'],
                ['key' => 'room_capacity_available', 'label' => 'Room capacity available', 'status' => 'pass', 'detail' => 'Room has remaining capacity for assignment'],
            ];

            $coverage = [
                'session_id' => ['classification' => 'display', 'field' => 'session'],
                'admission_attempt_id' => ['classification' => 'display', 'field' => 'applicant'],
            ];

            $result = [
                'mode' => 'propose',
                'ok' => true,
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => $impact,
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'create',
                    'title' => 'Assign applicants to session',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session_assignment', 'Exam session assignment', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight('pass', $checks),
                    'coverage' => $coverage,
                ],
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['exam_eligible', 'session_not_terminal_precheck', 'room_capacity_available'],
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        if ($executionIdentity instanceof ActionExecutionIdentity) {
            $replay = $this->action->replayConfirmed($session, $attempt, $actor, $executionIdentity);
            if ($replay !== null) {
                return ['mode' => 'execute', ...$replay->safeResult];
            }

            if (! $attempt->isExamEligible()) {
                throw new RuntimeException('attempt_not_exam_eligible');
            }

            $result = $this->action->handleConfirmed($session, $attempt, $actor, $executionIdentity);

            return ['mode' => 'execute', ...$result->safeResult];
        }

        if (! $attempt->isExamEligible()) {
            throw new RuntimeException('attempt_not_exam_eligible');
        }

        $assignment = $this->action->handle($session, $attempt, $actor);

        return [
            'mode' => 'execute',
            'assignment_id' => $assignment->id,
            'session_id' => $session->id,
            'admission_attempt_id' => $attempt->id,
        ];
    }
}
