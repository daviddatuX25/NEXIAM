<?php

namespace App\Ingestion\Actions;

use App\Admission\Support\ApplicantReferenceResolver;
use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Ingestion\Services\ExternalFinalImportAdapter;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CommitScoreIngestionBatch
{
    public function __construct(
        private readonly RecordScores $recordScores,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ExternalFinalImportAdapter $externalFinalAdapter,
    ) {}

    /**
     * @param  list<int>  $selectedRowIds
     */
    public function handle(
        ImportBatch $batch,
        array $selectedRowIds,
        User $actor,
        CapabilityAuthorizer $authorizer,
    ): ImportBatch {
        if ($batch->import_type !== ImportBatch::TYPE_SCORES_ONLY) {
            throw new \DomainException('Only scores-only batches can use the score commit action.');
        }
        if (! $authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_IMPORT, [
            'institution_id' => (int) $batch->institution_id,
            'academic_cycle_id' => (int) $batch->academic_cycle_id,
            'office' => 'guidance',
        ])->allowed) {
            throw new AuthorizationException('Scoring import capability required.');
        }
        $batch = DB::transaction(function () use ($batch, $selectedRowIds): ImportBatch {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status === ImportBatch::STATUS_COMPLETED) {
                return $lockedBatch;
            }
            if ($lockedBatch->status !== ImportBatch::STATUS_STAGED) {
                throw new \DomainException("Import batch {$lockedBatch->id} is not staged for scoring commit.");
            }
            $eligibleIds = $lockedBatch->rows()
                ->where('status', ImportBatchRow::STATUS_VALID)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            $selectedIds = array_values(array_unique(array_map('intval', $selectedRowIds)));
            if ($selectedRowIds !== [] && count($selectedIds) !== count($selectedRowIds)) {
                throw ValidationException::withMessages(['selected_row_ids' => 'Selection cannot contain duplicate rows.']);
            }
            if ($selectedIds !== [] && array_diff($selectedIds, $eligibleIds) !== []) {
                throw ValidationException::withMessages(['selected_row_ids' => 'Selection contains rows that are not eligible for this batch.']);
            }
            if ($selectedIds !== []) {
                $lockedBatch->rows()
                    ->where('status', ImportBatchRow::STATUS_VALID)
                    ->whereNotIn('id', $selectedIds)
                    ->update(['status' => ImportBatchRow::STATUS_SKIPPED]);
            }
            $lockedBatch->update(['status' => ImportBatch::STATUS_COMMITTING]);

            return $lockedBatch;
        });

        if ($batch->status === ImportBatch::STATUS_COMPLETED) {
            return $batch;
        }

        $rowsQuery = $batch->rows()->where('status', ImportBatchRow::STATUS_VALID);
        if ($selectedRowIds !== []) {
            $rowsQuery->whereIn('id', $selectedRowIds);
        }

        $importedCount = 0;
        $failedCount = 0;

        $batch->loadMissing('academicCycle');
        foreach ($rowsQuery->get() as $row) {
            try {
                $this->planningMutex->run(
                    AssignmentPlanningMutex::DOMAIN_CONSULTATION,
                    (int) $batch->academic_cycle_id,
                    function () use ($batch, $row, $actor): void {
                        DB::transaction(function () use ($batch, $row, $actor): void {
                            $payload = $row->normalized_payload ?? [];
                            $referenceCode = (string) ($payload['applicant_number'] ?? $payload['reference_code'] ?? '');
                            if ($referenceCode === '') {
                                throw new RuntimeException('Applicant reference code is required for score imports.');
                            }
                            $attemptQuery = AdmissionAttempt::query()
                                ->where('institution_id', $batch->institution_id)
                                ->where('academic_cycle_id', $batch->academic_cycle_id);
                            ApplicantReferenceResolver::applyMatch($attemptQuery, $referenceCode, $batch->academicCycle);
                            $attempt = $attemptQuery->sole();

                            $targetExamSession = ExamSession::query()
                                ->where('academic_cycle_id', $batch->academic_cycle_id)
                                ->whereKey($row->target_exam_session_id)
                                ->where('status', ExamSession::STATUS_COMPLETED)
                                ->first();
                            if ($targetExamSession === null) {
                                throw new RuntimeException('The target examination session is missing or not completed.');
                            }

                            $assignment = ExamAssignment::query()
                                ->active()
                                ->where('exam_session_id', $targetExamSession->id)
                                ->where('admission_attempt_id', $attempt->id)
                                ->first();
                            if ($assignment === null) {
                                throw new RuntimeException('Applicant is not actively assigned to the target examination session.');
                            }

                            $gradingSession = GradingSession::query()
                                ->where('exam_session_id', $targetExamSession->id)
                                ->first();
                            if ($gradingSession === null) {
                                throw new RuntimeException('The target examination session has no grading context.');
                            }

                            $selection = $batch->topology_meta['assessment_selection'] ?? [];
                            $testKitVersionId = is_numeric($selection['test_kit_version_id'] ?? null)
                                ? (int) $selection['test_kit_version_id']
                                : null;
                            if ($testKitVersionId === null) {
                                throw new RuntimeException('The staged score batch has no exact Test Kit version.');
                            }
                            $kitVersion = TestKitVersion::query()->findOrFail($testKitVersionId);
                            if ($targetExamSession->test_kit_version_id !== null
                                && (int) $targetExamSession->test_kit_version_id !== $testKitVersionId) {
                                throw new RuntimeException('The target session no longer matches the staged Test Kit version.');
                            }
                            if ($targetExamSession->test_kit_version_id === null
                                && $kitVersion->status !== TestKitVersion::STATUS_ACTIVE) {
                                throw new RuntimeException('A new score binding requires an active Test Kit version.');
                            }
                            $profileVersion = $kitVersion->testInstrumentVersion()->with('metrics.construct')->first();
                            if ($profileVersion === null) {
                                throw new RuntimeException('The selected Test Kit has no scoring profile.');
                            }
                            $finalOnly = $profileVersion->isFinalOnly();
                            if ($finalOnly !== (($selection['input_kind'] ?? null) === 'external_final')) {
                                throw new RuntimeException('The staged scoring mode does not match the selected exact profile.');
                            }
                            if ($finalOnly && (int) ($selection['test_kit_version_id'] ?? 0) !== (int) $kitVersion->id) {
                                throw new RuntimeException('The staged external-final contract does not match the selected Test Kit.');
                            }
                            $scores = $finalOnly
                                ? $this->externalFinalAdapter->entries($payload, $profileVersion, $selection, $testKitVersionId)
                                : [];
                            if (! $finalOnly) {
                                foreach ($profileVersion->metrics as $metric) {
                                    $rawScore = $payload[$metric->code]
                                        ?? $payload[strtoupper($metric->code)]
                                        ?? $payload[strtolower($metric->code)]
                                        ?? null;
                                    if (($rawScore === null || $rawScore === '') && $metric->construct !== null) {
                                        $rawScore = $payload[strtoupper($metric->construct->code)]
                                            ?? $payload[strtolower($metric->construct->code)]
                                            ?? null;
                                    }
                                    if ($rawScore === null || $rawScore === '') {
                                        continue;
                                    }
                                    $scores[] = ['metric_code' => $metric->code, 'score' => (string) $rawScore];
                                }
                            }
                            if ($scores === []) {
                                throw new RuntimeException('At least one selected Test Kit metric score is required.');
                            }
                            $normPackageVersionId = is_numeric($selection['norm_package_version_id'] ?? null)
                                ? (int) $selection['norm_package_version_id']
                                : null;

                            $this->recordScores->handle(
                                gradingSession: $gradingSession,
                                attempt: $attempt,
                                scores: $scores,
                                actor: $actor,
                                idempotencyKey: $row->idempotency_key,
                                source: ScoreRecord::SOURCE_IMPORT,
                                scoringMode: $finalOnly ? 'manual_converted' : 'manual_raw',
                                testKitVersionId: $testKitVersionId,
                                normPackageVersionId: $normPackageVersionId,
                            );

                            $row->update([
                                'status' => ImportBatchRow::STATUS_IMPORTED,
                                'admission_attempt_id' => $attempt->id,
                                'reference_code' => $attempt->reference_code,
                                'imported_at' => now(),
                            ]);
                        });
                    });

                $importedCount++;
            } catch (\Throwable $exception) {
                $failedCount++;
                ImportBatchRow::query()->whereKey($row->id)->update([
                    'status' => ImportBatchRow::STATUS_FAILED,
                    'errors' => [$exception->getMessage()],
                ]);
            }
        }

        $skippedCount = $batch->rows()->where('status', ImportBatchRow::STATUS_SKIPPED)->count();
        $batch->update([
            'status' => ImportBatch::STATUS_COMPLETED,
            'imported_rows' => $importedCount,
            'failed_rows' => $failedCount,
            'skipped_rows' => $skippedCount,
            'committed_at' => now(),
        ]);

        $this->recordAuditEvent->handle('import.scores_committed', [
            'batch_id' => $batch->id,
            'imported_rows' => $importedCount,
            'failed_rows' => $failedCount,
            'skipped_rows' => $skippedCount,
        ], (string) $actor->id);

        return $batch->fresh('rows');
    }
}
