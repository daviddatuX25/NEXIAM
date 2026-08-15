<?php

namespace App\Examination\Queries;

use App\Examination\Support\ExamSessionPresenter;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;

/**
 * Scheduling detail: the session show page assembly — roster, available
 * applicants, reassign targets, and publishability.
 */
class ExamSessionDetailQuery
{
    public function __construct(private readonly ExamSessionPresenter $presenter)
    {
    }

    /**
     * @return array{
     *     session: array<string, mixed>,
     *     assigned: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     available: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     reassign_targets: list<array<string, mixed>>,
     *     publish_block_reason: ?string,
     * }
     */
    public function forShow(ExamSession $session): array
    {
        $session->load([
            'room:id,code,name,capacity',
            'academicCycle:id,school_year,semester,name,is_active',
            'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name,email'),
            'assignments' => fn ($q) => $q->active()->with('admissionAttempt.identity'),
        ]);

        $activeAttemptIds = ExamAssignment::query()->active()->pluck('admission_attempt_id');

        $available = AdmissionAttempt::query()
            ->with('identity')
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->whereNotIn('id', $activeAttemptIds)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => $attempt->id,
                'reference_number' => $attempt->reference_code,
                'name' => $this->presenter->attemptName($attempt),
                'email' => $attempt->contact_email,
            ]);

        $assigned = $session->assignments->map(fn (ExamAssignment $assignment) => [
            'id' => $assignment->id,
            'reference_number' => $assignment->admissionAttempt?->reference_code,
            'name' => $this->presenter->attemptName($assignment->admissionAttempt),
            'source' => $assignment->source,
        ]);

        $publishBlock = $this->presenter->publishBlockReason($session);

        $reassignTargets = ExamSession::query()
            ->with('room:id,code,name,capacity')
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->where('id', '!=', $session->id)
            ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
            ->orderBy('starts_at')
            ->get()
            ->map(function (ExamSession $target) {
                $assignedCount = ExamAssignment::query()->active()->where('exam_session_id', $target->id)->count();
                $capacity = (int) ($target->room?->capacity ?? 0);

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
                    'remaining' => max(0, $capacity - $assignedCount),
                    'full' => $capacity > 0 && $assignedCount >= $capacity,
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
