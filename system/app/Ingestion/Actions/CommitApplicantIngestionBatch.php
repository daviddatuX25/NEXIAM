<?php

namespace App\Ingestion\Actions;

use App\Admission\Actions\CreateAdmissionAttempt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CommitApplicantIngestionBatch
{
    public function __construct(
        private readonly CreateAdmissionAttempt $createAdmissionAttempt,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AutoAssignEligibleAttempt $autoAssign,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    /**
     * @param  list<int>  $selectedRowIds
     */
    public function handle(ImportBatch $batch, array $selectedRowIds, User $actor, CapabilityAuthorizer $authorizer): ImportBatch
    {
        if (! $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)) {
            throw new AuthorizationException('Admissions import capability required.');
        }

        $cycleId = (int) ($batch->academic_cycle_id ?: ImportBatch::query()->whereKey($batch->id)->value('academic_cycle_id'));
        if ($cycleId <= 0) {
            throw new \DomainException('Import batch must be associated with an academic cycle.');
        }

        /** @var list<AdmissionAttempt> $createdAttempts */
        $createdAttempts = [];

        $committedBatch = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            $cycleId,
            function () use ($batch, $selectedRowIds, $actor, &$createdAttempts): ImportBatch {
                $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
                if ($lockedBatch->import_type !== ImportBatch::TYPE_APPLICANTS_ONLY) {
                    throw new \DomainException('Only applicant-only imports can be committed here.');
                }
                if ($lockedBatch->status === ImportBatch::STATUS_COMPLETED) {
                    return $lockedBatch->fresh('rows');
                }
                if ($lockedBatch->status !== ImportBatch::STATUS_STAGED) {
                    throw new \DomainException('Only staged applicant-only imports can be committed here.');
                }
                $eligibleRows = $lockedBatch->rows()->where('status', ImportBatchRow::STATUS_VALID)->orderBy('id')->lockForUpdate()->get();
                $selectedIds = array_values(array_unique(array_map('intval', $selectedRowIds)));
                if ($selectedRowIds !== [] && count($selectedIds) !== count($selectedRowIds)) {
                    throw ValidationException::withMessages(['selected_row_ids' => 'Selection cannot contain duplicate rows.']);
                }
                if ($selectedIds !== [] && array_diff($selectedIds, $eligibleRows->modelKeys()) !== []) {
                    throw ValidationException::withMessages(['selected_row_ids' => 'Selection contains rows that are not eligible for this batch.']);
                }
                $rows = $selectedIds === [] ? $eligibleRows : $eligibleRows->whereIn('id', $selectedIds)->values();
                $skipped = $eligibleRows->count() - $rows->count();
                if ($selectedIds !== []) {
                    $lockedBatch->rows()
                        ->where('status', ImportBatchRow::STATUS_VALID)
                        ->whereNotIn('id', $selectedIds)
                        ->update(['status' => ImportBatchRow::STATUS_SKIPPED]);
                }
                $institution = $lockedBatch->institution;
                $cycle = $lockedBatch->academicCycle;
                $window = $cycle->primaryWindow() ?? ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->first();
                $failed = 0;
                foreach ($rows as $row) {
                    try {
                        DB::transaction(function () use ($row, $institution, $cycle, $window, $actor, &$createdAttempts): void {
                            $attempt = $this->createAdmissionAttempt->handle(
                                institution: $institution,
                                cycle: $cycle,
                                window: $window,
                                payload: $row->normalized_payload ?? [],
                                actor: $actor,
                            );
                            $row->update([
                                'status' => ImportBatchRow::STATUS_IMPORTED,
                                'admission_attempt_id' => $attempt->id,
                                'reference_code' => $attempt->reference_code,
                                'imported_at' => now(),
                            ]);
                            $createdAttempts[] = $attempt;
                        });
                    } catch (\Throwable $exception) {
                        $failed++;
                        $row->update(['status' => ImportBatchRow::STATUS_FAILED, 'errors' => [$exception->getMessage()]]);
                    }
                }

                $lockedBatch->update([
                    'status' => ImportBatch::STATUS_COMPLETED,
                    'imported_rows' => $lockedBatch->rows()->where('status', ImportBatchRow::STATUS_IMPORTED)->count(),
                    'failed_rows' => $failed,
                    'skipped_rows' => $skipped,
                    'committed_at' => now(),
                ]);
                $this->recordAuditEvent->handle('import.applicants_committed', [
                    'batch_id' => $lockedBatch->id,
                    'rows_imported' => $lockedBatch->imported_rows,
                    'rows_failed' => $failed,
                    'rows_skipped' => $skipped,
                ], (string) $actor->id);

                return $lockedBatch->fresh('rows');
            }
        );

        foreach ($createdAttempts as $attempt) {
            try {
                $this->autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $attempt, $actor);
            } catch (\Throwable $exception) {
                Log::error('Exam continuous auto-assignment failed for imported attempt: '.$exception->getMessage(), [
                    'attempt_id' => $attempt->id,
                    'exception' => $exception,
                ]);
            }
        }

        return $committedBatch;
    }
}
