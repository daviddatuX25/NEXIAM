<?php

namespace App\Examination\Support;

use App\Foundation\SeatingArrangementEligibility;
use App\Foundation\SeatingArrangementPreview;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionLogisticsSeat;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class FreezeExamSessionLogisticsPlan
{
    public function __construct(
        private readonly SeatingArrangementEligibility $arrangementEligibility,
        private readonly SeatingArrangementPreview $seatingPreview,
    ) {}

    /** @param Collection<int, ExamAssignment> $assignments */
    public function handle(ExamSession $session, Room $room, Collection $assignments, User $actor): ExamSessionLogisticsPlan
    {
        if (! in_array($session->seating_mode, ExamSession::SEATING_MODES, true)) {
            throw new RuntimeException('Choose simple capacity or assigned seating before publishing.');
        }

        $mode = $session->seating_mode;
        $arrangement = null;
        $desks = [];
        $capacity = max(0, (int) $room->capacity);

        if ($mode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY) {
            if ($session->seating_arrangement_id !== null) {
                throw new RuntimeException('Simple-capacity sessions cannot publish with a seating arrangement.');
            }
        } else {
            if ($session->seating_arrangement_id === null) {
                throw new RuntimeException('An active seating arrangement is required.');
            }
            $arrangement = SeatingArrangement::query()->lockForUpdate()->find($session->seating_arrangement_id);
            if ($arrangement === null) {
                throw new RuntimeException('The selected seating arrangement is unavailable.');
            }
            try {
                $this->arrangementEligibility->assertEligible($room, $arrangement);
            } catch (ValidationException $exception) {
                throw new RuntimeException((string) collect($exception->errors())->flatten()->first(), previous: $exception);
            }
            $desks = $this->seatingPreview->forArrangement($arrangement);
            $capacity = min($capacity, count($desks));
            $desks = array_slice($desks, 0, $capacity);
        }

        if ($capacity < 1 || $assignments->count() > $capacity) {
            throw new RuntimeException('The session roster exceeds the frozen room or seating capacity.');
        }
        if ($mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING && count($desks) !== $capacity) {
            throw new LogicException('Frozen physical seat count differs from the assigned-seating capacity.');
        }

        $publishedAt = now()->floorSecond();
        $revision = ((int) ExamSessionLogisticsPlan::query()
            ->where('exam_session_id', $session->id)
            ->orderByDesc('revision')->lockForUpdate()->value('revision')) + 1;
        ExamSessionLogisticsPlan::query()
            ->where('exam_session_id', $session->id)->where('is_current', true)
            ->update(['is_current' => false, 'updated_at' => $publishedAt]);

        $manifest = array_map(static fn (array $desk): array => [
            'ordinal' => (int) $desk['index'],
            'row_number' => (int) $desk['row'],
            'column_number' => (int) $desk['column'],
            'seat_label' => (string) $desk['label'],
        ], $desks);
        $plan = ExamSessionLogisticsPlan::query()->create([
            'exam_session_id' => $session->id,
            'revision' => $revision,
            'capacity_limit' => $capacity,
            'seating_mode' => $mode,
            'seating_arrangement_id' => $arrangement?->id,
            'arrangement_lock_version' => $arrangement?->lock_version,
            'arrangement_rows' => $arrangement?->rows,
            'arrangement_columns' => $arrangement?->columns,
            'arrangement_booklet_pattern' => $arrangement?->booklet_pattern,
            'seat_manifest_hash' => $arrangement === null ? null : hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
            'is_current' => true,
            'published_at' => null,
        ]);

        $seats = collect();
        if ($arrangement !== null) {
            $seats = collect($manifest)->map(fn (array $seat): ExamSessionLogisticsSeat => $plan->seats()->create($seat));
        }
        $plan->update(['published_at' => $publishedAt]);

        if ($arrangement !== null) {
            foreach ($assignments->values() as $index => $assignment) {
                $seat = $seats->get($index);
                ExamAssignmentLogisticsSeatAllocation::query()->create([
                    'exam_assignment_id' => $assignment->id,
                    'exam_session_id' => $session->id,
                    'logistics_plan_id' => $plan->id,
                    'logistics_seat_id' => $seat->id,
                    'effective_from' => $publishedAt,
                    'reason' => 'session_publication',
                    'assigned_by_user_id' => $actor->id,
                ]);
            }
        }

        return $plan;
    }
}
