<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\CopyProgramAdvisingNoteToCycle;
use App\Admission\Actions\PublishProgramAdvisingNoteRevision;
use App\Admission\Actions\RecordProgramAdvisingNoteUse;
use App\Admission\Actions\RestoreProgramAdvisingNoteRevision;
use App\Admission\Actions\RetractProgramAdvisingNote;
use App\Admission\Actions\SaveProgramAdvisingNote;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Queries\ProgramAdvisingNoteQuery;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNotePublication;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Admission\ProgramAdvisingNoteUsage;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\Models\StaffProfile;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

function advisingNoteFixture(): array
{
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-TEST',
        'name' => 'Advising Note Test Program',
        'is_active' => true,
    ]);

    $author = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $author->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => null,
        'program_codes' => [$program->code],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $author->givePermissionTo(
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
    );

    $guidance = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $guidance->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'department_id' => null,
        'program_codes' => [],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $guidance->givePermissionTo(
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
    );

    return compact('institution', 'cycle', 'program', 'author', 'guidance');
}

test('advising note drafts append immutable history and restore from an exact revision', function (): void {
    ['cycle' => $cycle, 'program' => $program, 'author' => $author] = advisingNoteFixture();
    $save = app(SaveProgramAdvisingNote::class);
    $restore = app(RestoreProgramAdvisingNoteRevision::class);

    $first = $save->handle($author, $program, $cycle, 'Initial advising text.', 0, 'Initial draft', 'save-first');
    $second = $save->handle($author, $program, $cycle, 'Revised advising text.', 1, 'Clarified guidance', 'save-second', (int) $first->program_advising_note_id);
    $restored = $restore->handle($author, $second->note, $first, 2, 'Restore reviewed wording', 'restore-first');

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($restored->version)->toBe(3)
        ->and($restored->content)->toBe('Initial advising text.')
        ->and($restored->restored_from_revision_id)->toBe($first->id)
        ->and($restored->note->published_revision_id)->toBeNull()
        ->and(ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $first->program_advising_note_id)->count())->toBe(3);
    $historyPage = app(ProgramAdvisingNoteQuery::class)->historyFor($author, $program, $cycle, (int) $first->program_advising_note_id, 2, 1);
    expect($historyPage['items'])->toHaveCount(1)
        ->and($historyPage['items'][0]['id'])->toBe($second->id)
        ->and($historyPage['items'][0]['version'])->toBe(2)
        ->and($historyPage['pagination'])->toMatchArray(['current_page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3]);
});

test('draft retries replay the original receipt and changed or stale writes conflict', function (): void {
    ['cycle' => $cycle, 'program' => $program, 'author' => $author] = advisingNoteFixture();
    $action = app(SaveProgramAdvisingNote::class);

    expect((new ProgramAdvisingNote)->current_version)->toBe(0);

    $first = $action->handle($author, $program, $cycle, 'Exact saved text.', 0, 'Save once', 'same-key');
    $replay = $action->handle($author, $program, $cycle, 'Exact saved text.', 0, 'Save once', 'same-key');

    expect($replay->id)->toBe($first->id)
        ->and($first->note->current_version)->toBe(1)
        ->and(ProgramAdvisingNoteRevision::query()->count())->toBe(1);

    $second = $action->handle($author, $program, $cycle, 'Updated saved text.', 1, 'Save again', 'next-key', (int) $first->program_advising_note_id);
    $originalReplay = $action->handle($author, $program, $cycle, 'Exact saved text.', 0, 'Save once', 'same-key');

    expect($second->version)->toBe(2)
        ->and($originalReplay->id)->toBe($first->id)
        ->and(ProgramAdvisingNoteRevision::query()->count())->toBe(2);
    expect(fn () => $action->handle($author, $program, $cycle, 'Stale text.', 1, 'Save stale draft', 'stale-key'))
        ->toThrow(RuntimeException::class, 'The advising note version is stale.');
    expect(fn () => $action->handle($author, $program, $cycle, 'Changed text.', 0, 'Save once', 'same-key'))
        ->toThrow(RuntimeException::class);
    expect(ProgramAdvisingNoteRevision::query()->count())->toBe(2);
});

test('only exact current publication is projected and retraction preserves the publication receipt', function (): void {
    ['cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $revision = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Published counseling guidance.', 0, 'Prepare for review', 'note-draft');
    $query = app(ProgramAdvisingNoteQuery::class);

    expect($query->publishedFor($author, $program, $cycle))->toBe([]);

    $publication = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $revision->note, $revision, 1, 'Guidance review complete', 'note-publish');
    $draftRevision = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'New draft awaiting Guidance review.', 1, 'Prepare updated guidance', 'note-draft-v2', (int) $revision->program_advising_note_id);
    expect($query->publishedFor($author, $program, $cycle)[0])->toMatchArray([
        'content' => 'Published counseling guidance.',
        'revision_id' => $revision->id,
        'version' => 1,
        'author_id' => $author->id,
        'publisher_id' => $guidance->id,
    ]);

    $updatedPublication = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $draftRevision->note, $draftRevision, 2, 'Review updated guidance', 'note-publish-v2');
    expect($query->publishedFor($author, $program, $cycle)[0])->toMatchArray([
        'content' => 'New draft awaiting Guidance review.',
        'revision_id' => $draftRevision->id,
        'version' => 2,
        'author_id' => $author->id,
        'publisher_id' => $guidance->id,
    ]);

    $retraction = app(RetractProgramAdvisingNote::class)->handle($guidance, $draftRevision->note, $updatedPublication, 2, 'Guidance has changed', 'note-retract');
    $historyPage = $query->publicationHistoryFor($author, $program, $cycle, (int) $draftRevision->program_advising_note_id, 2, 1);
    expect($retraction->operation)->toBe('retract')
        ->and($retraction->retracts_publication_id)->toBe($updatedPublication->id)
        ->and($query->publishedFor($author, $program, $cycle))->toBe([])
        ->and($historyPage['items'])->toHaveCount(1)
        ->and($historyPage['items'][0]['operation'])->toBe('publish')
        ->and($historyPage['items'][0]['revision_id'])->toBe($draftRevision->id)
        ->and($historyPage['pagination'])->toMatchArray(['current_page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3])
        ->and(ProgramAdvisingNotePublication::query()->where('operation', 'publish')->count())->toBe(2)
        ->and(ProgramAdvisingNotePublication::query()->where('operation', 'retract')->count())->toBe(1);
});
test('multiple notes retain independent versions and all current publications are returned to scoped readers', function (): void {
    ['cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $save = app(SaveProgramAdvisingNote::class);
    $first = $save->handle($author, $program, $cycle, 'First shared guidance.', 0, 'Create first card', 'multi-first');
    $second = $save->handle($author, $program, $cycle, 'Second shared guidance.', 0, 'Create second card', 'multi-second');
    $save->handle($author, $program, $cycle, 'Updated first card.', 1, 'Update first only', 'multi-first-v2', (int) $first->program_advising_note_id);
    app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $first->note, $first->note->currentRevision()->firstOrFail(), 2, 'Review first', 'multi-first-publish');
    app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $second->note, $second, 1, 'Review second', 'multi-second-publish');

    $query = app(ProgramAdvisingNoteQuery::class);
    $board = $query->boardFor($author, $program, $cycle);
    $published = $query->publishedFor($author, $program, $cycle);
    expect($board['counts'])->toBe(['total' => 2, 'drafts' => 0, 'shared' => 2])
        ->and($board['items'])->toHaveCount(2)
        ->and($published)->toHaveCount(2)
        ->and(array_column($published, 'content'))->toContain('Updated first card.', 'Second shared guidance.')
        ->and($query->draftFor($author, $program, $cycle, (int) $first->program_advising_note_id)['content'])->toBe('Updated first card.')
        ->and($query->draftFor($author, $program, $cycle, (int) $second->program_advising_note_id)['content'])->toBe('Second shared guidance.')
        ->and($query->historyFor($author, $program, $cycle, (int) $first->program_advising_note_id)['pagination']['total'])->toBe(2)
        ->and($query->historyFor($author, $program, $cycle, (int) $second->program_advising_note_id)['pagination']['total'])->toBe(1);
});

