<?php

namespace App\Application\Capabilities\Handlers\Admission;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Support\AcceptAdmissionAttemptEvaluator;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class AdmissionAttemptAcceptHandler
{
    private const CAPABILITY_ID = 'admissions.attempt.accept@1';

    public function __construct(
        private readonly AcceptAdmissionAttemptEvaluator $evaluator,
        private readonly AcceptAdmissionAttempt $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $attemptId = $this->requiredInteger($input['attempt_id'] ?? null, 'attempt_id');
        $attempt = AdmissionAttempt::query()->find($attemptId);
        if (! $attempt instanceof AdmissionAttempt) {
            throw new RuntimeException('admission_attempt_not_found');
        }

        $expectedVersion = $this->requiredInteger($input['expected_version'] ?? null, 'expected_version');

        if (! (bool) ($input['execute'] ?? false)) {
            $evaluation = $this->evaluator->evaluate($attempt, $expectedVersion);
            $command = [
                'attempt_id' => (int) $attempt->id,
                'expected_version' => $expectedVersion,
            ];

            $attempt->loadMissing(['identity']);
            $context = [];
            if ($attempt->identity?->fullName()) {
                $context[] = $attempt->identity->fullName();
            }
            if ($attempt->preferred_program_code) {
                $context[] = 'Program: '.$attempt->preferred_program_code;
            }
            $attemptLabel = (string) ($attempt->reference_code ?: ('Attempt #'.$attempt->id));

            $result = [
                'mode' => 'propose',
                'ok' => $evaluation->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'attempt_id' => (int) $attempt->id,
                    'reference_code' => $attempt->reference_code,
                    'expected_version' => $expectedVersion,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Accept applicant',
                    'sections' => [
                        ProposalViewContract::fieldsSection('attempt', 'Admission attempt', [
                            ProposalViewContract::field(
                                'attempt',
                                'Application',
                                ['type' => 'record', 'label' => $attemptLabel, 'context' => $context],
                                ['type' => 'record', 'label' => $attemptLabel, 'context' => $context],
                                'context',
                            ),
                            ProposalViewContract::field(
                                'status',
                                'Status',
                                ['type' => 'enum', 'code' => $attempt->status, 'label' => ucfirst(str_replace('_', ' ', $attempt->status))],
                                ['type' => 'enum', 'code' => AdmissionAttempt::STATUS_ACCEPTED, 'label' => 'Accepted'],
                                'effect',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $evaluation->checks),
                    'coverage' => [
                        'attempt_id' => ['classification' => 'display', 'field' => 'attempt'],
                        'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
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

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $accepted = $this->action->handle($attempt, $actor, $expectedVersion);

        return [
            'mode' => 'execute',
            'ok' => true,
            'attempt_id' => (int) $accepted->id,
            'status' => $accepted->status,
            'version' => (int) $accepted->version,
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
