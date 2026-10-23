<?php

namespace App\Assessment\Omr\Diagnostics;

use InvalidArgumentException;
use JsonSerializable;

use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function hash;
use function in_array;
use function is_array;
use function is_finite;
use function is_int;
use function is_string;
use function json_encode;
use function preg_match;
use function str_contains;
use function strtoupper;
use function usort;

/**
 * Immutable, versioned diagnostic scenario manifest.
 *
 * Scenario inputs are deliberately kept separate from machine evidence. This
 * value object only describes deterministic challenge intent and provenance;
 * it never claims that a provider produced the declared outcome.
 */
final readonly class OmrDiagnosticScenarioManifest implements JsonSerializable
{
    public const SCHEMA = 'nexiam.omr.diagnostic-scenario-manifest.v1';

    public const MANIFEST_VERSION = 'v1';

    public const MANIFEST_VERSION_V2 = 'v2';

    public const QUICK_REHEARSAL_V1 = 'quick_rehearsal_v1';

    public const QUICK_REHEARSAL_V2 = 'quick_rehearsal_v2';

    public const FULL_DIAGNOSTIC_V1 = 'full_diagnostic_v1';

    public const LANE_GENERATED = 'generated';

    public const LANE_HAND_SHADED = 'hand_shaded';

    public const EXPECTED_EXACT = 'exact';

    public const EXPECTED_REVIEW_REQUIRED = 'review_required';

    public const EXPECTED_CAPTURE_REJECTED = 'capture_rejected';

    public const MARK_NORMAL_DARK = 'normal_dark';

    public const MARK_FAINT = 'light_faint_threshold';

    public const MARK_PARTIAL_SMALL = 'partial_small';

    public const MARK_OFF_CENTER = 'off_center';

    public const MARK_OVERFILLED = 'overfilled';

    public const MARK_CHECK_CROSS = 'check_cross';

    public const MARK_ERASED = 'erased';

    public const MARK_SMUDGED = 'smudged';

    public const MARK_STRAY = 'stray';

    public const ANSWER_EXPECTED_MATCH = 'expected_match';

    public const ANSWER_DEFINITE_MISMATCH = 'definite_mismatch';

    public const ANSWER_BLANK = 'blank';

    public const ANSWER_DOUBLE_WITH_EXPECTED = 'double_with_expected';

    public const ANSWER_DOUBLE_WITHOUT_EXPECTED = 'double_without_expected';

    public const ANSWER_TRIPLE = 'triple';

    public const ANSWER_UNREADABLE = 'unreadable';

    public const ANSWER_BETWEEN_BUBBLE = 'between_bubble';

    public const CAPTURE_CLEAN_SCANNER = 'clean_scanner';

    public const CAPTURE_PHONE = 'phone';

    public const CAPTURE_ROTATION = 'rotation';

    public const CAPTURE_PERSPECTIVE = 'perspective';

    public const CAPTURE_UNEVEN_LIGHTING = 'uneven_lighting';

    public const CAPTURE_LOW_CONTRAST = 'low_contrast';

    public const CAPTURE_BLUR = 'blur';

    public const CAPTURE_SHADOW = 'shadow';

    public const CAPTURE_PARTIAL_PAGE = 'partial_page';

    /** @var list<string> */
    public const MARK_STYLES = [
        self::MARK_NORMAL_DARK,
        self::MARK_FAINT,
        self::MARK_PARTIAL_SMALL,
        self::MARK_OFF_CENTER,
        self::MARK_OVERFILLED,
        self::MARK_CHECK_CROSS,
        self::MARK_ERASED,
        self::MARK_SMUDGED,
        self::MARK_STRAY,
    ];

    /** @var list<string> */
    public const ANSWER_CONDITIONS = [
        self::ANSWER_EXPECTED_MATCH,
        self::ANSWER_DEFINITE_MISMATCH,
        self::ANSWER_BLANK,
        self::ANSWER_DOUBLE_WITH_EXPECTED,
        self::ANSWER_DOUBLE_WITHOUT_EXPECTED,
        self::ANSWER_TRIPLE,
        self::ANSWER_UNREADABLE,
        self::ANSWER_BETWEEN_BUBBLE,
    ];

    /** @var list<string> */
    public const CAPTURE_CONDITIONS = [
        self::CAPTURE_CLEAN_SCANNER,
        self::CAPTURE_PHONE,
        self::CAPTURE_ROTATION,
        self::CAPTURE_PERSPECTIVE,
        self::CAPTURE_UNEVEN_LIGHTING,
        self::CAPTURE_LOW_CONTRAST,
        self::CAPTURE_BLUR,
        self::CAPTURE_SHADOW,
        self::CAPTURE_PARTIAL_PAGE,
    ];

    /** @var list<string> */
    public const LANES = [self::LANE_GENERATED, self::LANE_HAND_SHADED];

    /** @var list<string> */
    public const EXPECTED_CLASSES = [
        self::EXPECTED_EXACT,
        self::EXPECTED_REVIEW_REQUIRED,
        self::EXPECTED_CAPTURE_REJECTED,
    ];

    public array $payload;

    public string $schemaVersion;

    public string $manifestVersion;

    public string $plan;

    public string $lane;

    /** @var list<array<string, mixed>> */
    public array $scenarios;

    /** @var array<string, mixed> */
    public array $provenance;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $normalized = self::normalizeManifest($payload);
        self::validateManifest($normalized);

        $this->payload = $normalized;
        $this->schemaVersion = $normalized['schema_version'];
        $this->manifestVersion = $normalized['manifest_version'];
        $this->plan = $normalized['plan'];
        $this->lane = $normalized['lane'];
        $this->scenarios = $normalized['scenarios'];
        $this->provenance = $normalized['provenance'];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /**
     * Build the original fixed Quick Rehearsal v1 manifest from the first
     * canonical scorable items.
     *
     * @param  OmrDiagnosticTarget|array<string, mixed>  $targetOrContext
     */
    public static function quickRehearsalV1(
        OmrDiagnosticTarget|array $targetOrContext,
        string $lane = self::LANE_GENERATED,
        ?array $pins = null,
    ): self {
        [$context, $items] = self::contextAndItems($targetOrContext, $pins);

        return self::quickRehearsal(
            context: $context,
            items: $items,
            lane: $lane,
            manifestVersion: self::MANIFEST_VERSION,
            plan: self::QUICK_REHEARSAL_V1,
        );
    }

    /**
     * Build Quick Rehearsal v2 against the proof plan's exact sampled rows.
     * This keeps guide instructions, generated marks, and expected evidence
     * on one canonical item/option set.
     *
     * @param  OmrDiagnosticTarget|array<string, mixed>  $targetOrContext
     * @param  list<array{page_no:int,label:string}>  $sampleManifest
     * @param  array<string, string>  $expectedVector
     */
    public static function quickRehearsalV2(
        OmrDiagnosticTarget|array $targetOrContext,
        array $sampleManifest,
        array $expectedVector,
        string $lane = self::LANE_GENERATED,
        ?array $pins = null,
    ): self {
        [$context, $items] = self::contextAndItems($targetOrContext, $pins);
        $itemsByIdentity = [];
        foreach ($items as $item) {
            $itemsByIdentity[(int) $item['page_no'].'|'.(string) $item['label']] = $item;
        }

        $selectedItems = [];
        foreach ($sampleManifest as $sample) {
            $pageNo = (int) ($sample['page_no'] ?? 0);
            $label = (string) ($sample['label'] ?? '');
            $key = $pageNo.'|'.$label;
            $item = $itemsByIdentity[$key] ?? null;
            $expectedOption = $expectedVector[$label] ?? null;
            if (! is_array($item) || ! is_string($expectedOption) || ! in_array($expectedOption, $item['options'], true)) {
                throw new InvalidArgumentException('scenario_quick_sample_invalid: every sampled row requires canonical geometry and an expected option.');
            }
            $item['options'] = [
                $expectedOption,
                ...array_values(array_filter(
                    $item['options'],
                    static fn (string $option): bool => $option !== $expectedOption,
                )),
            ];
            $selectedItems[] = $item;
        }

        if ($selectedItems === []) {
            throw new InvalidArgumentException('scenario_quick_sample_count_invalid: Quick Rehearsal v2 requires at least one sampled row.');
        }

        return self::quickRehearsal(
            context: $context,
            items: $selectedItems,
            lane: $lane,
            manifestVersion: self::MANIFEST_VERSION_V2,
            plan: self::QUICK_REHEARSAL_V2,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $items
     */
    private static function quickRehearsal(
        array $context,
        array $items,
        string $lane,
        string $manifestVersion,
        string $plan,
    ): self {
        if ($items === []) {
            throw new InvalidArgumentException('scenario_quick_items_missing: canonical geometry must expose at least one scorable item.');
        }

        $captureVariants = self::quickCaptureVariants();
        $scenarios = [];
        for ($index = 0; $index < 18; $index++) {
            $item = $items[$index % count($items)];
            $markStyle = self::MARK_STYLES[$index % count(self::MARK_STYLES)];
            $answerCondition = self::ANSWER_CONDITIONS[$index % count(self::ANSWER_CONDITIONS)];
            $captureVariant = $captureVariants[$index % count($captureVariants)];
            if ($index === 17) {
                $captureVariant = [
                    'id' => 'partial-page',
                    'condition' => self::CAPTURE_PARTIAL_PAGE,
                    'conditions' => [self::CAPTURE_PARTIAL_PAGE],
                    'transform' => [
                        'kind' => 'partial_page',
                        'visible_fraction' => 0.72,
                        'crop_edge' => 'bottom_right',
                    ],
                ];
            }

            $scenarios[] = self::scenarioDefinition(
                context: $context,
                manifestVersion: $manifestVersion,
                plan: $plan,
                lane: $lane,
                item: $item,
                seed: self::seed($context, $plan, $index),
                markStyle: $markStyle,
                answerCondition: $answerCondition,
                capture: $captureVariant,
            );
        }

        $payload = self::header($context, $plan, $lane, $manifestVersion);
        $payload['capture_variants'] = $captureVariants;
        $payload['limits'] = [
            'item_count' => 18,
            'max_generated_capture_variants' => 4,
            'partial_page_rejection' => true,
        ];
        $payload['coverage_requirements'] = self::quickCoverageRequirements();
        $payload['scenarios'] = $scenarios;

        return new self($payload);
    }

    /**
     * Build a deterministic targeted/pairwise full diagnostic manifest. Full
     * coverage is finite: all mark × answer pairs, one targeted mark × capture
     * row per mark family, and one targeted answer × capture row per answer.
     *
     * @param  OmrDiagnosticTarget|array<string, mixed>  $targetOrContext
     */
    public static function fullDiagnosticV1(
        OmrDiagnosticTarget|array $targetOrContext,
        string $lane = self::LANE_GENERATED,
        ?array $pins = null,
    ): self {
        [$context, $items] = self::contextAndItems($targetOrContext, $pins);
        if ($items === []) {
            throw new InvalidArgumentException('scenario_full_items_missing: canonical geometry must expose at least one scorable item.');
        }

        $scenarios = [];
        $index = 0;
        foreach (self::MARK_STYLES as $markStyle) {
            foreach (self::ANSWER_CONDITIONS as $answerCondition) {
                $scenarios[] = self::scenarioDefinition(
                    context: $context,
                    manifestVersion: self::MANIFEST_VERSION,
                    plan: self::FULL_DIAGNOSTIC_V1,
                    lane: $lane,
                    item: $items[$index % count($items)],
                    seed: self::seed($context, self::FULL_DIAGNOSTIC_V1.'|mark-answer', $index),
                    markStyle: $markStyle,
                    answerCondition: $answerCondition,
                    capture: self::captureVariant(self::CAPTURE_CLEAN_SCANNER),
                );
                $index++;
            }
        }

        foreach (self::MARK_STYLES as $markIndex => $markStyle) {
            $captureCondition = self::CAPTURE_CONDITIONS[$markIndex % count(self::CAPTURE_CONDITIONS)];
            $scenarios[] = self::scenarioDefinition(
                context: $context,
                manifestVersion: self::MANIFEST_VERSION,
                plan: self::FULL_DIAGNOSTIC_V1,
                lane: $lane,
                item: $items[$index % count($items)],
                seed: self::seed($context, self::FULL_DIAGNOSTIC_V1.'|mark-capture', $index),
                markStyle: $markStyle,
                answerCondition: self::ANSWER_EXPECTED_MATCH,
                capture: self::captureVariant($captureCondition),
            );
            $index++;
        }

        foreach (self::ANSWER_CONDITIONS as $answerIndex => $answerCondition) {
            $captureCondition = self::CAPTURE_CONDITIONS[$answerIndex % count(self::CAPTURE_CONDITIONS)];
            $scenarios[] = self::scenarioDefinition(
                context: $context,
                manifestVersion: self::MANIFEST_VERSION,
                plan: self::FULL_DIAGNOSTIC_V1,
                lane: $lane,
                item: $items[$index % count($items)],
                seed: self::seed($context, self::FULL_DIAGNOSTIC_V1.'|answer-capture', $index),
                markStyle: self::MARK_NORMAL_DARK,
                answerCondition: $answerCondition,
                capture: self::captureVariant($captureCondition),
            );
            $index++;
        }

        $payload = self::header($context, self::FULL_DIAGNOSTIC_V1, $lane);
        $payload['capture_variants'] = array_map(
            static fn (string $condition): array => self::captureVariant($condition),
            self::CAPTURE_CONDITIONS,
        );
        $payload['coverage_requirements'] = self::fullCoverageRequirements();
        $payload['scenarios'] = $scenarios;

        return new self($payload);
    }

    /**
     * Explicit constructor for callers that already have a review manifest
     * and pinned target identity but do not have an OmrDiagnosticTarget.
     *
     * @param  array<string, mixed>  $reviewManifest
     * @param  array<string, mixed>  $pins
     */
    public static function fromReviewManifest(
        array $reviewManifest,
        array $pins,
        string $plan = self::QUICK_REHEARSAL_V1,
        string $lane = self::LANE_GENERATED,
    ): self {
        return match ($plan) {
            self::QUICK_REHEARSAL_V1 => self::quickRehearsalV1([
                'review_manifest' => $reviewManifest,
                ...$pins,
            ], $lane),
            self::FULL_DIAGNOSTIC_V1 => self::fullDiagnosticV1([
                'review_manifest' => $reviewManifest,
                ...$pins,
            ], $lane),
            default => throw new InvalidArgumentException('scenario_plan_invalid: unsupported diagnostic scenario plan.'),
        };
    }

    /** @param array<string, mixed> $payload */
    public static function stableScenarioId(array $payload, array $context = []): string
    {
        $identity = [
            'manifest_version' => (string) ($context['manifest_version'] ?? $payload['manifest_version'] ?? self::MANIFEST_VERSION),
            'plan' => (string) ($context['plan'] ?? $payload['plan'] ?? ''),
            'seed' => (string) ($payload['seed'] ?? ''),
            'item' => self::canonicalize((array) ($payload['item'] ?? [
                'page_no' => $payload['page_no'] ?? null,
                'label' => $payload['label'] ?? null,
            ])),
            'mark' => self::canonicalize((array) ($payload['mark'] ?? [
                'style' => $payload['mark_style'] ?? null,
                'parameters' => $payload['mark_parameters'] ?? [],
            ])),
            'answer' => self::canonicalize((array) ($payload['answer'] ?? [
                'condition' => $payload['answer_condition'] ?? null,
                'expected_option' => $payload['expected_option'] ?? null,
                'candidates' => $payload['expected_candidates'] ?? [],
            ])),
            'capture' => self::canonicalize((array) ($payload['capture'] ?? [
                'condition' => $payload['capture_condition'] ?? null,
                'conditions' => $payload['capture_conditions'] ?? [],
                'transform' => $payload['capture_transform'] ?? [],
            ])),
            'expected' => self::canonicalize((array) ($payload['expected'] ?? [
                'machine_class' => $payload['expected_machine_class'] ?? null,
                'selected_option' => $payload['expected_selected_option'] ?? null,
                'candidates' => $payload['expected_candidates'] ?? [],
            ])),
            'provenance' => self::canonicalize((array) ($context['provenance'] ?? $payload['provenance'] ?? [])),
        ];

        return 'SCN-'.strtoupper(substr(hash('sha256', self::encode($identity)), 0, 20));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    /** @return array<string, mixed> */
    public function canonicalPayload(): array
    {
        $payload = $this->payload;
        $scenarios = $payload['scenarios'];
        usort($scenarios, static fn (array $left, array $right): int => strcmp((string) $left['id'], (string) $right['id']));
        $payload['scenarios'] = $scenarios;

        if (isset($payload['capture_variants']) && is_array($payload['capture_variants'])) {
            $variants = array_values($payload['capture_variants']);
            usort($variants, static fn (array $left, array $right): int => strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? '')));
            $payload['capture_variants'] = $variants;
        }

        return self::canonicalize($payload);
    }

    public function canonicalJson(): string
    {
        return self::encode($this->canonicalPayload());
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonicalJson());
    }

    public function manifestHash(): string
    {
        return $this->hash();
    }

    public function scenarioId(int $index): string
    {
        $scenario = $this->scenarios[$index] ?? null;
        if (! is_array($scenario)) {
            throw new InvalidArgumentException('scenario_index_invalid: scenario index is not present.');
        }

        return (string) $scenario['id'];
    }

    /** @return list<string> */
    public function scenarioIds(): array
    {
        return array_values(array_map(static fn (array $scenario): string => (string) $scenario['id'], $this->scenarios));
    }

    /** @return array<string, mixed> */
    public function scenario(string $id): array
    {
        foreach ($this->scenarios as $scenario) {
            if ($scenario['id'] === $id) {
                return $scenario;
            }
        }

        throw new InvalidArgumentException('scenario_not_found: '.$id);
    }

    public function isComplete(): bool
    {
        return $this->coverage()->isComplete();
    }

    public function coverage(): OmrDiagnosticScenarioCoverage
    {
        return new OmrDiagnosticScenarioCoverage($this);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    private static function normalizeManifest(array $payload): array
    {
        $manifest = $payload;
        $manifest['schema_version'] = (string) ($payload['schema_version'] ?? '');
        $manifest['manifest_version'] = (string) ($payload['manifest_version'] ?? '');
        $manifest['plan'] = (string) ($payload['plan'] ?? '');
        $manifest['lane'] = (string) ($payload['lane'] ?? '');
        $manifest['provenance'] = self::normalizeProvenance($payload);
        $manifest['scenarios'] = self::normalizeScenarios((array) ($payload['scenarios'] ?? []));

        if (isset($payload['capture_variants'])) {
            $manifest['capture_variants'] = self::normalizeCaptureVariants($payload['capture_variants']);
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    private static function normalizeProvenance(array $payload): array
    {
        $provenance = is_array($payload['provenance'] ?? null) ? $payload['provenance'] : [];
        $targetHashes = is_array($payload['target_hashes'] ?? null) ? $payload['target_hashes'] : [];
        $profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : (is_array($provenance['profile'] ?? null) ? $provenance['profile'] : []);

        $geometryHash = (string) ($provenance['geometry_hash'] ?? $payload['geometry_hash'] ?? $targetHashes['manifest'] ?? $targetHashes['mapping'] ?? '');
        $artifactHash = (string) ($provenance['artifact_hash'] ?? $payload['artifact_hash'] ?? $targetHashes['artifact'] ?? '');
        $profileHash = (string) ($provenance['profile_hash'] ?? $payload['profile_hash'] ?? $profile['hash'] ?? $payload['marker_profile_hash'] ?? '');
        $rendererVersion = (string) ($provenance['renderer_version'] ?? $payload['renderer_version'] ?? '');

        return [
            ...$provenance,
            'target_id' => (string) ($provenance['target_id'] ?? $payload['target_id'] ?? ''),
            'target_kind' => (string) ($provenance['target_kind'] ?? $payload['target_kind'] ?? ''),
            'geometry_hash' => $geometryHash,
            'renderer_version' => $rendererVersion,
            'profile_hash' => $profileHash,
            'artifact_hash' => $artifactHash,
            'native_bundle_hash' => (string) ($provenance['native_bundle_hash'] ?? $payload['native_bundle_hash'] ?? $targetHashes['native_bundle'] ?? ''),
            'config_hash' => (string) ($provenance['config_hash'] ?? $payload['config_hash'] ?? $targetHashes['config'] ?? ''),
            'mapping_hash' => (string) ($provenance['mapping_hash'] ?? $payload['mapping_hash'] ?? $targetHashes['mapping'] ?? ''),
            'profile' => [
                ...$profile,
                'code' => (string) ($profile['code'] ?? $payload['profile_code'] ?? ''),
                'version' => isset($profile['version']) || isset($payload['profile_version'])
                    ? (int) ($profile['version'] ?? $payload['profile_version'])
                    : null,
                'hash' => $profileHash,
            ],
        ];
    }

    /** @param array<int|string, mixed> $scenarios @return list<array<string, mixed>> */
    private static function normalizeScenarios(array $scenarios): array
    {
        if (! array_is_list($scenarios)) {
            throw new InvalidArgumentException('scenario_list_invalid: scenarios must be an ordered list.');
        }

        return array_values(array_map(static function (mixed $raw): array {
            if (! is_array($raw)) {
                throw new InvalidArgumentException('scenario_invalid: each scenario must be an object.');
            }

            $item = is_array($raw['item'] ?? null) ? $raw['item'] : [];
            $mark = is_array($raw['mark'] ?? null) ? $raw['mark'] : [];
            $answer = is_array($raw['answer'] ?? null) ? $raw['answer'] : [];
            $capture = is_array($raw['capture'] ?? null) ? $raw['capture'] : [];
            $expected = is_array($raw['expected'] ?? null) ? $raw['expected'] : [];
            $captureConditions = $capture['conditions'] ?? $raw['capture_conditions'] ?? [];
            if (! is_array($captureConditions)) {
                $captureConditions = [];
            }
            $captureCondition = (string) ($capture['condition'] ?? $raw['capture_condition'] ?? '');
            if ($captureCondition !== '' && ! in_array($captureCondition, $captureConditions, true)) {
                $captureConditions = [$captureCondition, ...$captureConditions];
            }

            $answerCandidates = $answer['candidates'] ?? $raw['expected_candidates'] ?? [];
            $expectedCandidates = $expected['candidates'] ?? $answerCandidates;

            return [
                'id' => (string) ($raw['id'] ?? ''),
                'lane' => (string) ($raw['lane'] ?? ''),
                'seed' => (string) ($raw['seed'] ?? ''),
                'item' => [
                    'page_no' => (int) ($item['page_no'] ?? $item['page'] ?? $raw['page_no'] ?? $raw['page'] ?? 0),
                    'label' => (string) ($item['label'] ?? $raw['label'] ?? ''),
                    'position' => isset($item['position']) || isset($raw['position']) ? (int) ($item['position'] ?? $raw['position']) : null,
                    'options' => array_values(array_map('strval', is_array($item['options'] ?? null) ? $item['options'] : [])),
                ],
                'mark' => [
                    'style' => (string) ($mark['style'] ?? $raw['mark_style'] ?? ''),
                    'parameters' => is_array($mark['parameters'] ?? null) ? $mark['parameters'] : (is_array($raw['mark_parameters'] ?? null) ? $raw['mark_parameters'] : []),
                ],
                'answer' => [
                    'condition' => (string) ($answer['condition'] ?? $raw['answer_condition'] ?? ''),
                    'expected_option' => isset($answer['expected_option']) || array_key_exists('expected_option', $raw)
                        ? ($answer['expected_option'] ?? $raw['expected_option'])
                        : null,
                    'candidates' => self::stringList($answerCandidates),
                ],
                'capture' => [
                    'id' => (string) ($capture['id'] ?? $raw['capture_id'] ?? ''),
                    'condition' => $captureCondition,
                    'conditions' => self::stringList($captureConditions),
                    'transform' => is_array($capture['transform'] ?? null) ? $capture['transform'] : (is_array($raw['capture_transform'] ?? null) ? $raw['capture_transform'] : []),
                ],
                'expected' => [
                    'machine_class' => (string) ($expected['machine_class'] ?? $raw['expected_machine_class'] ?? ''),
                    'selected_option' => isset($expected['selected_option']) || array_key_exists('expected_selected_option', $raw)
                        ? ($expected['selected_option'] ?? $raw['expected_selected_option'])
                        : null,
                    'candidates' => self::stringList($expectedCandidates),
                ],
                'provenance' => is_array($raw['provenance'] ?? null) ? $raw['provenance'] : [],
            ];
        }, $scenarios));
    }

    private static function normalizeCaptureVariants(mixed $variants): array
    {
        if (! is_array($variants) || ! array_is_list($variants)) {
            throw new InvalidArgumentException('scenario_capture_variants_invalid: capture variants must be an ordered list.');
        }

        return array_values(array_map(static function (mixed $variant): array {
            if (! is_array($variant)) {
                throw new InvalidArgumentException('scenario_capture_variant_invalid: each capture variant must be an object.');
            }
            $condition = (string) ($variant['condition'] ?? '');
            $conditions = self::stringList($variant['conditions'] ?? ($condition !== '' ? [$condition] : []));
            if ($condition !== '' && ! in_array($condition, $conditions, true)) {
                $conditions = [$condition, ...$conditions];
            }

            return [
                ...$variant,
                'id' => (string) ($variant['id'] ?? ''),
                'condition' => $condition,
                'conditions' => $conditions,
                'transform' => is_array($variant['transform'] ?? null) ? $variant['transform'] : [],
            ];
        }, $variants));
    }

    /** @param array<string, mixed> $manifest */
    private static function validateManifest(array $manifest): void
    {
        if ($manifest['schema_version'] !== self::SCHEMA) {
            throw new InvalidArgumentException('scenario_manifest_schema_invalid: unsupported schema version.');
        }
        if (! in_array($manifest['manifest_version'], [self::MANIFEST_VERSION, self::MANIFEST_VERSION_V2], true)) {
            throw new InvalidArgumentException('scenario_manifest_version_invalid: unsupported manifest version.');
        }
        if (! in_array($manifest['plan'], [self::QUICK_REHEARSAL_V1, self::QUICK_REHEARSAL_V2, self::FULL_DIAGNOSTIC_V1], true)) {
            throw new InvalidArgumentException('scenario_plan_invalid: unsupported diagnostic scenario plan.');
        }
        if (! in_array($manifest['lane'], self::LANES, true)) {
            throw new InvalidArgumentException('scenario_lane_invalid: lane must be generated or hand_shaded.');
        }
        self::validateProvenance($manifest['provenance']);

        if ($manifest['scenarios'] === [] || ! array_is_list($manifest['scenarios'])) {
            throw new InvalidArgumentException('scenario_list_invalid: at least one scenario is required.');
        }

        $ids = [];
        foreach ($manifest['scenarios'] as $scenario) {
            self::validateScenario($scenario, $manifest);
            $id = $scenario['id'];
            if (isset($ids[$id])) {
                throw new InvalidArgumentException('scenario_id_duplicate: '.$id);
            }
            $ids[$id] = true;
            $computed = self::stableScenarioId($scenario, [
                'manifest_version' => $manifest['manifest_version'],
                'plan' => $manifest['plan'],
                'provenance' => $manifest['provenance'],
            ]);
            if (! hash_equals($computed, $id)) {
                throw new InvalidArgumentException('scenario_id_invalid: '.$id);
            }
        }

        if (isset($manifest['capture_variants'])) {
            $variants = $manifest['capture_variants'];
            if ($variants === [] || count($variants) > 9) {
                throw new InvalidArgumentException('scenario_capture_variants_invalid: capture variant count is out of bounds.');
            }
            $variantIds = [];
            foreach ($variants as $variant) {
                if ($variant['id'] === '' || isset($variantIds[$variant['id']])) {
                    throw new InvalidArgumentException('scenario_capture_variant_id_invalid: capture variant IDs must be unique.');
                }
                $variantIds[$variant['id']] = true;
                foreach ($variant['conditions'] as $condition) {
                    if (! in_array($condition, self::CAPTURE_CONDITIONS, true)) {
                        throw new InvalidArgumentException('scenario_capture_condition_invalid: '.$condition);
                    }
                }
            }
        }

        if (in_array($manifest['plan'], [self::QUICK_REHEARSAL_V1, self::QUICK_REHEARSAL_V2], true)) {
            self::validateQuickPlan($manifest);
        }
    }

    /** @param array<string, mixed> $provenance */
    private static function validateProvenance(array $provenance): void
    {
        foreach (['geometry_hash', 'profile_hash', 'artifact_hash'] as $key) {
            if (! is_string($provenance[$key] ?? null) || preg_match('/^[a-f0-9]{64}$/i', $provenance[$key]) !== 1) {
                throw new InvalidArgumentException('scenario_provenance_invalid: '.$key.' must be a SHA-256 hash.');
            }
        }
        if (! is_string($provenance['renderer_version'] ?? null) || $provenance['renderer_version'] === '') {
            throw new InvalidArgumentException('scenario_provenance_invalid: renderer_version is required.');
        }
        foreach (['native_bundle_hash', 'config_hash', 'mapping_hash'] as $key) {
            if (($provenance[$key] ?? '') !== '' && preg_match('/^[a-f0-9]{64}$/i', (string) $provenance[$key]) !== 1) {
                throw new InvalidArgumentException('scenario_provenance_invalid: '.$key.' must be a SHA-256 hash when present.');
            }
        }
    }

    /** @param array<string, mixed> $scenario @param array<string, mixed> $manifest */
    private static function validateScenario(array $scenario, array $manifest): void
    {
        if ($scenario['id'] === '' || ! preg_match('/^SCN-[A-F0-9]{20}$/', $scenario['id'])) {
            throw new InvalidArgumentException('scenario_id_invalid: scenario IDs must be stable SCN identifiers.');
        }
        if ($scenario['lane'] !== $manifest['lane']) {
            throw new InvalidArgumentException('scenario_lane_invalid: scenario lane must match manifest lane.');
        }
        if ($scenario['seed'] === '') {
            throw new InvalidArgumentException('scenario_seed_invalid: deterministic scenario seed is required.');
        }

        $item = $scenario['item'];
        if ((int) $item['page_no'] < 1 || $item['label'] === '') {
            throw new InvalidArgumentException('scenario_item_invalid: page and canonical item label are required.');
        }
        if ($item['position'] !== null && (int) $item['position'] < 1) {
            throw new InvalidArgumentException('scenario_item_invalid: item position must be positive when present.');
        }
        if ($item['options'] !== [] && count($item['options']) !== count(array_unique($item['options']))) {
            throw new InvalidArgumentException('scenario_item_invalid: item options must be unique.');
        }

        if (! in_array($scenario['mark']['style'], self::MARK_STYLES, true)) {
            throw new InvalidArgumentException('scenario_mark_style_invalid: '.$scenario['mark']['style']);
        }
        if (! in_array($scenario['answer']['condition'], self::ANSWER_CONDITIONS, true)) {
            throw new InvalidArgumentException('scenario_answer_condition_invalid: '.$scenario['answer']['condition']);
        }
        if (! in_array($scenario['expected']['machine_class'], self::EXPECTED_CLASSES, true)) {
            throw new InvalidArgumentException('scenario_expected_class_invalid: '.$scenario['expected']['machine_class']);
        }
        if ($scenario['capture']['condition'] === '' || ! in_array($scenario['capture']['condition'], self::CAPTURE_CONDITIONS, true)) {
            throw new InvalidArgumentException('scenario_capture_condition_invalid: '.$scenario['capture']['condition']);
        }
        foreach ($scenario['capture']['conditions'] as $condition) {
            if (! in_array($condition, self::CAPTURE_CONDITIONS, true)) {
                throw new InvalidArgumentException('scenario_capture_condition_invalid: '.$condition);
            }
        }
        if ($scenario['capture']['conditions'] === []) {
            throw new InvalidArgumentException('scenario_capture_condition_invalid: at least one capture condition is required.');
        }

        $answerCandidates = $scenario['answer']['candidates'];
        $expectedCandidates = $scenario['expected']['candidates'];
        if (count($answerCandidates) !== count(array_unique($answerCandidates))
            || count($expectedCandidates) !== count(array_unique($expectedCandidates))) {
            throw new InvalidArgumentException('scenario_candidates_invalid: candidate options must be ordered and unique.');
        }
        if ($answerCandidates !== $expectedCandidates) {
            throw new InvalidArgumentException('scenario_candidates_invalid: answer and expected candidate sets must agree.');
        }

        $expectedOption = $scenario['answer']['expected_option'];
        if ($expectedOption !== null && ! is_string($expectedOption)) {
            throw new InvalidArgumentException('scenario_expected_option_invalid: expected option must be a string or null.');
        }
        $selected = $scenario['expected']['selected_option'];
        if ($selected !== null && ! is_string($selected)) {
            throw new InvalidArgumentException('scenario_selected_option_invalid: selected option must be a string or null.');
        }
        if ($scenario['expected']['machine_class'] === self::EXPECTED_CAPTURE_REJECTED && $selected !== null) {
            throw new InvalidArgumentException('scenario_selected_option_invalid: capture-rejected scenarios cannot select an answer.');
        }
        if ($scenario['expected']['machine_class'] === self::EXPECTED_EXACT && $selected === null) {
            throw new InvalidArgumentException('scenario_selected_option_invalid: exact scenarios require a selected option.');
        }
        if ($selected !== null && $expectedCandidates !== [] && ! in_array($selected, $expectedCandidates, true)) {
            throw new InvalidArgumentException('scenario_selected_option_invalid: selected option must be one of the declared candidates.');
        }
        if ($scenario['capture']['condition'] === self::CAPTURE_PARTIAL_PAGE
            && $scenario['expected']['machine_class'] !== self::EXPECTED_CAPTURE_REJECTED) {
            throw new InvalidArgumentException('scenario_expected_class_invalid: partial-page captures must be capture_rejected.');
        }
        if ($scenario['expected']['machine_class'] === self::EXPECTED_CAPTURE_REJECTED
            && $scenario['capture']['condition'] !== self::CAPTURE_PARTIAL_PAGE) {
            throw new InvalidArgumentException('scenario_expected_class_invalid: capture_rejected is reserved for partial-page captures.');
        }
    }

    /** @param array<string, mixed> $manifest */
    private static function validateQuickPlan(array $manifest): void
    {
        if (count($manifest['scenarios']) !== 18) {
            throw new InvalidArgumentException('scenario_quick_count_invalid: Quick Rehearsal must contain exactly 18 scenarios.');
        }
        $limits = is_array($manifest['limits'] ?? null) ? $manifest['limits'] : [];
        if (($limits['item_count'] ?? null) !== 18 || (int) ($limits['max_generated_capture_variants'] ?? 0) !== 4) {
            throw new InvalidArgumentException('scenario_quick_limits_invalid: Quick Rehearsal bounds are fixed.');
        }
        $variants = is_array($manifest['capture_variants'] ?? null) ? $manifest['capture_variants'] : [];
        if (count($variants) > 4) {
            throw new InvalidArgumentException('scenario_quick_variants_invalid: no more than four generated capture variants are allowed.');
        }
        $partial = array_filter(
            $manifest['scenarios'],
            static fn (array $scenario): bool => $scenario['capture']['condition'] === self::CAPTURE_PARTIAL_PAGE
                && $scenario['expected']['machine_class'] === self::EXPECTED_CAPTURE_REJECTED,
        );
        if ($partial === []) {
            throw new InvalidArgumentException('scenario_quick_partial_page_missing: Quick Rehearsal requires a partial-page rejection case.');
        }
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private static function header(
        array $context,
        string $plan,
        string $lane,
        string $manifestVersion = self::MANIFEST_VERSION,
    ): array {
        return [
            'schema_version' => self::SCHEMA,
            'manifest_version' => $manifestVersion,
            'plan' => $plan,
            'lane' => $lane,
            'target' => [
                'id' => $context['provenance']['target_id'],
                'kind' => $context['provenance']['target_kind'],
            ],
            'provenance' => $context['provenance'],
        ];
    }

    /**
     * @param  OmrDiagnosticTarget|array<string, mixed>  $targetOrContext
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private static function contextAndItems(OmrDiagnosticTarget|array $targetOrContext, ?array $pins): array
    {
        if ($targetOrContext instanceof OmrDiagnosticTarget) {
            $reviewManifest = $targetOrContext->reviewManifest();
            $context = [
                'review_manifest' => $reviewManifest,
                'provenance' => [
                    'target_id' => $targetOrContext->targetId(),
                    'target_kind' => $targetOrContext->kind(),
                    'geometry_hash' => $targetOrContext->manifestHash(),
                    'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
                    'profile_hash' => (string) ($targetOrContext->markerAssetHash() ?? ''),
                    'artifact_hash' => $targetOrContext->artifactHash(),
                    'native_bundle_hash' => $targetOrContext->nativeBundleHash(),
                    'config_hash' => $targetOrContext->configHash(),
                    'mapping_hash' => $targetOrContext->mappingHash(),
                    'profile' => [
                        'code' => $targetOrContext->markerProfileCode(),
                        'version' => $targetOrContext->markerProfileVersion(),
                        'hash' => (string) ($targetOrContext->markerAssetHash() ?? ''),
                    ],
                ],
            ];
        } else {
            $context = $targetOrContext;
            $reviewManifest = is_array($context['review_manifest'] ?? null)
                ? $context['review_manifest']
                : (is_array($context['manifest'] ?? null) ? $context['manifest'] : $context);
            $context['review_manifest'] = $reviewManifest;
            $context['provenance'] = self::normalizeProvenance([
                ...$context,
                ...(is_array($context['provenance'] ?? null) ? $context['provenance'] : []),
                ...(is_array($pins) ? $pins : []),
            ]);
        }

        if (is_array($pins) && $pins !== [] && $targetOrContext instanceof OmrDiagnosticTarget) {
            $context['provenance'] = self::normalizeProvenance([
                ...$context,
                ...$context['provenance'],
                ...$pins,
            ]);
        }

        $items = self::itemsFromReviewManifest($context['review_manifest']);

        return [$context, $items];
    }

    /** @param array<string, mixed> $reviewManifest @return list<array<string, mixed>> */
    private static function itemsFromReviewManifest(array $reviewManifest): array
    {
        $fields = $reviewManifest['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields)) {
            throw new InvalidArgumentException('scenario_geometry_missing: canonical review manifest fields are required.');
        }
        $items = [];
        $seen = [];
        $position = 1;
        foreach ($fields as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null) || ! is_int($field['page'] ?? null)) {
                throw new InvalidArgumentException('scenario_geometry_invalid: canonical review manifest field identity is malformed.');
            }
            $label = $field['label'];
            if (str_contains($label, 'APPLICANT_NUMBER_') || str_contains($label, 'BIRTHDATE_')) {
                continue;
            }
            $key = $field['page'].'|'.$label;
            if (! isset($seen[$key])) {
                $seen[$key] = count($items);
                $items[] = [
                    'page_no' => $field['page'],
                    'label' => $label,
                    'position' => $position++,
                    'options' => [],
                ];
            }
            $itemIndex = $seen[$key];
            $option = (string) ($field['option'] ?? '');
            if ($option === '') {
                throw new InvalidArgumentException('scenario_geometry_invalid: canonical option code is required.');
            }
            $items[$itemIndex]['options'][] = $option;
        }
        foreach ($items as $index => $item) {
            $items[$index]['options'] = array_values(array_unique($item['options']));
            if ($items[$index]['options'] === []) {
                throw new InvalidArgumentException('scenario_geometry_invalid: every item requires at least one option.');
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $item @param array<string, mixed> $capture @return array<string, mixed> */
    private static function scenarioDefinition(
        array $context,
        string $manifestVersion,
        string $plan,
        string $lane,
        array $item,
        string $seed,
        string $markStyle,
        string $answerCondition,
        array $capture,
    ): array {
        $options = array_values(array_map('strval', (array) ($item['options'] ?? [])));
        $expectedOption = $options === [] ? null : $options[0];
        $candidates = self::candidates($answerCondition, $options, $expectedOption);
        $machineClass = self::expectedClass($markStyle, $answerCondition, (string) ($capture['condition'] ?? ''));
        $selectedOption = self::selectedOption($machineClass, $answerCondition, $expectedOption, $candidates);
        if ($machineClass === self::EXPECTED_CAPTURE_REJECTED) {
            $candidates = [];
            $selectedOption = null;
        }

        $scenario = [
            'id' => '',
            'lane' => $lane,
            'seed' => $seed,
            'item' => [
                'page_no' => (int) ($item['page_no'] ?? 0),
                'label' => (string) ($item['label'] ?? ''),
                'position' => (int) ($item['position'] ?? 0),
                'options' => $options,
            ],
            'mark' => [
                'style' => $markStyle,
                'parameters' => self::markParameters($markStyle),
            ],
            'answer' => [
                'condition' => $answerCondition,
                'expected_option' => $expectedOption,
                'candidates' => $candidates,
            ],
            'capture' => [
                'id' => (string) ($capture['id'] ?? $capture['condition'] ?? ''),
                'condition' => (string) ($capture['condition'] ?? ''),
                'conditions' => self::stringList($capture['conditions'] ?? [$capture['condition'] ?? '']),
                'transform' => is_array($capture['transform'] ?? null) ? $capture['transform'] : [],
            ],
            'expected' => [
                'machine_class' => $machineClass,
                'selected_option' => $selectedOption,
                'candidates' => $candidates,
            ],
            'provenance' => [
                'geometry_hash' => $context['provenance']['geometry_hash'],
                'renderer_version' => $context['provenance']['renderer_version'],
                'profile_hash' => $context['provenance']['profile_hash'],
                'artifact_hash' => $context['provenance']['artifact_hash'],
            ],
        ];
        $scenario['id'] = self::stableScenarioId($scenario, [
            'manifest_version' => $manifestVersion,
            'plan' => $plan,
            'provenance' => $context['provenance'],
        ]);

        return $scenario;
    }

    /** @param list<string> $options @return list<string> */
    private static function candidates(string $condition, array $options, ?string $expectedOption): array
    {
        if ($options === []) {
            return [];
        }
        $next = static function (int $offset) use ($options): string {
            return $options[$offset % count($options)];
        };

        return match ($condition) {
            self::ANSWER_EXPECTED_MATCH => $expectedOption === null ? [] : [$expectedOption],
            self::ANSWER_DEFINITE_MISMATCH => [$next(1)],
            self::ANSWER_BLANK => [],
            self::ANSWER_DOUBLE_WITH_EXPECTED => array_values(array_unique(array_filter([$expectedOption, $next(1)]))),
            self::ANSWER_DOUBLE_WITHOUT_EXPECTED => array_values(array_unique([$next(1), $next(2)])),
            self::ANSWER_TRIPLE => array_values(array_unique([$expectedOption, $next(1), $next(2)])),
            self::ANSWER_UNREADABLE => array_values(array_unique([$expectedOption, $next(1)])),
            self::ANSWER_BETWEEN_BUBBLE => array_values(array_unique([$expectedOption, $next(1)])),
            default => [],
        };
    }

    /** @param list<string> $candidates */
    private static function selectedOption(string $machineClass, string $answerCondition, ?string $expectedOption, array $candidates): ?string
    {
        if ($machineClass !== self::EXPECTED_EXACT) {
            return null;
        }
        if ($answerCondition === self::ANSWER_DEFINITE_MISMATCH) {
            return $candidates[0] ?? null;
        }

        return $expectedOption;
    }

    private static function expectedClass(string $markStyle, string $answerCondition, string $captureCondition): string
    {
        if ($captureCondition === self::CAPTURE_PARTIAL_PAGE) {
            return self::EXPECTED_CAPTURE_REJECTED;
        }
        if ($markStyle === self::MARK_NORMAL_DARK
            && in_array($answerCondition, [self::ANSWER_EXPECTED_MATCH, self::ANSWER_DEFINITE_MISMATCH], true)) {
            return self::EXPECTED_EXACT;
        }

        return self::EXPECTED_REVIEW_REQUIRED;
    }

    /** @return array<string, mixed> */
    private static function markParameters(string $markStyle): array
    {
        return match ($markStyle) {
            self::MARK_NORMAL_DARK => ['fill_ratio' => 0.88],
            self::MARK_FAINT => ['fill_ratio' => 0.64, 'alpha' => 100],
            self::MARK_PARTIAL_SMALL => ['fill_ratio' => 0.48, 'arc_degrees' => 180],
            self::MARK_OFF_CENTER => ['fill_ratio' => 0.7, 'dx_ratio' => 0.18, 'dy_ratio' => -0.12],
            self::MARK_OVERFILLED => ['fill_ratio' => 0.98],
            self::MARK_CHECK_CROSS => ['inset_ratio' => 0.18, 'stroke' => 'cross'],
            self::MARK_ERASED => ['fill_ratio' => 0.58, 'alpha' => 150, 'residual_alpha' => 110],
            self::MARK_SMUDGED => ['blob_count' => 5, 'spread_ratio' => 0.18, 'alpha' => 100],
            self::MARK_STRAY => ['dot_ratio' => 0.18, 'offset_ratio' => 0.28],
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private static function quickCaptureVariants(): array
    {
        return [
            [
                'id' => 'clean',
                'condition' => self::CAPTURE_CLEAN_SCANNER,
                'conditions' => [self::CAPTURE_CLEAN_SCANNER],
                'transform' => ['kind' => 'identity'],
            ],
            [
                'id' => 'geometry',
                'condition' => self::CAPTURE_ROTATION,
                'conditions' => [self::CAPTURE_ROTATION, self::CAPTURE_PERSPECTIVE],
                'transform' => [
                    'kind' => 'rotation_perspective',
                    'degrees' => 1.25,
                    'perspective_x' => 0.012,
                    'perspective_y' => -0.009,
                ],
            ],
            [
                'id' => 'illumination',
                'condition' => self::CAPTURE_UNEVEN_LIGHTING,
                'conditions' => [self::CAPTURE_UNEVEN_LIGHTING, self::CAPTURE_LOW_CONTRAST],
                'transform' => [
                    'kind' => 'lighting_contrast',
                    'gradient' => 0.18,
                    'contrast' => 0.78,
                ],
            ],
            [
                'id' => 'focus-shadow',
                'condition' => self::CAPTURE_BLUR,
                'conditions' => [self::CAPTURE_BLUR, self::CAPTURE_SHADOW],
                'transform' => [
                    'kind' => 'blur_shadow',
                    'radius' => 1.2,
                    'opacity' => 0.16,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function captureVariant(string $condition): array
    {
        return [
            'id' => $condition,
            'condition' => $condition,
            'conditions' => [$condition],
            'transform' => match ($condition) {
                self::CAPTURE_CLEAN_SCANNER => ['kind' => 'identity'],
                self::CAPTURE_PHONE => ['kind' => 'phone', 'downsample_ratio' => 0.92],
                self::CAPTURE_ROTATION => ['kind' => 'rotation', 'degrees' => 1.25],
                self::CAPTURE_PERSPECTIVE => ['kind' => 'perspective', 'x' => 0.012, 'y' => -0.009],
                self::CAPTURE_UNEVEN_LIGHTING => ['kind' => 'uneven_lighting', 'gradient' => 0.18],
                self::CAPTURE_LOW_CONTRAST => ['kind' => 'low_contrast', 'contrast' => 0.78],
                self::CAPTURE_BLUR => ['kind' => 'blur', 'radius' => 1.2],
                self::CAPTURE_SHADOW => ['kind' => 'shadow', 'opacity' => 0.16],
                self::CAPTURE_PARTIAL_PAGE => ['kind' => 'partial_page', 'visible_fraction' => 0.72, 'crop_edge' => 'bottom_right'],
                default => [],
            },
        ];
    }

    /** @return array<string, mixed> */
    private static function quickCoverageRequirements(): array
    {
        return [
            'mark_styles' => self::MARK_STYLES,
            'answer_conditions' => self::ANSWER_CONDITIONS,
            'capture_conditions' => [
                self::CAPTURE_CLEAN_SCANNER,
                self::CAPTURE_ROTATION,
                self::CAPTURE_PERSPECTIVE,
                self::CAPTURE_UNEVEN_LIGHTING,
                self::CAPTURE_LOW_CONTRAST,
                self::CAPTURE_BLUR,
                self::CAPTURE_SHADOW,
                self::CAPTURE_PARTIAL_PAGE,
            ],
            'pairs' => [
                'mark_answer' => array_values(array_map(
                    static fn (int $index): string => self::MARK_STYLES[$index % count(self::MARK_STYLES)].'|'.self::ANSWER_CONDITIONS[$index % count(self::ANSWER_CONDITIONS)],
                    range(0, 17),
                )),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function fullCoverageRequirements(): array
    {
        $markAnswer = [];
        foreach (self::MARK_STYLES as $markStyle) {
            foreach (self::ANSWER_CONDITIONS as $answerCondition) {
                $markAnswer[] = $markStyle.'|'.$answerCondition;
            }
        }
        $markCapture = [];
        foreach (self::MARK_STYLES as $index => $markStyle) {
            $markCapture[] = $markStyle.'|'.self::CAPTURE_CONDITIONS[$index % count(self::CAPTURE_CONDITIONS)];
        }
        $answerCapture = [];
        foreach (self::ANSWER_CONDITIONS as $index => $answerCondition) {
            $answerCapture[] = $answerCondition.'|'.self::CAPTURE_CONDITIONS[$index % count(self::CAPTURE_CONDITIONS)];
        }

        return [
            'mark_styles' => self::MARK_STYLES,
            'answer_conditions' => self::ANSWER_CONDITIONS,
            'capture_conditions' => self::CAPTURE_CONDITIONS,
            'pairs' => [
                'mark_answer' => $markAnswer,
                'mark_capture' => $markCapture,
                'answer_capture' => $answerCapture,
            ],
        ];
    }

    /** @param array<string, mixed> $context */
    private static function seed(array $context, string $plan, int $index): string
    {
        return hash('sha256', self::encode([
            'plan' => $plan,
            'index' => $index,
            'target' => $context['provenance'],
        ]));
    }

    /** @param mixed $value @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('scenario_list_invalid: expected an ordered string list.');
        }
        $values = array_values(array_map('strval', $value));
        if (in_array('', $values, true) || count($values) !== count(array_unique($values))) {
            throw new InvalidArgumentException('scenario_list_invalid: values must be non-empty and unique.');
        }

        return $values;
    }

    private static function encode(mixed $value): string
    {
        return json_encode(self::canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            if (is_float($value) && ! is_finite($value)) {
                throw new InvalidArgumentException('scenario_canonical_value_invalid: non-finite numbers are not permitted.');
            }

            return $value;
        }
        if (array_is_list($value)) {
            return array_values(array_map([self::class, 'canonicalize'], $value));
        }

        $keys = array_keys($value);
        usort($keys, static fn (int|string $left, int|string $right): int => strcmp((string) $left, (string) $right));
        $result = [];
        foreach ($keys as $key) {
            $result[(string) $key] = self::canonicalize($value[$key]);
        }

        return $result;
    }
}
