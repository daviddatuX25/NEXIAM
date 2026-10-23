<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;

test('audit inspection and CSV apply the same recursive contact and credential policy', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $event = app(RecordAuditEvent::class)->handle('admission.attempt_corrected', [
        'institution_id' => $admin->actorScope()->institutionId,
        'reference_code' => 'AUDIT-PRIVACY-01',
        'before_attempt' => ['contact_email' => 'hidden-before@example.test'],
        'before' => ['identity' => ['email' => 'nested-before@example.test', 'password' => 'hidden-old-password']],
        'after' => ['identity' => ['email' => 'nested-after@example.test', 'password' => 'hidden-new-password']],
    ], (string) $admin->id);

    $this->actingAs($admin)->get('/staff/logs?search=AUDIT-PRIVACY-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.logs.0.id', (string) $event->id)
            ->where('audit.logs.0.payload.before_attempt.contact_email', '[REDACTED]')
            ->where('audit.logs', function ($logs): bool {
                $serialized = json_encode($logs, JSON_THROW_ON_ERROR);

                return ! str_contains($serialized, 'hidden-before@example.test')
                    && ! str_contains($serialized, 'nested-before@example.test')
                    && ! str_contains($serialized, 'nested-after@example.test')
                    && ! str_contains($serialized, 'hidden-old-password')
                    && ! str_contains($serialized, 'hidden-new-password');
            }));

    $csv = $this->get('/staff/logs/export?ids='.$event->id)->assertOk()->streamedContent();
    expect($csv)->toContain('AUDIT-PRIVACY-01')
        ->not->toContain('hidden-before@example.test', 'nested-before@example.test', 'nested-after@example.test', 'hidden-old-password', 'hidden-new-password');
    expect($event->fresh()->payload['before_attempt']['contact_email'])->toBe('hidden-before@example.test');
});

test('audit discovery and export do not treat missing institution as global or infer it from the actor', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $recorder = app(RecordAuditEvent::class);
    $own = $recorder->handle('admission.audit_scope_own', [
        'institution_id' => $admin->actorScope()->institutionId,
        'reference_code' => 'AUDIT-SCOPE-OWN',
    ], (string) $admin->id);
    $foreign = $recorder->handle('admission.audit_scope_foreign', [
        'institution_id' => 999999,
        'reference_code' => 'AUDIT-SCOPE-FOREIGN',
    ], (string) $admin->id);
    $unresolved = $recorder->handle('admission.audit_scope_unresolved', [
        'reference_code' => 'AUDIT-SCOPE-UNRESOLVED',
    ], (string) $admin->id);

    $this->actingAs($admin)->get('/staff/logs?search=AUDIT-SCOPE')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.total_filtered', 1)
            ->where('audit.logs.0.id', (string) $own->id)
            ->where('audit.events', fn ($events) => ! collect($events)->contains('admission.audit_scope_unresolved')
                && ! collect($events)->contains('admission.audit_scope_foreign')));

    $csv = $this->get('/staff/logs/export?ids='.$own->id.','.$foreign->id.','.$unresolved->id)
        ->assertOk()->streamedContent();
    expect($csv)->toContain($own->id)->not->toContain($foreign->id, $unresolved->id);
    expect($unresolved->fresh()->payload)->toBe(['reference_code' => 'AUDIT-SCOPE-UNRESOLVED']);
});

test('hidden audit facts cannot influence search or escape through unknown payload fields', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $event = app(RecordAuditEvent::class)->handle('admission.audit_search_safe', [
        'institution_id' => $admin->actorScope()->institutionId,
        'reference_code' => 'AUDIT-SEARCH-PUBLIC',
        'before_identity' => ['birthdate' => '1999-05-04', 'email' => 'hidden-search@example.test'],
        'private_note' => 'private-audit-narrative',
    ], (string) $admin->id);

    foreach (['1999-05-04', 'hidden-search@example.test', 'private-audit-narrative'] as $guess) {
        $this->actingAs($admin)->get('/staff/logs?search='.urlencode($guess))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('audit.total_filtered', 0)->where('audit.logs', []));
    }

    $this->get('/staff/logs?search=AUDIT-SEARCH-PUBLIC')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.logs.0.id', (string) $event->id)
            ->where('audit.logs.0.payload.private_note', '[REDACTED]'));
});

