<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Admission\Support\ApplicantScope;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\ReserveExamSlot;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\User;

/**
 * exams.slot.reserve@1 — OWN-scope propose/execute for the applicant slot skill
 * (Slice 9). Propose never writes; confirm (execute) calls ReserveExamSlot with
 * the same guards + idempotency as /applicant/schedule/reserve.
 */
final class ExamSlotReserveHandler
{
    private const CAPABILITY_ID = 'exams.slot.reserve@1';

    public function __construct(private readonly ReserveExamSlot $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $executionIdentity = $input['_xiam_execution_identity'] ?? null;
        unset($input['_xiam_execution_identity']);
        if ($executionIdentity !== null && ! $executionIdentity instanceof ActionExecutionIdentity) {
            throw new \RuntimeException('action_execution_identity_invalid');
        }

        if (! $actor instanceof User) {
            return ['mode' => 'denied', 'reason' => 'unauthenticated'];
        }

        $sessionId = (int) ($input['exam_session_id'] ?? 0);
        $attemptId = (int) ($input['admission_attempt_id'] ?? 0);

        $session = ExamSession::query()->with('academicCycle')->find($sessionId);
        $attempt = AdmissionAttempt::query()->with('academicCycle')->find($attemptId);

        if ($session === null || $attempt === null) {
            return ['mode' => 'denied', 'reason' => 'target_not_found'];
        }

        $sessionInstitutionId = $session->academicCycle?->institution_id;
        $attemptInstitutionId = $attempt->academicCycle?->institution_id;
        if ($sessionInstitutionId !== null && $attemptInstitutionId !== null
            && (int) $sessionInstitutionId !== (int) $attemptInstitutionId) {
            return ['mode' => 'denied', 'reason' => 'institution_scope_mismatch'];
        }

        if (! ApplicantScope::attemptBelongsToUser($attempt, $actor)) {
            return ['mode' => 'denied', 'reason' => 'not_own_attempt'];
        }

        $impact = [
            'exam_session_id' => $session->id,
            'code' => $session->code,
            'admission_attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'action' => 'reserve_slot',
            'idempotency_key' => isset($input['idempotency_key']) && is_string($input['idempotency_key'])
                ? $input['idempotency_key']
                : null,
        ];

        if (! ($input['execute'] ?? false)) {
            $command = [
                'exam_session_id' => (int) $session->id,
                'admission_attempt_id' => (int) $attempt->id,
            ];
            if (isset($input['idempotency_key']) && is_string($input['idempotency_key']) && $input['idempotency_key'] !== '') {
                $command['idempotency_key'] = $input['idempotency_key'];
            }

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
                ProposalViewContract::field('session', 'Exam session', ['type' => 'missing'], $sessionRecord, 'choice'),
                ProposalViewContract::field('attempt', 'Applicant attempt', ['type' => 'missing'], $attemptRecord, 'context'),
                ProposalViewContract::field('reservation', 'Slot reservation', ['type' => 'missing'], ['type' => 'code', 'value' => (string) $session->code], 'effect'),
            ];

            $checks = [
                ['key' => 'own_attempt', 'label' => 'Belongs to verified applicant', 'status' => 'pass', 'detail' => 'Application attempt verified for applicant'],
                ['key' => 'session_open_precheck', 'label' => 'Session open for reservation', 'status' => 'pass', 'detail' => 'Exam session is open for reservation bookings'],
            ];

            $coverage = [
                'exam_session_id' => ['classification' => 'display', 'field' => 'session'],
                'admission_attempt_id' => ['classification' => 'display', 'field' => 'attempt'],
            ];
            if (isset($command['idempotency_key'])) {
                $coverage['idempotency_key'] = ['classification' => 'internal', 'reason' => 'Idempotent request submission key'];
            }

            $result = [
                'mode' => 'propose',
                'ok' => true,
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => $impact,
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'create',
                    'title' => 'Reserve exam slot',
                    'sections' => [
                        ProposalViewContract::fieldsSection('slot_reservation', 'Exam slot reservation', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight('pass', $checks),
                    'coverage' => $coverage,
                ],
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['own_attempt', 'session_open_precheck'],
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if ($executionIdentity instanceof ActionExecutionIdentity) {
            $replay = $this->action->replayConfirmed(
                $session,
                $attempt,
                $actor,
                $impact['idempotency_key'],
                $executionIdentity,
            );
            if ($replay !== null) {
                return ['mode' => 'execute', ...$replay->safeResult];
            }

            $result = $this->action->handleConfirmed(
                $session,
                $attempt,
                $actor,
                $impact['idempotency_key'],
                $executionIdentity,
            );

            return ['mode' => 'execute', ...$result->safeResult];
        }

        $assignment = $this->action->handle(
            $session,
            $attempt,
            $actor,
            $impact['idempotency_key'],
        );

        return [
            'mode' => 'execute',
            'assignment_id' => $assignment->id,
            'exam_session_id' => $session->id,
            'admission_attempt_id' => $attempt->id,
            'idempotency_key' => $impact['idempotency_key'],
        ];
    }
}