test('explicit future copy pins a historical source into a new target note even when other notes exist', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author] = advisingNoteFixture();
    $save = app(SaveProgramAdvisingNote::class);
    $sourceRevision = $save->handle($author, $program, $cycle, 'Historical source wording.', 0, 'Source draft', 'source-draft');
    $save->handle($author, $program, $cycle, 'Newer source wording.', 1, 'Later source draft', 'newer-source', (int) $sourceRevision->program_advising_note_id);
    $targetCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-PLANNED-2028',
        'name' => 'Planned Note Cycle',
        'school_year' => '2028-2029',
        'semester' => 'First',
        'opens_on' => now()->addYears(2)->toDateString(),
        'closes_on' => now()->addYears(3)->toDateString(),
        'is_active' => false,
    ]);
    $copy = app(CopyProgramAdvisingNoteToCycle::class);
    $firstCopy = $copy->handle($author, $sourceRevision, $program, $targetCycle, 0, 'Prepare next cycle', 'copy-one');
    $firstReplay = $copy->handle($author, $sourceRevision, $program, $targetCycle, 0, 'Prepare next cycle', 'copy-one');
    $nextCopy = $copy->handle($author, $sourceRevision, $program, $targetCycle, 0, 'Prepare another note', 'copy-two');
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-PLANNED-2029',
        'name' => 'Another Planned Note Cycle',
        'school_year' => '2029-2030',
        'semester' => 'First',
        'opens_on' => now()->addYears(3)->toDateString(),
        'closes_on' => now()->addYears(4)->toDateString(),
        'is_active' => false,
    ]);
    expect(fn () => $copy->handle($author, $sourceRevision, $program, $otherCycle, 1, 'Prepare next cycle', 'copy-one'))
        ->toThrow(RuntimeException::class);

    expect($firstCopy->id)->toBe($firstReplay->id)
        ->and($firstCopy->version)->toBe(1)
        ->and($firstCopy->content)->toBe('Historical source wording.')
        ->and($firstCopy->copied_from_revision_id)->toBe($sourceRevision->id)
        ->and($nextCopy->version)->toBe(1)
        ->and($nextCopy->program_advising_note_id)->not->toBe($firstCopy->program_advising_note_id)
        ->and(ProgramAdvisingNote::query()->where('program_id', $program->id)->where('academic_cycle_id', $targetCycle->id)->count())->toBe(2)
        ->and(ProgramAdvisingNote::query()->where('academic_cycle_id', $otherCycle->id)->count())->toBe(0)
        ->and($nextCopy->note->published_revision_id)->toBeNull();
});

