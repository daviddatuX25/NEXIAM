<?php

namespace App\Consultation\Queries;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Consultation\Support\ConsultationSessionPresenter;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;

/**
 * Scheduling detail: the consultation session show page assembly — roster, ready pool,
 * reassign targets, and publishability with dual Inertia and Xiam-safe projections.
 */
class ConsultationSessionDetailQuery
{
    public function __construct(
        private readonly ConsultationSessionPresenter $presenter,
        private readonly ConsultationReadyPoolQuery $readyPool,
        private readonly IndividualScoringReadiness $scoringReadiness,
    ) {}

    /**
     * Fenced query returning dual-projection show payloads.
     *
     * @return array{
     *     session: ConsultationSession,
     *     forInertia: \Closure(): array<string, mixed>,
     *     forXiamSafe: \Closure(): array<string, mixed>,
     * }
     */
    public function query(int $sessionId, int $institutionId): array
    {
        $query = ConsultationSession::query()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active,institution_id',
                'assignments' => fn ($q) => $q->active()->whereHas('admissionAttempt', fn ($attempt) => $attempt->forWorkspace())->with('admissionAttempt.identity'),
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active()->whereHas('admissionAttempt', fn ($attempt) => $attempt->forWorkspace()),
            ]);

        $query->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId));

        $session = $query->findOrFail($sessionId);

        return [
            'session' => $session,
            'forInertia' => fn (): array => $this->forShow($session),
            'forXiamSafe' => function () use ($session): array {
                $payload = $this->presenter->listPayload($session);
                $payload['version'] = (int) $session->version;
                $payload['academic_cycle_id'] = (int) $session->academic_cycle_id;
                $payload['room_id'] = $session->room_id !== null ? (int) $session->room_id : null;
                $payload['assignments'] = $session->assignments->map(function (ConsultationAssignment $assignment): array {
                    $attempt = $assignment->admissionAttempt;

                    return [
                        'id' => (int) $assignment->id,
                        'attempt_id' => (int) $assignment->admission_attempt_id,
                        'reference_code' => $attempt?->reference_code,
                        'source' => $assignment->source,
                        'status' => $assignment->status,
                    ];
                })->values()->all();

                return $payload;
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forShow(ConsultationSession $session): array
    {
        $session->loadMissing([
            'room',
            'academicCycle',
            'assignments' => fn ($q) => $q->active()->whereHas('admissionAttempt', fn ($attempt) => $attempt->forWorkspace())->with('admissionAttempt.identity'),
        ]);
        $session->loadCount([
            'assignments as assigned_count' => fn ($q) => $q->active()->whereHas('admissionAttempt', fn ($attempt) => $attempt->forWorkspace()),
        ]);

        $rosterAttempts = $session->assignments->pluck('admissionAttempt')->filter()->values();
        $readinessByAttempt = $this->scoringReadiness->forAttempts($rosterAttempts);
        $assigned = $session->assignments->map(static function (ConsultationAssignment $assignment) use ($readinessByAttempt): array {
            $attempt = $assignment->admissionAttempt;
            $scoring = $attempt === null ? null : $readinessByAttempt[$attempt->id];

            return [
                'id' => (int) $assignment->id,
                'admission_attempt_id' => (int) $assignment->admission_attempt_id,
                'reference_number' => $attempt?->reference_code,
                'name' => $attempt?->identity?->fullName() ?? '',
                'source' => $assignment->source,
                'status' => $assignment->status,
                'scoring_blocker' => $scoring === null || $scoring['ready'] ? null : $scoring['reason'],
            ];
        })->values()->all();

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
                    - ConsultationAssignment::query()->active()->whereHas('admissionAttempt', fn ($attempt) => $attempt->forWorkspace())->where('consultation_session_id', $target->id)->count()),
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
