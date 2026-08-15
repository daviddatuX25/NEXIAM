<?php

namespace App\Application\Capabilities;

use App\Application\Capabilities\Evidence\ConstraintEvidence;
use App\Application\Capabilities\Projection\ChannelProjector;
use App\Application\Capabilities\Publication\ActiveRulePublication;
use App\Application\Capabilities\Schema\CapabilityInputValidator;
use App\Authorization\CapabilityAuthorizer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

/**
 * Non-bypassable capability dispatch (AD-1).
 */
final class CapabilityKernel
{
    public function __construct(
        private readonly CapabilityRegistry $registry,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CapabilityInputValidator $validator,
        private readonly ChannelProjector $projector,
        private readonly ActiveRulePublication $publications,
        private readonly Container $container,
    ) {}

    public function invoke(CapabilityInvocationRequest $request): CapabilityResult
    {
        $capabilityId = $request->key.'@'.$request->version;
        $definition = $this->registry->get($request->key, $request->version);

        if ($definition === null) {
            return CapabilityResult::unsupported($capabilityId, 'capability_not_registered');
        }

        if (! in_array($request->channel, $definition->channels, true)) {
            return CapabilityResult::invalid($capabilityId, 'unsupported_channel');
        }

        $publication = $this->publications->current();
        if (! $publication->allows($definition->publicationBinding)) {
            return CapabilityResult::denied(
                $capabilityId,
                'publication_incompatible',
                ConstraintEvidence::fail('publication_incompatible', $definition->ruleSetRefs),
            );
        }

        $schemaError = $this->validator->validate($definition->inputSchema, $request->input);
        if ($schemaError !== null) {
            return CapabilityResult::invalid($capabilityId, $schemaError);
        }

        if (! $this->authorize($definition, $request->actor)) {
            return CapabilityResult::denied(
                $capabilityId,
                'capability_missing',
                ConstraintEvidence::fail('capability_missing', $definition->ruleSetRefs, [
                    // page_pack is never an authorization grant (AD-3).
                    'page_pack_key' => $request->pagePackKey,
                    'surface_key' => $request->surfaceKey,
                ]),
            );
        }

        $handler = $this->container->make($definition->handlerClass);
        if (! is_object($handler) || ! method_exists($handler, 'handle')) {
            return CapabilityResult::unsupported($capabilityId, 'handler_missing');
        }

        /** @var array<string, mixed> $authoritative */
        $authoritative = $handler->handle($request->input);
        $projected = $this->projector->project($definition, $request->channel, $authoritative);

        return CapabilityResult::success(
            capabilityId: $capabilityId,
            data: $projected,
            evidence: ConstraintEvidence::pass($definition->ruleSetRefs, [
                'channel' => $request->channel,
                'page_pack_key' => $request->pagePackKey,
                'surface_key' => $request->surfaceKey,
            ]),
            meta: [
                'correlation_id' => $request->correlationId ?? (string) Str::uuid(),
                'publication_version' => $publication->version,
                // Explicit: pack absence must not appear as an authz factor.
                'pack_used_for_authz' => false,
            ],
        );
    }

    private function authorize(CapabilityDefinition $definition, mixed $actor): bool
    {
        $decisions = [];
        foreach ($definition->applicationCapabilities as $capability) {
            $decisions[] = $this->authorizer->allows($actor, $capability);
        }

        if ($definition->authorizationMode === 'any') {
            return in_array(true, $decisions, true);
        }

        return ! in_array(false, $decisions, true);
    }
}
