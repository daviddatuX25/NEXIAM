<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ResolveOmrApplicantException
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrCaptureSheet $sheet,
        ExamAssignment $assignment,
        string $reason,
        User $actor,
        ?int $expectedRevision = null,
    ): OmrCaptureSheet {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            throw new RuntimeException('You are not authorized to resolve OMR identity exceptions.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A resolution reason is required.');
        }

        return DB::transaction(function () use ($sheet, $assignment, $reason, $actor, $expectedRevision): OmrCaptureSheet {
            $batch = OmrCaptureBatch::query()
                ->with('gradingSession')
                ->lockForUpdate()
                ->findOrFail($sheet->batch_id);
            $sheet = OmrCaptureSheet::query()->lockForUpdate()->findOrFail($sheet->id);
            $sheet->setRelation('batch', $batch);
            $this->assertExpectedRevision($batch, $expectedRevision);
            $assignment = ExamAssignment::query()->with('admissionAttempt')->lockForUpdate()->findOrFail($assignment->id);
            $examSessionId = $batch->gradingSession?->exam_session_id;

            if ($sheet->batch?->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Identity exceptions may only be resolved while the OMR batch is open.');
            }
            if ((int) $assignment->exam_session_id !== (int) $examSessionId || ! $assignment->isActive()) {
                throw new RuntimeException('Select an eligible assignment from this grading session.');
            }
            if ($assignment->admissionAttempt === null) {
                throw new RuntimeException('The selected assignment has no admission attempt.');
            }

            $previousEvidence = [
                'detected_applicant_number' => $sheet->detected_applicant_number,
                'admission_attempt_id' => $sheet->admission_attempt_id,
                'set_resolution_reason' => $sheet->set_resolution_reason,
            ];
            $sheet->update([
                'admission_attempt_id' => $assignment->admission_attempt_id,
                'set_resolution_reason' => 'identity_exception',
                'version' => (int) $sheet->version + 1,
            ]);

            $this->recordAuditEvent->handle('grading.omr_applicant_exception_resolved', [
                'sheet_id' => $sheet->id,
                'batch_id' => $sheet->batch_id,
                'previous_evidence' => $previousEvidence,
                'selected_assignment_id' => $assignment->id,
                'selected_admission_attempt_id' => $assignment->admission_attempt_id,
                'reason' => $reason,
                'resolved_at' => now()->toIso8601String(),
            ], (string) $actor->id);

            return $sheet->fresh() ?? $sheet;
        });
    }

    private function assertExpectedRevision(OmrCaptureBatch $batch, ?int $expectedRevision): void
    {
        if ($expectedRevision === null) {
            return;
        }

        $revision = app(OmrRunStatusSnapshot::class)
            ->forBatch($batch->load(['sheets.pages', 'sheets.issues', 'sheets.processingRuns']))['revision'];

        if ($revision !== $expectedRevision) {
            throw new RuntimeException('The OMR run changed after validation. Refresh the current status before continuing.');
        }
    }
}
