<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Models\Examination\ExamSession;
use App\Models\User;
use RuntimeException;

/**
 * Propose (default) or execute open-reservation — same Action as the page.
 *
 * @phpstan-type Input array{session_id: int, expected_version: int, execute?: bool, reason?: string}
 */
final class ExamSessionReservationOpenHandler
{
    private const CAPABILITY_ID = 'exams.session.reservation.open@1';

    public function __construct(private readonly OpenExamSessionReservation $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $executionIdentity = $input['_xiam_execution_identity'] ?? null;
        unset($input['_xiam_execution_identity']);
        if ($executionIdentity !== null && ! $executionIdentity instanceof ActionExecutionIdentity) {
            throw new RuntimeException('action_execution_identity_invalid');
        }

        $sessionId = (int) ($input['session_id'] ?? 0);
        $expectedVersion = (int) ($input['expected_version'] ?? 0);
        $execute = (bool) ($input['execute'] ?? false);
        $session = ExamSession::query()->find($sessionId);
        if ($session === null) {
            throw new RuntimeException('session_not_found');
        }

        $session->loadMissing(['academicCycle', 'room']);
        $sessionInstitutionId = $session->academicCycle?->institution_id;
        $actorInstitutionId = $actor?->actorScope()->institutionId;
        if ($actorInstitutionId !== null && $sessionInstitutionId !== null
            && (int) $actorInstitutionId !== (int) $sessionInstitutionId) {
            return ['mode' => 'denied', 'reason' => 'institution_scope_mismatch'];
        }

        $impact = [
            'session_id' => $session->id,
            'code' => $session->code,
            'expected_version' => $expectedVersion,
            'action' => 'open_reservation',
            'reason' => isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : null,
        ];

        if (! $execute) {
            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('stale_session_version');
            }

            if (in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED, 'closed'], true)) {
                return ['mode' => 'denied', 'reason' => 'session_terminal'];
            }

            if ($session->room === null || $session->starts_at === null || $session->effective_end_at === null) {
                return ['mode' => 'denied', 'reason' => 'session_room_required'];
            }

            if ($session->isOpenForReservation()) {
                return ['mode' => 'denied', 'reason' => 'reservation_already_open'];
            }

            $command = [
                'session_id' => (int) $session->id,
                'expected_version' => $expectedVersion,
            ];
            if (isset($input['reason']) && is_string($input['reason']) && trim($input['reason']) !== '') {
                $command['reason'] = trim($input['reason']);
            }

            $sessionRecord = [
                'type' => 'record',
                'label' => (string) $session->code,
                'context' => array_values(array_filter([
                    $session->room?->name ? "Room: {$session->room->name}" : null,
                    $session->starts_at ? "Starts: {$session->starts_at->setTimezone('Asia/Manila')->format('Y-m-d H:i')}" : null,
                ])),
            ];

            $fields = [
                ProposalViewContract::field('session', 'Exam session', ['type' => 'missing'], $sessionRecord, 'context'),
                ProposalViewContract::field(
                    'booking_mode',
                    'Reservation state',
                    ['type' => 'enum', 'code' => (string) ($session->booking_mode ?? 'closed'), 'label' => $session->isOpenForReservation() ? 'Open for reservation' : 'Closed for reservation'],
                    ['type' => 'enum', 'code' => ExamSession::BOOKING_OPEN_FOR_RESERVATION, 'label' => 'Open for reservation'],
                    'effect',
                ),
            ];

            $coverage = [
                'session_id' => ['classification' => 'display', 'field' => 'session'],
                'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
            ];

            if (isset($command['reason'])) {
                $fields[] = ProposalViewContract::field(
                    'reason',
                    'Open reason',
                    ['type' => 'missing'],
                    ['type' => 'text', 'value' => $command['reason'], 'multiline' => true],
                    'choice',
                );
                $coverage['reason'] = ['classification' => 'display', 'field' => 'reason'];
            }

            $checks = [
                ['key' => 'version_match', 'label' => 'Session version current', 'status' => 'pass', 'detail' => 'Session version matches expected state'],
                ['key' => 'published_session', 'label' => 'Published exam session', 'status' => 'pass', 'detail' => 'Exam session is in valid active state'],
                ['key' => 'room_assigned', 'label' => 'Room and schedule configured', 'status' => 'pass', 'detail' => 'Room and session timing are assigned'],
                ['key' => 'reservation_not_open', 'label' => 'Reservations not currently open', 'status' => 'pass', 'detail' => 'Session is eligible to open reservations'],
                ['key' => 'not_terminal', 'label' => 'Session is non-terminal', 'status' => 'pass', 'detail' => 'Session is not completed, closed, or cancelled'],
            ];

            $result = [
                'mode' => 'propose',
                'ok' => true,
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => $impact,
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Open exam reservations',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session_reservation_open', 'Exam session reservation', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight('pass', $checks),
                    'coverage' => $coverage,
                ],
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['version_match', 'published_session', 'room_assigned', 'reservation_not_open', 'not_terminal'],
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        if ($executionIdentity instanceof ActionExecutionIdentity) {
            $replay = $this->action->replayConfirmed(
                $session,
                $actor,
                $expectedVersion,
                $impact['reason'],
                $executionIdentity,
            );
            if ($replay !== null) {
                return ['mode' => 'execute', ...$replay->safeResult];
            }

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('stale_session_version');
            }

            $result = $this->action->handleConfirmed(
                $session,
                $actor,
                $expectedVersion,
                $impact['reason'],
                $executionIdentity,
            );

            return ['mode' => 'execute', ...$result->safeResult];
        }

        if ((int) $session->version !== $expectedVersion) {
            throw new RuntimeException('stale_session_version');
        }

        $updated = $this->action->handle($session, $actor, $expectedVersion, $impact['reason']);

        return [
            'mode' => 'execute',
            'session_id' => $updated->id,
            'booking_mode' => $updated->booking_mode,
            'version' => $updated->version,
            'status' => $updated->status,
        ];
    }
}