test('note view is required independently and only archived cycles are read-only', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author] = advisingNoteFixture();
    $revision = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Historical guidance.', 0, 'Initial', 'history-draft');
    $query = app(ProgramAdvisingNoteQuery::class);
    $noView = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $noView->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => null,
        'program_codes' => [$program->code],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $noView->givePermissionTo(CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT);

    expect(fn () => $query->historyFor($noView, $program, $cycle, (int) $revision->program_advising_note_id))
        ->toThrow(AuthorizationException::class);

    $cycle->update(['closes_on' => now()->subDay()->toDateString()]);
    $updated = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Active cycle remains editable after its close date.', 1, 'Correct active guidance', 'active-closed-date-save', (int) $revision->program_advising_note_id);
    expect($query->historyFor($author, $program, $cycle, (int) $revision->program_advising_note_id)['items'])->toHaveCount(2)
        ->and($updated->version)->toBe(2);

    $cycle->update(['is_active' => false, 'opens_on' => now()->subYears(2)->toDateString()]);
    expect(fn () => app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Cannot change archived history.', 2, 'Too late', 'archived-save', (int) $revision->program_advising_note_id))
        ->toThrow(AuthorizationException::class);
    expect($query->historyFor($author, $program, $cycle, (int) $revision->program_advising_note_id)['items'])->toHaveCount(2);
});

