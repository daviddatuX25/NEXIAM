<?php

namespace App\Application\Capabilities\Handlers\Consultation;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Consultation\Actions\CancelConsultationSession;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class ConsultationSessionCancelHandler
{
    private const CAPABILITY_ID = 'consultation.session.cancel@1';

    public function __construct(private readonly CancelConsultationSession $action) {}

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
        if (! is_string($reason) || trim($reason) === '') {
            throw new RuntimeException('reason_required');
        }
        $reason = trim($reason);
        $session = ConsultationSession::query()->find($sessionId);
        if (! $session instanceof ConsultationSession) {
            throw new RuntimeException('session_not_found');
        }
        if ($session->version !== $expectedVersion) {
            return ['mode' => 'denied', 'reason' => 'stale_consultation_session_version'];
        }
        if (! in_array($session->status, [ConsultationSession::STATUS_DRAFT, ConsultationSession::STATUS_PUBLISHED], true)) {
            return ['mode' => 'denied', 'reason' => 'consultation_session_not_cancellable'];
        }

        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'session_id' => (int) $session->id,
                'expected_version' => $expectedVersion,
                'reason' => $reason,
            ];

            $session->loadMissing(['room']);
            $roomContext = $session->room ? ["Room: {$session->room->name}"] : [];

            $checks = [
                [
                    'key' => 'version_matches',
                    'label' => 'Session version',
                    'status' => 'pass',
                    'detail' => (string) $session->version,
                ],
                [
                    'key' => 'status_cancellable',
                    'label' => 'Cancellable status',
                    'status' => 'pass',
                    'detail' => 'Session is in draft or published status',
                ],
                [
                    'key' => 'reason_provided',
                    'label' => 'Cancellation reason',
                    'status' => 'pass',
                    'detail' => $reason,
                ],
            ];

            $diff = [[
                'key' => 'status',
                'label' => 'Session status',
                'before' => $session->status,
                'after' => ConsultationSession::STATUS_CANCELLED,
            ]];

            $result = [
                'mode' => 'propose',
                'ok' => true,
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'session_id' => $session->id,
                    'code' => $session->code,
                    'expected_version' => $expectedVersion,
                    'action' => 'cancel',
                    'reason' => $reason,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Cancel consultation session',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session', 'Consultation session', [
                            ProposalViewContract::field(
                                'session',
                                'Consultation session',
                                ['type' => 'record', 'label' => (string) $session->code, 'context' => $roomContext],
                                ['type' => 'record', 'label' => (string) $session->code, 'context' => $roomContext],
                                'context',
                            ),
                            ProposalViewContract::field(
                                'status',
                                'Session status',
                                ['type' => 'enum', 'code' => $session->status, 'label' => ucfirst(str_replace('_', ' ', $session->status))],
                                ['type' => 'enum', 'code' => ConsultationSession::STATUS_CANCELLED, 'label' => 'Cancelled'],
                                'effect',
                            ),
                            ProposalViewContract::field(
                                'reason',
                                'Cancellation reason',
                                ['type' => 'null'],
                                ['type' => 'text', 'value' => $reason, 'multiline' => true],
                                'choice',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight('pass', $checks),
                    'coverage' => [
                        'session_id' => ['classification' => 'display', 'field' => 'session'],
                        'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
                        'reason' => ['classification' => 'display', 'field' => 'reason'],
                    ],
                ],
                'diff' => $diff,
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => $checks,
                ],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $cancelled = $this->action->handle($session, $actor, $expectedVersion, $reason);

        return [
            'mode' => 'execute',
            'ok' => true,
            'session_id' => $cancelled->id,
            'version' => $cancelled->version,
            'status' => $cancelled->status,
        ];
    }

    private function requiredInteger(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new RuntimeException($field.'_invalid');
    }
}
