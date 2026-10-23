<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\PublishAnnouncement;
use App\Communications\Actions\SaveAnnouncement;
use App\Communications\Actions\WithdrawAnnouncement;
use App\Communications\Queries\AnnouncementQuery;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Communications\Announcement;
use App\Models\Communications\AnnouncementCommandReceipt;
use App\Models\Communications\AnnouncementRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PublicCommunicationsDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
});

function communicationsActor(string $office = 'registrar', array $capabilities = [
    CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
    CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
]): User
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $actor = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $actor->getKey(),
        'institution_id' => $institution->getKey(),
        'office' => $office,
        'department_id' => null,
        'program_codes' => $office === 'program' ? ['BSN'] : [],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $actor->getKey(),
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $actor->givePermissionTo($capabilities);

    return $actor->fresh();
}

function communicationsPayload(string $requestId, string $publisher = 'office:registrar', array $overrides = []): array
{
    return array_replace([
        'publisher_key' => $publisher,
        'request_id' => $requestId,
        'title' => 'Synthetic admissions desk update',
        'summary' => 'A short synthetic public communications summary.',
        'body' => 'Synthetic public information, authored as plain text.',
        'priority' => 'normal',
        'is_featured' => true,
        'starts_at' => null,
        'ends_at' => null,
    ], $overrides);
}

test('office clerks publish their own notices but loaded users lose revoked management', function (): void {
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($clerk)->post('/communications', communicationsPayload((string) Str::uuid()))
        ->assertRedirect('/communications');
    $draft = Announcement::query()->where('created_by', $clerk->id)->firstOrFail();
    $this->post("/communications/{$draft->id}/publish", [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => $draft->revision,
    ])->assertRedirect('/communications');
    $this->get("/announcements/{$draft->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('announcement.title', 'Synthetic admissions desk update'));

    $actor = communicationsActor();
    $missingPublisher = communicationsPayload((string) Str::uuid());
    unset($missingPublisher['publisher_key']);
    expect(fn () => app(SaveAnnouncement::class)->handle($actor, $missingPublisher))->toThrow(ValidationException::class);
    $loadedBeforeRevocation = User::query()->findOrFail($actor->getKey());
    $actor->revokePermissionTo(CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE);
    expect(fn () => app(SaveAnnouncement::class)->handle($loadedBeforeRevocation, communicationsPayload((string) Str::uuid())))
        ->toThrow(AuthorizationException::class);
});

test('academic source options are limited to active programs owned by an academic profile', function (): void {
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $announcement = app(SaveAnnouncement::class)->handle(
        $programHead,
        communicationsPayload((string) Str::uuid(), 'program:BSIT', [
            'program_id' => Program::query()->where('code', 'BSIT')->value('id'),
        ]),
    );
    expect($announcement->publisher_label)->toBe('Bachelor of Science in Information Technology')
        ->and(fn () => app(SaveAnnouncement::class)->handle(
            $programHead,
            communicationsPayload((string) Str::uuid(), 'office:registrar'),
        ))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveAnnouncement::class)->handle(
            $programHead,
            communicationsPayload((string) Str::uuid(), 'program:NOT-OWNED', [
                'program_id' => Program::query()->where('code', 'BSN')->value('id'),
            ]),
        ))->toThrow(AuthorizationException::class);
});

test('historical campus capability does not grant new campus authoring', function (): void {
    $actor = communicationsActor();
    $actor->givePermissionTo(CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_CAMPUS);
    expect(fn () => app(SaveAnnouncement::class)->handle($actor, communicationsPayload((string) Str::uuid(), 'campus')))
        ->toThrow(AuthorizationException::class);
});

