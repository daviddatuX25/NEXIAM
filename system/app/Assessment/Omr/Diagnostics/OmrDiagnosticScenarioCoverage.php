<?php

namespace App\Assessment\Omr\Diagnostics;

use InvalidArgumentException;

use function array_filter;
use function array_is_list;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;

/**
 * Machine-readable taxonomy and targeted-pair coverage for a scenario
 * manifest. Coverage is a report, not a substitute for machine evidence.
 */
final readonly class OmrDiagnosticScenarioCoverage
{
    public const SCHEMA = 'nexiam.omr.diagnostic-scenario-coverage.v1';

    /** @var array<string, list<string>> */
    public array $required;

    /** @var array<string, list<string>> */
    public array $covered;

    /** @var array<string, list<string>> */
    public array $missing;

    /** @var array<string, array<string, list<string>>> */
    public array $pairs;

    /** @var list<string> */
    public array $gaps;

    public bool $complete;

    private array $report;

    public function __construct(OmrDiagnosticScenarioManifest|array $manifest)
    {
        $payload = $manifest instanceof OmrDiagnosticScenarioManifest ? $manifest->toArray() : $manifest;
        $scenarios = $payload['scenarios'] ?? null;
        if (! is_array($scenarios) || ! array_is_list($scenarios)) {
            throw new InvalidArgumentException('scenario_coverage_manifest_invalid: scenarios must be an ordered list.');
        }

        $requirements = is_array($payload['coverage_requirements'] ?? null)
            ? $payload['coverage_requirements']
            : self::defaultRequirements((string) ($payload['plan'] ?? ''));
        $this->required = [
            'mark_styles' => self::requiredList($requirements['mark_styles'] ?? OmrDiagnosticScenarioManifest::MARK_STYLES),
            'answer_conditions' => self::requiredList($requirements['answer_conditions'] ?? OmrDiagnosticScenarioManifest::ANSWER_CONDITIONS),
            'capture_conditions' => self::requiredList($requirements['capture_conditions'] ?? OmrDiagnosticScenarioManifest::CAPTURE_CONDITIONS),
        ];

        $covered = [
            'mark_styles' => [],
            'answer_conditions' => [],
            'capture_conditions' => [],
        ];
        $coveredPairs = [
            'mark_answer' => [],
            'mark_capture' => [],
            'answer_capture' => [],
        ];
        foreach ($scenarios as $scenario) {
            if (! is_array($scenario)) {
                continue;
            }
            $mark = is_array($scenario['mark'] ?? null) ? (string) ($scenario['mark']['style'] ?? '') : (string) ($scenario['mark_style'] ?? '');
            $answer = is_array($scenario['answer'] ?? null) ? (string) ($scenario['answer']['condition'] ?? '') : (string) ($scenario['answer_condition'] ?? '');
            $capture = is_array($scenario['capture'] ?? null) ? $scenario['capture'] : [];
            $captureValues = $capture['conditions'] ?? ($scenario['capture_conditions'] ?? []);
            if (! is_array($captureValues)) {
                $captureValues = [];
            }
            $captureCondition = (string) ($capture['condition'] ?? $scenario['capture_condition'] ?? '');
            if ($captureCondition !== '' && ! in_array($captureCondition, $captureValues, true)) {
                $captureValues = [$captureCondition, ...$captureValues];
            }

            if ($mark !== '') {
                $covered['mark_styles'][] = $mark;
            }
            if ($answer !== '') {
                $covered['answer_conditions'][] = $answer;
            }
            foreach ($captureValues as $condition) {
                if (! is_string($condition) || $condition === '') {
                    continue;
                }
                $covered['capture_conditions'][] = $condition;
                if ($mark !== '') {
                    $coveredPairs['mark_capture'][] = $mark.'|'.$condition;
                }
                if ($answer !== '') {
                    $coveredPairs['answer_capture'][] = $answer.'|'.$condition;
                }
            }
            if ($mark !== '' && $answer !== '') {
                $coveredPairs['mark_answer'][] = $mark.'|'.$answer;
            }
        }
        foreach ($covered as $dimension => $values) {
            $values = array_values(array_unique($values));
            $allowed = $this->required[$dimension];
            $covered[$dimension] = array_values(array_filter($allowed, static fn (string $value): bool => in_array($value, $values, true)));
        }
        $this->covered = $covered;

        $missing = [];
        foreach ($this->required as $dimension => $values) {
            $missing[$dimension] = array_values(array_filter($values, static fn (string $value): bool => ! in_array($value, $covered[$dimension], true)));
        }
        $this->missing = $missing;

        $requiredPairs = is_array($requirements['pairs'] ?? null) ? $requirements['pairs'] : [];
        $pairs = [];
        foreach (['mark_answer', 'mark_capture', 'answer_capture'] as $pairType) {
            $requiredValues = self::requiredList($requiredPairs[$pairType] ?? []);
            $actualValues = array_values(array_unique($coveredPairs[$pairType]));
            $actualValues = array_values(array_filter($requiredValues, static fn (string $value): bool => in_array($value, $actualValues, true)));
            $missingValues = array_values(array_filter($requiredValues, static fn (string $value): bool => ! in_array($value, $actualValues, true)));
            $pairs[$pairType] = [
                'required' => $requiredValues,
                'covered' => $actualValues,
                'missing' => $missingValues,
            ];
        }
        $this->pairs = $pairs;

        $gaps = [];
        foreach ($this->missing as $dimension => $values) {
            foreach ($values as $value) {
                $gaps[] = $dimension.':'.$value;
            }
        }
        foreach ($this->pairs as $pairType => $pair) {
            foreach ($pair['missing'] as $value) {
                $gaps[] = 'pair:'.$pairType.':'.$value;
            }
        }
        $bounds = self::bounds($payload, count($scenarios));
        foreach ($bounds['violations'] as $violation) {
            $gaps[] = $violation;
        }
        $this->gaps = array_values(array_unique($gaps));
        $this->complete = $this->gaps === [];
        $this->report = [
            'schema_version' => self::SCHEMA,
            'manifest_version' => (string) ($payload['manifest_version'] ?? ''),
            'plan' => (string) ($payload['plan'] ?? ''),
            'lane' => (string) ($payload['lane'] ?? ''),
            'scenario_count' => count($scenarios),
            'required' => $this->required,
            'covered' => $this->covered,
            'missing' => $this->missing,
            'pairs' => $this->pairs,
            'bounds' => $bounds,
            'gaps' => $this->gaps,
            'complete' => $this->complete,
        ];
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function hasGaps(): bool
    {
        return $this->gaps !== [];
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return $this->report;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->report();
    }

    public function assertComplete(): void
    {
        if (! $this->complete) {
            throw new InvalidArgumentException('scenario_coverage_incomplete: '.implode(', ', $this->gaps));
        }
    }

    /** @return array<string, mixed> */
    private static function defaultRequirements(string $plan): array
    {
        if ($plan === OmrDiagnosticScenarioManifest::QUICK_REHEARSAL_V1) {
            return [
                'mark_styles' => OmrDiagnosticScenarioManifest::MARK_STYLES,
                'answer_conditions' => OmrDiagnosticScenarioManifest::ANSWER_CONDITIONS,
                'capture_conditions' => OmrDiagnosticScenarioManifest::CAPTURE_CONDITIONS,
                'pairs' => [
                    'mark_answer' => array_values(array_map(
                        static fn (int $index): string => OmrDiagnosticScenarioManifest::MARK_STYLES[$index % count(OmrDiagnosticScenarioManifest::MARK_STYLES)].'|'.OmrDiagnosticScenarioManifest::ANSWER_CONDITIONS[$index % count(OmrDiagnosticScenarioManifest::ANSWER_CONDITIONS)],
                        range(0, 17),
                    )),
                ],
            ];
        }

        $markAnswer = [];
        foreach (OmrDiagnosticScenarioManifest::MARK_STYLES as $mark) {
            foreach (OmrDiagnosticScenarioManifest::ANSWER_CONDITIONS as $answer) {
                $markAnswer[] = $mark.'|'.$answer;
            }
        }

        return [
            'mark_styles' => OmrDiagnosticScenarioManifest::MARK_STYLES,
            'answer_conditions' => OmrDiagnosticScenarioManifest::ANSWER_CONDITIONS,
            'capture_conditions' => OmrDiagnosticScenarioManifest::CAPTURE_CONDITIONS,
            'pairs' => ['mark_answer' => $markAnswer],
        ];
    }

    /** @param mixed $values @return list<string> */
    private static function requiredList(mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw new InvalidArgumentException('scenario_coverage_requirements_invalid: requirements must be ordered lists.');
        }
        $result = array_values(array_unique(array_map('strval', $values)));
        if (in_array('', $result, true)) {
            throw new InvalidArgumentException('scenario_coverage_requirements_invalid: requirement values must be non-empty.');
        }

        return $result;
    }

    /** @return array{valid: bool, violations: list<string>, limits: array<string, mixed>} */
    private static function bounds(array $payload, int $scenarioCount): array
    {
        $plan = (string) ($payload['plan'] ?? '');
        $limits = is_array($payload['limits'] ?? null) ? $payload['limits'] : [];
        $violations = [];
        if ($plan === OmrDiagnosticScenarioManifest::QUICK_REHEARSAL_V1) {
            if ($scenarioCount !== 18) {
                $violations[] = 'quick_scenario_count_must_be_18';
            }
            $variants = is_array($payload['capture_variants'] ?? null) ? $payload['capture_variants'] : [];
            if (count($variants) > 4) {
                $violations[] = 'quick_generated_capture_variants_exceed_4';
            }
            if (($limits['partial_page_rejection'] ?? false) !== true) {
                $violations[] = 'quick_partial_page_rejection_required';
            }
        }

        return [
            'valid' => $violations === [],
            'violations' => $violations,
            'limits' => $limits,
        ];
    }
}
