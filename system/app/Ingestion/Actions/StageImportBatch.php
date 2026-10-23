<?php

namespace App\Ingestion\Actions;

use App\Admission\Support\ApplicantReference;
use App\Admission\Support\ApplicantReferenceResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Ingestion\Services\ResolvedImportContract;
use App\Ingestion\Services\SpreadsheetInspector;
use App\Ingestion\Services\SpreadsheetParser;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StageImportBatch
{
    public function __construct(
        private readonly SpreadsheetInspector $inspector,
        private readonly SpreadsheetParser $parser,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /**
     * @param  list<string>  $enabledSheetNames
     * @param  array<string, int|null>  $sheetToSessionMap  [sheet_name => target_exam_session_id]
     */
    public function handle(
        UploadedFile|string $file,
        Institution $institution,
        AcademicCycle $cycle,
        string $importType,
        User $actor,
        array $enabledSheetNames = [],
        array $sheetToSessionMap = [],
        ?ExamSession $targetExamSession = null,
        ?TestKitVersion $targetKitVersion = null,
        ?AssessmentNormPackageVersion $normPackageVersion = null,
    ): ImportBatch {
        $path = is_string($file) ? $file : ($file->getRealPath() ?: $file->getPathname());
        $originalName = is_string($file) ? basename($file) : $file->getClientOriginalName();
        $fileHash = hash_file('sha256', $path) ?: hash('sha256', $originalName.microtime());

        $inspection = $this->inspector->inspect($file);

        if ($inspection['total_examinee_rows'] === 0) {
            throw new \InvalidArgumentException('The spreadsheet does not contain any importable data rows.');
        }
        if (in_array($importType, [ImportBatch::TYPE_SCORES_ONLY, ImportBatch::TYPE_ALL_IN_ONE], true)) {
            if ($targetKitVersion === null
                || (int) $targetKitVersion->institution_id !== (int) $institution->id
                || $targetKitVersion->test_instrument_version_id === null
                || $targetKitVersion->revoked_at !== null
                || ($importType === ImportBatch::TYPE_ALL_IN_ONE
                    && $targetKitVersion->status !== TestKitVersion::STATUS_ACTIVE)) {
                throw new \InvalidArgumentException('Select an active exact Test Kit version for this score import.');
            }
        }
        if ($importType === ImportBatch::TYPE_SCORES_ONLY) {
            if ($targetExamSession === null
                || (int) $targetExamSession->academic_cycle_id !== (int) $cycle->id
                || $targetExamSession->status !== ExamSession::STATUS_COMPLETED) {
                throw new \InvalidArgumentException('Select a completed exam session in this academic cycle for score imports.');
            }
            if ($targetExamSession->test_kit_version_id !== null
                && (int) $targetExamSession->test_kit_version_id !== (int) $targetKitVersion?->id) {
                throw new \InvalidArgumentException('The selected Test Kit version does not match the target session.');
            }
            if ($targetExamSession->test_kit_version_id === null
                && $targetKitVersion?->status !== TestKitVersion::STATUS_ACTIVE) {
                throw new \InvalidArgumentException('A new score binding requires an active Test Kit version.');
            }
        }
        $contract = ResolvedImportContract::compile($institution, $cycle, $targetKitVersion);
        if ($contract->finalOnly && $normPackageVersion !== null) {
            throw new \InvalidArgumentException('External percentile-rank imports do not accept a norm package.');
        }

        $multiSessionPins = [];
        if ($importType === ImportBatch::TYPE_MULTI_SESSION) {
            foreach ($sheetToSessionMap as $sheetName => $sessionId) {
                $session = is_numeric($sessionId)
                    ? ExamSession::query()
                        ->where('academic_cycle_id', $cycle->id)
                        ->whereKey((int) $sessionId)
                        ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                        ->first()
                    : null;
                if ($session === null) {
                    throw new \InvalidArgumentException("Select a non-cancelled session in this academic cycle for sheet {$sheetName}.");
                }
                $multiSessionPins[$sheetName] = [
                    'session_id' => (int) $session->id,
                    'session_version' => (int) $session->version,
                    'test_kit_version_id' => $session->test_kit_version_id === null ? null : (int) $session->test_kit_version_id,
                ];
            }
            ksort($multiSessionPins);
        }
        ksort($sheetToSessionMap);
        $fingerprintParts = [
            'v4',
            (string) $institution->id,
            (string) $cycle->id,
            (string) $contract->policyVersion,
            $fileHash,
            $importType,
            $contract->finalOnly ? 'external_final' : 'raw',
            (string) ($targetExamSession?->id ?? ''),
            (string) ($targetKitVersion?->id ?? ''),
            (string) ($normPackageVersion?->id ?? ''),
            hash('sha256', json_encode($sheetToSessionMap) ?: ''),
            hash('sha256', json_encode($multiSessionPins) ?: ''),
        ];
        $batchFingerprint = hash('sha256', implode(':', $fingerprintParts));

        // Check if an existing batch with this fingerprint exists
        $existing = ImportBatch::query()
            ->where('institution_id', $institution->id)
            ->where('batch_fingerprint', $batchFingerprint)
            ->first();

        if ($existing !== null) {
            return $existing->load('rows');
        }

        return DB::transaction(function () use (
            $file,
            $originalName,
            $fileHash,
            $batchFingerprint,
            $institution,
            $cycle,
            $contract,
            $importType,
            $actor,
            $targetExamSession,
            $inspection,
            $enabledSheetNames,
            $sheetToSessionMap,
            $multiSessionPins,

            $targetKitVersion,
            $normPackageVersion,
        ): ImportBatch {
            $status = ImportBatch::STATUS_STAGED;
            if ($importType === ImportBatch::TYPE_MULTI_SESSION
                || ($importType === ImportBatch::TYPE_ALL_IN_ONE
                    && ! $this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)
                    && ! $cycle->allow_guidance_all_in_one_intake)) {
                $status = ImportBatch::STATUS_PENDING_APPROVAL;
            }

            $batch = ImportBatch::query()->create([
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'policy_version' => $contract->policyVersion,
                'import_type' => $importType,
                'file_name' => $originalName,
                'file_hash' => $fileHash,
                'batch_fingerprint' => $batchFingerprint,
                'status' => $status,
                'topology_meta' => [
                    ...$inspection,
                    'assessment_selection' => [
                        ...$this->contractSnapshot($contract, $targetKitVersion),
                        'norm_package_version_id' => $normPackageVersion?->id,
                        'target_sitting_context' => $importType === ImportBatch::TYPE_SCORES_ONLY
                            ? ['kind' => 'existing_completed_session', 'exam_session_id' => $targetExamSession?->id]
                            : ['kind' => 'new_direct_session_per_applicant'],
                        'session_contracts' => [],
                    ],
                ],
                'created_by_user_id' => $actor->id,
            ]);

            $totalRows = 0;
            $validRows = 0;
            $invalidRows = 0;

            // Determine which sheets to process
            $sheetsToProcess = empty($enabledSheetNames)
                ? array_map(fn ($s) => $s['name'], array_filter($inspection['sheets'], fn ($s) => $s['row_count'] > 0))
                : $enabledSheetNames;
            $targetSessions = [];
            $targetKitVersionsBySession = [];
            if ($importType === ImportBatch::TYPE_MULTI_SESSION) {
                foreach ($sheetsToProcess as $sheetName) {
                    $sessionId = $sheetToSessionMap[$sheetName] ?? null;
                    $session = is_numeric($sessionId)
                        ? ExamSession::query()
                            ->where('academic_cycle_id', $cycle->id)
                            ->whereKey((int) $sessionId)
                            ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                            ->first()
                        : null;
                    if ($session === null) {
                        throw new \InvalidArgumentException("Select a non-cancelled session in this academic cycle for sheet {$sheetName}.");
                    }
                    $expectedPin = $multiSessionPins[$sheetName] ?? null;
                    if ($expectedPin === null
                        || (int) $expectedPin['session_id'] !== (int) $session->id
                        || (int) $expectedPin['session_version'] !== (int) $session->version
                        || $expectedPin['test_kit_version_id'] !== ($session->test_kit_version_id === null ? null : (int) $session->test_kit_version_id)) {
                        throw new \RuntimeException('The mapped session or its exact Kit changed during import staging; analyze the workbook again.');
                    }
                    $targetSessions[$sheetName] = $session;
                    if ($session->test_kit_version_id !== null) {
                        $targetKitVersionsBySession[$session->id] = (int) $session->test_kit_version_id;
                    }
                }
            }

            $seenReferences = [];

            $sessionContractSnapshots = [];

            foreach ($sheetsToProcess as $sheetName) {
                $targetSessionForSheet = $targetSessions[$sheetName] ?? null;
                $sheetContract = $contract;
                if ($importType === ImportBatch::TYPE_MULTI_SESSION && $targetSessionForSheet?->test_kit_version_id !== null) {
                    $sheetKitVersion = TestKitVersion::query()
                        ->where('institution_id', $institution->id)
                        ->whereKey((int) $targetSessionForSheet->test_kit_version_id)
                        ->firstOrFail();
                    $sheetContract = ResolvedImportContract::compile($institution, $cycle, $sheetKitVersion);
                }
                if ($sheetContract->finalOnly && $normPackageVersion !== null) {
                    throw new \InvalidArgumentException('External percentile-rank imports do not accept a norm package.');
                }
                if ($importType === ImportBatch::TYPE_MULTI_SESSION && $targetSessionForSheet !== null && $targetSessionForSheet->test_kit_version_id !== null) {
                    $sessionContractSnapshots[(string) $targetSessionForSheet->id] = $this->contractSnapshot($sheetContract, $sheetKitVersion);
                }

                $parsedRows = $this->parser->parseSheetRows($file, $sheetName);
                foreach ($parsedRows as $rowEntry) {
                    $rowNumber = $rowEntry['row_number'];
                    $record = $rowEntry['data'];
                    $totalRows++;

                    $targetSession = $importType === ImportBatch::TYPE_SCORES_ONLY
                        ? $targetExamSession
                        : ($targetSessions[$sheetName] ?? null);
                    $validation = $this->validateRow($record, $sheetContract, $seenReferences, $importType, $cycle, (int) $institution->id);
                    if ($importType === ImportBatch::TYPE_MULTI_SESSION && $targetSession !== null
                        && $targetSession->status !== ExamSession::STATUS_COMPLETED
                        && $this->rowHasScores($validation['normalized'], $sheetContract)) {
                        $validation['errors'][] = 'Rows with scores must be mapped to a completed exam session with a grading context.';
                    }
                    if ($importType === ImportBatch::TYPE_MULTI_SESSION
                        && $targetSession !== null
                        && $this->rowHasScores($validation['normalized'], $sheetContract)
                        && $targetSession->test_kit_version_id === null) {
                        $validation['errors'][] = 'Rows with scores require the selected session to have an exact locked Test Kit version.';
                    }
                    $isValid = empty($validation['errors']);

                    if ($isValid) {
                        $validRows++;
                    } else {
                        $invalidRows++;
                    }

                    $rowIdempotencyKey = hash('sha256', implode(':', [
                        $batchFingerprint,
                        $sheetName,
                        (string) $rowNumber,
                        hash('sha256', json_encode($validation['normalized']) ?: ''),
                    ]));

                    ImportBatchRow::query()->create([
                        'import_batch_id' => $batch->id,
                        'sheet_name' => $sheetName,
                        'row_index' => $rowNumber,
                        'idempotency_key' => $rowIdempotencyKey,
                        'status' => $isValid ? ImportBatchRow::STATUS_VALID : ImportBatchRow::STATUS_INVALID,
                        'raw_payload' => $record,
                        'normalized_payload' => $validation['normalized'],
                        'errors' => $isValid ? null : $validation['errors'],
                        'reference_code' => $validation['reference_code'],
                        'target_exam_session_id' => $targetSession?->id,
                    ]);
                }
            }

            if ($totalRows === 0) {
                throw new \InvalidArgumentException('The selected sheets do not contain any importable data rows.');
            }

            if ($importType === ImportBatch::TYPE_MULTI_SESSION) {
                $batch->update([
                    'topology_meta' => [
                        ...($batch->topology_meta ?? []),
                        'assessment_selection' => [
                            'test_kit_version_id' => null,
                            'profile_version_id' => null,
                            'scoring_mode' => 'session_bound',
                            'input_kind' => 'session_bound',
                            'output_scale' => null,
                            'norm_package_version_id' => $normPackageVersion?->id,
                            'target_sitting_context' => ['kind' => 'row_bound_target_exam_session_id'],
                            'session_kit_versions' => $targetKitVersionsBySession,
                            'session_contracts' => $sessionContractSnapshots,
                        ],
                    ],
                ]);
            }

            $batch->update([
                'total_rows' => $totalRows,
                'valid_rows' => $validRows,
                'invalid_rows' => $invalidRows,
            ]);

            return $batch->load('rows');
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, bool>  $seenReferences
     * @return array{isValid: bool, errors: list<string>, normalized: array<string, mixed>, reference_code: ?string}
     */
    private function validateRow(
        array $record,
        ResolvedImportContract $contract,
        array &$seenReferences,
        string $importType,
        ?AcademicCycle $cycle,
        int $institutionId,
    ): array {
        $errors = [];
        $normalized = [];

        foreach ($record as $key => $val) {
            $normalizedKey = SpreadsheetParser::normalizeHeader((string) $key);
            if (array_key_exists($normalizedKey, $normalized)) {
                $errors[] = "Conflicting input columns normalize to {$normalizedKey}.";

                continue;
            }
            $normalized[$normalizedKey] = is_string($val) ? trim($val) : $val;
        }
        foreach ($contract->unsupportedScoreCodes as $unsupportedScoreCode) {
            $value = $normalized[SpreadsheetParser::normalizeHeader($unsupportedScoreCode)] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $errors[] = "Score field {$unsupportedScoreCode} is not accepted by the selected Test Kit contract.";
            }
        }

        // Required demographic checks (unless scores_only mode)
        if ($importType !== ImportBatch::TYPE_SCORES_ONLY) {
            foreach ($contract->requiredFields as $field) {
                $val = $normalized[$field] ?? null;
                if ($val === null || trim((string) $val) === '') {
                    $errors[] = "Missing required field: {$field}";
                }
            }
        }

        // Reference / Applicant Number check
        $ref = isset($normalized['applicant_number']) ? trim((string) $normalized['applicant_number']) : null;
        if ($importType === ImportBatch::TYPE_SCORES_ONLY && ($ref === null || $ref === '')) {
            $errors[] = 'Missing required field: applicant_number';
        } elseif ($ref !== null && $ref !== '') {
            if ($importType === ImportBatch::TYPE_SCORES_ONLY && $cycle !== null) {
                $ref = ApplicantReferenceResolver::normalizeForCycle($cycle, $ref, $institutionId);
                $normalized['applicant_number'] = $ref;
                $normalized['reference_code'] = $ref;
            }
            $sequence = ApplicantReference::extractSequence($ref, $cycle);
            $sequenceKey = $sequence === null ? null : 'SEQ:'.$sequence;
            if (isset($seenReferences[$ref]) || ($sequenceKey !== null && isset($seenReferences[$sequenceKey]))) {
                $errors[] = "Duplicate applicant reference in batch: {$ref}";
            } else {
                $seenReferences[$ref] = true;
                if ($sequenceKey !== null) {
                    $seenReferences[$sequenceKey] = true;
                }
            }
        }

        // Date of birth validation
        if (! empty($normalized['birthdate'])) {
            $rawDate = (string) $normalized['birthdate'];
            if (is_numeric($rawDate) && (float) $rawDate > 1000 && (float) $rawDate < 100000) {
                try {
                    $rawDate = Date::excelToDateTimeObject((float) $rawDate)->format('Y-m-d');
                    $normalized['birthdate'] = $rawDate;
                } catch (\Throwable) {
                    // fallback
                }
            }

            try {
                $normalized['birthdate'] = Carbon::parse($rawDate)->format('Y-m-d');
            } catch (\Exception $e) {
                $errors[] = 'Invalid date of birth format: '.$rawDate;
            }
        }

        // Email validation if present
        if (! empty($normalized['contact_email']) && ! filter_var($normalized['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address format: '.$normalized['contact_email'];
        }

        // Score validation applies to every score-bearing import mode.
        $recognizedScoreCount = 0;

        if (in_array($importType, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_SCORES_ONLY, ImportBatch::TYPE_MULTI_SESSION], true)) {
            if ($contract->finalOnly) {
                $hasFinalInput = $this->rowHasScores($normalized, $contract);
                if ($importType !== ImportBatch::TYPE_MULTI_SESSION || $hasFinalInput) {
                    foreach ($contract->requiredMetricCodes as $code) {
                        $value = $normalized[SpreadsheetParser::normalizeHeader($code)] ?? null;
                        $sourceHeader = SpreadsheetParser::normalizeHeader($contract->metricCodeToSourceHeader[$code]);
                        $sourceReference = $normalized[$sourceHeader] ?? null;
                        if ($value === null || trim((string) $value) === '') {
                            $errors[] = "Missing required final score: {$code}";

                            continue;
                        }
                        if (! is_numeric($value)) {
                            $errors[] = "Percentile rank for {$code} must be numeric";
                        } else {
                            try {
                                $precision = $contract->metricCodeToPrecision[$code] ?? 6;
                                if (BigDecimal::of((string) $value)->getScale() > $precision) {
                                    $errors[] = "Percentile rank for {$code} exceeds the selected Setup precision of {$precision} decimal places";
                                }
                            } catch (NumberFormatException) {
                                $errors[] = "Percentile rank for {$code} must be numeric";
                            }
                            if ((float) $value < 0 || (float) $value > 100) {
                                $errors[] = "Percentile rank for {$code} must be from 0 to 100";
                            }
                            $recognizedScoreCount++;
                        }
                        if (! is_string($sourceReference) || trim($sourceReference) === '') {
                            $errors[] = "A nonblank source reference is required for {$code}";
                        }
                    }
                }

            } else {
                foreach ($contract->aptitudeAreaCodeToId as $code => $areaId) {
                    if (isset($contract->metricCodeToId[$code])) {
                        continue;
                    }
                    $val = $normalized[$code] ?? $normalized[strtolower($code)] ?? null;
                    if ($val !== null && trim((string) $val) !== '') {
                        $recognizedScoreCount++;
                        if (! is_numeric($val)) {
                            $errors[] = "Score for {$code} must be numeric (got \"{$val}\")";
                        } else {
                            $num = (float) $val;
                            $maxScore = $contract->aptitudeAreaCodeToMaxScore[$code] ?? 100;
                            if ($num < 0 || $num > $maxScore) {
                                $errors[] = "Score for {$code} out of valid range (0-{$maxScore}): {$num}";
                            }
                        }
                    }
                }
                foreach ($contract->metricCodeToId as $code => $metricId) {
                    $value = $normalized[$code] ?? $normalized[strtolower($code)] ?? null;
                    if ($value === null || trim((string) $value) === '') {
                        continue;
                    }
                    $recognizedScoreCount++;
                    if (! is_numeric($value)) {
                        $errors[] = "Score for {$code} must be numeric";

                        continue;
                    }
                    try {
                        $precision = $contract->metricCodeToPrecision[$code] ?? 6;
                        if (BigDecimal::of((string) $value)->getScale() > $precision) {
                            $errors[] = "Score for {$code} exceeds the selected Setup precision of {$precision} decimal places";
                        }
                    } catch (NumberFormatException) {
                        $errors[] = "Score for {$code} must be numeric";
                    }
                    $minimum = $contract->metricCodeToMinScore[$code] ?? null;
                    $maximum = $contract->metricCodeToMaxScore[$code] ?? null;
                    if ($minimum !== null && (float) $value < (float) $minimum) {
                        $errors[] = "Score for {$code} is below the selected Setup minimum {$minimum}";
                    }
                    if ($maximum !== null && (float) $value > (float) $maximum) {
                        $errors[] = "Score for {$code} exceeds the selected Setup maximum {$maximum}";
                    }
                }
                if ($importType === ImportBatch::TYPE_SCORES_ONLY && $recognizedScoreCount === 0) {
                    $errors[] = 'At least one recognized aptitude score is required';
                }
            }

        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'normalized' => $normalized,
            'reference_code' => $ref,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function rowHasScores(array $normalized, ResolvedImportContract $contract): bool
    {
        $headers = $contract->finalOnly
            ? [
                ...array_keys($contract->metricCodeToId),
                ...array_values($contract->metricCodeToSourceHeader),
            ]
            : array_unique([...array_keys($contract->aptitudeAreaCodeToId), ...array_keys($contract->metricCodeToId)]);
        foreach ($headers as $header) {
            $value = $normalized[SpreadsheetParser::normalizeHeader($header)] ?? null;
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function contractSnapshot(ResolvedImportContract $contract, ?TestKitVersion $kitVersion): array
    {
        return [
            'test_kit_version_id' => $kitVersion?->id,
            'profile_version_id' => $kitVersion?->test_instrument_version_id,
            'scoring_mode' => $contract->finalOnly ? 'manual_converted' : 'manual_raw',
            'input_kind' => $contract->finalOnly ? 'external_final' : 'raw',
            'output_scale' => $contract->finalOnly ? 'percentile_rank' : null,
            'metric_code_to_id' => $contract->metricCodeToId,
            'source_header_map' => $contract->metricCodeToSourceHeader,
            'required_metric_codes' => $contract->requiredMetricCodes,
        ];
    }
}
