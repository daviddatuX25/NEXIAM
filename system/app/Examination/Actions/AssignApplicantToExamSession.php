<?php

namespace App\Examination\Actions;

use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Support\ActionExecutionReceiptClaim;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Examination\Support\ActionExecutionResult;
use App\Examination\Support\PublishedSessionSeatAllocator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use LogicException;
use RuntimeException;

class AssignApplicantToExamSession
{
    private const ACTION_KEY = 'exams.session.assign@1';

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly ActionExecutionReceiptStore $receipts,
        private readonly PublishedSessionSeatAllocator $seatAllocator,
    ) {}

    public function handle(ExamSession $session, AdmissionAttempt $attempt, User $actor): ExamAssignment
    {
        $result = $this->execute($session, $attempt, $actor, null);
        if ($result instanceof ActionExecutionResult) {
            throw new LogicException('Human exam assignment returned a Xiam receipt.');
        }

        return $result;
    }

    public function handleConfirmed(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ActionExecutionIdentity $identity,
    ): ActionExecutionResult {
        $result = $this->execute($session, $attempt, $actor, $identity);
        if (! $result instanceof ActionExecutionResult) {
            throw new LogicException('Confirmed exam assignment did not return an Action receipt.');
        }

        return $result;
    }

    public function replayConfirmed(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ActionExecutionIdentity $identity,
    ): ?ActionExecutionResult {
        $claim = $this->receipts->replay(
            identity: $identity,
            actor: $actor,
            actionKey: self::ACTION_KEY,
            target: [
                'exam_session_id' => (int) $session->id,
                'admission_attempt_id' => (int) $attempt->id,
            ],
            command: [],
        );

        return $claim === null ? null : new ActionExecutionResult($claim->safeResult, replayed: true);
    }

    private function execute(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?ActionExecutionIdentity $identity,
    ): ExamAssignment|ActionExecutionResult {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $session->academic_cycle_id,
            function () use ($session, $attempt, $actor, $identity): ExamAssignment|ActionExecutionResult {
                $claim = $identity === null
                    ? null
                    : $this->receipts->claim(
                        identity: $identity,
                        actor: $actor,
                        actionKey: self::ACTION_KEY,
                        target: [
                            'exam_session_id' => (int) $session->id,
                            'admission_attempt_id' => (int) $attempt->id,
                        ],
                        command: [],
                    );

                if ($claim?->replayed) {
                    return new ActionExecutionResult($claim->safeResult, replayed: true);
                }

                $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
                $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                ExamAssignment::releaseTerminalMissedAssignmentsForAttempt(
                    (int) $attempt->id,
                    "Reassigned to {$session->code} after prior absence",
                );

                $existing = ExamAssignment::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('status', ExamAssignment::STATUS_ASSIGNED)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null && (int) $existing->exam_session_id !== (int) $session->id) {
                    throw new RuntimeException('Attempt belongs to another exam session. Use reassignment.');
                }

                $this->invariant->validateManualExamAssignment($session, $attempt);

                $assignment = ExamAssignment::query()->create([
                    'exam_session_id' => $session->id,
                    'admission_attempt_id' => $attempt->id,
                    'status' => ExamAssignment::STATUS_ASSIGNED,
                    'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                    'origin' => ExamAssignment::ORIGIN_MANUAL,
                ]);

                $this->seatAllocator->allocateIfPublished($session, $assignment, $actor, 'Staff assignment created');

                $this->recordAuditEvent->handle('exam.assignment_created', [
                    'assignment_id' => $assignment->id,
                    'exam_session_id' => $session->id,
                    'attempt_id' => $attempt->id,
                    'source' => $assignment->source,
                ], (string) $actor->id);

                return $this->completeReceipt($assignment, $claim);
            },
        );
    }

    private function completeReceipt(
        ExamAssignment $assignment,
        ?ActionExecutionReceiptClaim $claim,
    ): ExamAssignment|ActionExecutionResult {
        if ($claim === null) {
            return $assignment;
        }

        $safeResult = [
            'assignment_id' => $assignment->id,
            'session_id' => $assignment->exam_session_id,
            'admission_attempt_id' => $assignment->admission_attempt_id,
        ];
        $this->receipts->complete($claim, $safeResult);

        return new ActionExecutionResult($safeResult, replayed: false);
    }
}
