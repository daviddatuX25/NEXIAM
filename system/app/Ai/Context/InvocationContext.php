<?php

namespace App\Ai\Context;

use InvalidArgumentException;

/**
 * Server-resolved authority envelope for one Xiam invocation.
 *
 * This DTO is safe to serialize into a queued run because it contains stable
 * identifiers and policy context, never a request or session object.
 */
final readonly class InvocationContext
{
    public function __construct(
        public string $actorId,
        public string $actorType,
        public ?int $institutionId,
        public string $surfaceProfile,
        public string $mode,
        public string $contextEpoch,
        public string $purpose,
        public string $correlationId,
        public ?string $browserSessionId = null,
        public ?string $routeName = null,
        public ?string $executionId = null,
        public ?string $canonicalDigest = null,
        public ?string $grantId = null,
        public ?string $conversationId = null,
    ) {
        if ($this->actorId === '' || $this->actorType === '') {
            throw new InvalidArgumentException('Invocation actor is required.');
        }

        if ($this->surfaceProfile === '' || $this->mode === '' || $this->contextEpoch === '') {
            throw new InvalidArgumentException('Invocation policy context is required.');
        }

        if ($this->purpose === '' || $this->correlationId === '') {
            throw new InvalidArgumentException('Invocation provenance is required.');
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'actor_id' => $this->actorId,
            'actor_type' => $this->actorType,
            'institution_id' => $this->institutionId,
            'surface_profile' => $this->surfaceProfile,
            'mode' => $this->mode,
            'context_epoch' => $this->contextEpoch,
            'purpose' => $this->purpose,
            'correlation_id' => $this->correlationId,
            'browser_session_id' => $this->browserSessionId,
            'route_name' => $this->routeName,
            'execution_id' => $this->executionId,
            'canonical_digest' => $this->canonicalDigest,
            'grant_id' => $this->grantId,
            'conversation_id' => $this->conversationId,
        ];
    }
}
