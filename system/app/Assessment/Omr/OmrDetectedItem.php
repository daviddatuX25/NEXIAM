<?php

namespace App\Assessment\Omr;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_unique;
use function count;
use function hash;
use function hash_equals;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function preg_match;

/**
 * Per-item answer-key-blind machine evidence.
 *
 * `observedOptions` is the ordered set considered marked by the provider.
 * `optionObservations` preserves the provider's per-option measurements and
 * classifications without asking Laravel or the browser to reinterpret them.
 * Expected/correct options are deliberately absent. Human review is a
 * separate revision and must never be represented by this value object.
 */
final readonly class OmrDetectedItem
{
    public const SCHEMA_VERSION = 'nexiam.omr.detected-item.v1';

    public const STATE_MARKED = 'marked';

    public const STATE_BLANK = 'blank';

    public const STATE_AMBIGUOUS = 'ambiguous';

    public const STATE_UNREADABLE = 'unreadable';

    public const STATES = [self::STATE_MARKED, self::STATE_BLANK, self::STATE_AMBIGUOUS, self::STATE_UNREADABLE];

    private const FORBIDDEN_KEYS = [
        'answer_key',
        'correct_option',
        'correct_option_code',
        'expected',
        'expected_answer',
        'expected_option',
        'correctness',
        'score',
        'scores',
        'area_scores',
        'per_area_scores',
        'converted_score',
    ];

    /**
     * @param  list<string>  $observedOptions
     * @param  list<OmrOptionObservation>  $optionObservations
     * @param  list<string>  $issueCodes
     */
    public function __construct(
        public int $position,
        public string $areaCode,
        public int $questionNo,
        public ?string $selectedOption,
        public string $state,
        public ?float $confidence,
        public OmrRegion $region,
        public array $observedOptions,
        public array $optionObservations,
        public array $issueCodes,
        public bool $legacyEvidenceLimited = false,
    ) {
        $this->evidenceHash = hash(
            'sha256',
            json_encode($this->canonicalPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    public string $evidenceHash;

    /**
     * Parse the wire representation used by provider responses and stored
     * historical proposals.
     *
     * New live responses must carry both candidate fields. The historical
     * path is explicit: when either field is absent, the resulting item is
     * marked `legacy_evidence_limited` and cannot claim candidate completeness.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws OmrInvalidDetectionOutput
     */
    public static function fromArray(array $data, bool $historical = false): self
    {
        foreach (self::FORBIDDEN_KEYS as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $data)) {
                throw new OmrInvalidDetectionOutput("Detected item contains forbidden field '{$forbiddenKey}'.");
            }
        }
        $hasObservedOptions = array_key_exists('observed_options', $data);
        $hasOptionObservations = array_key_exists('option_observations', $data);
        $hasIssueCodes = array_key_exists('issue_codes', $data);
        if ((! $hasObservedOptions || ! $hasOptionObservations || ! $hasIssueCodes) && ! $historical) {
            throw new OmrInvalidDetectionOutput('Candidate-complete item fields are missing.');
        }

        $schemaVersion = $data['schema_version'] ?? self::SCHEMA_VERSION;
        if (! is_string($schemaVersion) || $schemaVersion !== self::SCHEMA_VERSION) {
            throw new OmrInvalidDetectionOutput('Detected item schema version is unsupported.');
        }

        $legacyEvidenceLimited = $data['legacy_evidence_limited'] ?? false;
        if (! is_bool($legacyEvidenceLimited)) {
            throw new OmrInvalidDetectionOutput('Legacy evidence marker is malformed.');
        }
        $legacyEvidenceLimited = $legacyEvidenceLimited
            || ($historical && (! $hasObservedOptions || ! $hasOptionObservations || ! $hasIssueCodes));

        $observedOptions = $hasObservedOptions
            ? self::stringList($data['observed_options'], 'observed options')
            : [];
        $optionObservations = $hasOptionObservations
            ? self::optionObservations($data['option_observations'])
            : [];
        $issueCodes = array_key_exists('issue_codes', $data)
            ? self::stringList($data['issue_codes'], 'item issue codes')
            : [];

        if (! is_int($data['position'] ?? null)
            || ! is_string($data['area_code'] ?? null)
            || ! is_int($data['question_no'] ?? null)
            || ! is_string($data['state'] ?? null)
            || ! is_array($data['region'] ?? null)) {
            throw new OmrInvalidDetectionOutput('Detected item identity or region is malformed.');
        }

        $selectedOption = $data['selected_option'] ?? null;
        if ($selectedOption !== null && (! is_string($selectedOption) || $selectedOption === '')) {
            throw new OmrInvalidDetectionOutput('Detected item selected option is malformed.');
        }

        $confidence = $data['confidence'] ?? null;
        if ($confidence !== null
            && (! is_int($confidence) && ! is_float($confidence) || ! is_finite((float) $confidence))) {
            throw new OmrInvalidDetectionOutput('Detected item confidence is malformed.');
        }

        $item = new self(
            position: $data['position'],
            areaCode: $data['area_code'],
            questionNo: $data['question_no'],
            selectedOption: $selectedOption,
            state: $data['state'],
            confidence: $confidence === null ? null : (float) $confidence,
            region: OmrRegion::fromArray($data['region']),
            observedOptions: $observedOptions,
            optionObservations: $optionObservations,
            issueCodes: $issueCodes,
            legacyEvidenceLimited: $legacyEvidenceLimited,
        );

        $providedHash = $data['evidence_hash'] ?? null;
        if ($providedHash !== null
            && (! is_string($providedHash)
                || preg_match('/^[a-f0-9]{64}$/', $providedHash) !== 1
                || ! hash_equals($item->evidenceHash, $providedHash))) {
            throw new OmrInvalidDetectionOutput('Detected item evidence hash is invalid.');
        }
        if ($historical && $providedHash === null) {
            return new self(
                position: $item->position,
                areaCode: $item->areaCode,
                questionNo: $item->questionNo,
                selectedOption: $item->selectedOption,
                state: $item->state,
                confidence: $item->confidence,
                region: $item->region,
                observedOptions: $item->observedOptions,
                optionObservations: $item->optionObservations,
                issueCodes: $item->issueCodes,
                legacyEvidenceLimited: true,
            );
        }

        return $item;
    }

    /**
     * Build candidate-complete evidence for in-process detectors that operate
     * on the request's declared geometry.
     *
     * @param  list<string>  $observedOptions
     * @param  list<string>  $issueCodes
     */
    public static function fromGeometry(
        OmrItemGeometry $geometry,
        ?string $selectedOption,
        string $state,
        ?float $confidence,
        ?array $observedOptions = null,
        array $issueCodes = [],
    ): self {
        $candidates = $observedOptions ?? ($selectedOption === null ? [] : [$selectedOption]);

        return new self(
            position: $geometry->position,
            areaCode: $geometry->areaCode,
            questionNo: $geometry->questionNo,
            selectedOption: $selectedOption,
            state: $state,
            confidence: $confidence,
            region: $geometry->region,
            observedOptions: $candidates,
            optionObservations: array_map(
                static fn (OmrOptionGeometry $option): OmrOptionObservation => new OmrOptionObservation(
                    code: $option->code,
                    mean: null,
                    classification: in_array($option->code, $candidates, true) ? 'observed' : 'not_observed',
                ),
                $geometry->options,
            ),
            issueCodes: $issueCodes,
        );
    }

    public function isMarked(): bool
    {
        return $this->state === self::STATE_MARKED;
    }

    public function isCandidateComplete(): bool
    {
        return ! $this->legacyEvidenceLimited;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'position' => $this->position,
            'area_code' => $this->areaCode,
            'question_no' => $this->questionNo,
            'selected_option' => $this->selectedOption,
            'state' => $this->state,
            'confidence' => $this->confidence,
            'region' => $this->region->toArray(),
            'observed_options' => $this->observedOptions,
            'option_observations' => array_map(
                static fn (OmrOptionObservation $observation): array => $observation->toArray(),
                $this->optionObservations,
            ),
            'issue_codes' => $this->issueCodes,
            'legacy_evidence_limited' => $this->legacyEvidenceLimited,
            'evidence_hash' => $this->evidenceHash,
        ];
    }

    public function evidenceHash(): string
    {
        return $this->evidenceHash;
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'position' => $this->position,
            'area_code' => $this->areaCode,
            'question_no' => $this->questionNo,
            'selected_option' => $this->selectedOption,
            'state' => $this->state,
            'confidence' => $this->confidence,
            'region' => $this->region->toArray(),
            'observed_options' => $this->observedOptions,
            'option_observations' => array_map(
                static fn (OmrOptionObservation $observation): array => $observation->toArray(),
                $this->optionObservations,
            ),
            'issue_codes' => $this->issueCodes,
            'legacy_evidence_limited' => $this->legacyEvidenceLimited,
        ];
    }

    /**
     * @return list<string>
     *
     * @throws OmrInvalidDetectionOutput
     */
    private static function stringList(mixed $value, string $label): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new OmrInvalidDetectionOutput("Detected item {$label} must be an ordered list.");
        }

        $result = [];
        foreach ($value as $entry) {
            if (! is_string($entry) || $entry === '') {
                throw new OmrInvalidDetectionOutput("Detected item {$label} contains a malformed value.");
            }
            $result[] = $entry;
        }

        if (count($result) !== count(array_unique($result))) {
            throw new OmrInvalidDetectionOutput("Detected item {$label} contains duplicates.");
        }

        return $result;
    }

    /**
     * @return list<OmrOptionObservation>
     *
     * @throws OmrInvalidDetectionOutput
     */
    private static function optionObservations(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new OmrInvalidDetectionOutput('Detected item option observations must be an ordered list.');
        }

        $result = [];
        $codes = [];
        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw new OmrInvalidDetectionOutput('Detected item option observation is malformed.');
            }
            $observation = OmrOptionObservation::fromArray($entry);
            if (in_array($observation->code, $codes, true)) {
                throw new OmrInvalidDetectionOutput('Detected item option observations contain duplicates.');
            }
            $codes[] = $observation->code;
            $result[] = $observation;
        }

        return $result;
    }
}
