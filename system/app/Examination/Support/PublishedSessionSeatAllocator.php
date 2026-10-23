<?php

namespace App\Examination\Support;

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionLogisticsSeat;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class PublishedSessionSeatAllocator
{
    public function allocateIfPublished(ExamSession $session, ExamAssignment $assignment, User $actor, string $reason): void
    {
        if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
            return;
        }

        $plan = ExamSessionLogisticsPlan::query()
            ->where('exam_session_id', $session->id)->where('is_current', true)
            ->lockForUpdate()->first();
        if ($plan !== null) {
            if ($plan->seating_mode !== ExamSession::SEATING_MODE_ASSIGNED_SEATING) {
                return;
            }
            $allocation = ExamAssignmentLogisticsSeatAllocation::query()
                ->where('exam_assignment_id', $assignment->id)->whereNull('effective_to')->lockForUpdate()->first();
            if ($allocation !== null && (int) $allocation->logistics_plan_id === (int) $plan->id) {
                return;
            }
            $effectiveAt = now()->floorSecond();
            if ($allocation !== null) {
                $effectiveAt = $this->closeAllocation($allocation, $effectiveAt);
            }
            $seat = ExamSessionLogisticsSeat::query()
                ->where('logistics_plan_id', $plan->id)
                ->whereDoesntHave('activeAllocation')
                ->orderBy('ordinal')->lockForUpdate()->first();
            if ($seat === null) {
                throw new RuntimeException('Published exam session has no unoccupied frozen physical seat.');
            }
            ExamAssignmentLogisticsSeatAllocation::query()->create([
                'exam_assignment_id' => $assignment->id,
                'exam_session_id' => $session->id,
                'logistics_plan_id' => $plan->id,
                'logistics_seat_id' => $seat->id,
                'effective_from' => $effectiveAt,
                'reason' => $reason,
                'assigned_by_user_id' => $actor->id,
            ]);

            return;
        }

        if ($session->hasLogisticsGeneration()) {
            throw new RuntimeException('Published exam session has no current logistics plan.');
        }
        // Retained historical sessions continue using their material-owned seat generation.
        $snapshot = ExamSessionMaterialSnapshot::query()
            ->where('exam_session_id', $session->id)->where('is_current', true)->lockForUpdate()->first();
        if ($snapshot === null) {
            throw new RuntimeException('Published exam session has no current logistics plan.');
        }
        $legacyAllocation = ExamAssignmentSeatAllocation::query()
            ->where('exam_assignment_id', $assignment->id)->whereNull('effective_to')->lockForUpdate()->first();
        if ($legacyAllocation !== null && $legacyAllocation->sessionSeat()->where('material_snapshot_id', $snapshot->id)->exists()) {
            return;
        }
        $effectiveAt = now()->floorSecond();
        if ($legacyAllocation !== null) {
            $effectiveAt = $this->closeAllocation($legacyAllocation, $effectiveAt);
        }
        $seat = ExamSessionSeat::query()
            ->where('material_snapshot_id', $snapshot->id)->doesntHave('activeAllocation')
            ->orderBy('ordinal')->lockForUpdate()->first();
        if ($seat === null) {
            throw new RuntimeException('Published exam session has no unoccupied historical manifest slot.');
        }
        ExamAssignmentSeatAllocation::query()->create([
            'exam_assignment_id' => $assignment->id,
            'exam_session_seat_id' => $seat->id,
            'effective_from' => $effectiveAt,
            'reason' => $reason,
            'assigned_by_user_id' => $actor->id,
        ]);
    }

    private function closeAllocation(ExamAssignmentLogisticsSeatAllocation|ExamAssignmentSeatAllocation $allocation, Carbon $effectiveAt): Carbon
    {
        $from = $allocation->effective_from?->copy()->floorSecond();
        $closedAt = $from !== null && $from->gte($effectiveAt) ? $from->copy()->addSecond() : $effectiveAt;
        $allocation->update(['effective_to' => $closedAt]);

        return $closedAt;
    }
}
