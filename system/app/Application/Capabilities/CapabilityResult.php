<?php

namespace App\Application\Capabilities;

use App\Application\Capabilities\Evidence\ConstraintEvidence;

/**
 * @phpstan-type ResultPayload array<string, mixed>
 */
final readonly class CapabilityResult
{
    /**
     * @param  ResultPayload|null  $data
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $ok,
        public string $status,
        public string $capabilityId,
        public ?array $data = null,
        public ?string $reason = null,
        public ?ConstraintEvidence $constraintEvidence = null,
        public array $meta = [],
    ) {}

    /**
     * @param  ResultPayload  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        string $capabilityId,
        array $data,
        ConstraintEvidence $evidence,
        array $meta = [],
    ): self {
        return new self(
            ok: true,
            status: 'ok',
            capabilityId: $capabilityId,
            data: $data,
            constraintEvidence: $evidence,
            meta: $meta,
        );
    }

    public static function denied(string $capabilityId, string $reason, ?ConstraintEvidence $evidence = null): self
    {
        return new self(
            ok: false,
            status: 'denied',
            capabilityId: $capabilityId,
            reason: $reason,
            constraintEvidence: $evidence,
        );
    }

    public static function invalid(string $capabilityId, string $reason): self
    {
        return new self(
            ok: false,
            status: 'invalid',
            capabilityId: $capabilityId,
            reason: $reason,
        );
    }

    public static function unsupported(string $capabilityId, string $reason): self
    {
        return new self(
            ok: false,
            status: 'unsupported',
            capabilityId: $capabilityId,
            reason: $reason,
        );
    }
}