test('revoked author cannot replay a saved receipt or read note history', function (): void {
    ['cycle' => $cycle, 'program' => $program, 'author' => $author] = advisingNoteFixture();
    $action = app(SaveProgramAdvisingNote::class);
    $revision = $action->handle($author, $program, $cycle, 'Private advising draft.', 0, 'Draft', 'revoked-save');
    $author->revokePermissionTo(CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT);
    $author->revokePermissionTo(CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW);

    expect(fn () => $action->handle($author, $program, $cycle, 'Private advising draft.', 0, 'Draft', 'revoked-save'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(ProgramAdvisingNoteQuery::class)->historyFor($author, $program, $cycle, (int) $revision->program_advising_note_id))
        ->toThrow(AuthorizationException::class);
    expect($revision->refresh()->content)->toBe('Private advising draft.');
});

test('note queries and copy commands reject another program scope', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author] = advisingNoteFixture();
    $otherProgram = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-OTHER',
        'name' => 'Other Note Program',
        'is_active' => true,
    ]);
    $source = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Scoped source.', 0, 'Draft', 'scope-source');

    expect(fn () => app(ProgramAdvisingNoteQuery::class)->publishedFor($author, $otherProgram, $cycle))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(CopyProgramAdvisingNoteToCycle::class)->handle($author, $source, $otherProgram, $cycle, 0, 'Forged target', 'scope-copy'))
        ->toThrow(RuntimeException::class);
    expect(ProgramAdvisingNote::query()->where('program_id', $otherProgram->id)->count())->toBe(0);
});

test('explicit use creates an idempotent pinned receipt only after the D080 protected case gate', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $author->givePermissionTo(CapabilityCatalog::PROGRAM_HEAD_VIEW);
    $revision = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Use this published guidance.', 0, 'Initial draft', 'use-note-draft');

    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institution->id,
        'normalized_key' => 'NOTE-USE-APPLICANT-1',
        'first_name' => 'Case',
        'last_name' => 'Applicant',
        'birthdate' => '2008-01-01',
        'sex' => 'female',
    ]);
    $attempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => 'NOTE-USE-ATTEMPT-1',
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
    ]);
    $documentId = DB::table('result_documents')->insertGetId([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'note-use-test',
        'checksum' => hash('sha256', 'note-use-test'),
        'status' => 'released',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('result_releases')->insert([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $documentId,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'note-use-release-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'opened_by_user_id' => $guidance->id,
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'A program-specific protected review is required.',
        'idempotency_key' => 'note-use-follow-up-1',
        'version' => 1,
    ]);

    $action = app(RecordProgramAdvisingNoteUse::class);

    $privateRevision = app(SaveProgramAdvisingNote::class)->handle(
        $author,
        $program,
        $cycle,
        'Private-only advising note.',
        0,
        null,
        'use-private-note',
        null,
        'private',
    );
    expect(fn () => $action->handle($author, $attempt, $privateRevision, 'use-private-note-denied'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->handle($author, $attempt, $revision, 'use-before-publication'))
        ->toThrow(RuntimeException::class);
    $publication = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $revision->note, $revision, 1, 'Reviewed', 'use-note-publication');
    $decisionCount = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count();
    $usage = $action->handle($author, $attempt, $revision, 'use-published-note');
    $replay = $action->handle($author, $attempt, $revision, 'use-published-note');

    expect($usage->id)->toBe($replay->id)
        ->and($usage->publication_id)->toBe($publication->id)
        ->and($usage->revision_id)->toBe($revision->id)
        ->and($usage->admission_attempt_id)->toBe($attempt->id)
        ->and($usage->actor_user_id)->toBe($author->id)
        ->and(ProgramAdvisingNoteUsage::query()->count())->toBe(1)
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($decisionCount);
});