test('office audit filters and discovery agree with the displayed event domain', function (string $email, string $eventType, string $domain): void {
    seedE2Foundation();
    $reviewer = User::query()->where('email', $email)->firstOrFail();
    $event = app(RecordAuditEvent::class)->handle($eventType, [
        'institution_id' => $reviewer->actorScope()->institutionId,
        'reference_code' => 'AUDIT-DOMAIN-PARITY',
    ], (string) $reviewer->id);

    $this->actingAs($reviewer)->get('/staff/logs?category='.$domain.'&search=AUDIT-DOMAIN-PARITY')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.total_filtered', 1)
            ->where('audit.logs.0.id', (string) $event->id)
            ->where('audit.logs.0.domain', $domain)
            ->where('audit.events', fn ($events) => collect($events)->contains($eventType)));
})->with([
    ['registrar.admin@nexiam.test', 'admission.attempt_accepted', 'admissions'],
    ['guidance.counselor@nexiam.test', 'grading.session_finalized', 'assessment'],
    ['registrar.admin@nexiam.test', 'setup.application_suggestions_saved', 'setup'],
    ['admin.lead@nexiam.test', 'notification.delivery_queued', 'system'],
]);

test('applicant actors are not exposed as staff profiles or searchable by their private email', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $submitted = submitSyntheticApplicant('audit-private-actor@example.test', 'AuditIdentity');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($submitted['access_token'], '2001-01-01');
    $event = app(RecordAuditEvent::class)->handle('admission.applicant_login', [
        'institution_id' => $admin->actorScope()->institutionId,
        'reference_code' => 'AUDIT-ACTOR-OWN',
    ], (string) $applicant->id);

    $this->actingAs($admin)->get('/staff/logs?search=AUDIT-ACTOR-OWN')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.logs.0.id', (string) $event->id)
            ->where('audit.logs.0.actor_info.kind', 'applicant')
            ->where('audit.logs.0.actor_info.email', null));
    $this->get('/staff/logs?search=audit-private-actor%40example.test')->assertOk()
        ->assertInertia(fn ($page) => $page->where('audit.total_filtered', 0));
});

test('invalid audit query shapes and date ranges fail validation before database execution', function (array $query): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $this->actingAs($admin)->getJson('/staff/logs?'.http_build_query($query))->assertUnprocessable();
})->with([
    [['search' => ['private']]],
    [['date_from' => '2026-10-20', 'date_to' => '2026-10-19']],
    [['category' => 'unknown-domain']],
    [['per_page' => 1000]],
]);

test('system actor search stays case insensitive without relying on the event key', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $event = app(RecordAuditEvent::class)->handle('setup.institution_profile_updated', [
        'institution_id' => $admin->actorScope()->institutionId,
    ], 'system');

    $this->actingAs($admin)->get('/staff/logs?search=SYSTEM')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.total_filtered', 1)
            ->where('audit.logs.0.id', (string) $event->id));
});

test('audit reference search treats SQL wildcard characters as literal text', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $record = app(RecordAuditEvent::class);
    $literal = $record->handle('admission.reference_checked', [
        'institution_id' => $admin->actorScope()->institutionId,
        'reference_code' => 'AUDIT_%!',
    ], (string) $admin->id);
    $record->handle('admission.reference_checked', [
        'institution_id' => $admin->actorScope()->institutionId,
        'reference_code' => 'AUDITXABC!',
    ], (string) $admin->id);

    $this->actingAs($admin)->get('/staff/logs?search='.urlencode('AUDIT_%!'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.total_filtered', 1)
            ->where('audit.logs.0.id', (string) $literal->id));
});
