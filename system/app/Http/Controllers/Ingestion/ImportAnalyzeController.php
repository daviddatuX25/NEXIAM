<?php

namespace App\Http\Controllers\Ingestion;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Ingestion\Services\ResolvedImportContract;
use App\Ingestion\Services\SpreadsheetInspector;
use App\Ingestion\Services\SpreadsheetParser;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Ingestion Pre-Flight Inspection Controller.
 *
 * Performs non-destructive, zero-database analysis of uploaded spreadsheets.
 * Inspects sheet counts, detects header rows, checks required demographic & scoring
 * fields against the institutional ResolvedImportContract, and reports recognition confidence.
 */
class ImportAnalyzeController extends Controller
{
    public function __construct(
        private readonly SpreadsheetInspector $inspector,
    ) {}

    public function analyze(Request $request, CapabilityAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $canImportApps = $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
        $canImportScores = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
        $canSchedule = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

        if (! $canImportApps && ! $canImportScores && ! $canSchedule) {
            return response()->json(['error' => 'You do not have permission to import data.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:25600'],
            'academic_cycle_id' => ['nullable', 'integer', 'exists:academic_cycles,id'],
            'test_kit_version_id' => ['nullable', 'integer', 'exists:test_kit_versions,id'],
            'target_exam_session_id' => ['nullable', 'integer', 'exists:exam_sessions,id'],
            'import_type' => ['nullable', 'in:applicants_only,scores_only,all_in_one,multi_session'],
        ]);

        $institution = Institution::current();
        $cycleId = $request->input('academic_cycle_id');
        $cycle = $cycleId !== null
            ? AcademicCycle::query()->where('institution_id', $institution->id)->findOrFail($cycleId)
            : AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first();

        if ($cycle === null) {
            $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->latest('id')->first();
        }

        if ($cycle === null) {
            return response()->json(['error' => 'No academic cycle available for validation.'], 422);
        }
        $importType = $request->input('import_type');
        if (($importType === 'applicants_only' && ! $canImportApps)
            || (in_array($importType, ['scores_only', 'all_in_one'], true) && ! $canImportScores)
            || ($importType === 'multi_session' && ! $canSchedule)) {
            abort(403);
        }

        $file = $request->file('file');
        try {
            $inspection = $this->inspector->inspect($file);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
        $targetSession = $request->filled('target_exam_session_id')
            ? ExamSession::query()
                ->where('academic_cycle_id', $cycle->id)
                ->whereKey((int) $request->input('target_exam_session_id'))
                ->where('status', ExamSession::STATUS_COMPLETED)
                ->firstOrFail()
            : null;
        $kitVersion = $request->filled('test_kit_version_id')
            ? TestKitVersion::query()
                ->where('institution_id', $institution->id)
                ->findOrFail((int) $request->input('test_kit_version_id'))
            : null;
        if (in_array($importType, ['scores_only', 'all_in_one'], true) && $kitVersion === null) {
            throw ValidationException::withMessages(['test_kit_version_id' => 'Select an exact locked Test Kit version before analyzing score columns.']);
        }
        if ($kitVersion !== null) {
            if ($kitVersion->revoked_at !== null
                || ! in_array($kitVersion->status, [TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_RETIRED], true)) {
                throw ValidationException::withMessages(['test_kit_version_id' => 'Select an active or applicable retired Test Kit version.']);
            }
            if ($importType === 'all_in_one') {
                if ($targetSession !== null || $kitVersion->status !== TestKitVersion::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['test_kit_version_id' => 'Combined imports require an active Test Kit and do not bind to an existing exam session.']);
                }
            } else {
                if ($targetSession === null) {
                    throw ValidationException::withMessages(['target_exam_session_id' => 'Select the completed target session before analyzing score columns.']);
                }
                if ($targetSession->test_kit_version_id !== null
                    && (int) $targetSession->test_kit_version_id !== (int) $kitVersion->id) {
                    throw ValidationException::withMessages(['test_kit_version_id' => 'The selected Test Kit does not match the target session.']);
                }
                if ($targetSession->test_kit_version_id === null
                    && $kitVersion->status !== TestKitVersion::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['test_kit_version_id' => 'A new score binding requires an active Test Kit version.']);
                }
            }
        }
        $contract = ResolvedImportContract::compile($institution, $cycle, $kitVersion);

