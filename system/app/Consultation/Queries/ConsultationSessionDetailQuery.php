<?php

namespace App\Consultation\Queries;

use App\Consultation\Support\ConsultationSessionPresenter;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;

/**
 * Scheduling detail: the session show page assembly — roster, ready pool,
 * reassign targets, and publishability.
 */
class ConsultationSessionDetailQuery
{
    public function __construct(
        private readonly ConsultationSessionPresenter $presenter,
        private readonly ConsultationReadyPoolQuery $readyPool,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forShow(ConsultationSession $session): array
    {
        $session->load([
            'room',
            'academicCycle',
            'assignments' => fn ($q) => $q->active()->with('admissionAttempt.identity'),
        ]);
        $session->loadCount([
            'assignments as assigned_count' => fn ($q) => $q->active(),
        ]);

        $assigned = $session->assignments->map(fn (ConsultationAssignment $assignment) => [
            'id' => $assignment->id,
            'attempt_id' => $assignment->admission_attempt_id,
            'reference_number' => $assignment->admissionAttempt?->reference_code,
            'name' => $this->presenter->attemptName($assignment->admissionAttempt),
            'source' => $assignment->source,
            'status' => $assignment->status,
        ]);

        $reassignTargets = ConsultationSession::query()
            ->with('room:id,code,name,capacity')
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->where('id', '!=', $session->id)
            ->whereNotIn('status', [ConsultationSession::STATUS_CANCELLED, ConsultationSession::STATUS_COMPLETED])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (ConsultationSession $target) => [
                'id' => $target->id,
                'code' => $target->code,
                'status' => $target->status,
                'scheduled_at' => $target->scheduled_at?->toIso8601String(),
                'room_name' => $target->room?->name ?: $target->room?->code,
                'remaining' => max(0, (int) $target->room->capacity
                    - ConsultationAssignment::query()->active()->where('consultation_session_id', $target->id)->count()),
            ])
            ->values()
            ->all();

        return [
            'session' => $this->presenter->detailPayload($session),
            'assigned' => $assigned,
            'ready_pool' => $this->readyPool->forSession($session),
            'reassign_targets' => $reassignTargets,
            'publish_block_reason' => $this->presenter->publishBlockReason($session),
        ];
    }
}
