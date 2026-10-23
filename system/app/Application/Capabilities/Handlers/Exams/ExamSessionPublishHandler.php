<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Models\Ai\XiamProposal;
use App\Models\Examination\ExamSession;
use App\Models\User;
use RuntimeException;

final class ExamSessionPublishHandler
{
    private const CAPABILITY_ID = 'exams.session.publish@1';

    public function __construct(
        private readonly PublishExamSession $action,
        private readonly ExamSessionPublishPreflight $preflight,
    ) {}

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
        $session = ExamSession::query()
            ->with(['room', 'bookletVersion.templateVersion', 'bookletVersion.sets', 'seatingArrangement', 'currentLogisticsPlan.arrangement'])
            ->withCount(['assignments as assigned_count' => fn ($q) => $q->active()])
            ->find($sessionId);
        if ($session === null) {
            throw new RuntimeException('session_not_found');
        }

        if (! $execute) {
            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('stale_session_version');
            }

            $preflight = $this->preflight->evaluate($session);
            if (! $preflight['ok']) {
                throw new RuntimeException((string) ($preflight['reason'] ?? $preflight['code']));
            }

            $command = [
                'session_id' => (int) $session->id,
                'expected_version' => $expectedVersion,
            ];
            if (isset($input['roster_material_fingerprint']) && is_string($input['roster_material_fingerprint']) && $input['roster_material_fingerprint'] !== '') {
                $command['roster_material_fingerprint'] = $input['roster_material_fingerprint'];
            }

            $sessionRecord = [
                'type' => 'record',
                'label' => (string) $session->code,
                'context' => array_values(array_filter([
                    $session->starts_at ? "Starts: {$session->starts_at->setTimezone('Asia/Manila')->format('Y-m-d H:i')}" : null,
                    $session->seating_mode ? 'Seating: '.$session->seating_mode : null,
                    $preflight['capacity_limit'] !== null ? 'Capacity limit: '.$preflight['capacity_limit'] : null,
                ])),
            ];

            $fpValue = $preflight['roster_material_fingerprint'] !== null
                ? ['type' => 'code', 'value' => (string) $preflight['roster_material_fingerprint']]
                : ['type' => 'null'];

            $fields = [
                ProposalViewContract::field('session', 'Exam session', ['type' => 'missing'], $sessionRecord, 'context'),
                ProposalViewContract::field(
                    'status',
                    'Publication status',
                    ['type' => 'enum', 'code' => ExamSession::STATUS_DRAFT, 'label' => 'Draft'],
                    ['type' => 'enum', 'code' => ExamSession::STATUS_PUBLISHED, 'label' => 'Published'],
                    'effect',
                ),
                ProposalViewContract::field(
                    'assigned_count',
                    'Assigned examinees',
                    ['type' => 'missing'],
                    ['type' => 'integer', 'value' => (int) $preflight['assigned_count'], 'semantic' => 'count', 'unit' => 'examinees'],
                    'effect',
                ),
                ProposalViewContract::field('roster_fingerprint', 'Roster and logistics fingerprint', ['type' => 'missing'], $fpValue, 'context'),
            ];

            $coverage = [
                'session_id' => ['classification' => 'display', 'field' => 'session'],
                'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
            ];
            if (isset($command['roster_material_fingerprint'])) {
                $coverage['roster_material_fingerprint'] = ['classification' => 'display', 'field' => 'roster_fingerprint'];
            }

            $checkLabels = [
                'draft' => 'Session is draft',
                'room_active' => 'Room is active and suitable for exams',
                'schedule_valid' => 'Session schedule is valid',
                'has_assignments' => 'Session has assigned examinees',
                'seating_mode_resolved' => 'Seating mode is configured',
                'seating_arrangement_compatible' => 'Seating arrangement is compatible with the room',
                'roster_within_desk_capacity' => 'Examinee count is within assigned-seat capacity',
                'roster_within_room_capacity' => 'Examinee count is within room capacity',
                'version_match' => 'Session version is current',
            ];

            $preflightChecks = [];
            foreach ([...$preflight['checks'], 'version_match'] as $cKey) {
                $preflightChecks[] = [
                    'key' => (string) $cKey,
                    'label' => $checkLabels[$cKey] ?? ucwords(str_replace('_', ' ', (string) $cKey)),
                    'status' => 'pass',
                    'detail' => null,
                ];
            }

            $impact = [
                'session_id' => $session->id,
                'code' => $session->code,
                'expected_version' => $expectedVersion,
                'action' => 'publish',
                'assigned_count' => $preflight['assigned_count'],
                'roster_material_fingerprint' => $preflight['roster_material_fingerprint'],
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
                    'title' => 'Publish exam session',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session_publish', 'Publish exam session', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight('pass', $preflightChecks),
                    'coverage' => $coverage,
                ],
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => [...$preflight['checks'], 'version_match'],
                    'roster_material_fingerprint' => $preflight['roster_material_fingerprint'],
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $expectedFingerprint = isset($input['roster_material_fingerprint']) && is_string($input['roster_material_fingerprint']) && $input['roster_material_fingerprint'] !== ''
            ? $input['roster_material_fingerprint']
            : $this->resolveExecutingProposalFingerprint($session->id, $actor);

        if ($executionIdentity instanceof ActionExecutionIdentity) {
            $replay = $this->action->replayConfirmed(
                $session,
                $actor,
                $expectedVersion,
                $expectedFingerprint,
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
                $expectedFingerprint,
                $executionIdentity,
            );

            return ['mode' => 'execute', ...$result->safeResult];
        }

        if ((int) $session->version !== $expectedVersion) {
            throw new RuntimeException('stale_session_version');
        }

        $published = $this->action->handle($session, $actor, $expectedVersion, $expectedFingerprint);

        return [
            'mode' => 'execute',
            'session_id' => $published->id,
            'status' => $published->status,
            'version' => $published->version,
        ];
    }

    private function resolveExecutingProposalFingerprint(int $sessionId, User $actor): ?string
    {
        $proposals = XiamProposal::query()
            ->where('capability_id', 'exams.session.publish@1')
            ->where('status', 'executing')
            ->orderByDesc('claimed_at')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($proposals as $proposal) {
            $summary = is_array($proposal->summary) ? $proposal->summary : [];
            if ((string) ($summary['actor_id'] ?? '') !== (string) $actor->id) {
                continue;
            }
            $impact = is_array($summary['impact'] ?? null) ? $summary['impact'] : [];
            if ((int) ($impact['session_id'] ?? 0) !== $sessionId) {
                continue;
            }
            $fingerprint = $impact['roster_material_fingerprint']
                ?? ($summary['evidence']['roster_material_fingerprint'] ?? null);
            if (is_string($fingerprint) && $fingerprint !== '') {
                return $fingerprint;
            }
        }

        return null;
    }
}