test('Guidance with current consultation authority can use a published note for an eligible case', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'author' => $author] = advisingNoteFixture();
    [$attempt, $applicant, $caseGuidance] = buildMessagingReadyAttempt($this, 'guidance-note-use@example.test');
    $program = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $author->staffProfile()->update(['program_codes' => [$program->code]]);
    $author->givePermissionTo(CapabilityCatalog::PROGRAM_HEAD_VIEW);
    $guidance = $caseGuidance;
    $revision = app(SaveProgramAdvisingNote::class)->handle(
        $author,
        $program,
        $cycle,
        'Guidance consultation text.',
        0,
        'Draft for Guidance review',
        'guidance-use-draft',
    );
    $publication = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $revision->note, $revision, 1, 'Published for consultation', 'guidance-use-publish');
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'guidance-note-use-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'guidance-note-use-acknowledgement');
    expect(app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $author)['tier'])->toBe('B');

    $unqualifiedGuidance = User::factory()->create();

    StaffProfile::query()->create([
        'user_id' => $unqualifiedGuidance->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'department_id' => null,
        'program_codes' => [],
        'active_cycle_code' => 'SYN-CYCLE-OUT-OF-SCOPE',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $unqualifiedGuidance->givePermissionTo(
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
        CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
    );
    $action = app(RecordProgramAdvisingNoteUse::class);
    expect(fn () => $action->handle($unqualifiedGuidance, $attempt, $revision, 'guidance-denied-use'))
        ->toThrow(AuthorizationException::class);

    $decisionCount = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count();
    $usage = $action->handle($guidance, $attempt, $revision, 'guidance-authorized-use');
    $replay = $action->handle($guidance, $attempt, $revision, 'guidance-authorized-use');
    $retraction = app(RetractProgramAdvisingNote::class)->handle($guidance, $revision->note, $publication, 1, 'Preserve prior consultation evidence', 'guidance-use-retract');
    $replayAfterRetraction = $action->handle($guidance, $attempt, $revision, 'guidance-authorized-use');
    expect($usage->id)->toBe($replay->id)
        ->and($replayAfterRetraction->id)->toBe($usage->id)
        ->and($usage->actor_user_id)->toBe($guidance->id)
        ->and($usage->revision_id)->toBe($revision->id)
        ->and($retraction->retracts_publication_id)->toBe($publication->id)
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($decisionCount);
});

test('private advising notes stay owner-only across boards, history, commands, replay, and exact staff copy', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $other = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $other->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => null,
        'program_codes' => [$program->code],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $other->givePermissionTo(
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
    );

    $save = app(SaveProgramAdvisingNote::class);
    $privateFirst = $save->handle($author, $program, $cycle, 'Private source revision one.', 0, null, 'private-source-v1', null, 'private');
    $privateCurrent = $save->handle($author, $program, $cycle, 'Private source revision two.', 1, null, 'private-source-v2', (int) $privateFirst->program_advising_note_id);
    $programNote = $save->handle($author, $program, $cycle, 'Program team note.', 0, '', 'team-note');
    $privateNoteId = (int) $privateCurrent->program_advising_note_id;
    $query = app(ProgramAdvisingNoteQuery::class);

    $ownerBoard = $query->boardFor($author, $program, $cycle);
    $otherBoard = $query->boardFor($other, $program, $cycle);
    $ownerPrivate = $query->draftFor($author, $program, $cycle, $privateNoteId);
    expect($privateCurrent->note->audience)->toBe('private')
        ->and($privateCurrent->note->owner_user_id)->toBe($author->id)
        ->and($programNote->note->audience)->toBe('program')
        ->and($programNote->note->owner_user_id)->toBeNull()
        ->and($ownerBoard['counts'])->toBe(['total' => 2, 'drafts' => 2, 'shared' => 0])
        ->and($ownerPrivate)->toMatchArray(['audience' => 'private', 'is_private_owner' => true, 'can_publish' => false])
        ->and($otherBoard['counts'])->toBe(['total' => 1, 'drafts' => 1, 'shared' => 0])
        ->and(array_column($otherBoard['items'], 'note_id'))->toBe([(int) $programNote->program_advising_note_id])
        ->and($query->boardFor($other, $program, $cycle, search: 'Private source')['pagination']['total'])->toBe(0)
        ->and($query->draftFor($other, $program, $cycle, $privateNoteId))->toBeNull()
        ->and($query->historyFor($author, $program, $cycle, $privateNoteId)['pagination']['total'])->toBe(2)
        ->and($query->historyFor($other, $program, $cycle, $privateNoteId)['pagination']['total'])->toBe(0)
        ->and($query->publicationHistoryFor($other, $program, $cycle, $privateNoteId)['pagination']['total'])->toBe(0);

    $operationCount = DB::table('operations')->count();
    expect($save->handle($author, $program, $cycle, 'Private source revision two.', 1, null, 'private-source-v2', $privateNoteId)->id)
        ->toBe($privateCurrent->id);
    expect(fn () => $save->handle($author, $program, $cycle, 'Attempted private-to-program change.', 2, '', 'private-audience-change', $privateNoteId, 'program'))
        ->toThrow(RuntimeException::class, 'cannot be changed after the note is created.');
    expect(fn () => $save->handle($other, $program, $cycle, 'Private source revision two.', 1, null, 'private-source-v2', $privateNoteId))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(RestoreProgramAdvisingNoteRevision::class)->handle($other, $privateCurrent->note, $privateFirst, 2, null, 'private-restore-denied'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(CopyProgramAdvisingNoteToCycle::class)->handle($other, $privateFirst, $program, $cycle, 0, null, 'private-copy-denied', 'program'))
        ->toThrow(AuthorizationException::class);
    $author->givePermissionTo(CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH);
    expect(fn () => app(PublishProgramAdvisingNoteRevision::class)->handle($author, $privateCurrent->note, $privateCurrent, 2, null, 'private-publish-denied'))
        ->toThrow(AuthorizationException::class);
    expect(DB::table('operations')->count())->toBe($operationCount + 0);

    $staffCopy = app(CopyProgramAdvisingNoteToCycle::class)->handle(
        $author,
        $privateFirst,
        $program,
        $cycle,
        0,
        null,
        'private-to-program-copy',
        'program',
    );
    $staffCopyNote = $staffCopy->note;
    $otherStaffCopy = $query->draftFor($other, $program, $cycle, (int) $staffCopy->program_advising_note_id);
    $staffCopyHistory = $query->historyFor($other, $program, $cycle, (int) $staffCopy->program_advising_note_id);
    expect($staffCopyNote->audience)->toBe('program')
        ->and($staffCopyNote->owner_user_id)->toBeNull()
        ->and($staffCopy->content)->toBe('Private source revision one.')
        ->and($staffCopy->copied_from_revision_id)->toBeNull()
        ->and($staffCopyHistory['pagination']['total'])->toBe(1)
        ->and($staffCopyHistory['items'][0]['copied_from_revision_id'])->toBeNull()
        ->and($otherStaffCopy['content'])->toBe('Private source revision one.');
    $publication = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $staffCopyNote, $staffCopy, 1, null, 'private-copy-publish');
    expect($query->publishedFor($other, $program, $cycle))->toHaveCount(1)
        ->and($query->publishedFor($other, $program, $cycle)[0])->toMatchArray([
            'note_id' => $staffCopyNote->id,
            'revision_id' => $staffCopy->id,
            'content' => 'Private source revision one.',
            'audience' => 'program',
        ]);

    $futureCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-PRIVATE-PLANNED-2028',
        'name' => 'Private Note Planned Cycle',
        'school_year' => '2028-2029',
        'semester' => 'First',
        'opens_on' => now()->addYears(2)->toDateString(),
        'closes_on' => now()->addYears(3)->toDateString(),
        'is_active' => false,
    ]);
    $privateFutureCopy = app(CopyProgramAdvisingNoteToCycle::class)->handle(
        $author,
        $privateFirst,
        $program,
        $futureCycle,
        0,
        null,
        'private-copy-next-cycle',
    );
    expect($privateFutureCopy->note->audience)->toBe('private')
        ->and($privateFutureCopy->note->owner_user_id)->toBe($author->id)
        ->and($privateFutureCopy->content)->toBe('Private source revision one.');
});

