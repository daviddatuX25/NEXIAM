<?php

namespace App\Consultation\Queries;

use App\Admission\Actions\ProgramAdvisingNoteCommandSupport;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNotePublication;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;

final class ProgramAdvisingNoteQuery
{
    public function __construct(private readonly ProgramAdvisingNoteCommandSupport $commands) {}

    /**
     * @return array{items:list<array<string, mixed>>,pagination:array{current_page:int,per_page:int,total:int,last_page:int},counts:array{total:int,drafts:int,shared:int},capabilities:array{can_create:bool,can_edit:bool,can_publish:bool}}
     */
    public function boardFor(User $actor, Program $program, AcademicCycle $cycle, int $page = 1, int $perPage = 50, string $search = ''): array
    {
        [$page, $perPage] = $this->pageBounds($page, $perPage);
        $scope = $this->scopeFor($actor, $program, $cycle);
        $canEdit = $this->commands->canEdit($actor, $scope['program'], $scope['cycle']);
        $canPublish = $this->commands->canPublish($actor, $scope['program'], $scope['cycle']);
        $base = $this->visibleNotesIn($actor, $scope['program'], $scope['cycle']);
        $counts = [
            'total' => (clone $base)->count(),
            'drafts' => (clone $base)->where(function ($notes): void {
                $notes->whereNull('published_revision_id')
                    ->orWhereColumn('current_revision_id', '!=', 'published_revision_id');
            })->count(),
            'shared' => (clone $base)->where('audience', 'program')->whereNotNull('published_revision_id')->count(),
        ];
        $query = $this->visibleNotesIn($actor, $scope['program'], $scope['cycle'])
            ->with(['currentRevision.author', 'publishedRevision.author', 'publishedPublication.actor'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
        $search = trim($search);
        if ($search !== '') {
            $query->whereHas('currentRevision', fn ($revision) => $revision->where('content', 'like', '%'.$search.'%'));
        }
        $notes = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $notes->getCollection()->map(fn (ProgramAdvisingNote $note): array => $this->boardItem($note, $actor, $canEdit, $canPublish))->all(),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
            'counts' => $counts,
            'capabilities' => ['can_create' => $canEdit, 'can_edit' => $canEdit, 'can_publish' => $canPublish],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function publishedFor(User $actor, Program $program, AcademicCycle $cycle): array
    {
        $scope = $this->scopeFor($actor, $program, $cycle);

        return $this->notesIn($scope['program'], $scope['cycle'])
            ->where('audience', 'program')
            ->whereNotNull('published_revision_id')
            ->with(['publishedRevision.author', 'publishedPublication.actor'])
            ->orderBy('id')
            ->get()
            ->map(fn (ProgramAdvisingNote $note): array => $this->publishedItem($note, $scope['program'], $scope['cycle']))
            ->all();
    }

    /** @return array{note_id:int,version:int,current_revision_id:?int,published_revision_id:?int,published_publication_id:?int,audience:string,is_private_owner:bool,can_edit:bool,can_publish:bool,content:?string,updated_at:?string,author_name:?string}|null */
    public function draftFor(User $actor, Program $program, AcademicCycle $cycle, int $noteId): ?array
    {
        $scope = $this->scopeFor($actor, $program, $cycle);
        $note = $this->findNote($actor, $scope['program'], $scope['cycle'], $noteId);
        if ($note === null) {
            return null;
        }
        $note->loadMissing('currentRevision.author');
        $canEdit = $this->commands->canEdit($actor, $scope['program'], $scope['cycle']);
        $canPublish = $note->audience === 'program'
            && $this->commands->canPublish($actor, $scope['program'], $scope['cycle']);

        return [
            'note_id' => (int) $note->id,
            'version' => (int) $note->current_version,
            'current_revision_id' => $note->current_revision_id === null ? null : (int) $note->current_revision_id,
            'published_revision_id' => $note->published_revision_id === null ? null : (int) $note->published_revision_id,
            'published_publication_id' => $note->published_publication_id === null ? null : (int) $note->published_publication_id,
            'audience' => $note->audience,
            'is_private_owner' => $note->audience === 'private' && (int) $note->owner_user_id === (int) $actor->id,
            'can_edit' => $canEdit,
            'can_publish' => $canPublish,
            'content' => $note->currentRevision?->content,
            'updated_at' => $note->updated_at?->toISOString(),
            'author_name' => $note->currentRevision?->author?->name,
        ];
    }

    /**
     * @return array{items:list<array{id:int,version:int,content:string,author_id:int,author_name:string,reason:string,command:string,predecessor_revision_id:?int,restored_from_revision_id:?int,copied_from_revision_id:?int,created_at:string}>,pagination:array{current_page:int,per_page:int,total:int,last_page:int}}
     */
    public function historyFor(User $actor, Program $program, AcademicCycle $cycle, int $noteId, int $page = 1, int $perPage = 20): array
    {
        $scope = $this->scopeFor($actor, $program, $cycle);
        $note = $this->findNote($actor, $scope['program'], $scope['cycle'], $noteId);
        [$page, $perPage] = $this->pageBounds($page, $perPage);
        if ($note === null) {
            return $this->emptyPage($page, $perPage);
        }
        $revisions = ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $note->id)->with('author')->orderByDesc('version')->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $revisions->getCollection()->map(static fn (ProgramAdvisingNoteRevision $revision): array => [
                'id' => (int) $revision->id,
                'version' => (int) $revision->version,
                'content' => $revision->content,
                'author_id' => (int) $revision->authored_by_user_id,
                'author_name' => $revision->author->name,
                'reason' => $revision->reason,
                'command' => $revision->command,
                'predecessor_revision_id' => $revision->predecessor_revision_id === null ? null : (int) $revision->predecessor_revision_id,
                'restored_from_revision_id' => $revision->restored_from_revision_id === null ? null : (int) $revision->restored_from_revision_id,
                'copied_from_revision_id' => $revision->copied_from_revision_id === null ? null : (int) $revision->copied_from_revision_id,
                'created_at' => $revision->created_at->toISOString(),
            ])->all(),
            'pagination' => ['current_page' => $revisions->currentPage(), 'per_page' => $revisions->perPage(), 'total' => $revisions->total(), 'last_page' => $revisions->lastPage()],
        ];
    }

    /**
     * @return array{items:list<array{id:int,revision_id:int,operation:string,actor_id:int,actor_name:string,reason:string,expected_version:int,retracts_publication_id:?int,recorded_at:string}>,pagination:array{current_page:int,per_page:int,total:int,last_page:int}}
     */
    public function publicationHistoryFor(User $actor, Program $program, AcademicCycle $cycle, int $noteId, int $page = 1, int $perPage = 20): array
    {
        $scope = $this->scopeFor($actor, $program, $cycle);
        $note = $this->findNote($actor, $scope['program'], $scope['cycle'], $noteId);
        [$page, $perPage] = $this->pageBounds($page, $perPage);
        if ($note === null) {
            return $this->emptyPage($page, $perPage);
        }
        $receipts = ProgramAdvisingNotePublication::query()->where('program_advising_note_id', $note->id)->with('actor')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $receipts->getCollection()->map(static fn (ProgramAdvisingNotePublication $receipt): array => [
                'id' => (int) $receipt->id,
                'revision_id' => (int) $receipt->revision_id,
                'operation' => $receipt->operation,
                'actor_id' => (int) $receipt->actor_user_id,
                'actor_name' => $receipt->actor->name,
                'reason' => $receipt->reason,
                'expected_version' => (int) $receipt->expected_version,
                'retracts_publication_id' => $receipt->retracts_publication_id === null ? null : (int) $receipt->retracts_publication_id,
                'recorded_at' => $receipt->recorded_at->toISOString(),
            ])->all(),
            'pagination' => ['current_page' => $receipts->currentPage(), 'per_page' => $receipts->perPage(), 'total' => $receipts->total(), 'last_page' => $receipts->lastPage()],
        ];
    }

