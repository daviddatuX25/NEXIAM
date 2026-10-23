<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Support\CloseExamSessionEvaluator;
use App\Models\Examination\ExamSession;
use App\Models\User;
use RuntimeException;

final class ExamSessionCloseHandler
{
    private const CAPABILITY_ID = 'exams.session.close@1';

    public function __construct(
        private readonly CloseExamSession $action,
        private readonly CloseExamSessionEvaluator $evaluator,
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

        $sessionId = $this->requiredInteger($input['session_id'] ?? null, 'session_id');
        $expectedVersion = $this->requiredInteger($input['expected_version'] ?? null, 'expected_version');
        $session = ExamSession::query()->find($sessionId);
        if (! $session instanceof ExamSession) {
            throw new RuntimeException('session_not_found');
        }

        $preflight = $this->evaluator->evaluate($session);
        $execute = (bool) ($input['execute'] ?? false);
        if (! $execute) {
            $command = [
                'session_id' => (int) $session->id,
                'expected_version' => $expectedVersion,
            ];

            $session->loadMissing(['room']);
            $assignedCount = $session->assignments()->active()->count();
            $sessionLabel = (string) $session->code;
            $sessionContext = array_values(array_filter([
                $session->room?->name ? "Room: {$session->room->name}" : null,
                "Assigned candidates: {$assignedCount}",
            ]));

            $sessionField = ProposalViewContract::field(
                'session',
                'Exam session',
                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                'context',
            );

            $statusField = ProposalViewContract::field(
                'status',
                'Session status',
                ['type' => 'enum', 'code' => $session->status, 'label' => ucfirst(str_replace('_', ' ', $session->status))],
                ['type' => 'enum', 'code' => ExamSession::STATUS_COMPLETED, 'label' => 'Completed'],
                'effect',
            );

            $attendanceStatusField = ProposalViewContract::field(
                'attendance_status',
                'Roster attendance status',
                ['type' => 'enum', 'code' => 'recording', 'label' => 'Attendance recording in progress'],
                ['type' => 'enum', 'code' => 'finalized', 'label' => 'All attendance recorded and locked'],
                'effect',
            );

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
                'impact' => ['session_id' => $session->id, 'expected_version' => $expectedVersion],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Close exam session',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session', 'Session transition', [
                            $sessionField,
                            $statusField,
                            $attendanceStatusField,
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($preflight->outcome, $checks),
                    'coverage' => [
                        'session_id' => ['classification' => 'display', 'field' => 'session'],
                        'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
                    ],
                ],
                'diff' => $preflight->diff,
                'evidence' => ['outcome' => $preflight->outcome, 'checks' => $checks],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        if ($preflight->outcome !== 'pass') {
            throw new RuntimeException('exam_session_close_preflight_failed');
        }

        $closed = $this->action->handle($session, $actor, $expectedVersion);

        return [
            'mode' => 'execute',
            'ok' => true,
            'session_id' => $closed->id,
            'status' => $closed->status,
            'version' => $closed->version,
            'diff' => $preflight->diff,
        ];
    }

    private function requiredInteger(mixed $value, string $field): int
    {
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException($field.'_invalid');
        }

        return $value;
    }
}