test('all five advising note mutations accept blank reasons and replay without duplicate audits', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $targetCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-REASON-PLANNED-2028',
        'name' => 'Reasonless Note Planned Cycle',
        'school_year' => '2028-2029',
        'semester' => 'First',
        'opens_on' => now()->addYears(2)->toDateString(),
        'closes_on' => now()->addYears(3)->toDateString(),
        'is_active' => false,
    ]);
    $auditTypes = ['advising_note.revision_appended', 'advising_note.published', 'advising_note.retracted'];
    $auditBefore = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();

    $saved = app(SaveProgramAdvisingNote::class)->handle(
        $author,
        $program,
        $cycle,
        'Reasonless source text.',
        0,
        idempotencyKey: 'reasonless-save',
    );
    $savedReplay = app(SaveProgramAdvisingNote::class)->handle(
        $author,
        $program,
        $cycle,
        'Reasonless source text.',
        0,
        idempotencyKey: 'reasonless-save',
    );
    $copied = app(CopyProgramAdvisingNoteToCycle::class)->handle(
        $author,
        $saved,
        $program,
        $targetCycle,
        0,
        null,
        'reasonless-copy',
    );
    $copiedReplay = app(CopyProgramAdvisingNoteToCycle::class)->handle(
        $author,
        $saved,
        $program,
        $targetCycle,
        0,
        null,
        'reasonless-copy',
    );
    $restored = app(RestoreProgramAdvisingNoteRevision::class)->handle($author, $copied->note, $copied, 1, " \t", 'reasonless-restore');
    $restoredReplay = app(RestoreProgramAdvisingNoteRevision::class)->handle($author, $copied->note, $copied, 1, " \t", 'reasonless-restore');
    $published = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $restored->note, $restored, 2, null, 'reasonless-publish');
    $publishedReplay = app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $restored->note, $restored, 2, null, 'reasonless-publish');
    $retracted = app(RetractProgramAdvisingNote::class)->handle(
        $guidance,
        $restored->note,
        $published,
        2,
        idempotencyKey: 'reasonless-retract',
    );
    $retractedReplay = app(RetractProgramAdvisingNote::class)->handle(
        $guidance,
        $restored->note,
        $published,
        2,
        idempotencyKey: 'reasonless-retract',
    );

    expect([$saved->reason, $copied->reason, $restored->reason, $published->reason, $retracted->reason])->toBe(['', '', '', '', ''])
        ->and($savedReplay->id)->toBe($saved->id)
        ->and($copiedReplay->id)->toBe($copied->id)
        ->and($restoredReplay->id)->toBe($restored->id)
        ->and($publishedReplay->id)->toBe($published->id)
        ->and($retractedReplay->id)->toBe($retracted->id)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditBefore + 5)
        ->and(ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $saved->program_advising_note_id)->count())->toBe(1)
        ->and(ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $copied->program_advising_note_id)->count())->toBe(2);
});

