<?php

namespace App\Ai\Knowledge;

use InvalidArgumentException;

/**
 * Code-owned contract defining an authorized grounding source class (Cut 4 / AD-2).
 */
final readonly class GroundingSourceDefinition
{
    public const STATUS_ACTIVE_INDEXED = 'active_indexed';

    public const STATUS_ACTIVE_PROJECTION = 'active_projection';

    public const STATUS_UNBACKED_STRUCTURED = 'unbacked_structured';

    public function __construct(
        public string $sourceClass,
        public string $status,
        public string $description,
        public ?string $requiredScopeKey = null,
        public int $maxCitations = 2,
        public ?int $freshnessTtlSeconds = null,
        public bool $requiresStaffActor = false,
    ) {
        if (! in_array($this->sourceClass, EvidenceBundle::SOURCE_CLASSES, true)) {
            throw new InvalidArgumentException("grounding_source_class_unsupported: {$this->sourceClass}");
        }

        if (! in_array($this->status, [
            self::STATUS_ACTIVE_INDEXED,
            self::STATUS_ACTIVE_PROJECTION,
            self::STATUS_UNBACKED_STRUCTURED,
        ], true)) {
            throw new InvalidArgumentException("grounding_source_status_invalid: {$this->status}");
        }
    }

    public function isSupported(): bool
    {
        return $this->status !== self::STATUS_UNBACKED_STRUCTURED;
    }

    public function isIndexed(): bool
    {
        return $this->status === self::STATUS_ACTIVE_INDEXED;
    }
}
