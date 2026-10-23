<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use RuntimeException;

class CompleteConsultationSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly DispatchPendingConsultationMediaCloses $mediaCloses,
    ) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion): ConsultationSession
    {
        $encounterIds = [];
        $completed = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $session->academic_cycle_id,
            function () use ($session, $actor, $expectedVersion, &$encounterIds): ConsultationSession {
                $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

                if ((int) $session->version !== $expectedVersion) {
                    throw new RuntimeException('Stale Release Day session version.');
                }

                if ($session->status === ConsultationSession::STATUS_COMPLETED) {
                    $encounterIds = ConsultationEncounter::query()
                        ->where('consultation_session_id', $session->id)
                        ->where('media_close_status', ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING)
                        ->orderBy('id')
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    return $session;
                }

                if (! in_array($session->status, [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS], true)) {
                    throw new RuntimeException('Release Day session cannot be completed from its current state.');
                }

                $session->update([
                    'status' => ConsultationSession::STATUS_COMPLETED,
                    'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                    'reservation_closed_at' => $session->isOpenForReservation() ? now() : $session->reservation_closed_at,
                    'completed_at' => now(),
                    'version' => $session->version + 1,
                ]);

                $encounters = ConsultationEncounter::query()
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
                    ->get();
                foreach ($encounters as $encounter) {
                    $encounterIds[] = (int) $encounter->getKey();
                    $changes = [
                        'schedule_generation' => (int) $encounter->schedule_generation + 1,
                        'closed_at' => $encounter->closed_at ?? now(),
                        'close_reason' => 'Session completed',
                        'media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING,
                    ];
                    if ($encounter->status !== ConsultationEncounter::STATUS_OUTCOME_RECORDED) {
                        $changes['status'] = ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME;
                    }
                    $encounter->update($changes);
                }

                $this->recordAuditEvent->handle('consultation.session_completed', [
                    'consultation_session_id' => $session->id,
                ], (string) $actor->id);

                return $session->fresh();
            },
        );
        $this->mediaCloses->handle($encounterIds);

        return $completed;
    }
}
