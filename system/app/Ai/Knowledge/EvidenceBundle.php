<?php

namespace App\Ai\Knowledge;

use InvalidArgumentException;

final readonly class EvidenceBundle
{
    public const SOURCE_PUBLIC_FAQ = 'public_faq';

    public const SOURCE_INSTITUTION_REQUIREMENT = 'institution_requirement';

    public const SOURCE_INSTITUTION_POLICY = 'institution_policy';

    public const SOURCE_ADMISSION_MODEL_FACT = 'admission_model_fact';

    public const SOURCE_SCORING_EVALUATION_FACT = 'scoring_evaluation_fact';

    public const SOURCE_EXAMINATION_FACT = 'examination_fact';

    public const SOURCE_ACTOR_OWNED_RECORD = 'actor_owned_record';

    public const SOURCE_STAFF_OPERATIONAL_FACT = 'staff_operational_fact';

    /** @var list<string> */
    public const SOURCE_CLASSES = [
        self::SOURCE_PUBLIC_FAQ,
        self::SOURCE_INSTITUTION_REQUIREMENT,
        self::SOURCE_INSTITUTION_POLICY,
        self::SOURCE_ADMISSION_MODEL_FACT,
        self::SOURCE_SCORING_EVALUATION_FACT,
        self::SOURCE_EXAMINATION_FACT,
        self::SOURCE_ACTOR_OWNED_RECORD,
        self::SOURCE_STAFF_OPERATIONAL_FACT,
    ];

    /**
     * @param  list<string>  $sourceClasses
     * @param  list<array{citation_id: string, source_class: string, version_id: string, source_id: string, title: string, excerpt: string, score: float, locator?: string|null, as_of?: string|null}>  $citations
     * @param  array<string, mixed>  $projections
     */
    public function __construct(
        public string $decisionId,
        public string $actorId,
        public string $contextEpoch,
        public array $sourceClasses,
        public array $citations,
        public array $projections = [],
        public ?string $reason = null,
    ) {
        if ($this->decisionId === '' || $this->actorId === '' || $this->contextEpoch === '') {
            throw new InvalidArgumentException('evidence_context_required');
        }

        if (array_diff($this->sourceClasses, self::SOURCE_CLASSES) !== []) {
            throw new InvalidArgumentException('evidence_source_class_invalid');
        }
        if ($this->reason !== null && $this->reason !== '' && ! in_array($this->reason, [
            'no_support',
            'unbacked_source',
            'stale_evidence',
            'scope_denied',
            'unsupported_class',
            'no_policy_citation',
        ], true)) {
            throw new InvalidArgumentException("evidence_refusal_reason_invalid: {$this->reason}");
        }

        foreach ($this->citations as $citation) {
            if (! in_array($citation['source_class'], self::SOURCE_CLASSES, true)) {
                throw new InvalidArgumentException('evidence_source_class_invalid');
            }
            if ($citation['citation_id'] === '' || $citation['version_id'] === ''
                || $citation['source_id'] === '' || $citation['title'] === ''
                || $citation['excerpt'] === '' || $citation['score'] < 0.0
                || $citation['score'] > 1.0) {
                throw new InvalidArgumentException('evidence_citation_invalid');
            }

            if (isset($citation['as_of']) && is_string($citation['as_of']) && $citation['as_of'] !== '') {
                $asOfTimestamp = strtotime($citation['as_of']);
                if ($asOfTimestamp === false || $asOfTimestamp > (time() + 300)) {
                    throw new InvalidArgumentException('evidence_citation_staleness_invalid');
                }
            }
        }
    }

    public function hasSupport(): bool
    {
        return $this->citations !== [] || $this->projections !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'decision_id' => $this->decisionId,
            'actor_id' => $this->actorId,
            'context_epoch' => $this->contextEpoch,
            'source_classes' => $this->sourceClasses,
            'citations' => $this->citations,
            'projections' => $this->projections,
            'reason' => $this->reason,
        ];
    }
}
