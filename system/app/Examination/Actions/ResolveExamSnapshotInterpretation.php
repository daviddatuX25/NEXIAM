<?php

namespace App\Examination\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Support\ExamSnapshotInterpretation;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ResolveExamSnapshotInterpretation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ExamSnapshotInterpretation $interpretation,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(ExamSession $session, ExamSessionMaterialSnapshot $snapshot, User $actor, int $expectedVersion, string $seatingMode, string $reason, ?string $provenanceNotes = null): ExamSessionMaterialSnapshot
    {
        return DB::transaction(function () use ($session, $snapshot, $actor, $expectedVersion, $seatingMode, $reason, $provenanceNotes): ExamSessionMaterialSnapshot {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, [
                'institution_id' => (int) $session->academicCycle->institution_id,
                'academic_cycle_id' => (int) $session->academic_cycle_id,
                'office' => 'guidance',
            ])->allowed) {
                throw new AuthorizationException;
            }
            $snapshot = ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->lockForUpdate()->findOrFail($snapshot->id);
            if ((int) $snapshot->revision !== $expectedVersion) {
                throw new RuntimeException('Stale snapshot revision.');
            }
            $errors = [];
            if (mb_strlen(trim($reason)) < 5) {
                $errors['reason'] = 'Provide an interpretation resolution reason of at least 5 characters.';
            }
            if (trim($provenanceNotes ?? '') === '') {
                $errors['provenance_notes'] = 'Provide concrete provenance references.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $this->interpretation->enrich($snapshot, $seatingMode);
            if ($snapshot->is_current && $session->seating_mode === null) {
                $session->update([
                    'seating_mode' => $seatingMode,
                    'seating_arrangement_id' => $seatingMode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY ? null : $session->seating_arrangement_id,
                    'version' => $session->version + 1,
                ]);
            }
            $this->audit->handle('exam.snapshot_interpretation_resolved', [
                'exam_session_id' => $session->id,
                'snapshot_id' => $snapshot->id,
                'snapshot_revision' => $snapshot->revision,
                'seating_mode' => $seatingMode,
                'reason' => $reason,
                'provenance_notes' => $provenanceNotes,
            ], (string) $actor->id);

            return $snapshot->fresh();
        });
    }
}
