<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffAuthenticationFixtures;

uses(StaffAuthenticationFixtures::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function governanceBenchmarkContent(AdmissionBenchmarkSet $set, Program $program): array
{
    $tiers = [];
    foreach ($set->exemplars()->where('program_id', $program->id)->with('values.feature')->get() as $exemplar) {
        $values = [];
        foreach ($exemplar->values as $value) {
            $feature = $value->feature;
            $cell = $value->numeric_value ?? $value->categorical_value;
            $values[$feature->feature_key] = $feature->logical_type === 'binary' && $cell !== null ? (int) $cell : $cell;
        }
        $tiers[(string) $exemplar->tier_level] = ['values' => $values];
    }

    return ['tiers' => $tiers];
}

test('program head receives only scoped Studio projection and may save a draft but cannot activate it', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $retiredProgram = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($programHead)
        ->get(route('setup.decision-support.index', ['program' => $retiredProgram->code, 'cycle' => $cycle->code]))
        ->assertForbidden();
    $this->actingAs($programHead)
        ->get(route('setup.decision-support.index', ['program' => $program->code, 'cycle' => $cycle->code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('governance.selected_program.code', $program->code)
            ->where('governance.active_content.program_id', (int) $program->id)
        );

    $actor = User::query()->where('email', 'dean.ccit@nexiam.test')->firstOrFail();
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    $set = $activation?->benchmarkSet()->with('features')->firstOrFail();
    expect($set)->toBeInstanceOf(AdmissionBenchmarkSet::class);

    $this->actingAs($actor)
        ->get(route('setup.decision-support.index', ['program' => $program->code, 'cycle' => $cycle->code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('governance.active_content.tiers', 5)
            ->where('governance.active_content.program_id', (int) $program->id)
            ->where('governance.active_content.tiers.1.tier_level', 1)
            ->where('governance.active_content.tiers.5.tier_level', 5)
            ->where('governance.draft', null)
            ->missing('active_raw_ranges')
        );

    $save = $this->actingAs($actor)->postJson(route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]), [
        'content' => governanceBenchmarkContent($set, $program),
        'expected_version' => 0,
        'reason' => 'Scoped HTTP draft integration test',
        'idempotency_key' => 'gov-http-draft-save-'.uniqid('', true),
    ]);
    $save->assertStatus(200, $save->getContent())->assertJsonPath('status', 'success')->assertJsonStructure(['revision' => ['id', 'version', 'content']]);
    $this->actingAs($actor)
        ->getJson(route('setup.admission-support.benchmarks.history', [$program, $cycle]).'?per_page=100')
        ->assertOk()
        ->assertJsonPath('pagination.per_page', 100)
        ->assertJsonPath('items.0.id', $save->json('revision.id'));

    $this->actingAs($actor)
        ->getJson(route('setup.admission-support.benchmarks.history', [$program, $cycle]).'?per_page=101')
        ->assertUnprocessable();

    $this->postJson(route('setup.admission-support.benchmarks.activate', $cycle), [
        'benchmark_set_id' => $set->id,
        'expected_current_activation_id' => $activation?->id,
        'expected_content_sha256' => $set->content_sha256,
        'idempotency_key' => 'gov-http-denied-activate-'.uniqid('', true),
    ])->assertForbidden();

    $this->postJson(route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]), [
        'program_id' => Program::query()->where('code', 'BSPSY')->value('id'),
        'content' => governanceBenchmarkContent($set, $program),
        'expected_version' => 1,
        'reason' => 'Forged scope must be rejected',
        'idempotency_key' => 'gov-http-forged-context-'.uniqid('', true),
    ])->assertUnprocessable();
});

