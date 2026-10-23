<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use App\Support\Navigation\StaffNavigationBuilder;

function adminOfficeFlattenNavigation(array $sections): array
{
    return collect($sections)->flatMap(fn (array $section) => $section['items'] ?? [])->all();
}

test('institutional admin seed has admin office rank four and canonical executive home', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    expect($admin->hasRole('institutional_admin'))->toBeTrue()
        ->and($admin->staffProfile->office)->toBe('admin')
        ->and(StaffRoleCeiling::ROLE_RANKS['institutional_admin'])->toBe(4);

    $this->actingAs($admin)->get('/staff')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Staff/Home')
        ->has('dashboard.cycle_roadmap')
        ->has('dashboard.setup_readiness')
        ->has('dashboard.academic_scope')
        ->has('dashboard.admissions')
        ->has('dashboard.assessment')
        ->has('dashboard.facility_health')
        ->has('dashboard.supervisory_audit'));
});

test('admin office navigation is exclusive and has no duplicate destinations', function (): void {
    seedE2Foundation();
    $builder = app(StaffNavigationBuilder::class);

    foreach (['admin.lead@nexiam.test', 'hr.admin@nexiam.test'] as $email) {
        $sections = $builder->for(User::query()->where('email', $email)->firstOrFail());
        $governance = collect($sections)->firstWhere('label', 'Governance');
        $items = adminOfficeFlattenNavigation($sections);
        expect(collect($sections)->pluck('label'))->not->toContain('Admin Office')
            ->toContain('Governance')
            ->and(collect($governance['items'] ?? [])->pluck('href'))->toContain('/staff/personnel', '/staff/logs')
            ->and(collect($items)->firstWhere('href', '/staff')['label'] ?? null)->toBe('Dashboard')
            ->and(collect($items)->pluck('href'))->not->toContain('/admin')
            ->and(collect($items)->pluck('href')->duplicates()->all())->toBe([]);
    }
});

test('operational staff cannot access institutional setup while retaining office tools', function (): void {
    seedE2Foundation();
    foreach ([
        'registrar.admin@nexiam.test', 'registrar.clerk@nexiam.test',
        'guidance.counselor@nexiam.test', 'guidance.clerk@nexiam.test',
    ] as $email) {
        $actor = User::query()->where('email', $email)->firstOrFail();
        foreach (['/admin', '/setup/cycles', '/setup/rooms', '/setup/programs'] as $uri) {
            $this->actingAs($actor)->get($uri)->assertForbidden();
        }
    }

    $this->actingAs(User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail())
        ->get('/setup/notifications/admissions')->assertOk();
    $this->actingAs(User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail())
        ->get('/setup/aptitude')->assertOk();
});
test('admin office owns central publishing while granular setup stays with its assigned offices', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $program = Program::query()->firstOrFail();

    $this->actingAs($admin)->get('/communications/policies')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Communications/Policies/Index')
        ->where('publishers', fn ($publishers) => collect($publishers)->contains(
            fn ($publisher) => $publisher['key'] === 'office:admin',
        )));
    $this->actingAs($admin)->get('/communications')->assertOk();
    $this->actingAs($admin)->get("/setup/programs/{$program->id}/public-details")->assertForbidden();

    foreach (['/setup/requirements', '/setup/intake-fields'] as $uri) {
        $this->actingAs($registrar)->get($uri)->assertOk();
    }
    foreach (['/setup/cycles', '/setup/rooms', '/setup/programs'] as $uri) {
        $this->actingAs($registrar)->get($uri)->assertForbidden();
    }
});

test('admin office navigation has one homepage studio destination in the governance section', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $sections = app(StaffNavigationBuilder::class)->for($admin);
    $governanceItems = collect(collect($sections)->firstWhere('label', 'Governance')['items'] ?? []);
    $allItems = adminOfficeFlattenNavigation($sections);

    expect(collect($sections)->pluck('label'))->not->toContain('Admin Office')
        ->toContain('Governance')
        ->and($governanceItems->pluck('href'))->toContain('/communications/homepage')
        ->and(collect($allItems)->pluck('href')->duplicates()->all())->toBe([]);
});

test('institutional administrator can export sanitized audit CSV while office leads are scoped', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    app(RecordAuditEvent::class)->handle('admin.export_test', [
        'password' => 'secret-password', 'token' => 'secret-token',
        'custom_api_key' => 'secret-key', 'access_key' => 'private-key',
        'birthdate' => '2000-01-01', 'formula' => '=SUM(A1:A2)',
    ], (string) $admin->id);

    $response = $this->actingAs($admin)->get('/staff/logs/export')->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $csv = $response->streamedContent();
    foreach (['password', 'token', 'custom_api_key', 'access_key', 'birthdate'] as $key) {
        expect($csv)->toContain($key)->toContain('[REDACTED]');
    }
    expect($csv)->toContain("'=SUM(A1:A2)");

    foreach ([
        'registrar.admin@nexiam.test' => 'registrar',
        'guidance.counselor@nexiam.test' => 'guidance',
    ] as $email => $office) {
        $actor = User::query()->where('email', $email)->firstOrFail();
        $this->actingAs($actor)->get('/staff/logs/export')->assertForbidden();
        $this->actingAs($actor)->get('/staff/logs')->assertOk()->assertInertia(fn ($page) => $page
            ->where('audit.logs', fn ($events) => collect($events)->every(
                fn ($event) => ($event['office'] ?? null) === $office
                    || str_starts_with((string) ($event['event_type'] ?? ''), $office.'.')
                    || ($office === 'registrar' && str_starts_with((string) ($event['event_type'] ?? ''), 'admissions.'))
                    || ($office === 'guidance' && collect(['exams.', 'assessment.', 'consultation.'])->contains(
                        fn (string $prefix) => str_starts_with((string) ($event['event_type'] ?? ''), $prefix),
                    )),
            )));
    }
});
