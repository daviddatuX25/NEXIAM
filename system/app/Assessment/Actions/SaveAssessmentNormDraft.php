<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\AssessmentNormAccess;
use App\Assessment\Support\AssessmentNormCommandReceipt;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveAssessmentNormDraft
{
    public function __construct(
        private readonly AssessmentNormAccess $access,
        private readonly AssessmentNormCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(
        User $actor,
        int|AssessmentNormPackage|null $package,
        int|AssessmentNormPackageVersion|null $version,
        ?int $expectedRevision,
        ?string $requestId,
        array $input,
        bool $deferAudit = false,
    ): AssessmentNormPackageVersion {
        $packageId = $package instanceof AssessmentNormPackage ? (int) $package->id : ($package !== null ? (int) $package : null);
        $versionId = $version instanceof AssessmentNormPackageVersion ? (int) $version->id : ($version !== null ? (int) $version : null);
        $requestId = ! empty($requestId) ? (string) $requestId : (string) Str::uuid();

        try {
            return DB::transaction(function () use ($actor, $packageId, $versionId, $expectedRevision, $requestId, $input, $deferAudit): AssessmentNormPackageVersion {
                $institutionId = $this->access->institutionId($actor);
                $freshActor = User::query()->findOrFail($actor->getKey());
                $normalized = $this->normalizeInput($input);

                $existingPackage = null;
                $draft = null;

                if ($packageId !== null) {
                    $existingPackage = AssessmentNormPackage::query()
                        ->where('institution_id', $institutionId)
                        ->whereKey($packageId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($versionId !== null) {
                        $draft = AssessmentNormPackageVersion::query()
                            ->where('norm_package_id', $existingPackage->id)
                            ->whereKey($versionId)
                            ->lockForUpdate()
                            ->firstOrFail();
                    }
                } elseif ($versionId !== null || $expectedRevision !== null) {
                    throw ValidationException::withMessages(['version' => 'A new norm package cannot include an existing version identity.']);
                }

                $aggregate = $packageId === null
                    ? "institution:{$institutionId}:norm-package:create"
                    : "norm-package:{$packageId}:version:".($versionId ?? 'successor');

                $receiptPayload = [
                    'institution_id' => $institutionId,
                    'package_id' => $packageId,
                    'version_id' => $versionId,
                    'expected_revision' => $expectedRevision,
                ];
                if ($deferAudit) {
                    $receiptPayload['input_sha256'] = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
                } else {
                    $receiptPayload['input'] = $normalized;
                }
                $receipt = $this->receipts->claim(
                    $freshActor,
                    'assessment.norm-package.save-draft',
                    $aggregate,
                    $receiptPayload,
                    $requestId,
                );

                $replay = $this->receipts->result($receipt);
                if ($replay !== null) {
                    return $this->versionForInstitution($institutionId, $replay['package_id'], $replay['version_id']);
                }

                if ($packageId === null) {
                    if (empty($normalized['code'])) {
                        throw ValidationException::withMessages(['code' => 'A norm package code is required.']);
                    }

                    if (AssessmentNormPackage::query()->where('institution_id', $institutionId)->where('code', $normalized['code'])->exists()) {
                        throw ValidationException::withMessages(['code' => 'A norm package with this code already exists in this institution.']);
                    }

                    $normPackage = new AssessmentNormPackage;
                    $normPackage->institution_id = $institutionId;
                    $normPackage->code = $normalized['code'];
                    $normPackage->is_synthetic = (bool) ($normalized['is_synthetic'] ?? false);
                    $normPackage->current_approved_version_id = null;
                    $normPackage->save();

                    $draft = new AssessmentNormPackageVersion;
                    $draft->norm_package_id = $normPackage->id;
                    $draft->version_number = 1;
                    $draft->status = AssessmentNormPackageVersion::STATUS_DRAFT;
                    $draft->display_name = $normalized['display_name'] ?: ($normPackage->code.' Version 1');
                    $draft->publisher_name = $normalized['publisher_name'];
                    $draft->form_code = $normalized['form_code'];
                    $draft->edition = $normalized['edition'];
                    $draft->language = $normalized['language'];
                    $draft->applicable_population = $normalized['applicable_population'];
                    $draft->source_reference = $normalized['source_reference'];
                    $draft->permitted_use_reference = $normalized['permitted_use_reference'];
                    $draft->lock_version = 1;
                    $draft->created_by_user_id = $freshActor->id;
                    $draft->save();
                } else {
                    if ($versionId === null) {
                        // Create successor draft
                        if ($existingPackage->draftVersion()->exists()) {
                            throw ValidationException::withMessages(['version' => 'An unapproved draft already exists for this norm package. Edit or approve that draft first.']);
                        }

                        $maxVersion = (int) $existingPackage->versions()->max('version_number');
                        $nextVersionNumber = $maxVersion + 1;

                        $draft = new AssessmentNormPackageVersion;
                        $draft->norm_package_id = $existingPackage->id;
                        $draft->version_number = $nextVersionNumber;
                        $draft->status = AssessmentNormPackageVersion::STATUS_DRAFT;
                        $draft->display_name = $normalized['display_name'] ?: ($existingPackage->code.' Version '.$nextVersionNumber);
                        $draft->publisher_name = $normalized['publisher_name'];
                        $draft->form_code = $normalized['form_code'];
                        $draft->edition = $normalized['edition'];
                        $draft->language = $normalized['language'];
                        $draft->applicable_population = $normalized['applicable_population'];
                        $draft->source_reference = $normalized['source_reference'];
                        $draft->permitted_use_reference = $normalized['permitted_use_reference'];
                        $draft->lock_version = 1;
                        $draft->created_by_user_id = $freshActor->id;
                        $draft->save();
                    } else {
                        // Update existing draft
                        if ($draft === null || $draft->status !== AssessmentNormPackageVersion::STATUS_DRAFT) {
                            throw ValidationException::withMessages(['version' => 'Approved versions are immutable. Create a successor draft before editing.']);
                        }
                        if ($expectedRevision === null) {
                            throw ValidationException::withMessages(['expected_revision' => 'Expected revision is required to edit a norm package draft.']);
                        }
                        if ((int) $draft->lock_version !== $expectedRevision) {
                            throw ValidationException::withMessages(['expected_revision' => 'This draft changed. Reload it and review the current version before saving.']);
                        }
                        if (! empty($normalized['code']) && strtoupper($normalized['code']) !== $existingPackage->code) {
                            throw ValidationException::withMessages(['code' => 'A norm package code is immutable after creation.']);
                        }

                        if (! empty($normalized['display_name'])) {
                            $draft->display_name = $normalized['display_name'];
                        }
                        $draft->publisher_name = $normalized['publisher_name'] ?? $draft->publisher_name;
                        $draft->form_code = $normalized['form_code'] ?? $draft->form_code;
                        $draft->edition = $normalized['edition'] ?? $draft->edition;
                        $draft->language = $normalized['language'] ?? $draft->language;
                        $draft->applicable_population = $normalized['applicable_population'] ?? $draft->applicable_population;
                        $draft->source_reference = $normalized['source_reference'] ?? $draft->source_reference;
                        $draft->permitted_use_reference = $normalized['permitted_use_reference'] ?? $draft->permitted_use_reference;
                        $draft->lock_version++;
                        $draft->save();
                    }
                }

                // Sync tables if provided
                if (array_key_exists('tables', $normalized)) {
                    AssessmentNormTable::query()->where('norm_package_version_id', $draft->id)->delete();

                    foreach ($normalized['tables'] as $tableData) {
                        AssessmentNormTable::query()->create([
                            'norm_package_version_id' => $draft->id,
                            'metric_code' => $tableData['metric_code'],
                            'output_scale' => $tableData['output_scale'],
                            'intervals_json' => $tableData['intervals_json'],
                        ]);
                    }
                }

                $digestTables = $normalized['tables'] ?? AssessmentNormTable::query()
                    ->where('norm_package_version_id', $draft->id)
                    ->orderBy('metric_code')
                    ->orderBy('output_scale')
                    ->get()
                    ->map(static fn (AssessmentNormTable $table): array => [
                        'metric_code' => (string) $table->metric_code,
                        'output_scale' => (string) $table->output_scale,
                        'intervals_json' => (array) $table->intervals_json,
                    ])
                    ->all();
                $contentDigest = $this->calculateDigest($draft, $digestTables);
                $draft->content_sha256 = $contentDigest;
                $draft->save();

                $version = $draft->fresh(['package', 'tables']) ?? $draft;
                $this->receipts->remember($receipt, $version);
                if (! $deferAudit) {
                    $this->audit->handle('assessment.norm_package_draft_saved', [
                        'package_id' => (int) $version->norm_package_id,
                        'version_id' => (int) $version->id,
                        'version_number' => (int) $version->version_number,
                        'content_sha256' => $contentDigest,
                    ], (string) $freshActor->id);
                }

                return $version;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505'
                && (str_contains($exception->getMessage(), 'assessment_norm_packages_institution_id_code_unique')
                    || str_contains($exception->getMessage(), 'unique'))) {
                throw ValidationException::withMessages(['code' => 'A norm package with this code already exists in this institution.']);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $packageData = is_array($input['package'] ?? null) ? $input['package'] : [];

        $code = trim((string) ($packageData['code'] ?? $input['code'] ?? ''));
        $code = $code !== '' ? strtoupper($code) : null;

        $displayName = trim((string) ($packageData['display_name'] ?? $input['display_name'] ?? $input['name'] ?? ''));
        $publisherName = trim((string) ($packageData['publisher_name'] ?? $input['publisher_name'] ?? ''));
        $formCode = trim((string) ($packageData['form_code'] ?? $input['form_code'] ?? ''));
        $edition = trim((string) ($packageData['edition'] ?? $input['edition'] ?? ''));
        $language = trim((string) ($packageData['language'] ?? $input['language'] ?? ''));
        $applicablePopulation = trim((string) ($packageData['applicable_population'] ?? $input['applicable_population'] ?? ''));
        $sourceReference = trim((string) ($packageData['source_reference'] ?? $input['source_reference'] ?? ''));
        $permittedUseReference = trim((string) ($packageData['permitted_use_reference'] ?? $input['permitted_use_reference'] ?? ''));
        $isSynthetic = (bool) ($packageData['is_synthetic'] ?? $input['is_synthetic'] ?? false);

        $tablesRaw = $input['tables'] ?? ($packageData['tables'] ?? null);
        $normalizedTables = null;

        if (is_array($tablesRaw)) {
            $normalizedTables = [];
            $seenPairs = [];

            foreach ($tablesRaw as $index => $tableRow) {
                if (! is_array($tableRow)) {
                    continue;
                }

                $metricCode = strtoupper(trim((string) ($tableRow['metric_code'] ?? $tableRow['metric'] ?? '')));
                if ($metricCode === '') {
                    throw ValidationException::withMessages(['tables' => "Table at row {$index} is missing a metric code."]);
                }

                $outputScale = strtolower(trim((string) ($tableRow['output_scale'] ?? $tableRow['scale'] ?? '')));
                if (! in_array($outputScale, [AssessmentNormTable::SCALE_PERCENTILE_RANK, AssessmentNormTable::SCALE_STANINE], true)) {
                    throw ValidationException::withMessages(['tables' => "Unsupported output scale '{$outputScale}' for metric '{$metricCode}'."]);
                }

                $pairKey = "{$metricCode}:{$outputScale}";
                if (isset($seenPairs[$pairKey])) {
                    throw ValidationException::withMessages(['tables' => "Duplicate table definition for metric {$metricCode} with scale {$outputScale}."]);
                }
                $seenPairs[$pairKey] = true;

                $rawIntervals = $tableRow['intervals'] ?? ($tableRow['intervals_json'] ?? ($tableRow['conversion_rows'] ?? []));
                if (is_string($rawIntervals)) {
                    $decoded = json_decode($rawIntervals, true);
                    $rawIntervals = is_array($decoded) ? $decoded : [];
                }

                $normalizedIntervals = $this->normalizeAndValidateIntervals($metricCode, $outputScale, is_array($rawIntervals) ? $rawIntervals : []);

                $normalizedTables[] = [
                    'metric_code' => $metricCode,
                    'output_scale' => $outputScale,
                    'intervals_json' => $normalizedIntervals,
                ];
            }
        }

        $result = [
            'code' => $code,
            'display_name' => $displayName ?: null,
            'publisher_name' => $publisherName ?: null,
            'form_code' => $formCode ?: null,
            'edition' => $edition ?: null,
            'language' => $language ?: null,
            'applicable_population' => $applicablePopulation ?: null,
            'source_reference' => $sourceReference ?: null,
            'permitted_use_reference' => $permittedUseReference ?: null,
            'is_synthetic' => $isSynthetic,
        ];

        if ($normalizedTables !== null) {
            $result['tables'] = $normalizedTables;
        }

        return $result;
    }

    /**
     * @param  array<int, mixed>  $intervals
     * @return list<array{min_score:int|string,max_score:int|string,output_value:string}>
     */
    private function normalizeAndValidateIntervals(string $metricCode, string $outputScale, array $intervals): array
    {
        $normalized = [];
        foreach ($intervals as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $min = $row['min_score'] ?? ($row['raw_min'] ?? ($row['min'] ?? null));
            $max = $row['max_score'] ?? ($row['raw_max'] ?? ($row['max'] ?? null));
            $out = $row['output_value'] ?? ($row['value'] ?? ($row[$outputScale] ?? null));
            $minDecimal = $this->decimalBound($min);
            $maxDecimal = $this->decimalBound($max);
            $outStr = trim((string) $out);
            if ($minDecimal === null || $maxDecimal === null || $out === null) {
                throw ValidationException::withMessages([
                    'tables' => "Row at index {$idx} in table {$metricCode} ({$outputScale}) requires finite decimal bounds and an output value.",
                ]);
            }
            if ($minDecimal->compareTo($maxDecimal) > 0) {
                throw ValidationException::withMessages([
                    'tables' => "Row at index {$idx} in table {$metricCode} ({$outputScale}) has a minimum greater than its maximum.",
                ]);
            }
            if ($outputScale === AssessmentNormTable::SCALE_STANINE && $outStr !== '') {
                $stanine = is_numeric($outStr) ? (int) $outStr : 0;
                if ($stanine < 1 || $stanine > 9 || (string) $stanine !== $outStr) {
                    throw ValidationException::withMessages([
                        'tables' => "Stanine output '{$outStr}' in table {$metricCode} must be an integer between 1 and 9.",
                    ]);
                }
            }
            $normalized[] = [
                'min_score' => $this->normalizedBound($min, $minDecimal),
                'max_score' => $this->normalizedBound($max, $maxDecimal),
                'output_value' => $outStr,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => BigDecimal::of((string) $a['min_score'])->compareTo((string) $b['min_score']));
        $previousMax = null;
        foreach ($normalized as $row) {
            $minimum = BigDecimal::of((string) $row['min_score']);
            if ($previousMax !== null && $minimum->compareTo($previousMax) <= 0) {
                throw ValidationException::withMessages([
                    'tables' => "Conversion intervals in table {$metricCode} ({$outputScale}) must not overlap.",
                ]);
            }
            $previousMax = BigDecimal::of((string) $row['max_score']);
        }

        return $normalized;
    }

    private function decimalBound(mixed $value): ?BigDecimal
    {
        if (is_float($value)) {
            if (! is_finite($value) || round($value, 6) !== $value) {
                return null;
            }
            $text = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
            $text = $text === '-0' || $text === '' ? '0' : $text;
        } else {
            $text = is_string($value) || is_int($value) ? (string) $value : '';
        }
        if (! preg_match('/^-?(?:0|[1-9][0-9]{0,11})(?:\\.[0-9]{1,6})?$/D', $text)) {
            return null;
        }
        try {
            return BigDecimal::of($text);
        } catch (NumberFormatException) {
            return null;
        }
    }

    private function normalizedBound(mixed $original, BigDecimal $value): int|string
    {
        return preg_match('/^-?(?:0|[1-9][0-9]*)(?:\\.0+)?$/D', (string) $original)
            ? (int) (string) $value
            : (string) $value;
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     */
    private function calculateDigest(AssessmentNormPackageVersion $version, array $tables): string
    {
        $payload = [
            'display_name' => $version->display_name,
            'publisher_name' => $version->publisher_name,
            'form_code' => $version->form_code,
            'edition' => $version->edition,
            'language' => $version->language,
            'applicable_population' => $version->applicable_population,
            'source_reference' => $version->source_reference,
            'permitted_use_reference' => $version->permitted_use_reference,
            'tables' => $tables,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function versionForInstitution(int $institutionId, int $packageId, int $versionId): AssessmentNormPackageVersion
    {
        return AssessmentNormPackageVersion::query()
            ->whereKey($versionId)
            ->whereHas('package', static fn ($query) => $query
                ->whereKey($packageId)
                ->where('institution_id', $institutionId))
            ->firstOrFail();
    }
}
