<?php

namespace App\Examination\Queries;

use App\Examination\Support\ExamSessionPresenter;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Scheduling\AssignmentInvariant;
use Illuminate\Support\Collection;

/**
 * Scheduling detail: the session show page assembly — roster, available
 * applicants, reassign targets, and publishability.
 */
class ExamSessionDetailQuery
{
    public function __construct(
        private readonly ExamSessionPresenter $presenter,
        private readonly AssignmentInvariant $assignmentInvariant,
    ) {}

    /**
     * Fenced query returning dual-projection show payloads.
     *
     * @return array{
     *     session: ExamSession,
     *     forInertia: \Closure(): array<string, mixed>,
     *     forXiamSafe: \Closure(): array<string, mixed>,
     * }
     */
    public function query(int $sessionId, int $institutionId): array
    {
        $query = ExamSession::query()->forWorkspace()
            ->with([
                'room:id,code,name,capacity,is_active,space_kind',
                'academicCycle:id,school_year,semester,name,is_active,institution_id',
                'bookletVersion.booklet:id,name',
                'bookletVersion.templateVersion.template:id,name',
                'seatingArrangement',
                'currentLogisticsPlan.arrangement',
                'currentMaterialSnapshot',
                'assignments' => fn ($q) => $q->active()->with([
                    'admissionAttempt.identity',
                    'activeSeatAllocation.sessionSeat',
                    'activeLogisticsSeatAllocation.seat',
                ]),
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->withExists('logisticsPlans as has_logistics_generation');

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
                $payload['assignments'] = $session->assignments->map(function (ExamAssignment $assignment): array {
                    $attempt = $assignment->admissionAttempt;

                    return [
                        'id' => (int) $assignment->id,
                        'admission_attempt_id' => (int) $assignment->admission_attempt_id,
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
     * @return array{
     *     session: array<string, mixed>,
     *     assigned: Collection<int, array<string, mixed>>,
     *     available: Collection<int, array<string, mixed>>,
     *     reassign_targets: list<array<string, mixed>>,
     *     publish_block_reason: ?string,
     * }
     */
    public function forShow(ExamSession $session): array
    {
        $session->loadMissing([
            'room:id,code,name,capacity',
            'academicCycle:id,institution_id,school_year,semester,name,is_active',
            'bookletVersion.booklet:id,name',
            'bookletVersion.templateVersion.template:id,name',
            'seatingArrangement',
            'currentLogisticsPlan.arrangement',
            'currentMaterialSnapshot',
            'assignments' => fn ($q) => $q->active()->with([
                'admissionAttempt.identity',
                'activeSeatAllocation.sessionSeat',
                'activeLogisticsSeatAllocation.seat',
            ]),
        ]);

        $available = AdmissionAttempt::query()->forPracticeSet($session->practice_fixture_set_id)
            ->with('identity')
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->whereDoesntHave('examAssignments', fn ($q) => $q->blockingScheduling())
            ->orderBy('accepted_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(500)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => $attempt->id,
                'reference_number' => $attempt->reference_code,
                'name' => $this->presenter->attemptName($attempt),
                'sex' => $attempt->identity?->sex,
                'email' => $attempt->contact_email,
            ]);

        $logisticsPlan = $session->currentLogisticsPlan;
        $snapshot = $session->hasLogisticsGeneration() ? null : $session->currentMaterialSnapshot;
        $hasPhysicalSeats = $logisticsPlan !== null
            ? $logisticsPlan->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
            : $snapshot?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING;

        $assigned = $session->assignments->map(fn (ExamAssignment $assignment) => [
            'id' => $assignment->id,
            'reference_number' => $assignment->admissionAttempt?->reference_code,
            'name' => $this->presenter->attemptName($assignment->admissionAttempt),
            'sex' => $assignment->admissionAttempt?->identity?->sex,
            'source' => $assignment->source,
            'seat_label' => $hasPhysicalSeats
                ? ($logisticsPlan !== null
                    ? $assignment->activeLogisticsSeatAllocation?->seat?->seat_label
                    : $assignment->activeSeatAllocation?->sessionSeat?->seat_label)
                : null,
        ]);

        $publishBlock = $this->presenter->publishBlockReason($session);

        $reassignTargets = ExamSession::query()->forPracticeSet($session->practice_fixture_set_id)
            ->with(['room:id,code,name,capacity', 'seatingArrangement', 'currentLogisticsPlan', 'currentMaterialSnapshot'])
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->where('id', '!=', $session->id)
            ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
            ->orderBy('starts_at')
            ->get()
            ->map(function (ExamSession $target): array {
                $assignedCount = ExamAssignment::query()->active()->where('exam_session_id', $target->id)->count();
                $capacity = $this->assignmentInvariant->capacityLimit($target);
                $remaining = $this->assignmentInvariant->remainingCapacity($target);

                return [
                    'id' => $target->id,
                    'code' => $target->code,
                    'status' => $target->status,
                    'date' => $target->starts_at?->toDateString(),
                    'start_time' => $target->starts_at?->format('H:i'),
                    'end_time' => $target->effective_end_at?->format('H:i'),
                    'room' => $target->room?->name ?: $target->room?->code,
                    'capacity' => $capacity,
                    'assigned_count' => $assignedCount,
                    'remaining' => $remaining,
                    'full' => $remaining <= 0,
                ];
            })
            ->values()
            ->all();

        return [
            'session' => $this->presenter->detailPayload($session),
            'assigned' => $assigned,
            'available' => $available,
            'reassign_targets' => $reassignTargets,
            'publish_block_reason' => $publishBlock,
        ];
    }
}
