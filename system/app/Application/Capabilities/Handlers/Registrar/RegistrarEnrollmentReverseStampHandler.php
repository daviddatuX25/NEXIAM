<?php

namespace App\Application\Capabilities\Handlers\Registrar;

use App\Admission\Actions\ReverseEnrollmentStamp;
use App\Admission\Support\EnrollmentStampingEvaluator;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class RegistrarEnrollmentReverseStampHandler
{
    private const CAPABILITY_ID = 'registrar.enrollment.reverse_stamp@1';

    private const RETIRED_MESSAGE = 'Enrollment stamping has been retired. Admission clearance is confirmed by authorized Registrar staff via the admission clearance workflow.';

    public function __construct(
        private readonly EnrollmentStampingEvaluator $evaluator,
        private readonly ReverseEnrollmentStamp $action,
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

        $attemptId = $this->requiredInteger($input['attempt_id'] ?? null, 'attempt_id');
        $reason = $this->requiredString($input['reason'] ?? null, 'reason');
        $execute = (bool) ($input['execute'] ?? false);

        $attempt = AdmissionAttempt::query()->find($attemptId);
        if (! $attempt instanceof AdmissionAttempt) {
            throw new RuntimeException('attempt_not_found');
        }

        if ($execute) {
            return [
                'mode' => 'denied',
                'ok' => false,
                'status' => 'denied',
                'capability_id' => self::CAPABILITY_ID,
                'reason' => 'retired_capability',
                'error' => self::RETIRED_MESSAGE,
                'message' => self::RETIRED_MESSAGE,
            ];
        }

        $command = [
            'attempt_id' => $attemptId,
            'reason' => $reason,
        ];

        $attempt->loadMissing(['identity']);
        $attemptContext = [];
        if ($attempt->identity?->fullName()) {
            $attemptContext[] = $attempt->identity->fullName();
        }
        if ($attempt->preferred_program_code) {
            $attemptContext[] = 'Program: '.$attempt->preferred_program_code;
        }
        $attemptLabel = (string) ($attempt->reference_code ?: ('Attempt #'.$attempt->id));

        $fields = [
            ProposalViewContract::field(
                'attempt',
                'Admission attempt',
                ['type' => 'record', 'label' => $attemptLabel, 'context' => $attemptContext],
                ['type' => 'record', 'label' => $attemptLabel, 'context' => $attemptContext],
                'context',
            ),
            ProposalViewContract::field(
                'status',
                'Status',
                ['type' => 'enum', 'code' => 'retired', 'label' => 'Retired'],
                ['type' => 'enum', 'code' => 'retired', 'label' => 'Retired'],
                'effect',
            ),
            ProposalViewContract::field(
                'notice',
                'Retirement notice',
                ['type' => 'text', 'value' => self::RETIRED_MESSAGE, 'multiline' => true],
                ['type' => 'text', 'value' => self::RETIRED_MESSAGE, 'multiline' => true],
                'context',
            ),
            ProposalViewContract::field(
                'reason',
                'Reversal reason',
                ['type' => 'null'],
                ['type' => 'text', 'value' => $reason, 'multiline' => true],
                'choice',
            ),
        ];

        $coverage = [
            'attempt_id' => ['classification' => 'display', 'field' => 'attempt'],
            'reason' => ['classification' => 'display', 'field' => 'reason'],
        ];

        $checks = [
            [
                'key' => 'capability_retired',
                'label' => 'Capability status',
                'status' => 'fail',
                'detail' => self::RETIRED_MESSAGE,
            ],
        ];

        $result = [
            'mode' => 'propose',
            'ok' => false,
            'status' => 'retired',
            'capability_id' => self::CAPABILITY_ID,
            'command_input' => $command,
            'impact' => [
                'attempt_id' => (int) $attempt->id,
                'status' => 'retired',
            ],
            'proposal_review' => [
                'version' => 1,
                'operation' => 'transition',
                'title' => 'Reverse enrollment stamp (retired)',
                'sections' => [
                    ProposalViewContract::fieldsSection('enrollment', 'Enrollment', $fields),
                ],
                'preflight' => ProposalViewContract::preflight('fail', $checks),
                'coverage' => $coverage,
            ],
            'diff' => [],
            'evidence' => [
                'outcome' => 'fail',
                'checks' => $checks,
            ],
            'error' => self::RETIRED_MESSAGE,
        ];

        ProposalReview::fromResult(self::CAPABILITY_ID, $result);

        return $result;
    }

    private function requiredInteger(mixed $value, string $field): int
    {
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException($field.'_invalid');
        }

        return $value;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException($field.'_invalid');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function idempotencyKey(array $input, mixed $executionIdentity, int $attemptId): string
    {
        $key = $executionIdentity instanceof ActionExecutionIdentity
            ? $executionIdentity->executionId
            : ($input['idempotency_key'] ?? 'enrollment-reverse-'.$attemptId.'-'.bin2hex(random_bytes(16)));

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('idempotency_key_invalid');
        }

        return $key;
    }
}
