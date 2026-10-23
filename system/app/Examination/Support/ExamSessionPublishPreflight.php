<?php

namespace App\Examination\Support;

use App\Foundation\SeatingArrangementEligibility;
use App\Foundation\SeatingArrangementPreview;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

use function count;
use function hash;
use function json_encode;

/** Shared operational readiness and frozen-logistics fingerprint for publication. */
final class ExamSessionPublishPreflight
{
    public function __construct(
        private readonly SeatingArrangementEligibility $arrangementEligibility,
        private readonly SeatingArrangementPreview $seatingPreview,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     code: string,
     *     reason: ?string,
     *     checks: list<string>,
     *     assigned_count: int,
     *     seating_mode: ?string,
     *     capacity_limit: ?int,
     *     desk_count: ?int,
     *     required_set_codes: list<string>,
     *     roster_material_fingerprint: ?string,
     * }
     */
    public function evaluate(ExamSession $session, ?int $assignedCountOverride = null): array
    {
        $checks = [];
        $assignedCount = $assignedCountOverride ?? (int) ($session->assigned_count ?? 0);
        if ($session->status !== ExamSession::STATUS_DRAFT) {
            return $this->fail('only_draft_may_publish', 'Only draft sessions may be published.', $checks, $assignedCount);
        }
        $checks[] = 'draft';

        $room = $session->relationLoaded('room') ? $session->room : $session->room()->first();
        if ($room === null) {
            return $this->fail('room_required', 'Room is required before publish.', $checks, $assignedCount);
        }
        if (! $room->is_active || ! $room->isExamRoom()) {
            return $this->fail('room_invalid', 'Select an active examination room before publish.', $checks, $assignedCount);
        }
        $checks[] = 'room_active';

        if ($session->starts_at === null || $session->effective_end_at === null) {
            return $this->fail('schedule_required', 'Schedule date and time are required.', $checks, $assignedCount);
        }
        if ($session->effective_end_at->lessThanOrEqualTo($session->starts_at)) {
            return $this->fail('schedule_invalid', 'Session end time must be after the start time.', $checks, $assignedCount);
        }
        $checks[] = 'schedule_valid';

        $assignments = ExamAssignment::query()
            ->active()
            ->where('exam_session_id', $session->id)
            ->orderBy('admission_attempt_id')->orderBy('id')
            ->get(['id', 'admission_attempt_id', 'version']);
        $assignedCount = $assignments->count();
        if ($assignedCount < 1) {
            return $this->fail('assign_required_before_publish', 'Assign at least one applicant before publishing.', $checks, 0);
        }
        $checks[] = 'has_assignments';

        $mode = $session->seating_mode;
        if (! in_array($mode, ExamSession::SEATING_MODES, true)) {
            return $this->fail('seating_mode_unresolved', 'Choose simple capacity or assigned seating before publishing.', $checks, $assignedCount, seatingMode: $mode);
        }
        $checks[] = 'seating_mode_resolved';
        $institution = Institution::current();
        $arrangement = null;
        $capacity = max(0, (int) $room->capacity);
        if ($mode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY) {
            if ($session->seating_arrangement_id !== null) {
                return $this->fail('simple_mode_arrangement_conflict', 'Simple-capacity sessions cannot publish with a seating arrangement.', $checks, $assignedCount, seatingMode: $mode);
            }
        } else {
            if (! $institution->usesSeatingArrangements()) {
                return $this->fail('assigned_seating_unavailable', 'Assigned seating is not available for new session publication.', $checks, $assignedCount, seatingMode: $mode);
            }
            if ($session->seating_arrangement_id === null) {
                return $this->fail('seating_arrangement_missing', 'An active seating arrangement is required.', $checks, $assignedCount, seatingMode: $mode);
            }
            $arrangement = SeatingArrangement::query()->find($session->seating_arrangement_id);
            if ($arrangement === null) {
                return $this->fail('seating_arrangement_missing', 'The selected seating arrangement is unavailable.', $checks, $assignedCount, seatingMode: $mode);
            }
            try {
                $this->arrangementEligibility->assertEligible($room, $arrangement);
            } catch (ValidationException $exception) {
                $reason = (string) collect($exception->errors())->flatten()->first();

                return $this->fail('seating_arrangement_incompatible', $reason !== '' ? $reason : 'Select a compatible active seating arrangement.', $checks, $assignedCount, seatingMode: $mode);
            }
            $checks[] = 'seating_arrangement_compatible';
            $capacity = min($capacity, count($this->seatingPreview->forArrangement($arrangement)));
        }

        if ($capacity < 1) {
            return $this->fail('room_capacity_invalid', 'The room or arrangement must have positive capacity before publishing.', $checks, $assignedCount, 0, seatingMode: $mode);
        }
        $checks[] = $mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING ? 'roster_within_desk_capacity' : 'roster_within_room_capacity';
        if ($assignedCount > $capacity) {
            return $this->fail('roster_exceeds_capacity', 'The session roster exceeds the available room or seating capacity.', $checks, $assignedCount, $capacity, seatingMode: $mode);
        }

        return [
            'ok' => true,
            'code' => 'ready',
            'reason' => null,
            'checks' => $checks,
            'assigned_count' => $assignedCount,
            'seating_mode' => $mode,
            'capacity_limit' => $capacity,
            'desk_count' => $capacity,
            'required_set_codes' => [],
            'roster_material_fingerprint' => $this->fingerprint($session, $assignments, $room, $arrangement, $capacity, $institution->examFeaturePolicyVersion()),
        ];
    }

