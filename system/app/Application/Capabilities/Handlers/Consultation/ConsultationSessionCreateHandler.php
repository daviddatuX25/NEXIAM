<?php

namespace App\Application\Capabilities\Handlers\Consultation;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Consultation\Actions\CreateConsultationSession;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ConsultationSessionCreateHandler
{
    private const CAPABILITY_ID = 'consultation.session.create@1';

    public function __construct(
        private readonly CreateConsultationSession $action,
        private readonly ActionExecutionReceiptStore $receipts,
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
        $room = $this->resolveRoom($input['room_id'] ?? null, (int) $institutionId);
        $scheduledAt = $this->parseScheduledAt($input['scheduled_at'] ?? null);

        $code = $input['code'] ?? null;
        if ($code !== null && ! is_string($code)) {
            throw new RuntimeException('consultation_session_code_invalid');
        }
        $code = is_string($code) && trim($code) !== ''
            ? trim($code)
            : 'CONS-'.strtoupper(Str::slug($room->name)).'-'.CarbonImmutable::parse($scheduledAt)->format('Ymd-Hi');

        $bookingMode = $input['booking_mode'] ?? ConsultationSession::BOOKING_STAFF_ONLY;
        if (! is_string($bookingMode) || ! in_array($bookingMode, ConsultationSession::BOOKING_MODES, true)) {
            throw new RuntimeException('consultation_booking_mode_invalid');
        }

        $execute = (bool) ($input['execute'] ?? false);
        $command = [
            'academic_cycle_id' => (int) $cycle->id,
            'room_id' => (int) $room->id,
            'scheduled_at' => $scheduledAt,
            'code' => $code,
            'booking_mode' => $bookingMode,
        ];
        $target = ['academic_cycle_id' => (int) $cycle->id, 'room_id' => (int) $room->id];

        if ($execute && $executionIdentity instanceof ActionExecutionIdentity) {
            return DB::transaction(function () use ($executionIdentity, $actor, $target, $command, $cycle, $room, $code, $scheduledAt, $bookingMode): array {
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

                $session = $this->action->handle($cycle, $room, $code, $scheduledAt, $bookingMode, $actor);
                $safeResult = [
                    'ok' => true,
                    'session_id' => $session->id,
                    'code' => $session->code,
                    'status' => $session->status,
                ];
                $this->receipts->complete($claim, $safeResult);

                return ['mode' => 'execute', ...$safeResult];
            });
        }

        if ($execute) {
            $session = $this->action->handle($cycle, $room, $code, $scheduledAt, $bookingMode, $actor);

            return [
                'mode' => 'execute',
                'ok' => true,
                'session_id' => $session->id,
                'code' => $session->code,
                'status' => $session->status,
            ];
        }

        $checks = [
            ['key' => 'room_assigned', 'label' => 'Room', 'status' => 'pass', 'detail' => $room->name],
            ['key' => 'scheduled_at_valid', 'label' => 'Appointment time', 'status' => 'pass', 'detail' => $scheduledAt],
            ['key' => 'academic_cycle', 'label' => 'Academic cycle', 'status' => 'pass', 'detail' => $cycle->code],
        ];

        $result = [
            'mode' => 'propose',
            'ok' => true,
            'capability_id' => self::CAPABILITY_ID,
            'command_input' => $command,
            'impact' => [
                'session_id' => null,
                'code' => $code,
                'room_name' => $room->name,
                'scheduled_at' => $scheduledAt,
                'booking_mode' => $bookingMode,
                'academic_cycle_id' => $cycle->id,
                'expected_version' => 1,
            ],
            'proposal_review' => [
                'version' => 1,
                'operation' => 'create',
                'title' => 'Create consultation session',
                'sections' => [
                    ProposalViewContract::fieldsSection('session', 'Consultation session', [
                        ProposalViewContract::field(
                            'cycle',
                            'Academic cycle',
                            ['type' => 'missing'],
                            ['type' => 'record', 'label' => (string) $cycle->code, 'context' => array_values(array_filter([(string) $cycle->staffLabel()]))],
                            'context',
                        ),
                        ProposalViewContract::field(
                            'room',
                            'Room',
                            ['type' => 'missing'],
                            ['type' => 'record', 'label' => (string) $room->name, 'context' => ["Capacity: {$room->capacity} seats"]],
                            'context',
                        ),
                        ProposalViewContract::field(
                            'code',
                            'Session code',
                            ['type' => 'missing'],
                            ['type' => 'code', 'value' => $code],
                            'choice',
                        ),
                        ProposalViewContract::field(
                            'scheduled_at',
                            'Appointment time',
                            ['type' => 'missing'],
                            ['type' => 'instant', 'value' => $scheduledAt, 'precision' => 'second', 'display_zone' => 'Asia/Manila'],
                            'effect',
                        ),
                        ProposalViewContract::field(
                            'booking_mode',
                            'Booking mode',
                            ['type' => 'missing'],
                            ['type' => 'enum', 'code' => $bookingMode, 'label' => ucfirst(str_replace('_', ' ', $bookingMode))],
                            'choice',
                        ),
                        ProposalViewContract::field(
                            'status',
                            'Initial status',
                            ['type' => 'missing'],
                            ['type' => 'enum', 'code' => ConsultationSession::STATUS_DRAFT, 'label' => 'Draft'],
                            'effect',
                        ),
                    ]),
                ],
                'preflight' => ProposalViewContract::preflight('pass', $checks),
                'coverage' => [
                    'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                    'room_id' => ['classification' => 'display', 'field' => 'room'],
                    'scheduled_at' => ['classification' => 'display', 'field' => 'scheduled_at'],
                    'code' => ['classification' => 'display', 'field' => 'code'],
                    'booking_mode' => ['classification' => 'display', 'field' => 'booking_mode'],
                ],
            ],
            'diff' => [
                ['key' => 'session', 'label' => 'Session', 'before' => 'None', 'after' => $code],
                ['key' => 'room', 'label' => 'Room', 'before' => 'None', 'after' => $room->name],
                ['key' => 'scheduled_at', 'label' => 'Appointment time', 'before' => 'None', 'after' => $scheduledAt],
                ['key' => 'booking_mode', 'label' => 'Booking mode', 'before' => 'None', 'after' => $bookingMode],
            ],
            'evidence' => [
                'outcome' => 'pass',
                'checks' => $checks,
            ],
        ];

        ProposalReview::fromResult(self::CAPABILITY_ID, $result);

        return $result;
    }

