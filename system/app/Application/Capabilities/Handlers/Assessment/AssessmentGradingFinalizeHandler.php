<?php

namespace App\Application\Capabilities\Handlers\Assessment;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Support\GradingFinalizationEvaluator;
use App\Models\Assessment\GradingSession;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class AssessmentGradingFinalizeHandler
{
    private const CAPABILITY_ID = 'assessment.grading.finalize@1';

    public function __construct(
        private readonly GradingFinalizationEvaluator $evaluator,
        private readonly FinalizeGradingSession $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $gradingSessionId = $input['grading_session_id'] ?? null;
        if (! is_int($gradingSessionId) || $gradingSessionId < 1) {
            throw new RuntimeException('grading_session_id_invalid');
        }
        $gradingSession = GradingSession::query()->find($gradingSessionId);
        if (! $gradingSession instanceof GradingSession) {
            throw new RuntimeException('grading_session_not_found');
        }

        if (! (bool) ($input['execute'] ?? false)) {
            $evaluation = $this->evaluator->evaluate($gradingSession);

            $command = [
                'grading_session_id' => (int) $gradingSession->id,
            ];
            $coverage = [
                'grading_session_id' => ['classification' => 'display', 'field' => 'session'],
            ];

            if (array_key_exists('expected_version', $input) && is_int($input['expected_version'])) {
                $command['expected_version'] = (int) $input['expected_version'];
                $coverage['expected_version'] = ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'];
            }

            $gradingSession->loadMissing(['examSession']);
            $sessionLabel = "Grading session #{$gradingSession->id}";
            $sessionContext = array_values(array_filter([
                $gradingSession->examSession?->code ? "Exam: {$gradingSession->examSession->code}" : null,
                $gradingSession->status ? 'Current status: '.ucfirst($gradingSession->status) : null,
            ]));

            $checks = array_map(static function (array $c): array {
                return [
                    'key' => (string) ($c['key'] ?? 'check'),
                    'label' => (string) ($c['label'] ?? 'Check'),
                    'status' => in_array($c['status'] ?? null, ['pass', 'fail', 'warn', 'noop', 'unknown'], true) ? $c['status'] : 'unknown',
                    'detail' => isset($c['detail']) ? (string) $c['detail'] : (isset($c['note']) ? (string) $c['note'] : null),
                ];
            }, $evaluation->checks);

            $result = [
                'mode' => 'propose',
                'ok' => $evaluation->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => ['grading_session_id' => (int) $gradingSession->id],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Finalize grading session',
                    'sections' => [
                        ProposalViewContract::fieldsSection('session', 'Grading session', [
                            ProposalViewContract::field(
                                'session',
                                'Grading session',
                                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                                'context',
                            ),
                            ProposalViewContract::field(
                                'status',
                                'Session status',
                                ['type' => 'enum', 'code' => $gradingSession->status, 'label' => ucfirst(str_replace('_', ' ', $gradingSession->status))],
                                ['type' => 'enum', 'code' => GradingSession::STATUS_FINALIZED, 'label' => 'Finalized'],
                                'effect',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $checks),
                    'coverage' => $coverage,
                ],
                'diff' => $evaluation->diff,
                'evidence' => ['outcome' => $evaluation->outcome, 'checks' => $checks],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }
        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        $expectedVersion = $input['expected_version'] ?? null;
        if (! is_int($expectedVersion) || $expectedVersion < 1) {
            throw new RuntimeException('expected_version_invalid');
        }
        if ($this->evaluator->evaluate($gradingSession)->outcome !== 'pass') {
            throw new RuntimeException('grading_finalization_preflight_failed');
        }

        $finalized = $this->action->handle($gradingSession, $actor, $expectedVersion);

        return [
            'mode' => 'execute',
            'ok' => true,
            'grading_session_id' => (int) $finalized->id,
            'status' => $finalized->status,
            'version' => (int) $finalized->version,
        ];
    }
}
