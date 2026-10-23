<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelConsultationSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly DispatchPendingConsultationMediaCloses $mediaCloses,
    ) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion, string $reason): ConsultationSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Cancel requires a reason.');
        }

        $encounterIds = [];
        $cancelled = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $session->academic_cycle_id,
            function () use ($session, $actor, $expectedVersion, $reason, &$encounterIds): ConsultationSession {
                return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason, &$encounterIds): ConsultationSession {
                    $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
                    if ((int) $session->version !== $expectedVersion) {
                        throw new RuntimeException('Stale Release Day session version.');
                    }
                    if (! in_array($session->status, [ConsultationSession::STATUS_DRAFT, ConsultationSession::STATUS_PUBLISHED], true)) {
                        throw new RuntimeException('Only draft or published Release Day sessions may be cancelled.');
                    }

                    $attemptIds = ConsultationAssignment::query()
                        ->where('consultation_session_id', $session->id)
                        ->pluck('admission_attempt_id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                    $onlineEncounters = ConsultationEncounter::query()
                        ->where('consultation_session_id', $session->id)
                        ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
                        ->whereIn('status', [
                            ConsultationEncounter::STATUS_INVITED,
                            ConsultationEncounter::STATUS_WAITING,
                            ConsultationEncounter::STATUS_IN_PROGRESS,
                            ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                        ])
                        ->get(['id', 'admission_attempt_id']);
                    $attemptIds = array_values(array_unique([
                        ...$attemptIds,
                        ...$onlineEncounters->pluck('admission_attempt_id')->map(fn ($id): int => (int) $id)->all(),
                    ]));
                    sort($attemptIds);
                    if ($attemptIds !== []) {
                        AdmissionAttempt::query()
                            ->whereIn('id', $attemptIds)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();
                    }

                    ConsultationAssignment::query()
                        ->active()
                        ->where('consultation_session_id', $session->id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->each(function (ConsultationAssignment $assignment) use ($reason): void {
                            $assignment->update([
                                'status' => ConsultationAssignment::STATUS_CANCELLED,
                                'cancelled_at' => now(),
                                'cancel_reason' => $reason,
                                'version' => $assignment->version + 1,
                            ]);
                        });

                    ConsultationEncounter::query()
                        ->where('consultation_session_id', $session->id)
                        ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
                        ->whereIn('status', [
                            ConsultationEncounter::STATUS_INVITED,
                            ConsultationEncounter::STATUS_WAITING,
                            ConsultationEncounter::STATUS_IN_PROGRESS,
                            ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                        ])
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->each(function (ConsultationEncounter $encounter) use ($reason, &$encounterIds): void {
                            $encounterIds[] = (int) $encounter->getKey();
                            $changes = [
                                'schedule_generation' => (int) $encounter->schedule_generation + 1,
                                'closed_at' => $encounter->closed_at ?? now(),
                                'close_reason' => $reason,
                                'media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING,
                            ];
                            if ($encounter->status !== ConsultationEncounter::STATUS_OUTCOME_RECORDED) {
                                $changes['status'] = ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME;
                            }
                            $encounter->update($changes);
                        });

                    $session->update([
                        'status' => ConsultationSession::STATUS_CANCELLED,
                        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                        'reservation_closed_at' => $session->isOpenForReservation() ? now() : $session->reservation_closed_at,
                        'cancelled_at' => now(),
                        'version' => $session->version + 1,
                    ]);

                    $this->recordAuditEvent->handle('consultation.session_cancelled', [
                        'consultation_session_id' => $session->id,
                        'reason' => $reason,
                    ], (string) $actor->id);

                    return $session->fresh();
                });
            },
        );

        $this->mediaCloses->handle($encounterIds);

        return $cancelled;
    }
}
