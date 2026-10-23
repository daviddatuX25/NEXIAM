<?php

namespace App\Ai\Adapters\LaravelAi;

use App\Ai\Context\InvocationContext;
use App\Ai\Evaluation\XiamEvalObserver;
use App\Ai\Gateway\AssistantToolDefinition;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Orchestration\RunEventAppender;
use App\Ai\Orchestration\RunEvidenceCollection;
use App\Ai\Orchestration\XiamRunBudgetExceeded;
use App\Ai\Orchestration\XiamRunExecutionBudget;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalService;
use App\Ai\Skills\ApplicantChooseSlotSkill;
use App\Ai\Skills\ExamSessionSchedulingSkill;
use App\Ai\Skills\SkillEngine;
use App\Models\Ai\XiamRun;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Adapter bridging an AssistantToolDefinition to Laravel\Ai\Contracts\Tool.
 */
final class AssistantToolBridge implements Tool
{
    public function __construct(
        public readonly AssistantToolDefinition $definition,
        private readonly AssistantToolGateway $gateway,
        private readonly User $actor,
        private readonly InvocationContext $context,
    ) {}

    public function name(): string
    {
        return str_replace(['.', '@', '-'], '_', $this->definition->id());
    }

    public function description(): Stringable|string
    {
        return $this->definition->description;
    }

