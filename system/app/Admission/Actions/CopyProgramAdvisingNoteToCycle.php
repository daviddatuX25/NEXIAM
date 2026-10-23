<?php

namespace App\Admission\Actions;

use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CopyProgramAdvisingNoteToCycle
{
    public function __construct(private readonly ProgramAdvisingNoteCommandSupport $commands) {}

    public function handle(
        User $actor,
        ProgramAdvisingNoteRevision $sourceRevision,
        Program $targetProgram,
        AcademicCycle $targetCycle,
        int $expectedVersion,
        ?string $reason = null,
        string $idempotencyKey = '',
        ?string $targetAudience = null,
    ): ProgramAdvisingNoteRevision {
        return DB::transaction(function () use ($actor, $sourceRevision, $targetProgram, $targetCycle, $expectedVersion, $reason, $idempotencyKey, $targetAudience): ProgramAdvisingNoteRevision {
            $source = ProgramAdvisingNoteRevision::query()->with('note')->findOrFail($sourceRevision->id);
            $sourceNote = $source->note;
            $sourceProgram = Program::query()->findOrFail($sourceNote->program_id);
            $sourceCycle = AcademicCycle::query()->findOrFail($sourceNote->academic_cycle_id);
            $this->commands->authorizeView($actor, $sourceProgram, $sourceCycle);
            $this->commands->assertAudienceAccess($actor, $sourceNote);

            $program = Program::query()->findOrFail($targetProgram->id);
            $cycle = AcademicCycle::query()->findOrFail($targetCycle->id);
            $targetAudience = $this->commands->normalizeAudience($targetAudience) ?? $sourceNote->audience;
            $sameCycle = (int) $sourceCycle->id === (int) $cycle->id;
            $privateStaffCopy = $sourceNote->audience === 'private' && $targetAudience === 'program';
            if ((int) $program->institution_id !== (int) $cycle->institution_id
                || (int) $program->institution_id !== (int) $sourceNote->institution_id
                || (int) $program->id !== (int) $sourceNote->program_id
                || ($sameCycle && ! $privateStaffCopy)) {
                throw new RuntimeException('Copy requires the same institution and program; same-cycle copying is limited to an explicit private-to-program staff copy.');
            }
            $this->commands->authorizeEdit($actor, $program, $cycle);
            $reason = $this->commands->normalizeReason($reason);
            if ($expectedVersion !== 0) {
                throw new RuntimeException('Copy creates a new note at version zero.');
            }

            $payload = [
                'source_revision_id' => $source->id,
                'source_note_id' => $sourceNote->id,
                'target_note_id' => null,
                'target_program_id' => $program->id,
                'target_cycle_id' => $cycle->id,
                'expected_version' => 0,
                'content' => $source->content,
                'reason' => $reason,
            ];
            if ($targetAudience === 'private' || $privateStaffCopy) {
                $payload['target_audience'] = $targetAudience;
            }

            $operation = $this->commands->claim($actor, 'advising.note.copy', 'program-cycle:'.$program->id.':'.$cycle->id, $payload, $idempotencyKey);
            $operation->refresh();
            $resolvedNoteId = $operation->payload['receipt_note_id'] ?? null;
            $note = $resolvedNoteId === null
                ? null
                : ProgramAdvisingNote::query()
                    ->where('institution_id', $program->institution_id)
                    ->where('program_id', $program->id)
                    ->where('academic_cycle_id', $cycle->id)
                    ->whereKey($resolvedNoteId)
                    ->lockForUpdate()
                    ->firstOrFail();
            if ($note !== null) {
                $this->commands->assertAudienceAccess($actor, $note);
                if ($note->audience !== $targetAudience) {
                    throw new RuntimeException('An advising note audience cannot be changed after the note is created.');
                }
            }
            if ($note === null) {
                $note = ProgramAdvisingNote::query()->create([
                    'institution_id' => $program->institution_id,
                    'program_id' => $program->id,
                    'academic_cycle_id' => $cycle->id,
                    'audience' => $targetAudience,
                    'owner_user_id' => $targetAudience === 'private' ? $actor->id : null,
                ]);
                $operation->forceFill(['payload' => [...$operation->payload, 'receipt_note_id' => $note->id]])->save();
            }

            $replay = $this->commands->replayRevision($operation, $note);
            if ($replay !== null) {
                return $replay;
            }
            if ($note->current_version !== 0) {
                throw new RuntimeException('A copy always starts a new note at version zero.');
            }

            return $this->commands->appendRevision(
                $note,
                $actor,
                $source->content,
                $reason,
                'copy',
                $operation,
                copiedFromRevisionId: $privateStaffCopy ? null : (int) $source->id,
            );
        });
    }
}