        // Aggregate headers across all non-empty sheets
        $allRawHeaders = [];
        foreach ($inspection['sheets'] as $sheet) {
            if ($sheet['row_count'] > 0) {
                $allRawHeaders = array_merge($allRawHeaders, $sheet['raw_headers']);
            }
        }
        $allRawHeaders = array_values(array_unique($allRawHeaders));

        $ext = strtolower($file->getClientOriginalExtension());
        $analysis = $contract->analyzeHeaders(
            rawHeaders: $allRawHeaders,
            totalRows: $inspection['total_examinee_rows'],
            fileExtension: $ext,
            fileSizeBytes: $file->getSize(),
        );
        if ($importType === 'scores_only') {
            $normalizedHeaders = array_map(
                SpreadsheetParser::normalizeHeader(...),
                $allRawHeaders,
            );
            $requiredScoreHeaders = $contract->finalOnly
                ? array_map(SpreadsheetParser::normalizeHeader(...), [
                    ...array_keys($contract->metricCodeToId),
                    ...array_values($contract->metricCodeToSourceHeader),
                ])
                : [];
            $analysis['missing_required'] = array_values(array_unique([
                ...(! in_array('applicant_number', $normalizedHeaders, true) ? ['applicant_number'] : []),
                ...array_diff($requiredScoreHeaders, $normalizedHeaders),
            ]));
            $hasCollisions = collect($analysis['column_analysis'])->contains(fn (array $column): bool => $column['status'] === 'collision');
            foreach ($analysis['checks'] as &$check) {
                if ($check['id'] === 'required_columns') {
                    $check['label'] = 'Required applicant matching and score columns';
                    $check['status'] = $analysis['missing_required'] === [] && ! $hasCollisions ? 'pass' : 'fail';
                    $check['detail'] = $analysis['missing_required'] === [] && ! $hasCollisions
                        ? 'Applicant number and every required score/source column are present.'
                        : 'Missing: '.implode(', ', $analysis['missing_required']);
                }
            }
            unset($check);
        }

