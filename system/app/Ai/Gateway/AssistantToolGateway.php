<?php

namespace App\Ai\Gateway;

use App\Ai\Context\InvocationContext;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Skills\SkillEngine;
use App\Application\Actions\ActionExecutionIdentity;
use App\Application\Capabilities\CapabilityKind;
use App\Application\Capabilities\CapabilityResult;
use App\Application\Capabilities\Evidence\ConstraintEvidence;
use App\Application\Capabilities\Schema\CapabilityInputValidator;
use App\Authorization\CapabilityAuthorizer;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Narrow inbound adapter for Xiam tool calls (D-XIAM-01).
 *
 * It never confirms or executes a mutation directly from client input.
 * Confirmation is a separate durable proposal operation owned by the application layer.
 */
final class AssistantToolGateway
{
    private const RETIRED_ENROLLMENT_TOOL_IDS = [
        'stamp_attempt_enrolling',
        'finalize_attempt_enrollment',
        'reverse_attempt_enrollment',
        'registrar.enrollment.stamp_enrolling@1',
        'registrar.enrollment.stamp_enrolled@1',
        'registrar.enrollment.reverse_stamp@1',
        'stamp_attempt_enrolling@1',
        'finalize_attempt_enrollment@1',
        'reverse_attempt_enrollment@1',
    ];

    private const RETIRED_ENROLLMENT_MESSAGE = 'Enrollment stamping has been retired. Admission clearance is confirmed by authorized Registrar staff via the admission clearance workflow.';

    private const RECEIPTED_ACTION_TOOL_IDS = [
        'exams.session.reservation.open@1',
        'exams.session.assign@1',
        'exams.session.publish@1',
        'exams.session.create@1',
        'exams.slot.reserve@1',
        'consultation.session.create@1',
        'consultation.session.reservation.open@1',
        'admission.office_inquiries.open@1',
        'admission.office_inquiries.reply@1',
        'release.result.publish@2',
    ];

