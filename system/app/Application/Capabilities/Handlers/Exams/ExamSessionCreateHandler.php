<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Examination\Support\CreateExamSessionEvaluator;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ExamSessionCreateHandler
{
    private const CAPABILITY_ID = 'exams.session.create@1';

    public function __construct(
        private readonly CreateExamSession $action,
        private readonly ActionExecutionReceiptStore $receipts,
        private readonly CreateExamSessionEvaluator $evaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $executionIdentity = $input['_xiam_execution_identity'] ?? null;
        unset($input['_xiam_execution_identity']);
        if ($executionIdentity !== null && ! $executionIdentity instanceof ActionExecutionIdentity) {
            throw new RuntimeException('action_execution_identity_invalid');
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $profile = $actor->staffProfile;
        if (! $profile instanceof StaffProfile || ! $profile->isActive()) {
            throw new RuntimeException('active_staff_profile_required');
        }

        $institutionId = $profile->institution_id;
        if ($institutionId === null || (int) $actor->actorScope()->institutionId !== (int) $institutionId) {
            throw new RuntimeException('institution_scope_mismatch');
        }

        $cycle = $this->resolveCycle($input['academic_cycle_id'] ?? null, (int) $institutionId);
        $roomResult = $this->resolveRoom($input['room_id'] ?? null, (int) $institutionId);
        if (! $roomResult instanceof Room) {
            return $roomResult;
        }
        $room = $roomResult;
        $startsAt = $this->parseIsoDateTime($input['starts_at'] ?? null, 'starts_at');
        $effectiveEndAt = $this->parseIsoDateTime($input['effective_end_at'] ?? null, 'effective_end_at');
        $startsAtDate = CarbonImmutable::parse($startsAt);
        $effectiveEndAtDate = CarbonImmutable::parse($effectiveEndAt);

        $seatingMode = $input['seating_mode'] ?? 'simple_capacity';
        if (! is_string($seatingMode) || $seatingMode !== 'simple_capacity') {
            throw new RuntimeException('exam_session_seating_mode_unavailable');
        }

        $code = $input['code'] ?? null;
        if ($code !== null && ! is_string($code)) {
            throw new RuntimeException('exam_session_code_invalid');
        }
        $code = is_string($code) && trim($code) !== ''
            ? trim($code)
            : 'EX-'.strtoupper(Str::slug($room->name)).'-'.$startsAtDate->format('Ymd-Hi');

        $execute = ($input['execute'] ?? false) === true;
        $command = [
            'academic_cycle_id' => (int) $cycle->id,
            'room_id' => (int) $room->id,
            'starts_at' => $startsAt,
            'effective_end_at' => $effectiveEndAt,
            'code' => $code,
            'seating_mode' => $seatingMode,
        ];
        if ($execute && $executionIdentity instanceof ActionExecutionIdentity) {
            $replay = $this->receipts->replay(
                identity: $executionIdentity,
                actor: $actor,
                actionKey: self::CAPABILITY_ID,
                target: ['academic_cycle_id' => (int) $cycle->id, 'room_id' => (int) $room->id],
                command: $command,
            );
            if ($replay !== null) {
                return ['mode' => 'execute', ...$replay->safeResult];
            }
        }

        $evaluation = $this->evaluator->evaluate($cycle, $room, $startsAtDate, $effectiveEndAtDate, $code);
        if ($execute && $evaluation->outcome !== 'pass') {
            throw new RuntimeException('exam_session_preflight_failed');
        }
        if ($execute) {
            if ($executionIdentity instanceof ActionExecutionIdentity) {
                return DB::transaction(function () use (
                    $executionIdentity,
                    $actor,
                    $cycle,
                    $room,
                    $command,
                    $code,
                    $startsAt,
                    $effectiveEndAt,
                    $seatingMode,
                ): array {
                    $target = ['academic_cycle_id' => (int) $cycle->id, 'room_id' => (int) $room->id];
                    $replay = $this->receipts->replay(
                        identity: $executionIdentity,
                        actor: $actor,
                        actionKey: self::CAPABILITY_ID,
                        target: $target,
                        command: $command,
                    );
                    if ($replay !== null) {
                        return ['mode' => 'execute', ...$replay->safeResult];
                    }

                    $claim = $this->receipts->claim(
                        identity: $executionIdentity,
                        actor: $actor,
                        actionKey: self::CAPABILITY_ID,
                        target: $target,
                        command: $command,
                    );
                    if ($claim->replayed) {
                        return ['mode' => 'execute', ...$claim->safeResult];
                    }

                    $session = $this->action->handle(
                        cycle: $cycle,
                        room: $room,
                        code: $code,
                        startsAt: $startsAt,
                        effectiveEndAt: $effectiveEndAt,
                        actor: $actor,
                        seatingMode: $seatingMode,
                    );
                    $safeResult = [
                        'ok' => true,
                        'session_id' => $session->id,
                        'code' => $session->code,
                        'status' => $session->status,
                        'room_id' => $session->room_id,
                        'starts_at' => $startsAt,
                        'effective_end_at' => $effectiveEndAt,
                        'seating_mode' => $seatingMode,
                    ];
                    $this->receipts->complete($claim, $safeResult);

                    return ['mode' => 'execute', ...$safeResult];
                });
            }

            $session = $this->action->handle(
                cycle: $cycle,
                room: $room,
                code: $code,
                startsAt: $startsAt,
                effectiveEndAt: $effectiveEndAt,
                actor: $actor,
                seatingMode: $seatingMode,
            );

            return [
                'mode' => 'execute',
                'ok' => true,
                'session_id' => $session->id,
                'code' => $session->code,
                'status' => $session->status,
                'room_id' => $session->room_id,
                'starts_at' => $startsAt,
                'effective_end_at' => $effectiveEndAt,
                'seating_mode' => $seatingMode,
            ];

        }

        $result = [
            'mode' => 'propose',
            'ok' => true,
            'command_input' => $command,
            'impact' => [
                'session_id' => null,
                'code' => $code,
                'room_name' => $room->name,
                'room_capacity' => $room->capacity,
                'starts_at' => $startsAt,
                'effective_end_at' => $effectiveEndAt,
                'academic_cycle_id' => (int) $cycle->id,
                'seating_mode' => $seatingMode,
                'status' => 'draft_only',
                'materials' => null,
                'expected_version' => 1,
            ],
            'proposal_review' => [
                'version' => 1,
                'operation' => 'create',
                'title' => 'Create exam session',
                'sections' => [
                    ProposalViewContract::fieldsSection('session', 'Exam session', [
                        ProposalViewContract::field('cycle', 'Academic cycle', ['type' => 'missing'], ['type' => 'record', 'label' => $cycle->code, 'context' => []], 'context'),
                        ProposalViewContract::field('room', 'Room', ['type' => 'missing'], ['type' => 'record', 'label' => $room->name, 'context' => ["Capacity: {$room->capacity} seats"]], 'context'),
                        ProposalViewContract::field('code', 'Session code', ['type' => 'missing'], ['type' => 'code', 'value' => $code]),
                        ProposalViewContract::field('starts_at', 'Starts at', ['type' => 'missing'], ['type' => 'instant', 'value' => $startsAt, 'display_zone' => 'Asia/Manila', 'precision' => 'second'], 'effect'),
                        ProposalViewContract::field('effective_end_at', 'Ends at', ['type' => 'missing'], ['type' => 'instant', 'value' => $effectiveEndAt, 'display_zone' => 'Asia/Manila', 'precision' => 'second'], 'effect'),
                        ProposalViewContract::field('seating_mode', 'Seating', ['type' => 'missing'], ['type' => 'enum', 'code' => $seatingMode, 'label' => 'Simple capacity']),
                        ProposalViewContract::field('initial_status', 'Initial status', ['type' => 'missing'], ['type' => 'enum', 'code' => 'draft', 'label' => 'Draft only'], 'effect'),
                        ProposalViewContract::field('materials', 'Exam materials', ['type' => 'missing'], ['type' => 'null'], 'context'),
                    ]),
                ],
                'preflight' => ProposalViewContract::preflight($evaluation->outcome, $evaluation->checks),
                'coverage' => [
                    'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                    'room_id' => ['classification' => 'display', 'field' => 'room'],
                    'starts_at' => ['classification' => 'display', 'field' => 'starts_at'],
                    'effective_end_at' => ['classification' => 'display', 'field' => 'effective_end_at'],
                    'code' => ['classification' => 'display', 'field' => 'code'],
                    'seating_mode' => ['classification' => 'display', 'field' => 'seating_mode'],
                ],
            ],
            'diff' => $evaluation->diff,
            'evidence' => [
                'outcome' => $evaluation->outcome,
                'checks' => $evaluation->checks,
            ],
        ];
        ProposalReview::fromResult(self::CAPABILITY_ID, $result);

        return $result;
    }

    /**
     * @return Room|array{mode: string, reason: string}
     */
    private function resolveRoom(int|string|null $roomReference, int $institutionId): Room|array
    {
        if ($roomReference === null || (is_string($roomReference) && trim($roomReference) === '')) {
            return ['mode' => 'denied', 'reason' => 'room_id_invalid'];
        }

        $query = Room::query()->where('institution_id', $institutionId);

        $room = null;
        if (is_int($roomReference) || ctype_digit((string) $roomReference)) {
            $room = (clone $query)->whereKey((int) $roomReference)->first();
        }

        if ($room === null) {
            $trimmed = trim((string) $roomReference);
            // Try exact code (e.g. ITBR-106, RM-LabA)
            $room = (clone $query)->where('code', $trimmed)->first()
                ?? (clone $query)->whereRaw('LOWER(code) = ?', [strtolower($trimmed)])->first()
                ?? (clone $query)->where('name', $trimmed)->first()
                ?? (clone $query)->whereRaw('LOWER(name) = ?', [strtolower($trimmed)])->first();

            if ($room === null && str_contains($trimmed, ' ')) {
                $dashed = str_replace(' ', '-', $trimmed);
                $room = (clone $query)->whereRaw('LOWER(code) = ?', [strtolower($dashed)])->first();
            }

            if ($room === null) {
                $room = (clone $query)->where('name', 'LIKE', "%{$trimmed}%")->first()
                    ?? (clone $query)->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($trimmed).'%'])->first();
            }
        }

        if ($room === null) {
            return ['mode' => 'denied', 'reason' => 'room_not_found'];
        }

        if (! $room->isExamRoom()) {
            return ['mode' => 'denied', 'reason' => 'room_not_suitable_for_exam'];
        }

        return $room;
    }

    private function resolveCycle(int|string|null $cycleReference, int $institutionId): AcademicCycle
    {
        $query = AcademicCycle::query()->where('institution_id', $institutionId);
        $cycle = $cycleReference === null
            ? $query->where('is_active', true)->first()
            : (is_int($cycleReference) || ctype_digit($cycleReference)
                ? $query->whereKey((int) $cycleReference)->first()
                : $query->where('code', trim($cycleReference))->first());

        return $cycle ?? throw new RuntimeException('academic_cycle_not_found');
    }

    private function parseIsoDateTime(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException($field.'_invalid');
        }

        $value = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new RuntimeException($field.'_invalid');
        }

        try {
            $normalizedValue = str_ends_with($value, 'Z') ? substr($value, 0, -1).'+00:00' : $value;
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:sP', $normalizedValue);
            $errors = CarbonImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new RuntimeException($field.'_invalid');
            }
        } catch (Throwable) {
            throw new RuntimeException($field.'_invalid');
        }

        return $date->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
