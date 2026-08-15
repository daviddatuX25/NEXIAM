<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Scheduling\ReservationGuard;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ReserveExamSlot
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly ReservationGuard $guard,
    ) {}

    public function handle(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey = null,
    ): ExamAssignment {
        $created = collect();

        /** @var ExamAssignment $assignment */
        $assignment = $this->guard->handle(
            session: $session,
            attempt: $attempt,
            capacityOf: function (Model $s): int {
                /** @var ExamSession $s */
                return (int) $s->room()->value('capacity');
            },
            activeForSession: fn (Model $s, AdmissionAttempt $a): ?ExamAssignment => ExamAssignment::query()
                ->active()
                ->where('admission_attempt_id', $a->id)
                ->where('exam_session_id', $s->getKey())
                ->first(),
            activeAny: fn (AdmissionAttempt $a): ?ExamAssignment => ExamAssignment::query()
                ->active()
                ->where('admission_attempt_id', $a->id)
                ->first(),
            activeCount: fn (Model $s): int => ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $s->getKey())
                ->count(),
            create: fn (Model $s, AdmissionAttempt $a): ExamAssignment => ExamAssignment::query()->create([
                'exam_session_id' => $s->getKey(),
                'admission_attempt_id' => $a->id,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_APPLICANT_RESERVED,
            ]),
            onCreated: function (Model $assignment, Model $s, AdmissionAttempt $a) use ($actor, $idempotencyKey, &$created): void {
                /** @var ExamAssignment $assignment */
                /** @var ExamSession $s */
                $this->recordAuditEvent->handle('exam.slot_reserved', [
                    'assignment_id' => $assignment->id,
                    'exam_session_id' => $s->id,
                    'attempt_id' => $a->id,
                    'source' => $assignment->source,
                    'idempotency_key' => $idempotencyKey,
                ], (string) $actor->id);

                $recipient = NotificationRecipients::applicantFor($a);

                if ($recipient !== null) {
                    $intent = NotificationIntent::v1(
                        eventType: 'exam.slot_reserved.v1',
                        templateKey: 'exam.slot_reserved',
                        payload: [
                            'subject' => 'Your exam slot is reserved',
                            'body' => "Your slot for exam session {$s->code} is reserved. See the NEXIAM portal for the schedule.",
                            'action_label' => 'View schedule',
                            'action_url' => route('applicant.schedule'),
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
                if (! $s->isOpenForReservation()) {
                    return new RuntimeException('This session is not open for applicant reservation.');
                }

                return in_array($s->status, [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED], true)
                    ? new RuntimeException('This exam session is no longer available.')
                    : null;
            },
            duplicateConflictMessage: 'You already have an active exam assignment.',
            sessionFullMessage: 'This exam session is full.',
        );

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $assignment;
    }
}
