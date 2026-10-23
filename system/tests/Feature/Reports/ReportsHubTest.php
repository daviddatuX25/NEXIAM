<?php

use App\Models\Foundation\AuditEvent;
use App\Models\User;

test('unauthenticated users cannot view reports hub', function (): void {
    $response = $this->get('/reports');

    $response->assertRedirect('/login');
});

test('registrar administrator can access reports hub', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $response = $this->actingAs($registrar)->get('/reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('report.cycles')
            ->where('report.can_registrar', true)
            ->has('report.registrar_reports')
            ->has('report.pipeline')
        );
});

test('exporting a report downloads CSV and produces audit event', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $response = $this->actingAs($registrar)->get('/reports/export/applications-census');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    // Prove audit event recorded
    $audit = AuditEvent::query()->where('event_type', 'reports.export_downloaded')->latest('created_at')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['type'])->toBe('applications-census');
});

test('dean and program head receive scoped focus and distributions on reports hub', function (): void {
    seedE2Foundation();
    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
    $programHead = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();

    $this->actingAs($dean)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $dean->authenticationState->session_version,
    ])->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('report.can_program_head', true)
            ->where('report.analytics.academic_scope.scope_mode', 'department')
            ->where('report.analytics.academic_scope.default_department_code', 'CAS')
            ->where('report.analytics.academic_scope.can_filter_department', false)
            ->where('report.analytics.academic_scope.can_filter_program', true)
        );

    $this->actingAs($programHead)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $programHead->authenticationState->session_version,
    ])->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('report.can_program_head', true)
            ->where('report.analytics.academic_scope.scope_mode', 'program')
            ->where('report.analytics.academic_scope.default_department_code', 'CAS')
            ->where('report.analytics.academic_scope.default_program_code', 'BSIT')
            ->where('report.analytics.academic_scope.can_filter_department', false)
            ->where('report.analytics.academic_scope.can_filter_program', false)
        );
});