    public function __construct(
        private readonly AssistantToolCatalog $catalog,
        private readonly EmergencyStop $emergencyStop,
        private readonly TaskGrantService $grants,
        private readonly SkillEngine $skills,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CapabilityInputValidator $validator,
        private readonly Container $container,
        private readonly ToolPolicyGate $policyGate,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function invoke(
        User|Authenticatable $actor,
        InvocationContext $context,
        string $toolId,
        array $input,
        bool $serverConfirmed = false,
    ): CapabilityResult {
        if (in_array($toolId, self::RETIRED_ENROLLMENT_TOOL_IDS, true)) {
            return new CapabilityResult(
                ok: false,
                status: 'denied',
                capabilityId: $toolId,
                data: [
                    'status' => 'retired',
                    'error' => self::RETIRED_ENROLLMENT_MESSAGE,
                ],
                reason: self::RETIRED_ENROLLMENT_MESSAGE,
                constraintEvidence: ConstraintEvidence::fail('retired_capability', ['BR-CLEARANCE-WORKFLOW']),
                meta: [
                    'correlation_id' => $context->correlationId ?: (string) Str::uuid(),
                    'context_epoch' => $context->contextEpoch,
                    'invocation_context' => $context->toArray(),
                ],
            );
        }
        if ($this->emergencyStop->active($context->institutionId)) {
            return CapabilityResult::denied($toolId, 'emergency_stop');
        }

        $tool = $this->catalog->getById($toolId);
        if ($tool === null) {
            return CapabilityResult::unsupported($toolId, 'assistant_tool_not_registered');
        }
        if (array_key_exists('_xiam_execution_identity', $input)) {
            return CapabilityResult::invalid($toolId, 'forbidden_field:_xiam_execution_identity');
        }

        if ((string) $actor->getAuthIdentifier() !== $context->actorId) {
            return CapabilityResult::denied($toolId, 'invocation_actor_mismatch');
        }
        if (! $actor instanceof User) {
            return CapabilityResult::denied($toolId, 'invalid_actor_type');
        }

        if ($context->actorType === 'staff') {
            $staffProfile = $actor->staffProfile()->first();
            if ($staffProfile !== null && ! $staffProfile->isActive()) {
                return CapabilityResult::denied($toolId, 'actor_revoked');
            }
        }

        if (! $serverConfirmed) {
            $forbiddenFields = ['execute', 'command', 'command_kind', 'command_name', 'confirmation_token'];
            foreach ($forbiddenFields as $field) {
                if (array_key_exists($field, $input)) {
                    return CapabilityResult::invalid($toolId, 'forbidden_field:'.$field);
                }
            }
        }
        if ($context->institutionId !== null && $context->institutionId > 0) {
            try {
                if (Schema::hasTable('xiam_institution_policies')
                    && ! XiamInstitutionPolicy::forInstitution($context->institutionId)->isToolEnabled($toolId)) {
                    return CapabilityResult::denied($toolId, 'tool_disabled_by_institution');
                }
            } catch (\Throwable) {
                return CapabilityResult::denied($toolId, 'tool_disabled_by_institution');
            }
        }

        $envelope = $this->policyGate->resolveEnvelope($context, $actor);
        if (! $this->policyGate->allowsTool($envelope, $tool, $context->institutionId)) {
            return CapabilityResult::denied($toolId, 'envelope_denied:'.$envelope);
        }

        if ($context->grantId !== null && $context->grantId !== '') {
            $grant = $this->grants->findActive($context->grantId);
            if ($grant === null) {
                return CapabilityResult::denied($toolId, 'grant_unusable');
            }

            if ((string) $grant->actor_id !== $context->actorId) {
                return CapabilityResult::denied($toolId, 'grant_actor_mismatch');
            }

            if ((string) $grant->context_epoch !== $context->contextEpoch) {
                $this->grants->revoke($grant, 'stale_context_epoch');

                return CapabilityResult::denied($toolId, 'stale_context_epoch');
            }

            $allowed = $grant->allowed_capability_ids ?? [];
            if (! in_array($toolId, $allowed, true)) {
                return CapabilityResult::denied($toolId, 'capability_not_on_skill_allowlist');
            }

            try {
                $this->skills->assertTargetAllowed($grant, $toolId, $input);
            } catch (RuntimeException $e) {
                return CapabilityResult::denied($toolId, $e->getMessage());
            }
        }

        $schemaError = $this->validator->validate($tool->inputSchema, $input);
        if ($schemaError !== null) {
            return CapabilityResult::invalid($toolId, $schemaError);
        }

        $scope = [];
        if ($actor->staffProfile()->exists() && $context->institutionId !== null) {
            $scope['institution_id'] = $context->institutionId;
        }

        if (! $this->authorize($tool, $actor, $scope)) {
            return CapabilityResult::denied(
                $toolId,
                'capability_missing',
                ConstraintEvidence::fail('capability_missing', $tool->capability?->ruleSetRefs ?? []),
            );
        }
        if (
            $serverConfirmed
            && in_array($toolId, self::RECEIPTED_ACTION_TOOL_IDS, true)
            && (
                $context->executionId === null
                || $context->executionId === ''
                || $context->canonicalDigest === null
                || $context->canonicalDigest === ''
            )
        ) {
            return CapabilityResult::invalid($toolId, 'action_execution_identity_required');
        }

        $handler = $this->container->make($tool->handlerClass);
        $usesContextualRead = $handler instanceof ContextualReadToolHandler;
        if ($usesContextualRead) {
            if ($tool->kind !== CapabilityKind::Read) {
                return CapabilityResult::unsupported($toolId, 'contextual_handler_requires_read_capability');
            }
        } elseif (! is_object($handler) || ! method_exists($handler, 'handle')) {
            return CapabilityResult::unsupported($toolId, 'handler_missing');
        }

        $handlerInput = $input;
        if ($serverConfirmed) {
            $handlerInput['execute'] = true;

            if (in_array($toolId, self::RECEIPTED_ACTION_TOOL_IDS, true)) {
                $handlerInput['_xiam_execution_identity'] = new ActionExecutionIdentity(
                    executionId: (string) $context->executionId,
                    capabilityId: $toolId,
                    canonicalDigest: (string) $context->canonicalDigest,
                );
            }
        }
        $data = $usesContextualRead
            ? $handler->handleWithContext($handlerInput, $actor, $context)
            : $handler->handle($handlerInput, $actor);

        if (is_array($data) && (
            ($data['mode'] ?? null) === 'denied'
            || ($data['page_state'] ?? null) === 'denied'
        )) {
            $deniedReason = is_string($data['reason'] ?? null) && $data['reason'] !== ''
                ? $data['reason']
                : 'handler_denied';

            return new CapabilityResult(
                ok: false,
                status: 'denied',
                capabilityId: $toolId,
                data: $data,
                reason: $deniedReason,
                constraintEvidence: ConstraintEvidence::fail($deniedReason, $tool->capability?->ruleSetRefs ?? []),
                meta: [
                    'correlation_id' => $context->correlationId ?: (string) Str::uuid(),
                    'context_epoch' => $context->contextEpoch,
                    'invocation_context' => $context->toArray(),
                ],
            );
        }

        if (is_array($data) && (
            ($data['page_state'] ?? null) === 'unavailable'
            || ($data['mode'] ?? null) === 'unavailable'
            || ($data['status'] ?? null) === 'unavailable'
        )) {
            $unavailableReason = is_string($data['reason'] ?? null) && $data['reason'] !== ''
                ? $data['reason']
                : 'unavailable';

            return new CapabilityResult(
                ok: false,
                status: 'unavailable',
                capabilityId: $toolId,
                data: $data,
                reason: $unavailableReason,
                constraintEvidence: ConstraintEvidence::fail($unavailableReason, $tool->capability?->ruleSetRefs ?? []),
                meta: [
                    'correlation_id' => $context->correlationId ?: (string) Str::uuid(),
                    'context_epoch' => $context->contextEpoch,
                    'invocation_context' => $context->toArray(),
                ],
            );
        }

        return CapabilityResult::success(
            capabilityId: $toolId,
            data: $data,
            evidence: ConstraintEvidence::pass($tool->capability?->ruleSetRefs ?? [], [
                'channel' => 'xiam_safe',
                'surface_key' => $context->surfaceProfile,
                'context_epoch' => $context->contextEpoch,
            ]),
            meta: [
                'correlation_id' => $context->correlationId ?: (string) Str::uuid(),
                'publication_version' => 'xiam.v1',
                'pack_used_for_authz' => false,
                'context_epoch' => $context->contextEpoch,
                'invocation_context' => $context->toArray(),
            ],
        );
    }

    private function authorize(AssistantToolDefinition $tool, User $actor, array $scope): bool
    {
        if ($actor->staffProfile()->exists()) {
            $profile = $actor->staffProfile()->first();
            if ($profile !== null && ! $profile->isActive()) {
                return false;
            }
        }

        $caps = $tool->applicationCapabilities;
        if ($caps === []) {
            return false;
        }

        if ($tool->authorizationMode === 'any') {
            return $this->authorizer->allowsAny($actor, $caps, $scope);
        }

        foreach ($caps as $cap) {
            if (! $this->authorizer->allows($actor, $cap, $scope)) {
                return false;
            }
        }

        return true;
    }
}