        $dataSheets = array_values(array_filter($inspection['sheets'], fn (array $sheet): bool => $sheet['row_count'] > 0));
        $populatedHeaders = array_values(array_unique(array_merge(...array_map(
            fn (array $sheet): array => $sheet['populated_headers'] ?? [],
            $dataSheets ?: [[]],
        ))));
        $applicantFields = array_values(array_diff([...$contract->requiredFields, ...$contract->optionalFields], ['applicant_number']));
        $requiredApplicantFields = array_values(array_diff($contract->requiredFields, ['applicant_number']));
        $populatedApplicantFields = array_values(array_intersect($populatedHeaders, $applicantFields));
        $hasApplicantFields = $populatedApplicantFields !== []
            && array_diff($requiredApplicantFields, $populatedHeaders) === [];
        $scoreCodes = array_map(
            fn (string $code): string => strtoupper(SpreadsheetParser::normalizeHeader($code)),
            array_unique([...array_keys($contract->aptitudeAreaCodeToId), ...array_keys($contract->metricCodeToId)]),
        );
        $populatedUpperHeaders = array_map('strtoupper', $populatedHeaders);
        $hasScores = array_intersect($populatedUpperHeaders, $scoreCodes) !== [];
        $hasApplicantNumber = in_array('applicant_number', $populatedHeaders, true);
        $hasFuzzyMatches = collect($analysis['column_analysis'])->contains(fn (array $column): bool => $column['status'] === 'fuzzy');
        $multiSheetApplicantRows = count($dataSheets) > 1 && collect($dataSheets)->every(function (array $sheet) use ($requiredApplicantFields, $applicantFields): bool {
            $sheetPopulatedHeaders = $sheet['populated_headers'] ?? [];

            return array_intersect($sheetPopulatedHeaders, $applicantFields) !== []
                && array_diff($requiredApplicantFields, $sheetPopulatedHeaders) === [];
        });
        $suggestedType = null;
        if (count($dataSheets) > 1) {
            if (! $hasFuzzyMatches && $multiSheetApplicantRows) {
                $suggestedType = 'multi_session';
                $detectionReason = 'Multiple populated sheets each contain the cycle-policy applicant fields; assign each sheet to its target session.';
            } else {
                $detectionReason = 'Multiple populated sheets do not each match the applicant Multi-Session contract; import type is unresolved.';
            }
        } elseif (! $hasFuzzyMatches && $hasApplicantFields && $hasScores) {
            $suggestedType = 'all_in_one';
            $detectionReason = $contract->finalOnly
                ? 'Populated applicant fields and exact percentile-rank/source-reference columns match the selected final-only Test Kit profile.'
                : 'Populated applicant fields and direct raw score columns match the active intake policy and exact Test Kit profile.';
        } elseif (! $hasFuzzyMatches && $hasApplicantFields) {
            $suggestedType = 'applicants_only';
            $detectionReason = 'Populated applicant fields match the active cycle intake policy; Applicant Number is only an identifier.';
        } elseif (! $hasFuzzyMatches && $hasScores && $hasApplicantNumber) {
            $suggestedType = 'scores_only';
            $detectionReason = $contract->finalOnly
                ? 'A populated applicant identifier and exact percentile-rank/source-reference columns match the selected final-only Test Kit profile.'
                : 'A populated applicant identifier and raw score values match the selected Test Kit profile.';
        } else {
            $detectionReason = 'Populated headers do not identify one unambiguous supported import mode.';
        }
        $suggestionAuthorized = match ($suggestedType) {
            'applicants_only' => $canImportApps,
            'scores_only', 'all_in_one' => $canImportScores,
            'multi_session' => $canSchedule,
            default => false,
        };

        return response()->json([
            'file_name' => $inspection['file_name'],
            'file_size' => $inspection['file_size_bytes'],
            'total_rows' => $inspection['total_examinee_rows'],
            'aptitude_areas' => $analysis['aptitude_areas'] ?? [],
            'sheets' => array_map(function (array $sheet) use ($contract, $scoreCodes): array {
                $sheetPopulatedHeaders = $sheet['populated_headers'] ?? [];
                $sheetUpperHeaders = array_map('strtoupper', $sheetPopulatedHeaders);

                return [
                    'name' => $sheet['name'],
                    'index' => $sheet['index'],
                    'row_count' => $sheet['row_count'],
                    'raw_headers' => $sheet['raw_headers'],
                    'normalized_headers' => $sheet['normalized_headers'],
                    'populated_headers' => $sheetPopulatedHeaders,
                    'has_demographics' => array_intersect(
                        array_diff([...$contract->requiredFields, ...$contract->optionalFields], ['applicant_number']),
                        $sheetPopulatedHeaders,
                    ) !== [],
                    'has_scores' => array_intersect($scoreCodes, $sheetUpperHeaders) !== [],
                ];
            }, $inspection['sheets']),
            'column_analysis' => $analysis['column_analysis'],
            'missing_required' => $analysis['missing_required'],
            'checks' => $analysis['checks'],
            'policy_version' => $contract->policyVersion,
            'assessment_contract' => $kitVersion === null ? null : [
                'test_kit_version_id' => (int) $kitVersion->id,
                'profile_version_id' => (int) $kitVersion->test_instrument_version_id,
                'scoring_mode' => $contract->finalOnly ? 'manual_converted' : 'manual_raw',
                'input_kind' => $contract->finalOnly ? 'external_final' : 'raw',
                'output_scale' => $contract->finalOnly ? 'percentile_rank' : null,
                'metric_code_to_id' => $contract->metricCodeToId,
                'source_header_map' => $contract->metricCodeToSourceHeader,
                'required_metric_codes' => $contract->requiredMetricCodes,
            ],
            'suggested_import_type' => $suggestedType,
            'suggestion_authorized' => $suggestionAuthorized,
            'detection_reason' => $detectionReason,
        ]);
    }
}