    public function blockReason(ExamSession $session): ?string
    {
        return $this->evaluate($session)['reason'];
    }

    public function isPublishable(ExamSession $session): bool
    {
        return $this->evaluate($session)['ok'];
    }

    /** @param Collection<int, ExamAssignment> $assignments */
    private function fingerprint(ExamSession $session, Collection $assignments, Room $room, ?SeatingArrangement $arrangement, int $capacity, int $policyVersion): string
    {
        return hash('sha256', json_encode([
            'fingerprint_version' => 4,
            'exam_session_id' => (int) $session->id,
            'room_id' => (int) $room->id,
            'room_capacity' => (int) $room->capacity,
            'starts_at' => $session->starts_at?->toIso8601String(),
            'effective_end_at' => $session->effective_end_at?->toIso8601String(),
            'seating_mode' => $session->seating_mode,
            'capacity_limit' => $capacity,
            'arrangement' => $arrangement === null ? null : [
                'id' => (int) $arrangement->id,
                'lock_version' => (int) $arrangement->lock_version,
                'rows' => (int) $arrangement->rows,
                'columns' => (int) $arrangement->columns,
                'pattern' => (string) $arrangement->booklet_pattern,
            ],
            'policy_revision' => $policyVersion,
            'assignments' => $assignments->map(static fn (ExamAssignment $assignment): array => [
                'id' => (int) $assignment->id,
                'admission_attempt_id' => (int) $assignment->admission_attempt_id,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<string>  $checks
     * @return array{ok: bool, code: string, reason: ?string, checks: list<string>, assigned_count: int, seating_mode: ?string, capacity_limit: ?int, desk_count: ?int, required_set_codes: list<string>, roster_material_fingerprint: ?string}
     */
    private function fail(string $code, string $reason, array $checks, int $assignedCount, ?int $capacity = null, array $requiredSetCodes = [], ?string $seatingMode = null): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'reason' => $reason,
            'checks' => $checks,
            'assigned_count' => $assignedCount,
            'seating_mode' => $seatingMode,
            'capacity_limit' => $capacity,
            'desk_count' => $capacity,
            'required_set_codes' => $requiredSetCodes,
            'roster_material_fingerprint' => null,
        ];
    }
}
