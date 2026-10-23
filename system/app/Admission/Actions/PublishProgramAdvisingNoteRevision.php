<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNotePublication;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PublishProgramAdvisingNoteRevision
{
    public function __construct(
        private readonly ProgramAdvisingNoteCommandSupport $commands,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        ProgramAdvisingNote $note,
        ProgramAdvisingNoteRevision $revision,
        int $expectedVersion,
        ?string $reason = null,
        string $idempotencyKey = '',
    ): ProgramAdvisingNotePublication {
        return DB::transaction(function () use ($actor, $note, $revision, $expectedVersion, $reason, $idempotencyKey): ProgramAdvisingNotePublication {
            $locked = ProgramAdvisingNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            $program = Program::query()->findOrFail($locked->program_id);
            $cycle = AcademicCycle::query()->findOrFail($locked->academic_cycle_id);
            $this->commands->authorizePublish($actor, $program, $cycle);
            $this->commands->assertAudienceAccess($actor, $locked);
            $this->commands->assertPublishableAudience($locked);
            $exactRevision = ProgramAdvisingNoteRevision::query()
                ->where('program_advising_note_id', $locked->id)
                ->whereKey($revision->id)
                ->firstOrFail();
            $reason = $this->commands->normalizeReason($reason);

            $payload = ['note_id' => $locked->id, 'revision_id' => $exactRevision->id, 'expected_version' => $expectedVersion, 'reason' => $reason];
            $operation = $this->commands->claim($actor, 'advising.note.publish', 'note:'.$locked->id, $payload, $idempotencyKey);
            $operation->refresh();
            $receiptId = $operation->payload['receipt_publication_id'] ?? null;
            if ($receiptId !== null) {
                return ProgramAdvisingNotePublication::query()
                    ->where('program_advising_note_id', $locked->id)
                    ->whereKey($receiptId)
                    ->firstOrFail();
            }
            if ($locked->current_version !== $expectedVersion
                || (int) $locked->current_revision_id !== (int) $exactRevision->id) {
                throw new RuntimeException('The reviewed advising note revision is no longer current.');
            }

            $receipt = ProgramAdvisingNotePublication::query()->create([
                'program_advising_note_id' => $locked->id,
                'revision_id' => $exactRevision->id,
                'operation' => 'publish',
                'actor_user_id' => $actor->id,
                'reason' => $reason,
                'expected_version' => $expectedVersion,
                'idempotency_operation_key' => $operation->idempotency_key,
                'recorded_at' => now(),
            ]);
            $locked->forceFill([
                'published_revision_id' => $exactRevision->id,
                'published_publication_id' => $receipt->id,
            ])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_publication_id' => $receipt->id]])->save();
            $this->audit->handle('advising_note.published', [
                'note_id' => $locked->id,
                'revision_id' => $exactRevision->id,
                'publication_id' => $receipt->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $receipt;
        });
    }
}
