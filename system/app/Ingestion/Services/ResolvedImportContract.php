<?php

namespace App\Ingestion\Services;

use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldKey;
use App\Models\Foundation\IntakeFieldPolicy;
use App\Models\Foundation\IntakeRequirementLevel;

final class ResolvedImportContract
{
    /**
     * @param  list<string>  $requiredFields
     * @param  list<string>  $optionalFields
     * @param  array<string, int>  $aptitudeAreaCodeToId
     * @param  array<string, int>  $aptitudeAreaCodeToMaxScore
     * @param  array<string, int>  $metricCodeToId
     * @param  array<string, int|string|null>  $metricCodeToMaxScore
     * @param  array<string, int|string|null>  $metricCodeToMinScore
     * @param  list<string>  $unsupportedScoreCodes
     * @param  array<string, int|null>  $metricCodeToPrecision
     * @param  array<string, string>  $metricCodeToSourceHeader
     * @param  list<string>  $requiredMetricCodes
     */
    public function __construct(
        public readonly int $policyVersion,
        public readonly array $requiredFields,
        public readonly array $optionalFields,
        public readonly array $aptitudeAreaCodeToId,
        public readonly array $aptitudeAreaCodeToMaxScore,
        public readonly array $aptitudeAreasList,
        public readonly array $metricCodeToId = [],
        public readonly array $metricCodeToMaxScore = [],
        public readonly array $metricCodeToMinScore = [],
        public readonly array $unsupportedScoreCodes = [],
        public readonly array $metricCodeToPrecision = [],
        public readonly array $metricCodeToSourceHeader = [],
        public readonly array $requiredMetricCodes = [],
        public readonly bool $finalOnly = false,
    ) {}

