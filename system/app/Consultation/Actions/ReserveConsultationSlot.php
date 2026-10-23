<?php

namespace App\Consultation\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Scheduling\AssignmentPlanningMutex;
use App\Scheduling\ReservationGuard;
use App\Testing\PracticePurpose;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ReserveConsultationSlot
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly ReservationGuard $guard,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly IndividualScoringReadiness $scoringReadiness,
    ) {}

    public function handle(
        ConsultationSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey = null,
    ): ConsultationAssignment {
        if (AttemptProgramFacts::isAdvised($attempt)) {
            throw new RuntimeException('This applicant has already completed consultation.');
        }

        $created = collect();

        $assignment = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $session->academic_cycle_id,
            fn (): ConsultationAssignment => $this->guard->handle(
                session: $session,
                attempt: $attempt,
                capacityOf: fn (Model $s): int => $s->room_id === null ? 0 : (int) $s->room()->value('capacity'),
                activeForSession: fn (Model $s, AdmissionAttempt $a): ?ConsultationAssignment => ConsultationAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $a->id)
                    ->where('consultation_session_id', $s->getKey())
                    ->first(),
                activeAny: fn (AdmissionAttempt $a): ?ConsultationAssignment => ConsultationAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $a->id)
                    ->first(),
                activeCount: fn (Model $s): int => PracticePurpose::attempts(
                    ConsultationAssignment::query()->active()->where('consultation_session_id', $s->getKey()),
                    'admission_attempt_id',
                    $attempt->practice_fixture_set_id,
                )->count(),
                create: fn (Model $s, AdmissionAttempt $a): ConsultationAssignment => ConsultationAssignment::query()->create([
                    'consultation_session_id' => $s->getKey(),
                    'admission_attempt_id' => $a->id,
                    'status' => ConsultationAssignment::STATUS_ASSIGNED,
                    'source' => ConsultationAssignment::SOURCE_APPLICANT_RESERVED,
                    'origin' => ConsultationAssignment::ORIGIN_RESERVATION,
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
                        'origin' => $assignment->origin,
                        'idempotency_key' => $idempotencyKey,
                    ], (string) $actor->id);

                    $recipient = NotificationRecipients::applicantFor($a);

                    if ($recipient !== null) {
                        $intent = NotificationIntent::v1(
                            eventType: 'consultation.slot_reserved.v1',
                            templateKey: 'consultation.slot_reserved',
                            payload: [
                                'institution_id' => $a->institution_id,
                                'domain' => 'results',
                                'canonical_slug' => 'consultation-reserved',
                                'action_label' => 'View Release Day appointment',
                                'action_url' => route('applicant.consultation'),
                                'consultation_session_id' => $s->id,
                                'assignment_id' => $assignment->id,
                                'attempt_id' => $a->id,
                                'identity_id' => $recipient->id,
                                'session_version' => $s->version,
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
                eligibilityError: fn (AdmissionAttempt $a): ?RuntimeException => $this->scoringReadiness->forAttempt($a)['ready']
                    ? null
                    : new RuntimeException('Release Day appointment booking unlocks after your individual scoring is complete.'),
                availabilityError: function (Model $s): ?RuntimeException {
                    /** @var ConsultationSession $s */
                    if (! $s->isOpenForReservation()) {
                        return new RuntimeException('This Release Day session is not open for applicant reservations.');
                    }

                    return $s->status !== ConsultationSession::STATUS_DRAFT
                        ? new RuntimeException('This Release Day session is not accepting reservations.')
                        : null;
                },
                duplicateConflictMessage: 'You already have an active Release Day appointment.',
                sessionFullMessage: 'This Release Day session is full.',
            ),
        );

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $assignment;
    }
}