test('all five advising note mutations reject direct reasons over 500 characters atomically', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $targetCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-LIMIT-PLANNED-2028',
        'name' => 'Note Limit Planned Cycle',
        'school_year' => '2028-2029',
        'semester' => 'First',
        'opens_on' => now()->addYears(2)->toDateString(),
        'closes_on' => now()->addYears(3)->toDateString(),
        'is_active' => false,
    ]);
    $source = app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Reason limit source.', 0, null, 'limit-source');
    $longReason = str_repeat('x', 501);
    $auditTypes = ['advising_note.revision_appended', 'advising_note.published', 'advising_note.retracted'];
    $operationCount = DB::table('operations')->count();
    $auditCount = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();

    expect(fn () => app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle, 'Invalid reason save.', 1, $longReason, 'limit-save', (int) $source->program_advising_note_id))
        ->toThrow(RuntimeException::class, 'at most 500 characters.');
    expect(fn () => app(CopyProgramAdvisingNoteToCycle::class)->handle($author, $source, $program, $targetCycle, 0, $longReason, 'limit-copy'))
        ->toThrow(RuntimeException::class, 'at most 500 characters.');
    expect(fn () => app(RestoreProgramAdvisingNoteRevision::class)->handle($author, $source->note, $source, 1, $longReason, 'limit-restore'))
        ->toThrow(RuntimeException::class, 'at most 500 characters.');
    expect(fn () => app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $source->note, $source, 1, $longReason, 'limit-publish'))
        ->toThrow(RuntimeException::class, 'at most 500 characters.');

    $publication = app(PublishProgramAdvisingNoteRevision::class)->handle(
        $guidance,
        $source->note,
        $source,
        1,
        idempotencyKey: 'limit-valid-publish',
    );
    $operationCountBeforeRetract = DB::table('operations')->count();
    $auditCountBeforeRetract = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();
    expect(fn () => app(RetractProgramAdvisingNote::class)->handle($guidance, $source->note, $publication, 1, $longReason, 'limit-retract'))
        ->toThrow(RuntimeException::class, 'at most 500 characters.');

    expect(DB::table('operations')->count())->toBe($operationCountBeforeRetract)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditCountBeforeRetract)
        ->and(DB::table('operations')->count())->toBeGreaterThan($operationCount)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBeGreaterThan($auditCount)
        ->and(ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $source->program_advising_note_id)->count())->toBe(1)
        ->and(ProgramAdvisingNotePublication::query()->where('program_advising_note_id', $source->program_advising_note_id)->count())->toBe(1);
});

