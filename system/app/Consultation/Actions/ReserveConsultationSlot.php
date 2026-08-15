<?php

namespace App\Consultation\Actions;

use App\Consultation\Support\AttemptResultReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Scheduling\ReservationGuard;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ReserveConsultationSlot
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly ReservationGuard $guard,
    ) {}

    public function handle(
        ConsultationSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey = null,
    ): ConsultationAssignment {
        $created = collect();

        /** @var ConsultationAssignment $assignment */
        $assignment = $this->guard->handle(
            session: $session,
            attempt: $attempt,
            capacityOf: function (Model $s): int {
                /** @var ConsultationSession $s */
                return (int) $s->room()->value('capacity');
            },
            activeForSession: fn (Model $s, AdmissionAttempt $a): ?ConsultationAssignment => ConsultationAssignment::query()
                ->active()
                ->where('admission_attempt_id', $a->id)
                ->where('consultation_session_id', $s->getKey())
                ->first(),
            activeAny: fn (AdmissionAttempt $a): ?ConsultationAssignment => ConsultationAssignment::query()
                ->active()
                ->where('admission_attempt_id', $a->id)
                ->first(),
            activeCount: fn (Model $s): int => ConsultationAssignment::query()
                ->active()
                ->where('consultation_session_id', $s->getKey())
                ->count(),
            create: fn (Model $s, AdmissionAttempt $a): ConsultationAssignment => ConsultationAssignment::query()->create([
                'consultation_session_id' => $s->getKey(),
                'admission_attempt_id' => $a->id,
                'status' => ConsultationAssignment::STATUS_ASSIGNED,
                'source' => ConsultationAssignment::SOURCE_APPLICANT_RESERVED,
                'reserved_by_user_id' => $actor->id,
            ]),
            onCreated: function (Model $assignment, Model $s, AdmissionAttempt $a) use ($actor, $idempotencyKey, &$created): void {
                /** @var ConsultationAssignment $assignment */
                /** @var ConsultationSession $s */
                $this->recordAuditEvent->handle('consultation.slot_reserved', [
                    'assignment_id' => $assignment->id,
                    'consultation_session_id' => $s->id,
                    'attempt_id' => $a->id,
                    'source' => $assignment->source,
                    'idempotency_key' => $idempotencyKey,
                ], (string) $actor->id);

                $recipient = NotificationRecipients::applicantFor($a);

                if ($recipient !== null) {
                    $intent = NotificationIntent::v1(
                        eventType: 'consultation.slot_reserved.v1',
                        templateKey: 'consultation.slot_reserved',
                        payload: [
                            'subject' => 'Your consultation slot is reserved',
                            'body' => "Your consultation slot for session {$s->code} is reserved. See the NEXIAM portal for the schedule.",
                            'action_label' => 'View consultation schedule',
                            'action_url' => route('applicant.consultation'),
                            'consultation_session_id' => $s->id,
                            'assignment_id' => $assignment->id,
                            'code' => $s->code,
                            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                        ],
                    );

                    $created = $this->dispatcher->dispatch(
                        $intent,
                        hash('sha256', "consultation.slot_reserved:{$assignment->id}:v1"),
                        [$recipient],
                        $intent->channels,
                    );
                }
            },
            eligibilityError: fn (AdmissionAttempt $a): ?RuntimeException => AttemptResultReadiness::isReady($a)
                ? null
                : new RuntimeException('Consultation unlocks after your result is ready.'),
            availabilityError: function (Model $s): ?RuntimeException {
                /** @var ConsultationSession $s */
                if (! $s->isOpenForReservation()) {
                    return new RuntimeException('This consultation session is not open for applicant reservation.');
                }

                return $s->status !== ConsultationSession::STATUS_PUBLISHED
                    ? new RuntimeException('This consultation session is not accepting reservations.')
                    : null;
            },
            duplicateConflictMessage: 'You already have an active consultation assignment.',
            sessionFullMessage: 'This consultation session is full.',
        );

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $assignment;
    }
}
