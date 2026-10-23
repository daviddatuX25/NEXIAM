<?php

namespace App\Ingestion\Actions;

use App\Admission\Actions\CreateAdmissionAttempt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ApproveIngestionBatch
{
    public function __construct(
        private readonly CreateAdmissionAttempt $createAdmissionAttempt,
        private readonly RecordAuditEvent $recordAuditEvent,
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
        if (! $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)) {
            throw new AuthorizationException('Registrar applications import capability required to approve a batch.');
        }
        if ($selectedRowIds === [] || count($selectedRowIds) !== count(array_unique($selectedRowIds))) {
            throw ValidationException::withMessages(['selected_row_ids' => 'Select one or more eligible rows without duplicates.']);
        }

        return DB::transaction(function () use ($batch, $selectedRowIds, $actor): ImportBatch {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($lockedBatch->import_type, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true)) {
                throw new \DomainException("Import batch {$lockedBatch->id} is not a supported Registrar-consent batch.");
            }
            if ($lockedBatch->status === ImportBatch::STATUS_AWAITING_GUIDANCE) {
                return $lockedBatch->fresh('rows');
            }
            if ($lockedBatch->status === ImportBatch::STATUS_FAILED) {
                $decision = ($lockedBatch->topology_meta ?? [])['applicant_consent'] ?? [];
                $replayedIds = array_map('intval', $decision['selected_row_ids'] ?? []);
                sort($replayedIds);
                $submittedIds = array_map('intval', $selectedRowIds);
                sort($submittedIds);
                if (isset($decision['selected_row_ids']) && $replayedIds === $submittedIds) {
                    return $lockedBatch->fresh('rows');
                }
                throw new ConflictHttpException('This Registrar consent attempt already failed; its row outcomes are stored.');
            }
            if ($lockedBatch->status !== ImportBatch::STATUS_PENDING_APPROVAL) {
                throw new \DomainException("Import batch {$lockedBatch->id} is not awaiting Registrar consent.");
            }
            if (! $lockedBatch->academicCycle->is_active) {
                throw new \DomainException("Import batch {$lockedBatch->id} belongs to an inactive academic cycle.");
            }

            $eligibleIds = $lockedBatch->rows()
                ->where('status', ImportBatchRow::STATUS_VALID)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            $selectedIds = array_values(array_unique(array_map('intval', $selectedRowIds)));
            sort($selectedIds);
            if (array_diff($selectedIds, $eligibleIds) !== []) {
                throw ValidationException::withMessages(['selected_row_ids' => 'Selection contains rows that are not eligible for this batch.']);
            }

            $institution = $lockedBatch->institution;
            $cycle = $lockedBatch->academicCycle;
            $window = $cycle->primaryWindow()
                ?? ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->first();
            $rows = $lockedBatch->rows()->whereIn('id', $selectedIds)->orderBy('id')->lockForUpdate()->get();
            $consentedCount = 0;
            $failedCount = 0;
            $activeAreas = $lockedBatch->import_type === ImportBatch::TYPE_MULTI_SESSION
                ? AptitudeArea::query()->where('is_active', true)->get()->keyBy(fn (AptitudeArea $area): string => strtoupper($area->code))
                : collect();
            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($row, $institution, $cycle, $window, $actor, $lockedBatch, $activeAreas): void {
                        if ($lockedBatch->import_type === ImportBatch::TYPE_MULTI_SESSION) {
                            if (! is_numeric($row->target_exam_session_id)) {
                                throw new \RuntimeException('A target examination session is required before Registrar consent.');
                            }
                            $session = ExamSession::query()
                                ->where('academic_cycle_id', $cycle->id)
                                ->whereKey((int) $row->target_exam_session_id)
                                ->lockForUpdate()
                                ->first();
                            if ($session === null || $session->status === ExamSession::STATUS_CANCELLED) {
                                throw new \RuntimeException('The target examination session is missing or cancelled.');
                            }
                            $hasScores = false;
                            $payload = $row->normalized_payload ?? [];
                            foreach ($activeAreas as $code => $area) {
                                $value = $payload[$code] ?? $payload[strtolower($code)] ?? null;
                                if ($value === null || (is_string($value) && trim($value) === '')) {
                                    continue;
                                }
                                if (! is_numeric($value) || (float) $value < 0 || (float) $value > (float) $area->max_items) {
                                    throw new \RuntimeException("Score for {$code} is invalid for the active aptitude catalog.");
                                }
                                $hasScores = true;
                            }
                            if ($hasScores && $session->status !== ExamSession::STATUS_COMPLETED) {
                                throw new \RuntimeException('Rows containing scores must target a completed examination session.');
                            }
                        }
                        $attempt = $this->createAdmissionAttempt->handle(
                            institution: $institution,
                            cycle: $cycle,
                            window: $window,
                            payload: $row->normalized_payload ?? [],
                            actor: $actor,
                        );
                        $row->update([
                            'status' => ImportBatchRow::STATUS_APPLICANT_IMPORTED,
                            'guidance_status' => 'pending',
                            'admission_attempt_id' => $attempt->id,
                            'reference_code' => $attempt->reference_code,
                            'imported_at' => now(),
                            'errors' => null,
                        ]);
                    });
                    $consentedCount++;
                } catch (\Throwable $exception) {
                    $failedCount++;
                    $row->update([
                        'status' => ImportBatchRow::STATUS_FAILED,
                        'errors' => [$exception->getMessage()],
                    ]);
                }
            }

            $lockedBatch->rows()
                ->where('status', ImportBatchRow::STATUS_VALID)
                ->whereNotIn('id', $selectedIds)
                ->update(['status' => ImportBatchRow::STATUS_SKIPPED]);
            $skippedCount = count($eligibleIds) - count($selectedIds);
            $metadata = $lockedBatch->topology_meta ?? [];
            $metadata['applicant_consent'] = ['selected_row_ids' => $selectedIds];
            $lockedBatch->update([
                'status' => $consentedCount > 0 ? ImportBatch::STATUS_AWAITING_GUIDANCE : ImportBatch::STATUS_FAILED,
                'imported_rows' => $consentedCount,
                'skipped_rows' => $skippedCount,
                'failed_rows' => $failedCount,
                'topology_meta' => $metadata,
            ]);
            $this->recordAuditEvent->handle('import.batch_applicant_consent_processed', [
                'batch_id' => $lockedBatch->id,
                'rows_consented' => $consentedCount,
                'rows_failed' => $failedCount,
                'rows_skipped' => $skippedCount,
                'status' => $lockedBatch->status,
            ], (string) $actor->id);

            return $lockedBatch->fresh('rows');
        });
    }
}
