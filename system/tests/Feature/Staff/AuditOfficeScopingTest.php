<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guidance counselor sees guidance domain events and guidance staff logins', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $regClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $institutionId = $guidance->actorScope()->institutionId;

    $recorder = app(RecordAuditEvent::class);

    // 1. Guidance staff login
    $guidanceLogin = $recorder->handle('auth.staff_login', [
        'institution_id' => $institutionId,
        'user_id' => $clerk->id,
        'email' => $clerk->email,
        'office' => 'guidance',
        'source' => 'password',
    ], (string) $clerk->id);

    // 2. Registrar staff login (must NOT be visible to Guidance Counselor)
    $regLogin = $recorder->handle('auth.staff_login', [
        'institution_id' => $institutionId,
        'user_id' => $regClerk->id,
        'email' => $regClerk->email,
        'office' => 'registrar',
        'source' => 'password',
    ], (string) $regClerk->id);

    // 3. Guidance exam session created
    $examEvent = $recorder->handle('exam.session_created', [
        'institution_id' => $institutionId,
        'session_id' => 99101,
        'code' => 'EXAM-SCOPED-01',
    ], (string) $guidance->id);

    $response = $this->actingAs($guidance)->get('/staff/logs');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.logs', function ($logs) use ($guidanceLogin, $regLogin, $examEvent): bool {
                $ids = collect($logs)->pluck('id');

                return $ids->contains((string) $guidanceLogin->id)
                    && $ids->contains((string) $examEvent->id)
                    && ! $ids->contains((string) $regLogin->id);
            })
            ->where('audit.events', function ($events): bool {
                $eventCollection = collect($events);

                return $eventCollection->contains('auth.staff_login')
                    && $eventCollection->contains('exam.session_created');
            })
        );
});

test('view_scope=own restricts view to authenticated user actions only', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $institutionId = $guidance->actorScope()->institutionId;

    $recorder = app(RecordAuditEvent::class);

    $counselorEvent = $recorder->handle('exam.session_created', [
        'institution_id' => $institutionId,
        'session_id' => 99102,
        'code' => 'OWN-SESSION-01',
    ], (string) $guidance->id);

    $clerkEvent = $recorder->handle('exam.session_created', [
        'institution_id' => $institutionId,
        'session_id' => 99103,
        'code' => 'CLERK-SESSION-01',
    ], (string) $clerk->id);

    $response = $this->actingAs($guidance)->get('/staff/logs?view_scope=own');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.filters.view_scope', 'own')
            ->where('audit.logs', function ($logs) use ($counselorEvent, $clerkEvent): bool {
                $ids = collect($logs)->pluck('id');

                return $ids->contains((string) $counselorEvent->id)
                    && ! $ids->contains((string) $clerkEvent->id);
            })
        );
});

test('legacy association index resolves historical un-scoped events for audit explorer', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institutionId = $guidance->actorScope()->institutionId;

    $recorder = app(RecordAuditEvent::class);

    // Event created WITHOUT institution_id in payload
    $legacyEvent = $recorder->handle('exam.attendance_recorded', [
        'session_id' => 99104,
        'reference_code' => 'LEGACY-ASSOC-01',
    ], (string) $guidance->id);

    // Associated via audit_event_scope_associations table
    DB::table('audit_event_scope_associations')->insert([
        'audit_event_id' => $legacyEvent->id,
        'institution_id' => $institutionId,
        'office' => 'guidance',
        'resolution_evidence' => 'test_fixture',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($guidance)->get('/staff/logs?search=LEGACY-ASSOC-01');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audit.total_filtered', 1)
            ->where('audit.logs.0.id', (string) $legacyEvent->id)
            ->where('audit.logs.0.event_type', 'exam.attendance_recorded')
        );
});
