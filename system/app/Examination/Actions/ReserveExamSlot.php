<?php

namespace App\Examination\Actions;

use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Examination\Support\ActionExecutionResult;
use App\Examination\Support\PublishedSessionSeatAllocator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\ReservationGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class ReserveExamSlot
{
    private const ACTION_KEY = 'exams.slot.reserve@1';

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly ReservationGuard $guard,
        private readonly ActionExecutionReceiptStore $receipts,
        private readonly AssignmentInvariant $invariant,
        private readonly PublishedSessionSeatAllocator $seatAllocator,
    ) {}

    public function handle(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey = null,
    ): ExamAssignment {
        $result = $this->execute($session, $attempt, $actor, $idempotencyKey, null);
        if ($result instanceof ActionExecutionResult) {
            throw new LogicException('Human exam reservation returned a Xiam receipt.');
        }

        return $result;
    }

    public function handleConfirmed(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey,
        ActionExecutionIdentity $identity,
    ): ActionExecutionResult {
        $result = $this->execute($session, $attempt, $actor, $idempotencyKey, $identity);
        if (! $result instanceof ActionExecutionResult) {
            throw new LogicException('Confirmed applicant reservation did not return an Action receipt.');
        }

        return $result;
    }

    public function replayConfirmed(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey,
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
            command: ['idempotency_key' => $idempotencyKey],
        );

        return $claim === null ? null : new ActionExecutionResult($claim->safeResult, replayed: true);
    }

    private function execute(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey,
        ?ActionExecutionIdentity $identity,
    ): ExamAssignment|ActionExecutionResult {
        return DB::transaction(function () use ($session, $attempt, $actor, $idempotencyKey, $identity): ExamAssignment|ActionExecutionResult {
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
                    command: ['idempotency_key' => $idempotencyKey],
                );

            if ($claim?->replayed) {
                return new ActionExecutionResult($claim->safeResult, replayed: true);
            }

            $created = collect();

            /** @var ExamAssignment $assignment */
            $assignment = $this->guard->handle(
                session: $session,
                attempt: $attempt,
                capacityOf: function (Model $s): int {
                    /** @var ExamSession $s */
                    return $this->invariant->capacityLimit($s);
                },
                activeForSession: fn (Model $s, AdmissionAttempt $a): ?ExamAssignment => ExamAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $a->id)
                    ->where('exam_session_id', $s->getKey())
                    ->first(),
                activeAny: function (AdmissionAttempt $a): ?ExamAssignment {
                    ExamAssignment::releaseTerminalMissedAssignmentsForAttempt(
                        (int) $a->id,
                        'Released prior missed assignment on applicant slot reservation',
                    );

                    return ExamAssignment::query()
                        ->blockingScheduling()
                        ->where('admission_attempt_id', $a->id)
                        ->first();
                },
                activeCount: fn (Model $s): int => ExamAssignment::query()
                    ->active()
                    ->where('exam_session_id', $s->getKey())
                    ->count(),
                create: fn (Model $s, AdmissionAttempt $a): ExamAssignment => ExamAssignment::query()->create([
                    'exam_session_id' => $s->getKey(),
                    'admission_attempt_id' => $a->id,
                    'status' => ExamAssignment::STATUS_ASSIGNED,
                    'source' => ExamAssignment::SOURCE_APPLICANT_RESERVED,
                    'origin' => ExamAssignment::ORIGIN_RESERVATION,
                ]),
                onCreated: function (Model $assignment, Model $s, AdmissionAttempt $a) use ($actor, $idempotencyKey, &$created): void {
                    /** @var ExamAssignment $assignment */
                    /** @var ExamSession $s */
                    $this->seatAllocator->allocateIfPublished($s, $assignment, $actor, 'Applicant reservation');

                    $this->recordAuditEvent->handle('exam.slot_reserved', [
                        'assignment_id' => $assignment->id,
                        'exam_session_id' => $s->id,
                        'attempt_id' => $a->id,
                        'source' => $assignment->source,
                        'origin' => $assignment->origin,
                        'idempotency_key' => $idempotencyKey,
                    ], (string) $actor->id);

                    $recipient = NotificationRecipients::applicantFor($a);

                    if ($recipient !== null) {
                        $intent = NotificationIntent::v1(
                            eventType: 'exam.slot_reserved.v1',
                            templateKey: 'exam.slot_reserved',
                            payload: [
                                ...AdmissionExaminationNoticeContext::examinationPayload($s, 'slot-reserved'),
                                'action_label' => 'View schedule',
                                'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                                'exam_session_id' => $s->id,
                                'assignment_id' => $assignment->id,
                                'code' => $s->code,
                                'starts_at' => $s->starts_at?->toIso8601String(),
                            ],
                        );

                        $created = $this->dispatcher->dispatch(
                            $intent,
                            hash('sha256', "exam.slot_reserved:{$assignment->id}:v1"),
                            [$recipient],
                            $intent->channels,
                        );
                    }
                },
                eligibilityError: fn (AdmissionAttempt $a): ?RuntimeException => $a->isExamEligible()
                    ? null
                    : new RuntimeException('Only accepted applicants may reserve an exam slot.'),
                availabilityError: function (Model $s): ?RuntimeException {
                    /** @var ExamSession $s */
                    if ($s->status !== ExamSession::STATUS_DRAFT
                        || $s->booking_mode !== ExamSession::BOOKING_OPEN_FOR_RESERVATION) {
                        return new RuntimeException('Only draft sessions open for applicant reservation are available.');
                    }

                    return null;
                },
                duplicateConflictMessage: 'You already have an active exam assignment.',
                sessionFullMessage: 'This exam session is full.',
            );

            NotificationIntentDispatcher::enqueueAfterCommit($created);
            if ($claim === null) {
                return $assignment;
            }

            $safeResult = [
                'assignment_id' => $assignment->id,
                'exam_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
                'idempotency_key' => $idempotencyKey,
            ];
            $this->receipts->complete($claim, $safeResult);

            return new ActionExecutionResult($safeResult, replayed: false);
        });
    }
}