    public function handle(Request $request): Stringable|string
    {
        $input = $request->all();
        $runId = $this->context->correlationId;
        $budget = app(XiamRunExecutionBudget::class);
        $budgetStep = null;

        if ($runId !== '') {
            $budgetStep = $budget->beginToolCall($runId, $this->definition->id(), $request->toolCallId());
            $budget->assertRunCanContinue($runId);
        }

        try {
            $result = $this->gateway->invoke(
                actor: $this->actor,
                context: $this->context,
                toolId: $this->definition->id(),
                input: $input,
            );
        } catch (\Throwable $exception) {
            $this->observe(
                runId: $runId,
                input: $input,
                ok: false,
                status: 'failed',
                safeData: null,
                exceptionClass: $exception::class,
                stableCode: $this->stableExceptionCode($exception),
            );

            throw $exception;
        }
        $projector = app(XiamProjectorRegistry::class);
        if (! $projector->has($this->definition->id())) {
            $this->observe(
                runId: $runId,
                input: $input,
                ok: false,
                status: 'projection_refused',
                safeData: null,
                stableCode: 'projection_refused',
            );

            return $this->projectionRefused();
        }

        try {
            $safeData = is_array($result->data)
                ? $projector->project($this->definition->id(), $result->data)
                : $result->data;
        } catch (\Throwable) {
            $this->observe(
                runId: $runId,
                input: $input,
                ok: false,
                status: 'projection_refused',
                safeData: null,
                stableCode: 'projection_refused',
            );

            return $this->projectionRefused();
        }

        $proposalPayload = null;
        if ($result->ok && is_array($result->data) && ($result->data['mode'] ?? null) === 'propose') {
            $proposals = app(ProposalService::class);
            $grants = app(TaskGrantService::class);
            $grant = ($this->context->grantId !== null && $this->context->grantId !== '')
                ? $grants->findActive($this->context->grantId)
                : null;
            $data = $result->data;
            $impact = is_array($data['impact'] ?? null) ? $data['impact'] : [];
            unset($impact['idempotency_key']);
            $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
            $expectedVersion = isset($impact['expected_version']) ? (int) $impact['expected_version'] : null;
            $diff = $data['diff'] ?? null;
            $preflight = $data['preflight'] ?? $data['evidence'] ?? null;

            $cleanInput = is_array($data['command_input'] ?? null)
                ? $data['command_input']
                : array_diff_key($input, array_flip(['_xiam_execution_identity', 'execute', 'confirmation_token', 'idempotency_key']));

            $proposalReview = null;
            if (isset($data['proposal_review']) && $data['proposal_review'] instanceof ProposalReview) {
                $proposalReview = $data['proposal_review'];
            } elseif (isset($data['proposal_review']) && is_array($data['proposal_review'])) {
                $reviewData = $data;
                if (! isset($reviewData['command_input']) || ! is_array($reviewData['command_input'])) {
                    $reviewData['command_input'] = $cleanInput;
                }
                $proposalReview = ProposalReview::fromResult(
                    (string) ($data['capability_id'] ?? $this->definition->id()),
                    $reviewData
                );
            }

            $conversationId = $this->context->conversationId;
            if ($conversationId !== null) {
                $proposal = $proposals->create(
                    actor: $this->actor,
                    runId: $this->context->correlationId,
                    conversationId: $conversationId,
                    capabilityId: (string) ($data['capability_id'] ?? $this->definition->id()),
                    summary: [
                        ...$impact,
                        '_command_input' => $cleanInput,
                    ],
                    evidence: $evidence,
                    grant: $grant,
                    expectedVersion: $expectedVersion,
                    proposalView: $proposalReview ?? [
                        'diff' => $diff,
                        'preflight_checks' => $preflight,
                    ],
                );

                $safeProposalView = $projector->safeProposalView([
                    'id' => $proposal->id,
                    'proposal_id' => $proposal->id,
                    'run_id' => $proposal->run_id,
                    'capability_id' => $proposal->capability_id,
                    'status' => $proposal->status,
                    'safety_tier' => $proposal->safety_tier,
                    'confirmation_phrase' => $proposal->confirmation_phrase,
                    'requires_reauth' => (bool) $proposal->requires_reauth,
                    'expires_at' => $proposal->expires_at?->toIso8601String(),
                    'proposal_view' => $proposal->proposal_view,
                    'receipt' => is_array($proposal->receipt) ? $proposal->receipt : null,
                    'summary' => is_array($proposal->summary) ? $proposal->summary : [],
                ]);

                $proposalPayload = [
                    'id' => $proposal->id,
                    'proposal_id' => $proposal->id,
                    'capability_id' => $proposal->capability_id,
                    'status' => $proposal->status,
                    'summary' => $proposal->summary,
                    'diff' => $diff,
                    'preflight' => $preflight,
                    'evidence' => $data['evidence'] ?? null,
                    'expires_at' => $proposal->expires_at?->toIso8601String(),
                ];
            }
        }

        if ($result->ok && is_array($result->data) && $this->context->grantId !== null && $this->context->grantId !== '') {
            $grants = app(TaskGrantService::class);
            $skills = app(SkillEngine::class);
            $grant = $grants->findActive($this->context->grantId);
            if ($grant !== null) {
                $capId = $this->definition->id();
                if ($capId === 'exams.session.show@1') {
                    $skills->advanceCursor($grant, ExamSessionSchedulingSkill::CURSOR_SESSION_LOADED);
                    $bounds = $grant->scope_bounds ?? [];
                    if (! isset($bounds['exam_session_id']) || (int) $bounds['exam_session_id'] < 1) {
                        $bounds['exam_session_id'] = (int) ($input['session_id'] ?? 0) ?: null;
                    }
                    $grant->forceFill(['scope_bounds' => $bounds])->save();
                } elseif ($capId === 'exams.slots.list@1') {
                    $skills->advanceCursor($grant, ApplicantChooseSlotSkill::CURSOR_SLOTS_LOADED);
                }
            }
        }

        try {
            $safeProposal = $proposalPayload !== null
                ? $projector->project($this->definition->id(), $proposalPayload)
                : null;
            if (is_array($safeProposal)) {
                unset($safeProposal['proposal_view'], $safeProposal['proposal_review']);
            }
        } catch (\Throwable) {
            $this->observe(
                runId: $runId,
                input: $input,
                ok: false,
                status: 'projection_refused',
                safeData: null,
                stableCode: 'projection_refused',
            );

            $responsePayload = [
                'ok' => false,
                'status' => 'projection_refused',
                'capability_id' => $this->definition->id(),
                'data' => null,
                'reason' => 'Data projection refused by security policy.',
            ];

            return (string) json_encode($responsePayload, JSON_THROW_ON_ERROR);
        }
        $this->observe(
            runId: $runId,
            input: $input,
            ok: $result->ok,
            status: $result->status,
            safeData: $safeData,
            safeProposal: is_array($safeProposal) ? $safeProposal : null,
            stableCode: $result->ok ? null : $this->stableResultCode($result->reason),
        );

        if (is_array($safeProposal) && $this->context->correlationId !== '') {
            $run = XiamRun::query()->find($this->context->correlationId);
            if ($run !== null) {
                app(RunEventAppender::class)->append($run, 'skill.proposal', [
                    'proposal_id' => $safeProposal['proposal_id'] ?? $safeProposal['id'] ?? null,
                    'capability_id' => $safeProposal['capability_id'] ?? $this->definition->id(),
                    'expires_at' => $safeProposal['expires_at'] ?? null,
                    'summary' => $safeProposal['summary'] ?? null,
                    'diff' => $safeProposal['diff'] ?? null,
                    'preflight_checks' => $safeProposal['preflight'] ?? $safeProposal['evidence'] ?? null,
                    'run_id' => $proposal->run_id,
                    'status' => $proposal->status,
                    'proposal_view' => $safeProposalView,
                ]);
            }
        }

        try {
            $serialized = app(AssistantToolResultSerializer::class)->serialize(
                capabilityId: $this->definition->id(),
                result: $result,
                safeData: $safeData,
                safeProposal: is_array($safeProposal) ? $safeProposal : null,
            );

            $withinBudget = true;
            if ($budgetStep !== null) {
                $withinBudget = $budget->completeToolCall($budgetStep, strlen($serialized), $result->ok);
                if (! $withinBudget) {
                    throw new XiamRunBudgetExceeded('tool_result_budget_exhausted');
                }
            }

            if ($result->ok && $safeData !== null && $withinBudget
                && ($result->data['mode'] ?? null) !== 'propose'
                && $this->context->correlationId !== '') {
                RunEvidenceCollection::addToolCitation(
                    $this->context->correlationId,
                    $this->definition->id(),
                    $safeData,
                );
            }

            return $serialized;
        } catch (XiamRunBudgetExceeded $e) {
            throw $e;
        } catch (\Throwable) {
            return $this->projectionRefused();
        }
    }