    public static function compile(Institution $institution, AcademicCycle $cycle, ?TestKitVersion $kitVersion = null): self
    {
        $version = (int) (IntakeFieldPolicy::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->max('version') ?? 1);
        $policies = IntakeFieldPolicy::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('version', $version)
            ->orderBy('sort_order')
            ->get()
            ->keyBy(fn (IntakeFieldPolicy $policy): string => $policy->field_key->value);
        $rules = [];
        foreach (IntakeFieldKey::cases() as $fieldKey) {
            $policy = $policies->get($fieldKey->value);
            $rules[$fieldKey->value] = $fieldKey->isSystemMandatory()
                ? IntakeRequirementLevel::SystemMandatory
                : ($policy?->requirement_level ?? $fieldKey->defaultLevel());
            if ($policy !== null && ! $policy->is_active && ! $fieldKey->isSystemMandatory()) {
                $rules[$fieldKey->value] = IntakeRequirementLevel::Disabled;
            }
        }

        $required = [];
        $optional = [];

        foreach ($rules as $fieldKey => $level) {
            if ($level === IntakeRequirementLevel::SystemMandatory || $level === IntakeRequirementLevel::InstitutionRequired) {
                $required[] = $fieldKey;
            } elseif ($level === IntakeRequirementLevel::Optional) {
                $optional[] = $fieldKey;
            }
        }

        // Aptitude areas
        $aptitudeAreaCatalog = $kitVersion === null
            ? AptitudeArea::query()->where('is_active', true)->get(['id', 'code', 'name', 'max_items'])
            : collect();
        $aptitudeAreas = $aptitudeAreaCatalog
            ->mapWithKeys(fn ($area) => [strtoupper($area->code) => (int) $area->id])
            ->toArray();
        $aptitudeAreaMaxScores = $aptitudeAreaCatalog
            ->mapWithKeys(fn ($area) => [strtoupper($area->code) => (int) $area->max_items])
            ->toArray();
        $aptitudeAreasList = $aptitudeAreaCatalog
            ->map(fn ($area) => [
                'id' => (int) $area->id,
                'code' => strtoupper($area->code),
                'name' => $area->name,
                'max_items' => (int) $area->max_items,
            ])
            ->values()
            ->toArray();

        $unsupportedScoreCodes = [];
        $metricCatalog = collect();
        $finalOnly = false;
        if ($kitVersion !== null) {
            if ((int) $kitVersion->institution_id !== (int) $institution->id
                || $kitVersion->test_instrument_version_id === null) {
                throw new \InvalidArgumentException('The selected Test Kit does not contain an institutional scoring profile.');
            }
            $profile = $kitVersion->testInstrumentVersion()->with('metrics.construct')->first();
            $finalOnly = $profile?->isFinalOnly() ?? false;
            $kitResources = [
                'answer_sheet' => $kitVersion->template_version_id !== null,
                'booklet_form' => $kitVersion->booklet_version_id !== null,
                'key' => $kitVersion->booklet_set_id !== null,
                'physical_procedure' => filled($profile?->administration_procedure_reference),
            ];
            $allKitMetrics = $profile?->metrics?->sortBy('id')->values() ?? collect();
            $unsupportedScoreCodes = [
                ...AptitudeArea::query()->where('is_active', true)->pluck('code')->map(fn (string $code): string => strtoupper($code))->all(),
                ...$allKitMetrics->map(fn (TestInstrumentMetric $metric): string => strtoupper($metric->code))->all(),
            ];
            $metricCatalog = $allKitMetrics
                ->filter(function (TestInstrumentMetric $metric) use ($kitResources, $finalOnly): bool {
                    $contract = $metric->input_contract_json;
                    $requiredResources = (array) ($contract['required_resources'] ?? []);

                    if ($finalOnly) {
                        return $metric->rule_kind === TestInstrumentMetric::RULE_EXTERNAL_FINAL
                            && in_array('percentile_rank', (array) $metric->required_output_scales, true)
                            && in_array('import', (array) ($contract['allowed_sources'] ?? []), true)
                            && $metric->precision_scale !== null;
                    }

                    return in_array(AssessmentRawResultRevision::SOURCE_IMPORT, (array) ($contract['allowed_sources'] ?? []), true)
                        && ! in_array($metric->rule_kind, [TestInstrumentMetric::RULE_SUM_RAW_METRICS, TestInstrumentMetric::RULE_FORMULA_RAW_METRICS], true)
                        && array_filter($requiredResources, fn (string $resource): bool => ! ($kitResources[$resource] ?? false)) === [];
                });
        } else {
            $metricCatalog = TestInstrumentMetric::query()
                ->whereHas('version', function ($versionQuery) use ($institution): void {
                    $versionQuery
                        ->where('status', TestInstrumentVersion::STATUS_APPROVED)
                        ->whereHas('instrument', function ($instrumentQuery) use ($institution): void {
                            $instrumentQuery
                                ->where('institution_id', $institution->id)
                                ->whereColumn('test_instruments.current_approved_version_id', 'test_instrument_versions.id');
                        });
                })
                ->orderBy('id')
                ->get(['id', 'code', 'min_raw_value', 'max_raw_value', 'rule_kind', 'input_contract_json']);
        }
        $metricCodeToId = $metricCatalog
            ->mapWithKeys(fn (TestInstrumentMetric $metric) => [strtoupper($metric->code) => (int) $metric->id])
            ->toArray();
        if ($kitVersion !== null) {
            $normalizedMetricHeaders = array_map(SpreadsheetParser::normalizeHeader(...), array_keys($metricCodeToId));
            if (count($normalizedMetricHeaders) !== count(array_unique($normalizedMetricHeaders))) {
                throw new \InvalidArgumentException('The selected Test Kit profile has metric codes that collide after import header normalization.');
            }
        }
        $metricCodeToMaxScore = $finalOnly
            ? array_fill_keys(array_keys($metricCodeToId), 100)
            : $metricCatalog
                ->mapWithKeys(fn (TestInstrumentMetric $metric) => [strtoupper($metric->code) => $metric->max_raw_value])
                ->toArray();
        $metricCodeToMinScore = $finalOnly
            ? array_fill_keys(array_keys($metricCodeToId), 0)
            : ($kitVersion === null
                ? []
                : $metricCatalog
                    ->mapWithKeys(fn (TestInstrumentMetric $metric) => [strtoupper($metric->code) => $metric->min_raw_value])
                    ->toArray());
        $metricCodeToSourceHeader = $finalOnly
            ? array_combine(
                array_keys($metricCodeToId),
                array_map(fn (string $code): string => $code.'_source_reference', array_keys($metricCodeToId)),
            )
            : [];
        $requiredMetricCodes = $finalOnly
            ? $metricCatalog->filter(fn (TestInstrumentMetric $metric): bool => $metric->is_required)->map(fn (TestInstrumentMetric $metric): string => strtoupper($metric->code))->values()->all()
            : [];
        $unsupportedScoreCodes = array_values(array_diff(array_unique($unsupportedScoreCodes), array_keys($metricCodeToId)));

        return new self(
            policyVersion: $version,
            requiredFields: array_values(array_unique($required)),
            optionalFields: array_values(array_unique($optional)),
            aptitudeAreaCodeToId: $aptitudeAreas,
            aptitudeAreaCodeToMaxScore: $aptitudeAreaMaxScores,
            aptitudeAreasList: $aptitudeAreasList,
            metricCodeToId: $metricCodeToId,
            metricCodeToMaxScore: $metricCodeToMaxScore,
            metricCodeToMinScore: $metricCodeToMinScore,
            unsupportedScoreCodes: $unsupportedScoreCodes,
            metricCodeToPrecision: $kitVersion === null ? [] : $metricCatalog
                ->mapWithKeys(fn (TestInstrumentMetric $metric): array => [strtoupper($metric->code) => $metric->precision_scale])
                ->all(),
            metricCodeToSourceHeader: $metricCodeToSourceHeader,
            requiredMetricCodes: $requiredMetricCodes,
            finalOnly: $finalOnly,
        );

    }