test('authorized preview reports server-bound active provenance instead of browser-computed ranking', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $set = completeSyntheticBenchmarkTestBase();
    $dean = User::query()->where('email', 'dean.ccit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $exemplar = $set?->exemplars->firstWhere('program_id', $program->id);
    expect($set)->toBeInstanceOf(AdmissionBenchmarkSet::class)
        ->and($exemplar)->not->toBeNull();
    $featuresById = $set->features->keyBy('id');
    $input = ['gwa' => null, 'aptitudes' => []];
    foreach ($exemplar->values as $value) {
        $feature = $featuresById->get($value->benchmark_feature_id);
        if ($feature === null) {
            continue;
        }
        if ($feature->feature_key === 'gwa') {
            $input['gwa'] = (float) $value->numeric_value;
        } elseif ($feature->aptitude_area_id !== null) {
            $input['aptitudes'][$feature->feature_key] = (float) $value->numeric_value;
        }
    }
    expect($input['gwa'])->not->toBeNull()
        ->and($input['aptitudes'])->not->toBeEmpty();

    $this->actingAs($dean)
        ->postJson(route('setup.admission-support.sandbox.governance-preview', [$program, $cycle]), [
            'source' => 'active',
            'request_id' => 'http-preview-provenance',
            'input' => $input,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('evaluation.evaluation_context.source', 'active')
        ->assertJsonPath('evaluation.evaluation_context.program_id', (int) $program->id)
        ->assertJsonPath('evaluation.evaluation_context.academic_cycle_id', (int) $cycle->id)
        ->assertJsonPath('evaluation.evaluation_context.request_id', 'http-preview-provenance');
});

test('Guidance can publish an exact advising-note revision through the scoped HTTP adapter', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $draftResponse = $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.save', [$program, $cycle]), [
        'content' => 'This is a reviewed program advising note.',
        'expected_version' => 0,
        'reason' => 'Initial scoped HTTP draft',
        'idempotency_key' => 'gov-http-note-save-'.uniqid('', true),
    ]);
    $draftResponse->assertStatus(200, $draftResponse->getContent())
        ->assertJsonPath('status', 'success');
    $revisionId = (int) $draftResponse->json('revision.id');
    $noteId = (int) $draftResponse->json('revision.note_id');
    $note = ProgramAdvisingNote::query()
        ->where('program_id', $program->id)
        ->where('academic_cycle_id', $cycle->id)
        ->findOrFail($noteId);
    $draftResponse->assertJsonStructure(['revision' => ['id', 'version', 'note_id']])
        ->assertJsonPath('revision.note_id', $noteId);

    $publish = $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.publish', [$program, $cycle]), [
        'note_id' => $noteId,
        'revision_id' => $revisionId,
        'expected_version' => 1,
        'reason' => 'Guidance reviewed exact revision',
        'idempotency_key' => 'gov-http-note-publish-'.uniqid('', true),
    ]);
    $publish->assertStatus(200, $publish->getContent())
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['publication' => ['id', 'revision_id', 'operation']])
        ->assertJsonPath('publication.revision_id', $revisionId)
        ->assertJsonPath('publication.operation', 'publish');
});

