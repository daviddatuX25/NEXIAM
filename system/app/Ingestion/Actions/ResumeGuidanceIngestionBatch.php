<?php

namespace App\Ingestion\Actions;

use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\AssignDirectAssessmentSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Ingestion\Services\ExternalFinalImportAdapter;
use App\Ingestion\Services\ResolvedImportContract;
use App\Ingestion\Services\SpreadsheetParser;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ResumeGuidanceIngestionBatch
{
    public function __construct(
        private readonly AssignDirectAssessmentSession $assignDirectAssessmentSession,
        private readonly AssignApplicantToExamSession $assignApplicantToExamSession,
        private readonly RecordScores $recordScores,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ExternalFinalImportAdapter $externalFinalAdapter,
    ) {}

    public function handle(ImportBatch $batch, User $actor, CapabilityAuthorizer $authorizer): ImportBatch
    {
        $batchType = $batch->import_type;
        $canScore = $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
        if ($batchType === ImportBatch::TYPE_ALL_IN_ONE && ! $canScore) {
            throw new AuthorizationException('Scoring import capability required to resume an All-in-One batch.');
        }
        if ($batchType === ImportBatch::TYPE_MULTI_SESSION && ! $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            throw new AuthorizationException('Exam scheduling capability required to resume a multi-session batch.');
        }
        if (! in_array($batchType, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true)) {
            throw new \DomainException('Only consented All-in-One or multi-session batches can be resumed.');
        }

        $lockedBatch = DB::transaction(function () use ($batch): ImportBatch {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->import_type, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true)) {
                throw new \DomainException("Import batch {$locked->id} is not a Guidance-resumable batch.");
            }
            if ($locked->status === ImportBatch::STATUS_COMPLETED) {
                return $locked;
            }
            if ($locked->status !== ImportBatch::STATUS_AWAITING_GUIDANCE) {
                throw new \DomainException("Import batch {$locked->id} is not awaiting Guidance execution.");
            }

            return $locked;
        });
        if ($lockedBatch->status === ImportBatch::STATUS_COMPLETED) {
            return $lockedBatch->load('rows');
        }

        $institution = $lockedBatch->institution;
        $cycle = $lockedBatch->academicCycle;
        $contract = ResolvedImportContract::compile($institution, $cycle);
        $areaIds = array_values($contract->aptitudeAreaCodeToId);
        $areas = AptitudeArea::query()->whereIn('id', $areaIds)->where('is_active', true)->get()->keyBy(fn (AptitudeArea $area): string => strtoupper($area->code));
        $rows = $lockedBatch->rows()
            ->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)
            ->orderBy('id')
            ->get();

        if ($batchType === ImportBatch::TYPE_MULTI_SESSION && ! $canScore) {
            $selection = (array) ($lockedBatch->topology_meta['assessment_selection'] ?? []);
            foreach ($rows as $row) {
                $rowContract = (array) ($selection['session_contracts'][(string) $row->target_exam_session_id] ?? []);
                if ($this->hasScores(
                    $row->normalized_payload ?? [],
                    $areas->keys()->all(),
                    array_keys((array) ($rowContract['metric_code_to_id'] ?? [])),
                )) {
                    throw new AuthorizationException('Scoring import capability is required because this multi-session batch contains scores.');
                }
            }
        }

        $room = Room::query()->where('institution_id', $institution->id)->where('is_active', true)->first();
        $importedCount = 0;
        $failedCount = 0;
        foreach ($rows as $row) {
            try {
                $completed = $this->planningMutex->run(
                    AssignmentPlanningMutex::DOMAIN_CONSULTATION,
                    (int) $cycle->id,
                    function () use ($batchType, $row, $cycle, $room, $areas, $actor, $lockedBatch): bool {
                        return DB::transaction(function () use ($batchType, $row, $cycle, $room, $areas, $actor, $lockedBatch): bool {
                            $lockedRow = ImportBatchRow::query()->lockForUpdate()->findOrFail($row->id);
                            if ($lockedRow->status !== ImportBatchRow::STATUS_APPLICANT_IMPORTED || $lockedRow->admission_attempt_id === null) {
                                return false;
                            }

                            $attempt = $lockedRow->admissionAttempt()->lockForUpdate()->firstOrFail();
                            $payload = $lockedRow->normalized_payload ?? [];
                            $selection = $lockedBatch->topology_meta['assessment_selection'] ?? [];
                            $targetSession = null;
                            if ($batchType === ImportBatch::TYPE_MULTI_SESSION) {
                                if (! is_numeric($lockedRow->target_exam_session_id)) {
                                    throw new \RuntimeException('A target examination session is required for Guidance assignment.');
                                }
                                $targetSession = ExamSession::query()
                                    ->where('academic_cycle_id', $cycle->id)
                                    ->whereKey((int) $lockedRow->target_exam_session_id)
                                    ->lockForUpdate()
                                    ->first();
                                if ($targetSession === null || $targetSession->status === ExamSession::STATUS_CANCELLED) {
                                    throw new \RuntimeException('The target examination session is missing or cancelled.');
                                }
                                $sessionKitMap = $selection['session_kit_versions'] ?? [];
                                $targetKitVersionId = is_numeric($sessionKitMap[(string) $targetSession->id] ?? $sessionKitMap[$targetSession->id] ?? null)
                                    ? (int) ($sessionKitMap[(string) $targetSession->id] ?? $sessionKitMap[$targetSession->id])
                                    : null;
                                if ($targetKitVersionId !== null && (int) $targetSession->test_kit_version_id !== $targetKitVersionId) {
                                    throw new \RuntimeException('The target session no longer matches its staged Test Kit version.');
                                }
                            } else {
                                $targetKitVersionId = is_numeric($selection['test_kit_version_id'] ?? null)
                                    ? (int) $selection['test_kit_version_id']
                                    : null;
                            }
                            $kitVersion = $targetKitVersionId === null
                                ? null
                                : TestKitVersion::query()->findOrFail($targetKitVersionId);
                            $normPackageVersionId = is_numeric($selection['norm_package_version_id'] ?? null)
                                ? (int) $selection['norm_package_version_id']
                                : null;

                            $scoringSelection = $batchType === ImportBatch::TYPE_MULTI_SESSION && $targetSession !== null
                                ? (array) ($selection['session_contracts'][(string) $targetSession->id] ?? [])
                                : $selection;
                            $scores = [];
                            $finalOnly = false;
                            if ($kitVersion !== null) {
                                $profileVersion = $kitVersion->testInstrumentVersion()->with('metrics.construct')->first();
                                if ($profileVersion === null) {
                                    throw new \RuntimeException('The staged Test Kit has no scoring profile.');
                                }
                                $finalOnly = $profileVersion->isFinalOnly();
                                if ($batchType === ImportBatch::TYPE_MULTI_SESSION
                                    && ! $finalOnly
                                    && $scoringSelection === []) {
                                    $scoringSelection = [
                                        ...$scoringSelection,
                                        'input_kind' => 'raw',
                                        'test_kit_version_id' => (int) $kitVersion->id,
                                        'profile_version_id' => (int) $profileVersion->id,
                                    ];
                                }
                                if ($finalOnly !== (($scoringSelection['input_kind'] ?? null) === 'external_final')
                                    || (int) ($scoringSelection['test_kit_version_id'] ?? 0) !== (int) $kitVersion->id) {
                                    throw new \RuntimeException('The staged scoring mode or Kit does not match its exact session-bound profile.');
                                }
                                $finalInputHeaders = [
                                    ...array_keys((array) ($scoringSelection['metric_code_to_id'] ?? [])),
                                    ...array_values((array) ($scoringSelection['source_header_map'] ?? [])),
                                ];
                                $hasFinalInputs = $this->hasScores($payload, [], $finalInputHeaders);
                                $scores = $finalOnly
                                    && ($batchType !== ImportBatch::TYPE_MULTI_SESSION || $hasFinalInputs)
                                    ? $this->externalFinalAdapter->entries($payload, $profileVersion, $scoringSelection, $targetKitVersionId)
                                    : [];
                                if (! $finalOnly) {
                                    foreach ($profileVersion->metrics as $metric) {
                                        $value = $payload[$metric->code]
                                            ?? $payload[strtoupper($metric->code)]
                                            ?? $payload[strtolower($metric->code)]
                                            ?? null;
                                        if (($value === null || (is_string($value) && trim($value) === '')) && $metric->construct !== null) {
                                            $value = $payload[strtoupper($metric->construct->code)]
                                                ?? $payload[strtolower($metric->construct->code)]
                                                ?? null;
                                        }
                                        if ($value === null || (is_string($value) && trim($value) === '')) {
                                            continue;
                                        }
                                        if (! is_numeric($value)
                                            || (float) $value < (float) ($metric->min_raw_value ?? 0)
                                            || ($metric->max_raw_value !== null && (float) $value > (float) $metric->max_raw_value)) {
                                            throw new \RuntimeException("Score for {$metric->code} is invalid for the selected Test Kit profile.");
                                        }
                                        $scores[] = ['metric_code' => $metric->code, 'score' => (string) $value];
                                    }
                                }
                            } else {
                                foreach ($areas as $code => $area) {
                                    $value = $payload[$code] ?? $payload[strtolower($code)] ?? null;
                                    if ($value === null || (is_string($value) && trim($value) === '')) {
                                        continue;
                                    }
                                    $scores[] = ['aptitude_area_id' => $area->id, 'score' => (string) $value];
                                }
                            }
                            if ($scores !== [] && $kitVersion === null) {
                                throw new \RuntimeException('The staged scoring row has no exact Test Kit version.');
                            }
                            if ($batchType === ImportBatch::TYPE_ALL_IN_ONE) {
                                if ($kitVersion === null) {
                                    throw new \RuntimeException('The staged All-in-One batch has no exact Test Kit version.');
                                }
                                $assessment = $this->assignDirectAssessmentSession->handle($cycle, $room, $attempt, $actor, null, $kitVersion);
                                $assignedSession = $assessment['assignment']->examSession;
                            } elseif ($targetSession !== null) {
                                if ($scores !== [] && $targetSession->status !== ExamSession::STATUS_COMPLETED) {
                                    throw new \RuntimeException('Rows containing scores must target a completed examination session.');
                                }
                                if ($targetSession->status === ExamSession::STATUS_COMPLETED) {
                                    if ($kitVersion === null) {
                                        throw new \RuntimeException('The completed target session has no staged exact Test Kit version.');
                                    }
                                    $assessment = $this->assignDirectAssessmentSession->handle($cycle, $room, $attempt, $actor, $targetSession, $kitVersion);
                                    $assignedSession = $assessment['assignment']->examSession;
                                } else {
                                    $this->assignApplicantToExamSession->handle($targetSession, $attempt, $actor);
                                    $assessment = [];
                                    $assignedSession = $targetSession;
                                }
                            } else {
                                throw new \RuntimeException('The staged target session is unavailable.');
                            }
                            if ($scores !== []) {
                                $gradingSession = $assessment['grading_session'] ?? null;
                                if ($gradingSession === null
                                    || ! ($assignedSession instanceof ExamSession)
                                    || (int) $assessment['assignment']->exam_session_id !== (int) $assignedSession->id
                                    || (int) $gradingSession->exam_session_id !== (int) $assignedSession->id) {
                                    throw new \RuntimeException('The assigned examination session has no matching grading context.');
                                }
                                $this->recordScores->handle(
                                    gradingSession: $gradingSession,
                                    attempt: $attempt,
                                    scores: $scores,
                                    actor: $actor,
                                    idempotencyKey: 'import-guidance:'.$lockedRow->idempotency_key,
                                    source: ScoreRecord::SOURCE_IMPORT,
                                    scoringMode: $finalOnly ? 'manual_converted' : 'manual_raw',
                                    testKitVersionId: $targetKitVersionId,
                                    normPackageVersionId: $normPackageVersionId,
                                );
                            }
                            $lockedRow->update([
                                'status' => ImportBatchRow::STATUS_IMPORTED,
                                'guidance_status' => 'completed',
                                'errors' => null,
                            ]);

                            return true;
                        });
                    });
                if ($completed) {
                    $importedCount++;
                }
            } catch (\Throwable $exception) {
                $failedCount++;
                ImportBatchRow::query()
                    ->whereKey($row->id)
                    ->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)
                    ->update([
                        'guidance_status' => 'failed',
                        'errors' => [$exception->getMessage()],
                    ]);
            }
        }

        [$finalBatch, $remaining, $shouldAudit] = DB::transaction(function () use ($lockedBatch): array {
            $fresh = ImportBatch::query()->lockForUpdate()->findOrFail($lockedBatch->id);
            if ($fresh->status === ImportBatch::STATUS_COMPLETED) {
                return [$fresh, 0, false];
            }
            $remainingRows = $fresh->rows()->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)->count();
            $isCompleted = $remainingRows === 0;
            $fresh->update([
                'status' => $isCompleted ? ImportBatch::STATUS_COMPLETED : ImportBatch::STATUS_AWAITING_GUIDANCE,
                'imported_rows' => $fresh->rows()->where('status', ImportBatchRow::STATUS_IMPORTED)->count(),
                'failed_rows' => $fresh->rows()->where('guidance_status', 'failed')->count(),
                'committed_at' => $isCompleted ? now() : null,
            ]);

            return [$fresh->fresh(), $remainingRows, true];
        });
        if ($shouldAudit) {
            $this->recordAuditEvent->handle('import.guidance_phase_resumed', [
                'batch_id' => $finalBatch->id,
                'rows_completed_this_run' => $importedCount,
                'rows_failed_this_run' => $failedCount,
                'rows_remaining' => $remaining,
                'status' => $finalBatch->status,
            ], (string) $actor->id);
        }

        return $finalBatch->fresh('rows');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $areaCodes
     */
    private function hasScores(array $payload, array $areaCodes, array $metricCodes = []): bool
    {
        foreach (array_unique([...$areaCodes, ...$metricCodes]) as $code) {
            $value = $payload[SpreadsheetParser::normalizeHeader($code)] ?? null;
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return true;
            }
        }

        return false;
    }
}