    /**
     * Observation is deliberately best-effort and cannot change tool dispatch behavior.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $safeProposal
     */
    private function observe(
        string $runId,
        array $input,
        bool $ok,
        string $status,
        mixed $safeData,
        ?array $safeProposal = null,
        ?string $exceptionClass = null,
        ?string $stableCode = null,
    ): void {
        try {
            app(XiamEvalObserver::class)->recordToolCall(
                runId: $runId,
                capabilityId: $this->definition->id(),
                inputSchema: $this->definition->inputSchema,
                input: $input,
                ok: $ok,
                status: $status,
                safeData: $safeData,
                safeProposal: $safeProposal,
                exceptionClass: $exceptionClass,
                stableCode: $stableCode,
            );
        } catch (\Throwable) {
            // An optional evaluator must never affect a production tool call.
        }
    }

    private function stableExceptionCode(\Throwable $exception): ?string
    {
        return $this->allowlistedStableCode($exception->getMessage());
    }

    private function stableResultCode(?string $reason): ?string
    {
        return $this->allowlistedStableCode($reason);
    }

    private function allowlistedStableCode(?string $code): ?string
    {
        return in_array($code, [
            'credential_input_refused',
            'tool_result_budget_exhausted',
            'assistant_tool_not_registered',
            'capability_missing',
            'capability_denied',
            'capability_invalid',
            'projection_refused',
        ], true) ? $code : null;
    }

    private function projectionRefused(): string
    {
        return (string) json_encode([
            'ok' => false,
            'status' => 'projection_refused',
            'capability_id' => $this->definition->id(),
            'data' => null,
            'reason' => 'Data projection refused by security policy.',
        ], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        // When JSON Schema attributes are defined in definition->inputSchema, map them
        $properties = $this->definition->inputSchema['properties'] ?? [];
        $required = (array) ($this->definition->inputSchema['required'] ?? []);

        $fields = [];
        foreach ($properties as $name => $prop) {
            $rawType = $prop['type'] ?? 'string';
            $desc = $prop['description'] ?? '';

            $isNullable = false;
            $type = $rawType;
            if (is_array($rawType)) {
                $isNullable = in_array('null', $rawType, true);
                $typesWithoutNull = array_values(array_filter($rawType, fn ($t) => $t !== 'null'));
                $type = $typesWithoutNull[0] ?? 'string';
            }

            $field = match ($type) {
                'integer' => $schema->integer(),
                'boolean' => $schema->boolean(),
                'number' => $schema->number(),
                'array' => match ($prop['items']['type'] ?? 'string') {
                    'integer' => $schema->array()->items($schema->integer()),
                    'boolean' => $schema->array()->items($schema->boolean()),
                    'number' => $schema->array()->items($schema->number()),
                    default => $schema->array()->items($schema->string()),
                },
                default => $schema->string(),
            };

            if ($isNullable && method_exists($field, 'nullable')) {
                $field = $field->nullable();
            }

            $min = $prop['minimum'] ?? $prop['minLength'] ?? null;
            if ($min !== null && is_numeric($min) && method_exists($field, 'min')) {
                $field = $field->min((int) $min);
            }

            $max = $prop['maximum'] ?? $prop['maxLength'] ?? null;
            if ($max !== null && is_numeric($max) && method_exists($field, 'max')) {
                $field = $field->max((int) $max);
            }
            if (isset($prop['enum']) && is_array($prop['enum'])) {
                $enumValues = array_values(array_filter($prop['enum'], fn ($val) => $val !== null));
                if ($enumValues !== [] && method_exists($field, 'enum')) {
                    $field = $field->enum($enumValues);
                }
            }

            if ($desc !== '') {
                $field = $field->description($desc);
            }

            if (in_array($name, $required, true)) {
                $field = $field->required();
            }
            $fields[$name] = $field;
        }

        return $fields;
    }
}