test('public list and detail apply inclusive start exclusive end and hide scheduled records', function (): void {
    $actor = communicationsActor();
    $start = now()->addHour()->utc();
    $end = now()->addHours(3)->utc();
    $draft = app(SaveAnnouncement::class)->handle($actor, communicationsPayload((string) Str::uuid(), 'office:registrar', [
        'starts_at' => $start->toISOString(),
        'ends_at' => $end->toISOString(),
    ]));
    $scheduled = app(PublishAnnouncement::class)->handle($actor, $draft, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => $draft->revision,
    ]);
    $draftPrivate = Announcement::factory()->create([
        'institution_id' => $this->institution->getKey(),
        'created_by' => $actor->getKey(),
    ]);
    $expired = Announcement::factory()->published()->create([
        'institution_id' => $this->institution->getKey(),
        'created_by' => $actor->getKey(),
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);
    $foreignInstitution = Institution::query()->create(['name' => 'Foreign communications test', 'code' => 'COMM-FOREIGN']);
    $foreign = Announcement::factory()->published()->create([
        'institution_id' => $foreignInstitution->getKey(),
        'created_by' => $actor->getKey(),
    ]);
    $query = app(AnnouncementQuery::class);
    expect($query->publicIndex()['announcements']['total'])->toBe(0)
        ->and(fn () => $query->publicDetail((int) $scheduled->getKey()))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $query->publicDetail((int) $draftPrivate->getKey()))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $query->publicDetail((int) $expired->getKey()))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $query->publicDetail((int) $foreign->getKey()))->toThrow(ModelNotFoundException::class);
    Carbon::setTestNow($start);
    expect($query->publicIndex()['announcements']['data'])->toHaveCount(1);
    Carbon::setTestNow($end);
    expect($query->publicIndex()['announcements']['total'])->toBe(0)
        ->and(fn () => $query->publicDetail((int) $scheduled->getKey()))->toThrow(ModelNotFoundException::class);
    Carbon::setTestNow();
});

test('corrections append immutable snapshots, replay once, require publish authority, and withdrawal retains reason', function (): void {
    $actor = communicationsActor();
    $start = now()->addHour()->utc();
    $draft = app(SaveAnnouncement::class)->handle($actor, communicationsPayload((string) Str::uuid(), 'office:registrar', [
        'starts_at' => $start->toISOString(),
    ]));
    $published = app(PublishAnnouncement::class)->handle($actor, $draft, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => $draft->revision,
    ]);
    $original = AnnouncementRevision::query()->where('announcement_id', $published->getKey())->where('revision', $published->revision)->firstOrFail();
    $correction = [
        'announcement_id' => $published->getKey(),
        'request_id' => (string) Str::uuid(),
        'expected_revision' => $published->revision,
        'title' => 'Corrected synthetic admissions desk update',
        'summary' => 'A corrected synthetic summary.',
        'body' => 'Corrected synthetic plain text.',
        'priority' => 'urgent',
        'is_featured' => true,
        'starts_at' => null,
        'ends_at' => null,
    ];
    $saved = app(SaveAnnouncement::class)->handle($actor, $correction);
    $auditCount = AuditEvent::query()->count();
    expect($saved->status)->toBe('published')
        ->and($saved->starts_at?->toISOString())->toBe($original->starts_at?->toISOString())
        ->and(app(SaveAnnouncement::class)->handle($actor, $correction)->revision)->toBe($saved->revision)
        ->and(AnnouncementRevision::query()->where('announcement_id', $saved->getKey())->count())->toBe(3)
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AnnouncementRevision::query()->findOrFail($original->getKey())->title)->toBe('Synthetic admissions desk update');
    expect(fn () => $original->update(['title' => 'Overwritten historical title']))->toThrow(LogicException::class)
        ->and($original->fresh()->title)->toBe('Synthetic admissions desk update')
        ->and(fn () => AnnouncementCommandReceipt::query()->where('request_id', $correction['request_id'])->firstOrFail()->delete())
        ->toThrow(LogicException::class);
    expect(fn () => app(SaveAnnouncement::class)->handle($actor, [...$correction, 'body' => 'Changed body with same request id.']))
        ->toThrow(ValidationException::class);

    $actor->revokePermissionTo(CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH);
    expect(fn () => app(SaveAnnouncement::class)->handle(User::query()->findOrFail($actor->getKey()), $correction))
        ->toThrow(AuthorizationException::class);
    $actor->givePermissionTo(CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH);

    $withdrawPayload = [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => $saved->revision,
        'reason' => 'The synthetic notice is no longer current.',
    ];
    $withdrawn = app(WithdrawAnnouncement::class)->handle($actor, $saved, $withdrawPayload);
    expect($withdrawn->status)->toBe('withdrawn')
        ->and($withdrawn->withdrawal_reason)->toBe($withdrawPayload['reason'])
        ->and(AnnouncementRevision::query()->where('announcement_id', $saved->getKey())->count())->toBe(4)
        ->and(AuditEvent::query()->where('event_type', 'communications.announcement_withdrawn')->count())->toBe(1);
    $originalReplay = app(SaveAnnouncement::class)->handle($actor, $correction);
    expect($originalReplay->status)->toBe('published')
        ->and($originalReplay->revision)->toBe($saved->revision)
        ->and($originalReplay->title)->toBe('Corrected synthetic admissions desk update');
});

