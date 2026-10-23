<?php

namespace App\Admission\Actions;

use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RestoreProgramAdvisingNoteRevision
{
    public function __construct(private readonly ProgramAdvisingNoteCommandSupport $commands) {}

    public function handle(
        User $actor,
        ProgramAdvisingNote $note,
        ProgramAdvisingNoteRevision $sourceRevision,
        int $expectedVersion,
        ?string $reason = null,
        string $idempotencyKey = '',
    ): ProgramAdvisingNoteRevision {
        return DB::transaction(function () use ($actor, $note, $sourceRevision, $expectedVersion, $reason, $idempotencyKey): ProgramAdvisingNoteRevision {
            $locked = ProgramAdvisingNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            $program = Program::query()->findOrFail($locked->program_id);
            $cycle = AcademicCycle::query()->findOrFail($locked->academic_cycle_id);
            $this->commands->authorizeEdit($actor, $program, $cycle);
            $this->commands->assertAudienceAccess($actor, $locked);
            $source = ProgramAdvisingNoteRevision::query()
                ->where('program_advising_note_id', $locked->id)
                ->whereKey($sourceRevision->id)
                ->firstOrFail();
            $reason = $this->commands->normalizeReason($reason);
            $payload = ['note_id' => $locked->id, 'source_revision_id' => $source->id, 'expected_version' => $expectedVersion, 'reason' => $reason];
            if ($locked->audience === 'private') {
                $payload['audience'] = 'private';
            }
            $operation = $this->commands->claim($actor, 'advising.note.restore', 'note:'.$locked->id, $payload, $idempotencyKey);
            $operation->refresh();
            $replay = $this->commands->replayRevision($operation, $locked);
            if ($replay !== null) {
                return $replay;
            }
            if ($locked->current_version !== $expectedVersion) {
                throw new RuntimeException('The advising note version is stale.');
            }

            return $this->commands->appendRevision($locked, $actor, $source->content, $reason, 'restore', $operation, restoredFromRevisionId: (int) $source->id);
        });
    }
}
