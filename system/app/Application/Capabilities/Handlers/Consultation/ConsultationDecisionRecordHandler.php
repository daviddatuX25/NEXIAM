<?php

namespace App\Application\Capabilities\Handlers\Consultation;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Consultation\Support\ConsultationDecisionEvaluator;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Release\Actions\RecordConsultationDecision;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class ConsultationDecisionRecordHandler
{
    private const CAPABILITY_ID = 'consultation.decision.record@2';

    public function __construct(private readonly ConsultationDecisionEvaluator $evaluator, private readonly RecordConsultationDecision $action) {}

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
        $encounterId = $input['encounter_id'] ?? null;
        if (! is_string($encounterId) || $encounterId === '') {
            throw new RuntimeException('encounter_id_invalid');
        }
        $decision = $input['decision_type'] ?? null;
        if (! is_string($decision)) {
            throw new RuntimeException('decision_type_invalid');
        }
        $targetProgramCode = $input['target_program_code'] ?? null;
        if ($targetProgramCode !== null && ! is_string($targetProgramCode)) {
            throw new RuntimeException('target_program_code_invalid');
        }
        $defaultProgramCode = $input['default_program_code'] ?? null;
        if ($defaultProgramCode !== null && ! is_string($defaultProgramCode)) {
            throw new RuntimeException('default_program_code_invalid');
        }
        $poolOptions = $input['pool_options'] ?? null;
        if ($poolOptions !== null && ! is_array($poolOptions)) {
            throw new RuntimeException('pool_options_invalid');
        }
        $poolReason = $input['pool_reason'] ?? null;
        if ($poolReason !== null && ! is_string($poolReason)) {
            throw new RuntimeException('pool_reason_invalid');
        }
        $notes = $input['notes'] ?? null;
        if ($notes !== null && ! is_string($notes)) {
            throw new RuntimeException('notes_invalid');
        }
        $referralReason = $input['referral_reason'] ?? null;
        if ($referralReason !== null && ! is_string($referralReason)) {
            throw new RuntimeException('referral_reason_invalid');
        }
        $execute = (bool) ($input['execute'] ?? false);

        $attempt = AdmissionAttempt::query()->find($attemptId);
        if (! $attempt instanceof AdmissionAttempt) {
            throw new RuntimeException('attempt_not_found');
        }