    private function resolveRoom(mixed $roomReference, int $institutionId): Room
    {
        if ((! is_int($roomReference) && ! is_string($roomReference))
            || trim((string) $roomReference) === '') {
            throw new RuntimeException('room_id_invalid');
        }

        $query = Room::query()->where('institution_id', $institutionId);
        $room = null;
        if (is_int($roomReference) || ctype_digit((string) $roomReference)) {
            $room = (clone $query)->whereKey((int) $roomReference)->first();
        }

        if ($room === null) {
            $reference = trim((string) $roomReference);
            $room = (clone $query)->where('code', $reference)->first()
                ?? (clone $query)->whereRaw('LOWER(code) = ?', [mb_strtolower($reference)])->first()
                ?? (clone $query)->where('name', $reference)->first()
                ?? (clone $query)->whereRaw('LOWER(name) = ?', [mb_strtolower($reference)])->first();
        }

        return $room ?? throw new RuntimeException('room_not_found');
    }

    private function resolveCycle(mixed $cycleReference, int $institutionId): AcademicCycle
    {
        if ($cycleReference !== null && ! is_int($cycleReference) && ! is_string($cycleReference)) {
            throw new RuntimeException('academic_cycle_invalid');
        }
        $query = AcademicCycle::query()->where('institution_id', $institutionId);
        if ($cycleReference === null || (is_string($cycleReference) && trim($cycleReference) === '')) {
            return $query->where('is_active', true)->first()
                ?? throw new RuntimeException('academic_cycle_not_found');
        }

        $reference = trim((string) $cycleReference);
        $cycle = (is_int($cycleReference) || ctype_digit($reference))
            ? (clone $query)->whereKey((int) $reference)->first()
            : null;
        $cycle ??= (clone $query)->where('code', $reference)->first()
            ?? (clone $query)->whereRaw('LOWER(code) = ?', [mb_strtolower($reference)])->first();

        return $cycle ?? throw new RuntimeException('academic_cycle_not_found');
    }

    private function parseScheduledAt(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('scheduled_at_invalid');
        }

        try {
            return CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            throw new RuntimeException('scheduled_at_invalid');
        }
    }
}
