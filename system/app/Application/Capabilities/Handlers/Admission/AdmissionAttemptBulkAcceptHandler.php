<?php

namespace App\Application\Capabilities\Handlers\Admission;

use App\Admission\Actions\AcceptAdmissionAttempts;
use App\Admission\Support\AcceptAdmissionAttemptEvaluator;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class AdmissionAttemptBulkAcceptHandler
{
    private const CAPABILITY_ID = 'admissions.attempt.bulk_accept@1';

    public function __construct(
        private readonly AcceptAdmissionAttemptEvaluator $evaluator,
        private readonly AcceptAdmissionAttempts $action,
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

        $attemptIds = $input['attempt_ids'] ?? null;
        if (! is_array($attemptIds) || $attemptIds === [] || count($attemptIds) > 25) {
            throw new RuntimeException('attempt_ids_invalid');
        }
        foreach ($attemptIds as $attemptId) {
            if (! is_int($attemptId) || $attemptId < 1) {
                throw new RuntimeException('attempt_ids_invalid');
            }
        }

        $cycleId = $input['academic_cycle_id'] ?? null;
        if (! is_int($cycleId) || $cycleId < 1) {
            throw new RuntimeException('academic_cycle_id_invalid');
        }

        $attempts = AdmissionAttempt::query()
            ->whereIn('id', $attemptIds)
            ->where('academic_cycle_id', $cycleId)
            ->orderBy('id')
            ->get();
        if ($attempts->count() !== count(array_unique($attemptIds))) {
            throw new RuntimeException('admission_attempts_not_found_in_cycle');
        }

        if (! (bool) ($input['execute'] ?? false)) {
            $diff = [];
            $checks = [];
            $attemptGroups = [];
            $passCount = 0;
            $failCount = 0;
            $noopCount = 0;

            $attempts->loadMissing(['identity']);
            $cycle = AcademicCycle::query()->find($cycleId);
            $cycleCode = (string) ($cycle?->code ?? ('Cycle #'.$cycleId));

            foreach ($attempts as $attempt) {
                $evaluation = $this->evaluator->evaluate($attempt, (int) $attempt->version);
                $passCount += $evaluation->outcome === 'pass' ? 1 : 0;
                $failCount += $evaluation->outcome === 'fail' ? 1 : 0;
                $noopCount += $evaluation->outcome === 'noop' ? 1 : 0;
                $diff[] = [
                    'attempt_id' => (int) $attempt->id,
                    'reference_code' => $attempt->reference_code,
                    'diff' => $evaluation->diff,
                ];
                $checks[] = [
                    'attempt_id' => (int) $attempt->id,
                    'outcome' => $evaluation->outcome,
                    'checks' => $evaluation->checks,
                ];

                $context = [];
                if ($attempt->identity?->fullName()) {
                    $context[] = $attempt->identity->fullName();
                }
                if ($attempt->preferred_program_code) {
                    $context[] = 'Program: '.$attempt->preferred_program_code;
                }
                $label = (string) ($attempt->reference_code ?: ('Attempt #'.$attempt->id));

                $attemptGroups[] = [
                    'key' => 'attempt_'.$attempt->id,
                    'label' => $label,
                    'context' => [
                        'type' => 'record',
                        'label' => $label,
                        'context' => $context,
                    ],
                    'fields' => [
                        ProposalViewContract::field(
                            'attempt_'.$attempt->id.'_status',
                            'Status',
                            ['type' => 'enum', 'code' => $attempt->status, 'label' => ucfirst(str_replace('_', ' ', $attempt->status))],
                            ['type' => 'enum', 'code' => AdmissionAttempt::STATUS_ACCEPTED, 'label' => 'Accepted'],
                            'effect',
                        ),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $evaluation->checks),
                ];
            }

            $overallOutcome = ($failCount === 0 && $noopCount === 0) ? 'pass' : 'fail';
            $topChecks = [
                [
                    'key' => 'bulk_acceptance_eligibility',
                    'label' => 'Attempt eligibility',
                    'status' => $failCount === 0 ? 'pass' : 'fail',
                    'detail' => $failCount === 0 ? "{$passCount} attempt(s) ready to accept" : "{$failCount} attempt(s) failed preflight",
                ],
                [
                    'key' => 'cycle_consistency',
                    'label' => 'Academic cycle consistency',
                    'status' => 'pass',
                    'detail' => "All attempts belong to academic cycle {$cycleCode}",
                ],
            ];

            $command = [
                'attempt_ids' => array_values($attemptIds),
                'academic_cycle_id' => $cycleId,
            ];

            $result = [
                'mode' => 'propose',
                'ok' => $overallOutcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'academic_cycle_id' => $cycleId,
                    'attempt_ids' => array_values($attemptIds),
                    'count' => $attempts->count(),
                ],
                'summary' => [
                    'total' => $attempts->count(),
                    'pass' => $passCount,
                    'fail' => $failCount,
                    'noop' => $noopCount,
                ],
                'diff' => $diff,
                'evidence' => ['outcome' => $overallOutcome, 'checks' => $checks],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'bulk',
                    'title' => 'Accept applicants',
                    'sections' => [
                        ProposalViewContract::fieldsSection('summary', 'Batch summary', [
                            ProposalViewContract::field('cycle', 'Academic cycle', ['type' => 'missing'], ['type' => 'record', 'label' => $cycleCode, 'context' => []], 'context'),
                            ProposalViewContract::field('attempt_ids', 'Selected attempts', ['type' => 'missing'], ['type' => 'integer', 'value' => $attempts->count(), 'semantic' => 'count', 'unit' => 'attempts'], 'context'),
                        ]),
                        [
                            'kind' => 'groups',
                            'key' => 'attempts',
                            'label' => 'Admission attempts',
                            'groups' => $attemptGroups,
                        ],
                    ],
                    'preflight' => ProposalViewContract::preflight($overallOutcome, $topChecks),
                    'coverage' => [
                        'attempt_ids' => ['classification' => 'display', 'field' => 'attempt_ids'],
                        'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                    ],
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $result = $this->action->handle($attemptIds, $cycleId, $actor, $executionIdentity);

        return ['mode' => 'execute', 'ok' => true, ...$result];
    }
}