    /** @return array{program:Program,cycle:AcademicCycle} */
    private function scopeFor(User $actor, Program $program, AcademicCycle $cycle): array
    {
        $freshProgram = Program::query()->findOrFail($program->id);
        $freshCycle = AcademicCycle::query()->findOrFail($cycle->id);
        $this->commands->authorizeView($actor, $freshProgram, $freshCycle);
        abort_unless((int) $freshProgram->institution_id === (int) $freshCycle->institution_id, 404);

        return ['program' => $freshProgram, 'cycle' => $freshCycle];
    }

    private function notesIn(Program $program, AcademicCycle $cycle)
    {
        return ProgramAdvisingNote::query()
            ->where('institution_id', $program->institution_id)
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id);
    }

    private function visibleNotesIn(User $actor, Program $program, AcademicCycle $cycle)
    {
        return $this->notesIn($program, $cycle)
            ->where(function ($notes) use ($actor): void {
                $notes->where('audience', 'program')
                    ->orWhere(function ($privateNotes) use ($actor): void {
                        $privateNotes->where('audience', 'private')
                            ->where('owner_user_id', $actor->id);
                    });
            });
    }

    private function findNote(User $actor, Program $program, AcademicCycle $cycle, int $noteId): ?ProgramAdvisingNote
    {
        return $this->visibleNotesIn($actor, $program, $cycle)->whereKey($noteId)->first();
    }

    /** @return array<string, mixed> */
    private function publishedItem(ProgramAdvisingNote $note, Program $program, AcademicCycle $cycle): array
    {
        $revision = $note->publishedRevision;
        $publication = $note->publishedPublication;

        return [
            'note_id' => (int) $note->id,
            'program_id' => (int) $program->id,
            'program_code' => $program->code,
            'program_name' => $program->name,
            'academic_cycle_id' => (int) $cycle->id,
            'cycle_code' => $cycle->code,
            'audience' => $note->audience,
            'revision_id' => (int) $revision->id,
            'version' => (int) $revision->version,
            'content' => $revision->content,
            'author_id' => (int) $revision->authored_by_user_id,
            'author_name' => $revision->author->name,
            'publisher_id' => (int) $publication->actor_user_id,
            'publisher_name' => $publication->actor->name,
            'published_at' => $publication->recorded_at->toISOString(),
            'details_context' => ['program_id' => (int) $program->id, 'academic_cycle_id' => (int) $cycle->id, 'note_id' => (int) $note->id],
        ];
    }

    /** @return array<string, mixed> */
    private function boardItem(ProgramAdvisingNote $note, User $actor, bool $canEdit, bool $canPublish): array
    {
        return [
            'note_id' => (int) $note->id,
            'version' => (int) $note->current_version,
            'current_revision_id' => $note->current_revision_id === null ? null : (int) $note->current_revision_id,
            'published_revision_id' => $note->published_revision_id === null ? null : (int) $note->published_revision_id,
            'published_publication_id' => $note->published_publication_id === null ? null : (int) $note->published_publication_id,
            'audience' => $note->audience,
            'is_private_owner' => $note->audience === 'private' && (int) $note->owner_user_id === (int) $actor->id,
            'can_edit' => $canEdit,
            'can_publish' => $canPublish && $note->audience === 'program',
            'content' => $note->currentRevision?->content,
            'updated_at' => $note->updated_at?->toISOString(),
            'author_name' => $note->currentRevision?->author?->name,
            'published_author_name' => $note->publishedRevision?->author?->name,
        ];
    }

    /** @return array{items:list<never>,pagination:array{current_page:int,per_page:int,total:int,last_page:int}} */
    private function emptyPage(int $page, int $perPage): array
    {
        return ['items' => [], 'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1]];
    }

    /** @return array{int, int} */
    private function pageBounds(int $page, int $perPage): array
    {
        return [max(1, $page), min(100, max(1, $perPage))];
    }
}
