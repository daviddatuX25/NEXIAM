<?php

namespace App\Ai\Knowledge;

use InvalidArgumentException;

/**
 * Registry defining the 8 fixed grounding source classes (Cut 4).
 *
 * Scoped live queries for structured facts are unbacked until separate domain authority
 * contracts exist. Retired classes (e.g. governed_document/PDF) are rejected.
 */
final class GroundingSourceRegistry
{
    /** @var array<string, GroundingSourceDefinition> */
    private array $definitions = [];

    /**
     * @param  list<GroundingSourceDefinition>|null  $definitions
     */
    public function __construct(?array $definitions = null)
    {
        $sources = $definitions ?? $this->defaultDefinitions();
        foreach ($sources as $source) {
            $this->definitions[$source->sourceClass] = $source;
        }
    }

    public function get(string $sourceClass): ?GroundingSourceDefinition
    {
        return $this->definitions[$sourceClass] ?? null;
    }

    public function require(string $sourceClass): GroundingSourceDefinition
    {
        return $this->get($sourceClass)
            ?? throw new InvalidArgumentException("grounding_source_class_unsupported: {$sourceClass}");
    }

    /**
     * @return list<GroundingSourceDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * @return list<string>
     */
    public function unbackedClasses(): array
    {
        $unbacked = [];
        foreach ($this->definitions as $def) {
            if ($def->status === GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED) {
                $unbacked[] = $def->sourceClass;
            }
        }

        return $unbacked;
    }

    /**
     * @return list<string>
     */
    public function activeIndexedClasses(): array
    {
        $indexed = [];
        foreach ($this->definitions as $def) {
            if ($def->isIndexed()) {
                $indexed[] = $def->sourceClass;
            }
        }

        return $indexed;
    }

    /**
     * @return list<GroundingSourceDefinition>
     */
    private function defaultDefinitions(): array
    {
        return [
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_PUBLIC_FAQ,
                status: GroundingSourceDefinition::STATUS_ACTIVE_INDEXED,
                description: 'Office-governed public and staff FAQ text entries.',
                requiredScopeKey: 'owning_office',
                maxCitations: 2,
                freshnessTtlSeconds: 86400 * 30,
                requiresStaffActor: false,
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_INSTITUTION_POLICY,
                status: GroundingSourceDefinition::STATUS_ACTIVE_INDEXED,
                description: 'Office-governed institutional policy text entries.',
                requiredScopeKey: 'owning_office',
                maxCitations: 2,
                freshnessTtlSeconds: 86400 * 30,
                requiresStaffActor: true,
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_ACTOR_OWNED_RECORD,
                status: GroundingSourceDefinition::STATUS_ACTIVE_PROJECTION,
                description: 'Applicant-owned admission attempt and score projection.',
                requiredScopeKey: 'applicant_identity',
                maxCitations: 1,
                freshnessTtlSeconds: 3600,
                requiresStaffActor: false,
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_INSTITUTION_REQUIREMENT,
                status: GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED,
                description: 'Live academic cycle and institutional requirement facts (fails closed).',
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_ADMISSION_MODEL_FACT,
                status: GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED,
                description: 'Live admission model and quota calculation facts (fails closed).',
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_SCORING_EVALUATION_FACT,
                status: GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED,
                description: 'Live scoring scale and exam conversion facts (fails closed).',
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_EXAMINATION_FACT,
                status: GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED,
                description: 'Live examination session and room capacity facts (fails closed).',
            ),
            new GroundingSourceDefinition(
                sourceClass: EvidenceBundle::SOURCE_STAFF_OPERATIONAL_FACT,
                status: GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED,
                description: 'Live staff personnel and operational assignment facts (fails closed).',
            ),
        ];
    }
}