    /**
     * Analyze headers against this contract and return column analysis & health checks.
     *
     * @param  list<string>  $rawHeaders
     * @return array{
     *     column_analysis: list<array{raw: string, normalized: string, status: string, matched_to: ?string, scope: string}>,
     *     missing_required: list<string>,
     *     checks: list<array{id: string, label: string, status: string, detail: string}>
     * }
     */
    public function analyzeHeaders(array $rawHeaders, int $totalRows, string $fileExtension, int $fileSizeBytes): array
    {
        $matchedFields = [];
        $columnAnalysis = [];
        $seenHeaders = [];
        $collisions = [];
        $allDemographics = array_merge($this->requiredFields, $this->optionalFields);
        $finalScoreHeaders = array_fill_keys(array_map(
            static fn (string $code): string => SpreadsheetParser::normalizeHeader($code),
            array_keys($this->metricCodeToId),
        ), true);
        $metricCodeForHeader = [];
        foreach (array_keys($this->metricCodeToId) as $code) {
            $metricCodeForHeader[SpreadsheetParser::normalizeHeader($code)] = $code;
        }
        $finalSourceHeaders = array_fill_keys(array_map(
            SpreadsheetParser::normalizeHeader(...),
            array_values($this->metricCodeToSourceHeader),
        ), true);

        foreach ($rawHeaders as $raw) {
            $normalized = SpreadsheetParser::normalizeHeader($raw);
            $status = 'unknown';
            $matchedTo = null;
            $scope = 'ignored';

            if (isset($seenHeaders[$normalized])) {
                $status = 'collision';
                $matchedTo = $normalized;
                $scope = 'ambiguous';
                $collisions[] = $normalized;

            } elseif (in_array($normalized, $this->requiredFields, true)) {
                $status = 'required';
                $matchedTo = $normalized;
                $scope = 'applicant';
                $matchedFields[] = $normalized;
            } elseif (in_array($normalized, $this->optionalFields, true)) {
                $status = 'optional';
                $matchedTo = $normalized;
                $scope = 'applicant';
                $matchedFields[] = $normalized;
            } elseif (isset($finalScoreHeaders[$normalized])) {
                $status = 'aptitude';
                $matchedTo = 'score.'.($metricCodeForHeader[$normalized] ?? strtoupper($normalized));
                $scope = 'scoring';
            } elseif (isset($finalSourceHeaders[$normalized])) {
                $status = 'source';
                $matchedTo = 'source.'.strtoupper($normalized);
                $scope = 'scoring';
            } elseif (isset($this->aptitudeAreaCodeToId[strtoupper($normalized)])
                || isset($metricCodeForHeader[$normalized])) {
                $status = 'aptitude';
                $matchedTo = 'score.'.($metricCodeForHeader[$normalized] ?? strtoupper($normalized));
                $scope = 'scoring';
            } else {
                $fuzzy = $this->fuzzyMatch($normalized, $allDemographics);
                if ($fuzzy !== null) {
                    $status = 'fuzzy';
                    $matchedTo = $fuzzy;
                    $scope = 'applicant';
                    $matchedFields[] = $fuzzy;
                }
            }
            $seenHeaders[$normalized] = true;
            $columnAnalysis[] = [
                'raw' => $raw,
                'normalized' => $normalized,
                'status' => $status,
                'matched_to' => $matchedTo,
                'scope' => $scope,
            ];
        }

        $missingRequired = array_values(array_diff($this->requiredFields, $matchedFields));
        if ($this->finalOnly) {
            foreach ($this->requiredMetricCodes as $code) {
                $scoreHeader = SpreadsheetParser::normalizeHeader($code);
                $sourceHeader = SpreadsheetParser::normalizeHeader($this->metricCodeToSourceHeader[$code]);
                if (! isset($seenHeaders[$scoreHeader])) {
                    $missingRequired[] = $code;
                }
                if (! isset($seenHeaders[$sourceHeader])) {
                    $missingRequired[] = $this->metricCodeToSourceHeader[$code];
                }
            }
        }
        $checks = [
            [
                'id' => 'format',
                'label' => 'File format',
                'status' => in_array(strtolower($fileExtension), SpreadsheetParser::SUPPORTED_EXTENSIONS, true) ? 'pass' : 'fail',
                'detail' => strtoupper($fileExtension).' spreadsheet detected',
            ],
            [
                'id' => 'size',
                'label' => 'File size',
                'status' => $fileSizeBytes <= SpreadsheetParser::MAX_FILE_SIZE_BYTES ? 'pass' : 'fail',
                'detail' => round($fileSizeBytes / (1024 * 1024), 2).' MB (max 25 MB)',
            ],
            [
                'id' => 'rows',
                'label' => 'Data rows',
                'status' => $totalRows > 0 ? 'pass' : 'fail',
                'detail' => "{$totalRows} row(s) found",
            ],
            [
                'id' => 'required_columns',
                'label' => 'Required demographic columns',
                'status' => empty($missingRequired) && $collisions === [] ? 'pass' : 'fail',
                'detail' => empty($missingRequired) && $collisions === []
                    ? 'All required fields present per policy v'.$this->policyVersion
                    : (empty($collisions) ? 'Missing: '.implode(', ', $missingRequired) : 'Conflicting normalized headers: '.implode(', ', array_values(array_unique($collisions)))),
            ],
        ];

        return [
            'column_analysis' => $columnAnalysis,
            'aptitude_areas' => $this->aptitudeAreasList,
            'missing_required' => $missingRequired,
            'checks' => $checks,
        ];
    }

    private function fuzzyMatch(string $normalized, array $targets): ?string
    {
        foreach ($targets as $target) {
            if (str_contains($normalized, $target) || str_contains($target, $normalized)) {
                return $target;
            }
            if (strlen($normalized) > 3 && strlen($target) > 3 && levenshtein($normalized, $target) <= 2) {
                return $target;
            }
        }

        return null;
    }
}
