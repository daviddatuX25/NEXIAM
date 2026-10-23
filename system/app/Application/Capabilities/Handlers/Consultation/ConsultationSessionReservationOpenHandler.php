<?php

namespace App\Application\Capabilities\Handlers\Consultation;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Consultation\Actions\OpenConsultationSessionReservation;
use App\Consultation\Support\ConsultationReservationEvaluator;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ConsultationSessionReservationOpenHandler
{
    private const CAPABILITY_ID = 'consultation.session.reservation.open@1';

    public function __construct(
        private readonly OpenConsultationSessionReservation $action,
        private readonly ConsultationReservationEvaluator $evaluator,
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

        $sessionId = $this->requiredInteger($input['session_id'] ?? null, 'session_id');
        $expectedVersion = $this->requiredInteger($input['expected_version'] ?? null, 'expected_version');
        $reason = $input['reason'] ?? null;
        if ($reason !== null && ! is_string($reason)) {
            throw new RuntimeException('reason_invalid');
        }
        $reason = is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
        $execute = (bool) ($input['execute'] ?? false);

        $session = ConsultationSession::query()->with(['academicCycle', 'room'])->find($sessionId);
        if (! $session instanceof ConsultationSession) {
            throw new RuntimeException('session_not_found');
        }

        if ($actor instanceof User) {
            $actorInstitutionId = $actor->actorScope()->institutionId;
            $sessionInstitutionId = $session->academicCycle?->institution_id;
            if ($actorInstitutionId !== null && $sessionInstitutionId !== null
                && (int) $actorInstitutionId !== (int) $sessionInstitutionId) {
                return ['mode' => 'denied', 'reason' => 'institution_scope_mismatch'];
            }
        }

        $preflight = $this->evaluator->evaluate($session, $expectedVersion);
        if (! $execute) {
            $command = [
                'session_id' => (int) $session->id,
                'expected_version' => $expectedVersion,
                'reason' => $reason,
            ];

            $roomContext = $session->room ? ["Room: {$session->room->name}"] : [];
            $checks = array_map(static function (array $c): array {
                return [
                    'key' => (string) ($c['key'] ?? 'check'),
                    'label' => (string) ($c['label'] ?? 'Check'),
                    'status' => in_array($c['status'] ?? null, ['pass', 'fail', 'warn', 'noop', 'unknown'], true) ? $c['status'] : 'unknown',
                    'detail' => isset($c['detail']) ? (string) $c['detail'] : (isset($c['note']) ? (string) $c['note'] : null),
                ];
            }, $preflight->checks);

            $result = [
                'mode' => 'propose',
                'ok' => $preflight->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'session_id' => $session->id,
                    'code' => $session->code,
                    'expected_version' => $expectedVersion,
                    'action' => 'open_reservation',
                    'reason' => $reason,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Open consultation reservations',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session', 'Consultation session', [
                            ProposalViewContract::field(
                                'session',
                                'Session',
                                ['type' => 'record', 'label' => (string) $session->code, 'context' => $roomContext],
                                ['type' => 'record', 'label' => (string) $session->code, 'context' => $roomContext],
                                'context',
                            ),
                            ProposalViewContract::field(
                                'booking_mode',
                                'Booking mode',
                                ['type' => 'enum', 'code' => $session->booking_mode, 'label' => ucfirst(str_replace('_', ' ', $session->booking_mode))],
                                ['type' => 'enum', 'code' => ConsultationSession::BOOKING_OPEN, 'label' => 'Open'],
                                'effect',
                            ),
                            ProposalViewContract::field(
                                'reason',
                                'Reason',
                                ['type' => 'null'],
                                $reason !== null
                                    ? ['type' => 'text', 'value' => $reason, 'multiline' => true]
                                    : ['type' => 'null'],
                                'choice',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($preflight->outcome, $checks),
                    'coverage' => [
                        'session_id' => ['classification' => 'display', 'field' => 'session'],
                        'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
                        'reason' => ['classification' => 'display', 'field' => 'reason'],
                    ],
                ],
                'diff' => $preflight->diff,
                'evidence' => [
                    'outcome' => $preflight->outcome,
                    'checks' => $checks,
                ],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $command = ['expected_version' => $expectedVersion, 'reason' => $reason];
        $target = ['session_id' => (int) $session->id];
        if ($executionIdentity instanceof ActionExecutionIdentity) {
            return DB::transaction(function () use ($executionIdentity, $actor, $session, $expectedVersion, $reason, $command, $target): array {
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

                $updated = $this->action->handle($session, $actor, $expectedVersion, $reason);
                $safeResult = [
                    'ok' => true,
                    'session_id' => $updated->id,
                    'booking_mode' => $updated->booking_mode,
                    'version' => $updated->version,
                    'status' => $updated->status,
                ];
                $this->receipts->complete($claim, $safeResult);

                return ['mode' => 'execute', ...$safeResult];
            });
        }

        $updated = $this->action->handle($session, $actor, $expectedVersion, $reason);

        return [
            'mode' => 'execute',
            'ok' => true,
            'session_id' => $updated->id,
            'booking_mode' => $updated->booking_mode,
            'version' => $updated->version,
            'status' => $updated->status,
        ];
    }

    private function requiredInteger(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new RuntimeException($field.'_invalid');
    }
}
