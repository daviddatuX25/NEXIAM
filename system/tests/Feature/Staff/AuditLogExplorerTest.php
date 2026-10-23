<?php

use App\Admission\Actions\SaveProgramAdvisingNote;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Staff\Support\AuditLogEventCatalog;

test('unauthenticated users cannot view audit logs', function (): void {
    $response = $this->get('/staff/logs');

    $response->assertRedirect('/login');
});

test('users without audit capability are denied', function (): void {
    seedE2Foundation();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/staff/logs');

    $response->assertForbidden();
});

test('clerks with own audit capability can view their own activity with null presence', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $response = $this->actingAs($clerk)->get('/staff/logs');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Logs/Index')
            ->where('audit.filters.view_scope', 'own')
            ->where('presence', null)
        );
});

test('registrar administrator can view audit logs explorer with projection shape and pagination', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $response = $this->actingAs($admin)->get('/staff/logs');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Logs/Index')
            ->has('audit.logs')
            ->has('audit.events')
            ->has('audit.categories')
            ->has('audit.filters')
            ->has('audit.total_filtered')
            ->has('audit.pagination.current_page')
            ->has('audit.pagination.last_page')
            ->has('audit.pagination.per_page')
            ->has('audit.pagination.total')
        );
});

test('institutional admin can export audit logs and produce audit trail', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $response = $this->actingAs($leadAdmin)->get('/staff/logs/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $exportAudit = AuditEvent::query()->where('event_type', 'audit.logs_exported')->latest('created_at')->first();
    expect($exportAudit)->not->toBeNull()
        ->and($exportAudit->actor)->toBe((string) $leadAdmin->id);
});

test('private advising note audit data is excluded from explorer search and selective exports without deleting history', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $owner = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $save = app(SaveProgramAdvisingNote::class);
    $private = $save->handle($owner, $program, $cycle, 'Personal working content.', 0, 'private-audit-reason-token', 'audit-private-note', audience: 'private');
    $team = $save->handle($owner, $program, $cycle, 'Program working content.', 0, 'staff-audit-reason-token', 'audit-program-note');
    $privateEvent = AuditEvent::query()->where('event_type', 'advising_note.revision_appended')->where('payload->note_id', $private->program_advising_note_id)->firstOrFail();
    $teamEvent = AuditEvent::query()->where('event_type', 'advising_note.revision_appended')->where('payload->note_id', $team->program_advising_note_id)->firstOrFail();
    $recorder = app(RecordAuditEvent::class);
    $scopedPrivate = $recorder->handle($privateEvent->event_type, [
        ...$privateEvent->payload, 'institution_id' => $admin->actorScope()->institutionId,
    ], (string) $owner->id);
    $scopedTeam = $recorder->handle($teamEvent->event_type, [
        ...$teamEvent->payload, 'institution_id' => $admin->actorScope()->institutionId,
    ], (string) $owner->id);

    $this->actingAs($admin)->get('/staff/logs?search=private-audit-reason-token')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('audit.total_filtered', 0)->where('audit.logs', []));
    $export = $this->get('/staff/logs/export?ids='.$scopedPrivate->id.','.$scopedTeam->id.','.$privateEvent->id.','.$teamEvent->id)->assertOk()->streamedContent();
    expect($export)->toContain($scopedTeam->id)->not->toContain($scopedPrivate->id, $privateEvent->id, $teamEvent->id, 'private-audit-reason-token', 'staff-audit-reason-token');
    expect($privateEvent->fresh()->payload['reason'])->toBe('private-audit-reason-token');
});

test('users without audit export capability receive 403 on export', function (): void {
    seedE2Foundation();
    $regAdmin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $response = $this->actingAs($regAdmin)->get('/staff/logs/export');

    $response->assertForbidden();
});

test('selective export with ids query exports only chosen audit rows', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);

    $event1 = $recorder->handle('admission.test_selective_one', ['institution_id' => $leadAdmin->actorScope()->institutionId, 'reference_code' => 'SEL-1'], (string) $leadAdmin->id);
    $event2 = $recorder->handle('admission.test_selective_two', ['institution_id' => $leadAdmin->actorScope()->institutionId, 'reference_code' => 'SEL-2'], (string) $leadAdmin->id);

    $response = $this->actingAs($leadAdmin)->get('/staff/logs/export?ids='.$event1->id);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain((string) $event1->id)
        ->and($content)->not->toContain((string) $event2->id);
});

test('audit log projection resolves staff user actors with name and office', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);

    $event = $recorder->handle('admission.attempt_corrected', [
        'institution_id' => $leadAdmin->actorScope()->institutionId,
        'reference_code' => 'TEST-REF-999',
        'fields' => ['first_name'],
        'before_identity' => ['first_name' => 'OldName'],
        'after_identity' => ['first_name' => 'NewName'],
    ], (string) $guidance->id);

    $response = $this->actingAs($leadAdmin)->get('/staff/logs?search=TEST-REF-999');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('audit.logs.0', fn ($log) => $log
                ->where('id', (string) $event->id)
                ->where('actor_token', (string) $guidance->id)
                ->where('actor_info.name', $guidance->name)
                ->where('actor_info.email', $guidance->email)
                ->where('actor_info.office', 'guidance')
                ->where('title', 'Admission Attempt Corrected')
                ->where('domain', 'admissions')
                ->etc()
            )
        );
});

