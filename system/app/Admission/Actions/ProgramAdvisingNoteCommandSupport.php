<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use RuntimeException;

final class ProgramAdvisingNoteCommandSupport
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function authorizeView(User $actor, Program $program, AcademicCycle $cycle): void
    {
        $this->authorizer->authorize($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW, $program, $cycle);
    }

    public function authorizeEdit(User $actor, Program $program, AcademicCycle $cycle): void
    {
        $this->authorizer->authorize($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT, $program, $cycle, true);
        $this->assertWritableCycle($cycle);
    }

    public function authorizePublish(User $actor, Program $program, AcademicCycle $cycle): void
    {
        $this->authorizer->authorize($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH, $program, $cycle, true);
        $this->assertWritableCycle($cycle);
    }

    public function canEdit(User $actor, Program $program, AcademicCycle $cycle): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT, $program, $cycle, true);
    }

    public function canPublish(User $actor, Program $program, AcademicCycle $cycle): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH, $program, $cycle, true);
    }

    public function assertAudienceAccess(User $actor, ProgramAdvisingNote $note): void
    {
        if ($note->audience === 'private' && (int) $note->owner_user_id !== (int) $actor->id) {
            throw new AuthorizationException('This advising note is not available to the current actor.');
        }

        if (! in_array($note->audience, ['program', 'private'], true)) {
            throw new AuthorizationException('This advising note audience is invalid.');
        }
    }

    public function assertPublishableAudience(ProgramAdvisingNote $note): void
    {
        if ($note->audience !== 'program') {
            throw new AuthorizationException('Private advising notes cannot be shared for consultation.');
        }
    }

    public function normalizeReason(?string $reason): string
    {
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new RuntimeException('An advising note reason must be at most 500 characters.');
        }

        return blank($reason) ? '' : $reason;
    }

    public function normalizeAudience(?string $audience): ?string
    {
        if ($audience !== null && ! in_array($audience, ['program', 'private'], true)) {
            throw new RuntimeException('The advising note audience is invalid.');
        }

        return $audience;
    }

    public function assertWritableCycle(AcademicCycle $cycle): void
    {
        $today = Carbon::today();
        $isExplicitlyPlanned = $cycle->opens_on !== null && $cycle->opens_on->gt($today);
        if (! $cycle->is_active && ! $isExplicitlyPlanned) {
            throw new RuntimeException('Archived academic cycles are read-only.');
        }
    }

    /** @param array<string, mixed> $payload */
    public function claim(User $actor, string $command, string $aggregate, array $payload, string $idempotencyKey): Operation
    {
        if (trim($idempotencyKey) === '') {
            throw new RuntimeException('A stable idempotency key is required.');
        }

        return $this->idempotency->claim($actor, $command, $aggregate, $payload, $idempotencyKey);
    }

    public function replayRevision(Operation $operation, ProgramAdvisingNote $note): ?ProgramAdvisingNoteRevision
    {
        $revisionId = $operation->payload['receipt_revision_id'] ?? null;
        if ($revisionId === null) {
            return null;
        }

        return ProgramAdvisingNoteRevision::query()
            ->where('program_advising_note_id', $note->id)
            ->whereKey($revisionId)
            ->firstOrFail();
    }

    public function appendRevision(
        ProgramAdvisingNote $note,
        User $actor,
        string $content,
        string $reason,
        string $command,
        Operation $operation,
        ?int $restoredFromRevisionId = null,
        ?int $copiedFromRevisionId = null,
    ): ProgramAdvisingNoteRevision {
        $predecessorId = $note->current_revision_id === null ? null : (int) $note->current_revision_id;
        $revision = ProgramAdvisingNoteRevision::query()->create([
            'program_advising_note_id' => $note->id,
            'version' => $note->current_version + 1,
            'predecessor_revision_id' => $predecessorId,
            'restored_from_revision_id' => $restoredFromRevisionId,
            'copied_from_revision_id' => $copiedFromRevisionId,
            'content' => $content,
            'authored_by_user_id' => $actor->id,
            'reason' => $reason,
            'command' => $command,
            'idempotency_operation_key' => $operation->idempotency_key,
        ]);

        $note->forceFill([
            'current_version' => $revision->version,
            'current_revision_id' => $revision->id,
        ])->save();

        $operation->forceFill(['payload' => [...$operation->payload, 'receipt_revision_id' => $revision->id]])->save();
        $this->audit->handle('advising_note.revision_appended', [
            'note_id' => $note->id,
            'revision_id' => $revision->id,
            'version' => $revision->version,
            'command' => $command,
            'reason' => $reason,
        ], (string) $actor->id);

        return $revision;
    }
}
