<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use RuntimeException;

class StartConsultationSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion): ConsultationSession
    {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $session->academic_cycle_id,
            function () use ($session, $actor, $expectedVersion): ConsultationSession {
                $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

                if ((int) $session->version !== $expectedVersion) {
                    throw new RuntimeException('Stale Release Day session version.');
                }

                if ($session->status === ConsultationSession::STATUS_IN_PROGRESS) {
                    return $session;
                }

                if ($session->status !== ConsultationSession::STATUS_PUBLISHED) {
                    throw new RuntimeException('Only published Release Day sessions may be opened on the active board.');
                }

                $session->update([
                    'status' => ConsultationSession::STATUS_IN_PROGRESS,
                    'version' => (int) $session->version + 1,
                ]);

                $this->recordAuditEvent->handle('consultation.session.opened', [
                    'consultation_session_id' => $session->id,
                    'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                ], (string) $actor->id);

                return $session->fresh();
            },
        );
    }
}