test('an explicit Registrar quota grant is read-only and program-cycle scoped over HTTP', function (): void {
    seedE2Foundation();
    $issuer = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($issuer)
        ->postJson(route('setup.quotas.registrar-grants.issue', [$program, $cycle]), [
            'recipient_id' => $registrar->id,
            'expected_version' => 0,
            'reason' => 'Scoped quota read integration test',
            'idempotency_key' => 'gov-http-registrar-grant-'.uniqid('', true),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('grant.status', 'active');

    $this->actingAs($registrar)
        ->get(route('setup.quotas', ['cycle_id' => $cycle->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Quotas')
            ->where('quota_projections.0.program.code', $program->code)
            ->where('quota_projections.0.can_manage', false)
            ->where('capabilities', fn (Collection $capabilities): bool => $capabilities->get('quota') === false)
        );

    $this->actingAs($issuer)
        ->postJson(route('setup.quotas.registrar-grants.revoke', [$program, $cycle]), [
            'recipient_id' => $registrar->id,
            'expected_version' => 1,
            'reason' => 'Revoke scoped quota read integration grant',
            'idempotency_key' => 'gov-http-registrar-revoke-'.uniqid('', true),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('grant.status', 'revoked');

    $this->actingAs($registrar)
        ->get(route('setup.quotas', ['cycle_id' => $cycle->id]))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('setup.quotas.store'), [
            'program_id' => $program->id,
            'academic_cycle_id' => $cycle->id,
            'slots' => 999,
        ])
        ->assertForbidden();
    $this->actingAs($registrar)
        ->get(route('setup.decision-support.index'))
        ->assertForbidden();
});

test('unassigned staff cannot discover scoped Studio or governance projections', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create(['email' => 'unassigned.governance@nexiam.test']);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($actor)
        ->get(route('setup.decision-support.index'))
        ->assertForbidden();
    $this->getJson(route('setup.admission-support.governance.show', [$program, $cycle]))
        ->assertForbidden();
});

test('advising-note HTTP projections and known-ID mutations respect private ownership', function (): void {
    seedE2Foundation();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $authenticationSessions = [];
    $authenticate = function (User $staff) use (&$authenticationSessions) {
        if (! isset($authenticationSessions[$staff->id])) {
            $lease = $this->establishStaffAuthenticationFixture($staff, 'governance-private-test-'.$staff->id);
            $state = $staff->authenticationState()->firstOrFail();
            $authenticationSessions[$staff->id] = [
                'auth.surface' => 'staff',
                'auth.session_version' => (int) $state->session_version,
                'auth.staff_inactivity_generation' => $lease->inactivity_generation,
            ];
        }

        return $this->actingAs($staff)->withSession($authenticationSessions[$staff->id]);
    };
    $makeStaff = function () use ($program, $cycle): User {
        $staff = User::factory()->create();
        StaffProfile::query()->create([
            'user_id' => $staff->id,
            'institution_id' => $program->institution_id,
            'office' => 'program',
            'department_id' => null,
            'program_codes' => [$program->code],
            'active_cycle_code' => $cycle->code,
            'status' => StaffProfile::STATUS_ACTIVE,
        ]);
        $staff->givePermissionTo(
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
        );

        return $staff;
    };
    $owner = $makeStaff();
    $other = $makeStaff();
    $governanceUrl = route('setup.admission-support.governance.show', [$program, $cycle]);
    $beforePrivate = $authenticate($other)->getJson($governanceUrl)->assertOk();
    $beforeTotal = $beforePrivate->json('notes.counts.total');
    $marker = 'HTTP_PRIVATE_'.bin2hex(random_bytes(6));
    $firstContent = $marker.' reviewed revision one.';
    $currentContent = $marker.' owner-only current revision two.';

    $firstResponse = $authenticate($owner)->postJson(route('setup.admission-support.advising-note.save', [$program, $cycle]), [
        'content' => $firstContent,
        'expected_version' => 0,
        'audience' => 'private',
        'idempotency_key' => 'http-private-note-create',
    ])->assertOk()->assertJsonPath('status', 'success');
    $noteId = (int) $firstResponse->json('revision.note_id');
    $firstRevisionId = (int) $firstResponse->json('revision.id');
    $authenticate($owner)->postJson(route('setup.admission-support.advising-note.save', [$program, $cycle]), [
        'content' => $currentContent,
        'note_id' => $noteId,
        'expected_version' => 1,
        'idempotency_key' => 'http-private-note-update',
    ])->assertOk()->assertJsonPath('revision.version', 2);
    $currentRevisionId = (int) ProgramAdvisingNote::query()->findOrFail($noteId)->current_revision_id;

    $otherBoard = $authenticate($other)->getJson($governanceUrl)
        ->assertOk()
        ->assertJsonPath('notes.counts.total', $beforeTotal)
        ->assertJsonMissing(['content' => $currentContent]);
    $this->getJson($governanceUrl.'?'.http_build_query(['note_search' => $marker]))
        ->assertOk()
        ->assertJsonPath('notes.pagination.total', 0);
    $authenticate($owner)->getJson($governanceUrl.'?'.http_build_query(['note_id' => $noteId]))
        ->assertOk()
        ->assertJsonPath('note.draft.audience', 'private')
        ->assertJsonPath('note.draft.is_private_owner', true)
        ->assertJsonPath('note.history.pagination.total', 2);
    $authenticate($other)->getJson($governanceUrl.'?'.http_build_query(['note_id' => $noteId]))
        ->assertNotFound();

    $authenticate($other)->postJson(route('setup.admission-support.advising-note.save', [$program, $cycle]), [
        'content' => $currentContent,
        'note_id' => $noteId,
        'expected_version' => 1,
        'idempotency_key' => 'http-private-note-update',
    ])->assertForbidden();
    $this->postJson(route('setup.admission-support.advising-note.restore', [$program, $cycle]), [
        'note_id' => $noteId,
        'source_revision_id' => $firstRevisionId,
        'expected_version' => 2,
        'idempotency_key' => 'http-private-restore-denied',
    ])->assertForbidden();
    $this->postJson(route('setup.admission-support.advising-note.copy', [$program, $cycle]), [
        'source_revision_id' => $firstRevisionId,
        'target_cycle_id' => $cycle->id,
        'expected_version' => 0,
        'target_audience' => 'program',
        'idempotency_key' => 'http-private-copy-denied',
    ])->assertForbidden();
    $this->postJson(route('setup.admission-support.advising-note.publish', [$program, $cycle]), [
        'note_id' => $noteId,
        'revision_id' => $currentRevisionId,
        'expected_version' => 2,
        'idempotency_key' => 'http-private-publish-denied',
    ])->assertForbidden();
    $authenticate($owner)->postJson(route('setup.admission-support.advising-note.publish', [$program, $cycle]), [
        'note_id' => $noteId,
        'revision_id' => $currentRevisionId,
        'expected_version' => 2,
        'idempotency_key' => 'http-private-owner-publish-denied',
    ])->assertForbidden();

    $staffCopyResponse = $authenticate($owner)->postJson(route('setup.admission-support.advising-note.copy', [$program, $cycle]), [
        'source_revision_id' => $firstRevisionId,
        'target_cycle_id' => $cycle->id,
        'expected_version' => 0,
        'target_audience' => 'program',
        'idempotency_key' => 'http-private-copy-to-program',
    ])->assertOk()->assertJsonPath('status', 'success');
    $staffCopyId = (int) $staffCopyResponse->json('revision.note_id');
    $staffCopy = ProgramAdvisingNote::query()->findOrFail($staffCopyId);
    expect($staffCopy->audience)->toBe('program')
        ->and($staffCopy->owner_user_id)->toBeNull();
    $authenticate($other)->getJson($governanceUrl.'?'.http_build_query(['note_id' => $staffCopyId]))
        ->assertOk()
        ->assertJsonPath('note.draft.content', $firstContent)
        ->assertJsonPath('note.history.pagination.total', 1)
        ->assertJsonPath('note.history.items.0.copied_from_revision_id', null);
});

test('advising-note HTTP reasons are optional and capped at 500 on every mutation', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidance->givePermissionTo(
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
    );
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $targetCycle = AcademicCycle::query()->create([
        'institution_id' => $program->institution_id,
        'code' => 'GOV-HTTP-NOTE-PLANNED-2028',
        'name' => 'Governance HTTP Note Planned Cycle',
        'school_year' => '2028-2029',
        'semester' => 'First',
        'opens_on' => now()->addYears(2)->toDateString(),
        'closes_on' => now()->addYears(3)->toDateString(),
        'is_active' => false,
    ]);

    $saveResponse = $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.save', [$program, $cycle]), [
        'content' => 'HTTP reasonless source revision.',
        'expected_version' => 0,
        'idempotency_key' => 'http-reasonless-save',
    ])->assertOk();
    $sourceRevisionId = (int) $saveResponse->json('revision.id');
    $sourceNoteId = (int) $saveResponse->json('revision.note_id');
    $copyResponse = $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.copy', [$program, $cycle]), [
        'source_revision_id' => $sourceRevisionId,
        'target_cycle_id' => $targetCycle->id,
        'expected_version' => 0,
        'idempotency_key' => 'http-reasonless-copy',
    ])->assertOk();
    $copyRevisionId = (int) $copyResponse->json('revision.id');
    $copyNoteId = (int) $copyResponse->json('revision.note_id');
    $restoreResponse = $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.restore', [$program, $targetCycle]), [
        'note_id' => $copyNoteId,
        'source_revision_id' => $copyRevisionId,
        'expected_version' => 1,
        'idempotency_key' => 'http-reasonless-restore',
    ])->assertOk();
    $restoredRevisionId = (int) $restoreResponse->json('revision.id');
    $publicationResponse = $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.publish', [$program, $targetCycle]), [
        'note_id' => $copyNoteId,
        'revision_id' => $restoredRevisionId,
        'expected_version' => 2,
        'idempotency_key' => 'http-reasonless-publish',
    ])->assertOk();
    $publicationId = (int) $publicationResponse->json('publication.id');
    $this->actingAs($guidance)->postJson(route('setup.admission-support.advising-note.retract', [$program, $targetCycle]), [
        'note_id' => $copyNoteId,
        'publication_id' => $publicationId,
        'expected_version' => 2,
        'idempotency_key' => 'http-reasonless-retract',
    ])->assertOk();

    $source = ProgramAdvisingNote::query()->findOrFail($sourceNoteId);
    $copy = ProgramAdvisingNote::query()->findOrFail($copyNoteId);
    expect($source->revisions()->firstOrFail()->reason)->toBe('')
        ->and($copy->revisions()->orderBy('version')->pluck('reason')->all())->toBe(['', ''])
        ->and($copy->publications()->orderBy('id')->pluck('reason')->all())->toBe(['', '']);

    $longReason = str_repeat('x', 501);
    $operationsBefore = DB::table('operations')->count();
    $this->postJson(route('setup.admission-support.advising-note.save', [$program, $targetCycle]), [
        'content' => 'Invalid reason save.',
        'note_id' => $copyNoteId,
        'expected_version' => 2,
        'reason' => $longReason,
        'idempotency_key' => 'http-long-reason-save',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('setup.admission-support.advising-note.restore', [$program, $targetCycle]), [
        'note_id' => $copyNoteId,
        'source_revision_id' => $copyRevisionId,
        'expected_version' => 2,
        'reason' => $longReason,
        'idempotency_key' => 'http-long-reason-restore',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('setup.admission-support.advising-note.copy', [$program, $cycle]), [
        'source_revision_id' => $sourceRevisionId,
        'target_cycle_id' => $targetCycle->id,
        'expected_version' => 0,
        'reason' => $longReason,
        'idempotency_key' => 'http-long-reason-copy',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('setup.admission-support.advising-note.publish', [$program, $targetCycle]), [
        'note_id' => $copyNoteId,
        'revision_id' => $restoredRevisionId,
        'expected_version' => 2,
        'reason' => $longReason,
        'idempotency_key' => 'http-long-reason-publish',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('setup.admission-support.advising-note.retract', [$program, $targetCycle]), [
        'note_id' => $copyNoteId,
        'publication_id' => $publicationId,
        'expected_version' => 2,
        'reason' => $longReason,
        'idempotency_key' => 'http-long-reason-retract',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');

    expect(DB::table('operations')->count())->toBe($operationsBefore)
        ->and($copy->fresh()->revisions()->count())->toBe(2)
        ->and($copy->fresh()->publications()->count())->toBe(2);
});

test('HTTP note reasons retain nonblank bytes while ordinary note text retains request trimming', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $url = route('setup.admission-support.advising-note.save', [$program, $cycle]);
    $payload = ['content' => '  Reviewed note text.  ', 'reason' => '  Reviewed original source.  ', 'expected_version' => 0, 'idempotency_key' => 'reason-byte-preservation'];
    $first = actingAsAdmissionSupportStaff($this, $guidance)->postJson($url, $payload)
        ->assertOk()->assertJsonPath('revision.reason', $payload['reason'])->assertJsonPath('revision.content', 'Reviewed note text.');
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('revision.id', $first->json('revision.id'));
    $payload['reason'] = trim($payload['reason']);
    $this->postJson($url, $payload)->assertConflict();
});