test('reasonless advising note mutations roll back receipts, versions, and audits when audit recording fails', function (): void {
    ['institution' => $institution, 'cycle' => $cycle, 'program' => $program, 'author' => $author, 'guidance' => $guidance] = advisingNoteFixture();
    $targetCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NOTE-AUDIT-PLANNED-2028',
        'name' => 'Audit Failure Planned Cycle',
        'school_year' => '2028-2029',
        'semester' => 'First',
        'opens_on' => now()->addYears(2)->toDateString(),
        'closes_on' => now()->addYears(3)->toDateString(),
        'is_active' => false,
    ]);
    $realAudit = app(RecordAuditEvent::class);
    $auditTypes = ['advising_note.revision_appended', 'advising_note.published', 'advising_note.retracted'];
    $failWithAudit = function (callable $command) use ($realAudit): void {
        $failedAudit = Mockery::mock(RecordAuditEvent::class);
        $failedAudit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
        app()->instance(RecordAuditEvent::class, $failedAudit);
        expect($command)->toThrow(RuntimeException::class, 'audit unavailable');
        app()->instance(RecordAuditEvent::class, $realAudit);
    };

    $operationsBefore = DB::table('operations')->count();
    $auditsBefore = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();
    $failWithAudit(fn () => app(SaveProgramAdvisingNote::class)->handle(
        $author,
        $program,
        $cycle,
        'Atomicity failure on save.',
        0,
        idempotencyKey: 'atomic-audit-save',
    ));
    expect(ProgramAdvisingNote::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->count())->toBe(0)
        ->and(DB::table('operations')->count())->toBe($operationsBefore)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditsBefore);

    $source = app(SaveProgramAdvisingNote::class)->handle(
        $author,
        $program,
        $cycle,
        'Atomicity source revision.',
        0,
        idempotencyKey: 'atomic-audit-source',
    );
    $operationsBefore = DB::table('operations')->count();
    $auditsBefore = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();
    $failWithAudit(fn () => app(CopyProgramAdvisingNoteToCycle::class)->handle(
        $author,
        $source,
        $program,
        $targetCycle,
        0,
        idempotencyKey: 'atomic-audit-copy',
    ));
    expect(ProgramAdvisingNote::query()->where('program_id', $program->id)->where('academic_cycle_id', $targetCycle->id)->count())->toBe(0)
        ->and(DB::table('operations')->count())->toBe($operationsBefore)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditsBefore);

    $operationsBefore = DB::table('operations')->count();
    $auditsBefore = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();
    $failWithAudit(fn () => app(RestoreProgramAdvisingNoteRevision::class)->handle(
        $author,
        $source->note,
        $source,
        1,
        idempotencyKey: 'atomic-audit-restore',
    ));
    expect($source->note->fresh()->current_version)->toBe(1)
        ->and($source->note->revisions()->count())->toBe(1)
        ->and(DB::table('operations')->count())->toBe($operationsBefore)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditsBefore);

    $operationsBefore = DB::table('operations')->count();
    $auditsBefore = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();
    $failWithAudit(fn () => app(PublishProgramAdvisingNoteRevision::class)->handle(
        $guidance,
        $source->note,
        $source,
        1,
        idempotencyKey: 'atomic-audit-publish',
    ));
    expect($source->note->fresh()->published_publication_id)->toBeNull()
        ->and($source->note->publications()->count())->toBe(0)
        ->and(DB::table('operations')->count())->toBe($operationsBefore)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditsBefore);

    $publication = app(PublishProgramAdvisingNoteRevision::class)->handle(
        $guidance,
        $source->note,
        $source,
        1,
        idempotencyKey: 'atomic-audit-valid-publish',
    );
    $operationsBefore = DB::table('operations')->count();
    $auditsBefore = DB::table('audit_events')->whereIn('event_type', $auditTypes)->count();
    $failWithAudit(fn () => app(RetractProgramAdvisingNote::class)->handle(
        $guidance,
        $source->note,
        $publication,
        1,
        idempotencyKey: 'atomic-audit-retract',
    ));
    expect($source->note->fresh()->published_publication_id)->toBe($publication->id)
        ->and($source->note->publications()->count())->toBe(1)
        ->and(DB::table('operations')->count())->toBe($operationsBefore)
        ->and(DB::table('audit_events')->whereIn('event_type', $auditTypes)->count())->toBe($auditsBefore);
});