test('demo seeder repeats without duplicating synthetic announcements or revisions', function (): void {
    $seeder = new PublicCommunicationsDemoSeeder;
    $seeder->run();
    $announcementCount = Announcement::query()->count();
    $revisionCount = AnnouncementRevision::query()->count();
    $receiptCount = AnnouncementCommandReceipt::query()->count();

    $seeder->run();

    expect(Announcement::query()->count())->toBe($announcementCount)
        ->and(AnnouncementRevision::query()->count())->toBe($revisionCount)
        ->and(AnnouncementCommandReceipt::query()->count())->toBe($receiptCount)
        ->and(Announcement::query()->where('title', 'Demo: Registrar draft workflow example')->value('status'))->toBe('draft');
});

test('guest home excludes office notices attached to a program', function (): void {
    $actor = communicationsActor();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $general = Announcement::factory()->published()->create([
        'institution_id' => $this->institution->id,
        'created_by' => $actor->id,
        'publisher_key' => 'office:registrar',
        'title' => 'General Registrar headline',
        'is_featured' => true,
    ]);
    $attached = Announcement::factory()->published()->create([
        'institution_id' => $this->institution->id,
        'created_by' => $actor->id,
        'publisher_key' => 'office:registrar',
        'title' => 'BSN-only Registrar headline',
        'is_featured' => true,
        'program_id' => $program->id,
    ]);

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Home')
        ->where('alerts.0.title', $general->title)
        ->has('alerts', 1));
    $this->get('/programs/BSN')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Program')
        ->where('program.code', 'BSN')
        ->where('alerts.0.title', $attached->title)
        ->has('alerts', 1));
});

