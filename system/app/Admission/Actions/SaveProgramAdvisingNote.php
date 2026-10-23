<?php

namespace App\Admission\Actions;

use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SaveProgramAdvisingNote
{
    public function __construct(private readonly ProgramAdvisingNoteCommandSupport $commands) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        string $content,
        int $expectedVersion,
        ?string $reason = null,
        string $idempotencyKey = '',
        ?int $noteId = null,
        ?string $audience = null,
    ): ProgramAdvisingNoteRevision {
        return DB::transaction(function () use ($actor, $program, $cycle, $content, $expectedVersion, $reason, $idempotencyKey, $noteId, $audience): ProgramAdvisingNoteRevision {
            $freshProgram = Program::query()->findOrFail($program->id);
            $freshCycle = AcademicCycle::query()->findOrFail($cycle->id);
            if ((int) $freshProgram->institution_id !== (int) $freshCycle->institution_id) {
                throw new RuntimeException('The program and academic cycle must belong to the same institution.');
            }
            $this->commands->authorizeEdit($actor, $freshProgram, $freshCycle);

            $reason = $this->commands->normalizeReason($reason);
            $requestedAudience = $this->commands->normalizeAudience($audience);
            if (trim($content) === '' || mb_strlen($content) > 20000) {
                throw new RuntimeException('Note content must be at most 20,000 characters.');
            }
            if ($expectedVersion < 0) {
                throw new RuntimeException('The advising note version is invalid.');
            }

            $note = $noteId === null
                ? null
                : ProgramAdvisingNote::query()
                    ->where('institution_id', $freshProgram->institution_id)
                    ->where('program_id', $freshProgram->id)
                    ->where('academic_cycle_id', $freshCycle->id)
                    ->whereKey($noteId)
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($note !== null) {
                $this->commands->assertAudienceAccess($actor, $note);
                if ($requestedAudience !== null && $requestedAudience !== $note->audience) {
                    throw new RuntimeException('An advising note audience cannot be changed after the note is created.');
                }
                $effectiveAudience = $note->audience;
            } else {
                $effectiveAudience = $requestedAudience ?? 'program';
            }

            $payload = [
                'note_id' => $noteId,
                'program_id' => $freshProgram->id,
                'academic_cycle_id' => $freshCycle->id,
                'content' => $content,
                'expected_version' => $expectedVersion,
                'reason' => $reason,
            ];
            if ($effectiveAudience === 'private') {
                $payload['audience'] = 'private';
            }

            $aggregate = $noteId === null
                ? 'program-cycle:'.$freshProgram->id.':'.$freshCycle->id
                : 'note:'.$noteId;
            $operation = $this->commands->claim($actor, 'advising.note.save', $aggregate, $payload, $idempotencyKey);
            $operation->refresh();

            $resolvedNoteId = $note?->id ?? ($operation->payload['receipt_note_id'] ?? null);
            if ($note === null && $resolvedNoteId !== null) {
                $note = ProgramAdvisingNote::query()
                    ->where('institution_id', $freshProgram->institution_id)
                    ->where('program_id', $freshProgram->id)
                    ->where('academic_cycle_id', $freshCycle->id)
                    ->whereKey($resolvedNoteId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->commands->assertAudienceAccess($actor, $note);
                if ($note->audience !== $effectiveAudience) {
                    throw new RuntimeException('An advising note audience cannot be changed after the note is created.');
                }
            }

            if ($note === null) {
                if ($expectedVersion !== 0) {
                    throw new RuntimeException('The advising note version is stale.');
                }
                $note = ProgramAdvisingNote::query()->create([
                    'institution_id' => $freshProgram->institution_id,
                    'program_id' => $freshProgram->id,
                    'academic_cycle_id' => $freshCycle->id,
                    'audience' => $effectiveAudience,
                    'owner_user_id' => $effectiveAudience === 'private' ? $actor->id : null,
                ]);
                $operation->forceFill(['payload' => [...$operation->payload, 'receipt_note_id' => $note->id]])->save();
            }

            $replay = $this->commands->replayRevision($operation, $note);
            if ($replay !== null) {
                return $replay;
            }
            if ($note->current_version !== $expectedVersion) {
                throw new RuntimeException('The advising note version is stale.');
            }

            return $this->commands->appendRevision($note, $actor, $content, $reason, 'save', $operation);
        });
    }
}