test('audit log projection resolves system and external actors', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);

    $eventSystem = $recorder->handle('system.maintenance_run', ['institution_id' => $leadAdmin->actorScope()->institutionId, 'job' => 'prune'], 'system');
    $eventUnknown = $recorder->handle('system.webhook_ping', ['institution_id' => $leadAdmin->actorScope()->institutionId, 'source' => 'external'], '999999');

    $response = $this->actingAs($leadAdmin)->get('/staff/logs?search=maintenance_run');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('audit.logs.0', fn ($log) => $log
                ->where('actor_info.kind', 'system')
                ->where('actor_info.name', 'System Automated Process')
                ->etc()
            )
        );
});

test('category filter limits returned audit logs to specified domain', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);

    $recorder->handle('admission.applicant_amended_pending', ['institution_id' => $leadAdmin->actorScope()->institutionId, 'reference_code' => 'ADM-CAT-01'], (string) $leadAdmin->id);
    $recorder->handle('examination.session_published', ['institution_id' => $leadAdmin->actorScope()->institutionId, 'session_id' => 123], (string) $leadAdmin->id);

    $response = $this->actingAs($leadAdmin)->get('/staff/logs?category=assessment');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.filters.category', 'assessment')
            ->has('audit.logs', fn ($logs) => $logs
                ->each(fn ($item) => $item->where('domain', 'assessment')->etc())
            )
        );
});

test('admissions category includes registrar eligibility and enrollment events', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);
    $eligibilityEvent = $recorder->handle('registrar.eligibility_lookup', ['institution_id' => $admin->actorScope()->institutionId, 'attempt_id' => 8101], (string) $admin->id);
    $enrollmentEvent = $recorder->handle('enrollment.sheet_reissued', ['institution_id' => $admin->actorScope()->institutionId, 'attempt_id' => 8102], (string) $admin->id);

    $this->actingAs($admin)->get('/staff/logs?category=admissions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.logs', fn ($logs) => $logs->contains(fn ($log) => $log['id'] === (string) $eligibilityEvent->id && $log['domain'] === 'admissions')
                && $logs->contains(fn ($log) => $log['id'] === (string) $enrollmentEvent->id && $log['domain'] === 'admissions'))
        );
});

test('audit projection redacts sensitive identity values in payloads and diffs', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $event = app(RecordAuditEvent::class)->handle('admission.attempt_corrected', [
        'institution_id' => $admin->actorScope()->institutionId,
        'before_identity' => [
            'birthdate' => '2000-01-01',
            'email' => 'before@example.test',
            'phone' => '09170000001',
            'contact_number' => '09170000002',
            'mobile' => '09170000003',
            'contact_phone' => '09170000004',
        ],
        'after_identity' => [
            'birthdate' => '2001-02-02',
            'email' => 'after@example.test',
            'phone' => '09179999991',
            'contact_number' => '09179999992',
            'mobile' => '09179999993',
            'contact_phone' => '09179999994',
        ],
    ], (string) $admin->id);

    $this->actingAs($admin)->get('/staff/logs?event=admission.attempt_corrected')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.logs.0.id', (string) $event->id)
            ->where('audit.logs.0.payload.before_identity.birthdate', '[REDACTED]')
            ->where('audit.logs.0.payload.after_identity.phone', '[REDACTED]')
            ->where('audit.logs.0.diff', fn ($diff) => count($diff) === 6
                && collect($diff)->every(fn ($change) => $change['kind'] === 'changed'
                    && $change['old_value'] === '[REDACTED]'
                    && $change['new_value'] === '[REDACTED]'))
        );
});

test('multi-token search matches across actor and payload attributes', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);

    $recorder->handle('admission.attempt_corrected', [
        'institution_id' => $leadAdmin->actorScope()->institutionId,
        'reference_code' => 'TOKEN-MATCH-77',
        'reason' => 'Emergency update',
    ], (string) $guidance->id);

    // Multi-token: "guidance TOKEN-MATCH-77"
    $response = $this->actingAs($leadAdmin)->get('/staff/logs?search=guidance+TOKEN-MATCH-77');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('audit.logs', 1)
            ->where('audit.logs.0.summary', fn ($s) => str_contains((string) $s, 'TOKEN-MATCH-77'))
        );
});
test('case-insensitive search matches lowercase synthetic across mixed-case actors and payloads', function (): void {
    seedE2Foundation();
    $leadAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);

    // Guidance Counselor has name 'Synthetic Guidance Counselor'
    $event = $recorder->handle('admission.attempt_accepted', [
        'institution_id' => $leadAdmin->actorScope()->institutionId,
        'reference_code' => 'CASE-TEST-123',
    ], (string) $guidance->id);

    // Searching lowercase 'syntheti' must match 'Synthetic Guidance Counselor'
    $response = $this->actingAs($leadAdmin)->get('/staff/logs?search=syntheti');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('audit.logs')
            ->where('audit.logs.0.actor_info.name', fn ($name) => str_contains((string) $name, 'Synthetic'))
        );

    // Searching lowercase 'case-test' must match uppercase 'CASE-TEST-123'
    $responsePayload = $this->actingAs($leadAdmin)->get('/staff/logs?search=case-test');

    $responsePayload->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('audit.logs', 1)
            ->where('audit.logs.0.id', (string) $event->id)
        );
});

test('event catalog diff extraction identifies identity mutations', function (): void {
    $payload = [
        'fields' => ['first_name', 'last_name'],
        'before_identity' => [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ],
        'after_identity' => [
            'first_name' => 'Maria Clara',
            'last_name' => 'Santos',
        ],
    ];

    $diff = AuditLogEventCatalog::extractDiff($payload);

    expect($diff)->toBeArray()
        ->and($diff)->toHaveCount(1)
        ->and($diff[0]['field'])->toBe('first_name')
        ->and($diff[0]['old_value'])->toBe('Maria')
        ->and($diff[0]['new_value'])->toBe('Maria Clara')
        ->and($diff[0]['kind'])->toBe('changed');
});