        $program = $targetProgramCode === null
            ? null
            : Program::query()->where('code', $targetProgramCode)->firstOrFail();
        $preflight = $this->evaluator->evaluate($attempt, $decision, $program?->id);
        if (! $execute) {
            $command = [
                'attempt_id' => (int) $attempt->id,
                'encounter_id' => (string) $encounterId,
                'decision_type' => (string) $decision,
                'target_program_code' => $targetProgramCode,
                'default_program_code' => $defaultProgramCode,
                'pool_options' => is_array($poolOptions) ? array_values($poolOptions) : [],
                'pool_reason' => $poolReason,
                'notes' => $notes,
                'referral_reason' => $referralReason,
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

            $poolItems = [];
            if (is_array($poolOptions)) {
                foreach ($poolOptions as $opt) {
                    if (is_string($opt)) {
                        $poolItems[] = ['type' => 'code', 'value' => $opt];
                    } elseif (is_array($opt) && isset($opt['program_code'])) {
                        $poolItems[] = ['type' => 'code', 'value' => (string) $opt['program_code']];
                    } elseif (is_array($opt) && isset($opt['code'])) {
                        $poolItems[] = ['type' => 'code', 'value' => (string) $opt['code']];
                    } else {
                        $poolItems[] = ['type' => 'text', 'value' => is_scalar($opt) ? (string) $opt : (string) json_encode($opt), 'multiline' => false];
                    }
                }
            }

            $fields = [
                ProposalViewContract::field(
                    'attempt',
                    'Applicant',
                    ['type' => 'record', 'label' => $attemptLabel, 'context' => $context],
                    ['type' => 'record', 'label' => $attemptLabel, 'context' => $context],
                    'context',
                ),
                ProposalViewContract::field(
                    'encounter',
                    'Encounter',
                    ['type' => 'code', 'value' => (string) $encounterId],
                    ['type' => 'code', 'value' => (string) $encounterId],
                    'context',
                ),
                ProposalViewContract::field(
                    'decision',
                    'Consultation decision',
                    ['type' => 'missing'],
                    ['type' => 'enum', 'code' => $decision, 'label' => ucfirst(str_replace('_', ' ', $decision))],
                    'choice',
                ),
                ProposalViewContract::field(
                    'target_program',
                    'Target program',
                    ['type' => 'null'],
                    $targetProgramCode !== null
                        ? ['type' => 'code', 'value' => $targetProgramCode]
                        : ['type' => 'null'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'default_program',
                    'Default program',
                    ['type' => 'null'],
                    $defaultProgramCode !== null
                        ? ['type' => 'code', 'value' => $defaultProgramCode]
                        : ['type' => 'null'],
                    'context',
                ),
                ProposalViewContract::field(
                    'pool_options',
                    'Guidance pool options',
                    ['type' => 'list', 'items' => [], 'empty_label' => 'No pool options'],
                    ['type' => 'list', 'items' => $poolItems, 'empty_label' => 'No pool options'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'pool_reason',
                    'Pool reason',
                    ['type' => 'null'],
                    $poolReason !== null
                        ? ['type' => 'text', 'value' => $poolReason, 'multiline' => true]
                        : ['type' => 'null'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'notes',
                    'Consultation notes',
                    ['type' => 'null'],
                    $notes !== null
                        ? ['type' => 'text', 'value' => $notes, 'multiline' => true]
                        : ['type' => 'null'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'referral_reason',
                    'Referral reason',
                    ['type' => 'null'],
                    $referralReason !== null
                        ? ['type' => 'text', 'value' => $referralReason, 'multiline' => true]
                        : ['type' => 'null'],
                    'choice',
                ),
            ];

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
                    'attempt_id' => $attempt->id,
                    'encounter_id' => $encounterId,
                    'decision_type' => $decision,
                    'target_program_code' => $targetProgramCode,
                    'default_program_code' => $defaultProgramCode,
                    'pool_options' => $poolOptions,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Record consultation decision',
                    'sections' => [
                        ProposalViewContract::fieldsSection('decision', 'Consultation decision', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($preflight->outcome, $checks),
                    'coverage' => [
                        'attempt_id' => ['classification' => 'display', 'field' => 'attempt'],
                        'encounter_id' => ['classification' => 'display', 'field' => 'encounter'],
                        'decision_type' => ['classification' => 'display', 'field' => 'decision'],
                        'target_program_code' => ['classification' => 'display', 'field' => 'target_program'],
                        'default_program_code' => ['classification' => 'display', 'field' => 'default_program'],
                        'pool_options' => ['classification' => 'display', 'field' => 'pool_options'],
                        'pool_reason' => ['classification' => 'display', 'field' => 'pool_reason'],
                        'notes' => ['classification' => 'display', 'field' => 'notes'],
                        'referral_reason' => ['classification' => 'display', 'field' => 'referral_reason'],
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
        if ($preflight->outcome !== 'pass') {
            throw new RuntimeException('consultation_decision_preflight_failed');
        }

        $idempotencyKey = $executionIdentity instanceof ActionExecutionIdentity
            ? $executionIdentity->executionId
            : (string) ($input['idempotency_key'] ?? 'consultation-decision-'.$attemptId.'-'.bin2hex(random_bytes(16)));
        if ($idempotencyKey === '') {
            throw new RuntimeException('idempotency_key_invalid');
        }

        $recorded = $this->action->handle(
            attempt: $attempt,
            counselor: $actor,
            decisionType: $decision,
            notes: $notes,
            idempotencyKey: $idempotencyKey,
            targetProgramCode: $targetProgramCode,
            referralReason: $referralReason,
            defaultProgramCode: $defaultProgramCode,
            poolOptions: $poolOptions,
            poolReason: $poolReason,
            encounterId: $encounterId,
        );

        return [
            'mode' => 'execute',
            'ok' => true,
            'attempt_id' => $attempt->id,
            'encounter_id' => $encounterId,
            'decision_id' => $recorded->id,
            'decision_type' => $recorded->decision_type,
            'target_program_code' => $targetProgramCode,
            'version' => $recorded->version,
            'diff' => $preflight->diff,
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