test('office clerk correction and reasoned withdrawal apply to public reading with retry safety', function (): void {
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $create = communicationsPayload((string) Str::uuid(), 'office:guidance');
    $this->actingAs($clerk)->post('/communications', $create)->assertRedirect('/communications');
    $draft = Announcement::query()->where('created_by', $clerk->id)->firstOrFail();
    $publish = ['request_id' => (string) Str::uuid(), 'expected_revision' => $draft->revision];
    $this->post("/communications/{$draft->id}/publish", $publish)->assertRedirect('/communications');
    $this->post("/communications/{$draft->id}/publish", $publish)->assertRedirect('/communications');
    $this->get("/communications/{$draft->id}/edit")->assertInertia(fn ($page) => $page
        ->where('announcement.revision', 2)->has('revisions', 2));

    $correction = array_diff_key(communicationsPayload((string) Str::uuid()), ['publisher_key' => true]);
    $correction['expected_revision'] = 2;
    $correction['title'] = 'Corrected Guidance headline';
    $this->put("/communications/{$draft->id}", $correction)->assertRedirect('/communications');
    $this->putJson("/communications/{$draft->id}", [...$correction, 'request_id' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
    $this->get("/announcements/{$draft->id}")->assertInertia(fn ($page) => $page
        ->where('announcement.title', 'Corrected Guidance headline'));
    $this->postJson("/communications/{$draft->id}/withdraw", [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 3, 'reason' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->post("/communications/{$draft->id}/withdraw", [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 3,
        'reason' => 'Superseded by a corrected staff advisory.',
    ])->assertRedirect('/communications');
    $this->get("/announcements/{$draft->id}")->assertNotFound();
    $this->get("/communications/{$draft->id}/edit")->assertInertia(fn ($page) => $page
        ->where('announcement.status', 'withdrawn')->has('revisions', 4));
});

test('office and academic HTTP authors cannot cross issuer or destination scope', function (): void {
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $other = Program::query()->where('code', '!=', 'BSIT')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($clerk)->postJson('/communications', communicationsPayload((string) Str::uuid(), 'office:guidance'))
        ->assertForbidden();
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $this->actingAs($head)->postJson('/communications', communicationsPayload((string) Str::uuid(), 'program:BSIT'))
        ->assertUnprocessable()->assertJsonValidationErrors('program_id');
    $this->postJson('/communications', communicationsPayload((string) Str::uuid(), 'program:BSIT', ['program_id' => $other->id]))
        ->assertForbidden();
    $this->post('/communications', communicationsPayload((string) Str::uuid(), 'program:BSIT', ['program_id' => $program->id]))
        ->assertRedirect('/communications');
    $draft = Announcement::query()->where('created_by', $head->id)->firstOrFail();
    $this->putJson("/communications/{$draft->id}", [
        ...array_diff_key(communicationsPayload((string) Str::uuid()), ['publisher_key' => true]),
        'expected_revision' => 1, 'program_id' => $other->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('program_id');
    $program->update(['is_active' => false]);
    $this->postJson("/communications/{$draft->id}/publish", [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ])->assertForbidden();
    $this->get('/programs/BSIT')->assertNotFound();
});

test('public program notice detail fails closed with the same eligibility as its ticker and directory', function (): void {
    $actor = communicationsActor();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $notice = Announcement::factory()->published()->create([
        'institution_id' => $this->institution->id, 'created_by' => $actor->id,
        'publisher_key' => 'office:guidance', 'program_id' => $program->id, 'is_featured' => true,
    ]);
    $this->get('/programs/BSN/announcements')->assertOk()->assertInertia(fn ($page) => $page
        ->where('announcements.data.0.id', (string) $notice->id)->has('announcements.data', 1));
    $this->get("/announcements/{$notice->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('program.code', 'BSN')->where('alerts.0.program_code', 'BSN'));
    $this->get('/announcements')->assertOk()->assertInertia(fn ($page) => $page->has('announcements.data', 0));

    $program->update(['is_active' => false]);
    $this->get('/programs/BSN')->assertNotFound();
    $this->get('/programs/BSN/announcements')->assertNotFound();
    $this->get("/announcements/{$notice->id}")->assertNotFound();
    $this->get('/')->assertInertia(fn ($page) => $page->has('alerts', 0));
});

test('legacy unmapped issuers and mismatched academic attachments never become guest notices', function (): void {
    $actor = communicationsActor();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    foreach (['campus', 'office:hr', 'program:UNKNOWN', 'program:OTHER'] as $issuer) {
        $notice = Announcement::factory()->published()->create([
            'institution_id' => $this->institution->id, 'created_by' => $actor->id,
            'publisher_key' => $issuer, 'program_id' => $issuer === 'program:OTHER' ? $program->id : null,
            'is_featured' => true,
        ]);
        $this->get("/announcements/{$notice->id}")->assertNotFound();
    }
    $this->get('/')->assertInertia(fn ($page) => $page->has('alerts', 0));
    $this->get('/programs/BSN')->assertInertia(fn ($page) => $page->has('alerts', 0)->has('announcements', 0));
});

test('home program links resolve only active current-institution identities without invented profiles', function (): void {
    $foreignInstitution = Institution::query()->create(['name' => 'Foreign program catalog', 'code' => 'FOREIGN-CATALOG']);
    Program::query()->create([
        'institution_id' => $foreignInstitution->id, 'code' => 'FOREIGN', 'name' => 'Foreign program', 'is_active' => true,
    ]);
    $this->get('/')->assertInertia(fn ($page) => $page->where('programs', function ($programs): bool {
        return collect($programs)->contains(fn ($program) => $program['code'] === 'BSN' && $program['href'] === '/programs/BSN')
            && ! collect($programs)->contains('code', 'FOREIGN');
    }));
    $this->get('/programs/BSN')->assertInertia(fn ($page) => $page
        ->where('program.name', 'Bachelor of Science in Nursing')->where('publicProfile', null));
    $this->get('/programs/FOREIGN')->assertNotFound();
});

test('forward academic placement reconciliation preserves attribution and fails closed on unknown sources', function (): void {
    $actor = communicationsActor('program');
    $notice = Announcement::factory()->published()->create([
        'institution_id' => $this->institution->id, 'created_by' => $actor->id,
        'publisher_key' => 'program:BSN', 'publisher_label' => 'Original academic attribution',
    ]);
    $unknown = Announcement::factory()->published()->create([
        'institution_id' => $this->institution->id, 'created_by' => $actor->id,
        'publisher_key' => 'program:UNMAPPED',
    ]);
    $backfill = require database_path('migrations/2026_10_05_200001_backfill_announcement_program_placement.php');
    $backfill->up();

    $this->get("/announcements/{$notice->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('program.code', 'BSN')->where('announcement.publisher_label', 'Original academic attribution'));
    $this->get("/announcements/{$unknown->id}")->assertNotFound();
    $this->get('/')->assertInertia(fn ($page) => $page->has('alerts', 0));
});

test('blank-start save retries remain recoverable after the publication end passes', function (): void {
    $actor = communicationsActor();
    $end = now()->addMinute();
    $payload = communicationsPayload((string) Str::uuid(), 'office:registrar', ['ends_at' => $end->toISOString()]);
    $draft = app(SaveAnnouncement::class)->handle($actor, $payload);
    Carbon::setTestNow($end->copy()->addMinute());
    try {
        $this->actingAs($actor)->post('/communications', $payload)->assertRedirect('/communications');
        expect(app(SaveAnnouncement::class)->handle($actor, $payload)->id)->toBe($draft->id);
    } finally {
        Carbon::setTestNow();
    }
});

test('publication preserves the issuer label captured when the academic draft was created', function (): void {
    $head = communicationsActor('program');
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $draft = app(SaveAnnouncement::class)->handle($head, communicationsPayload((string) Str::uuid(), 'program:BSN', ['program_id' => $program->id]));
    $program->update(['name' => 'Renamed canonical nursing program']);
    $published = app(PublishAnnouncement::class)->handle($head, $draft, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ]);
    expect($published->publisher_label)->toBe('Bachelor of Science in Nursing');
});

test('announcement creation replay is bound to its program target and revoked operation', function (): void {
    $actor = communicationsActor();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $payload = communicationsPayload((string) Str::uuid(), 'office:registrar');
    $draft = app(SaveAnnouncement::class)->handle($actor, $payload);
    expect(fn () => app(SaveAnnouncement::class)->handle($actor, [...$payload, 'program_id' => $program->id]))
        ->toThrow(ValidationException::class);
    $this->actingAs($actor)->get("/communications/{$draft->id}/edit")->assertInertia(fn ($page) => $page
        ->where('announcement.program_id', null)->has('revisions', 1));
    $loadedActor = $actor->fresh();
    $actor->revokePermissionTo(CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE);
    expect(fn () => app(SaveAnnouncement::class)->handle($loadedActor, $payload))
        ->toThrow(AuthorizationException::class);
});
test('announcement list filters search within authorized issuer and placement scope', function (): void {
    $guidance = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $searchTerm = 'filter-scope-'.Str::uuid();
    $searchQuery = strtoupper($searchTerm);
    $title = "Synthetic {$searchTerm} announcement";

    app(SaveAnnouncement::class)->handle($guidance, communicationsPayload((string) Str::uuid(), 'office:guidance', [
        'title' => $title,
    ]));
    $programNotice = app(SaveAnnouncement::class)->handle($guidance, [
        ...communicationsPayload((string) Str::uuid(), 'office:guidance', ['title' => $title]),
        'program_id' => (int) $program->id,
    ]);
    app(SaveAnnouncement::class)->handle($registrar, communicationsPayload((string) Str::uuid(), 'office:registrar', [
        'title' => $title,
    ]));

    $this->actingAs($guidance)->get('/communications?search='.urlencode($searchQuery).'&status=draft&placement=program')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('announcements.total', 1)
            ->where('announcements.data.0.id', (string) $programNotice->getKey())
            ->where('filters.search', $searchQuery)
            ->where('filters.status', 'draft')
            ->where('filters.placement', 'program'));
});
