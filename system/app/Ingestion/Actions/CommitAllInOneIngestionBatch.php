<?php

namespace App\Ingestion\Actions;

use App\Admission\Actions\CreateAdmissionAttempt;
use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignDirectAssessmentSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Ingestion\Services\ExternalFinalImportAdapter;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Room;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommitAllInOneIngestionBatch
{
    public function __construct(
        private readonly CreateAdmissionAttempt $createAdmissionAttempt,
        private readonly AssignDirectAssessmentSession $assignDirectAssessmentSession,
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
        if ($batch->import_type !== ImportBatch::TYPE_ALL_IN_ONE) {
            throw new \DomainException('Only All-in-One batches can use the combined commit action.');
        }
        $cycle = $batch->academicCycle;
        $hasRegistrarImport = $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
        $hasGuidanceImport = $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
        $canCommit = ($hasRegistrarImport && $hasGuidanceImport)
            || ($cycle->allow_guidance_all_in_one_intake && $hasGuidanceImport);
        if (! $canCommit) {
            throw new AuthorizationException('Combined import requires Guidance score-import authority and either Registrar co-authority or cycle delegation.');
        }

        $batch = DB::transaction(function () use ($batch, $selectedRowIds): ImportBatch {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status === ImportBatch::STATUS_COMPLETED) {
                return $lockedBatch;
            }
            if ($lockedBatch->status !== ImportBatch::STATUS_STAGED) {
                throw new \DomainException("Import batch {$lockedBatch->id} is not staged for direct commit.");
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

        $institution = $batch->institution;
        $cycle = $batch->academicCycle;
        $window = $cycle->primaryWindow()
            ?? ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->first()
            ?? ApplicationWindow::query()->first();
        $room = Room::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->first()
            ?? Room::query()->first();
        $selection = $batch->topology_meta['assessment_selection'] ?? [];
        $testKitVersionId = is_numeric($selection['test_kit_version_id'] ?? null)
            ? (int) $selection['test_kit_version_id']
            : null;
        if ($testKitVersionId === null) {
            throw new \RuntimeException('The staged All-in-One batch has no exact Test Kit selection.');
        }
        $kitVersion = TestKitVersion::query()->findOrFail($testKitVersionId);
        $profileVersion = $kitVersion->testInstrumentVersion()->with('metrics.construct')->first();
        if ($profileVersion === null) {
            throw new \RuntimeException('The selected Test Kit has no scoring profile.');
        }
        $normPackageVersionId = is_numeric($selection['norm_package_version_id'] ?? null)
            ? (int) $selection['norm_package_version_id']
            : null;
        $rowsQuery = $batch->rows()->where('status', ImportBatchRow::STATUS_VALID);
        if ($selectedRowIds !== []) {
            $rowsQuery->whereIn('id', $selectedRowIds);
        }

        $importedCount = 0;
        $failedCount = 0;

        foreach ($rowsQuery->get() as $row) {
            try {
                $this->planningMutex->run(
                    AssignmentPlanningMutex::DOMAIN_CONSULTATION,
                    (int) $cycle->id,
                    function () use ($row, $institution, $cycle, $window, $room, $actor, $kitVersion, $profileVersion, $testKitVersionId, $normPackageVersionId, $selection): void {
                        DB::transaction(function () use ($row, $institution, $cycle, $window, $room, $actor, $kitVersion, $profileVersion, $testKitVersionId, $normPackageVersionId, $selection): void {
                            $payload = $row->normalized_payload ?? [];
                            $attempt = $this->createAdmissionAttempt->handle(
                                institution: $institution,
                                cycle: $cycle,
                                window: $window,
                                payload: $payload,
                                actor: $actor,
                            );
                            $assessment = $this->assignDirectAssessmentSession->handle(
                                $cycle,
                                $room,
                                $attempt,
                                $actor,
                                null,
                                $kitVersion,
                            );
                            $examSession = $assessment['assignment']->examSession;
                            $gradingSession = $assessment['grading_session'];
                            if (! ($examSession instanceof ExamSession)
                                || (int) $gradingSession->exam_session_id !== (int) $examSession->id) {
                                throw new \RuntimeException('The direct assessment session has no matching grading context.');
                            }

                            $finalOnly = $profileVersion->isFinalOnly();
                            if ($finalOnly !== (($selection['input_kind'] ?? null) === 'external_final')) {
                                throw new \RuntimeException('The staged scoring mode does not match the selected exact profile.');
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
                                    if ($rawScore !== null && $rawScore !== '') {
                                        $scores[] = ['metric_code' => $metric->code, 'score' => (string) $rawScore];
                                    }
                                }
                            }
                            if ($scores !== []) {
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
                            }

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

        $auditPayload = [
            'batch_id' => $batch->id,
            'imported_rows' => $importedCount,
            'failed_rows' => $failedCount,
            'skipped_rows' => $skippedCount,
        ];

        $this->recordAuditEvent->handle('import.all_in_one_committed', $auditPayload, (string) $actor->id);

        return $batch->fresh('rows');
    }
}
