<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
});

test('quota settings honor the selected program instead of displaying other authorized programs', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($actor)
        ->get(route('setup.quotas', ['program_id' => $program->id, 'cycle_id' => $cycle->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Quotas')
            ->has('quota_projections', 1)
            ->where('quota_projections.0.program.code', 'BSIT')
            ->where('cycle.id', $cycle->id));
});

test('read-only program head follows quota settings back to the same program and cycle without gaining write access', function (): void {
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $actor->revokePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    foreach ($actor->roles as $role) {
        $role->revokePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    ProgramQuota::query()->create(['program_id' => $program->id, 'academic_cycle_id' => $cycle->id, 'slots' => 42]);

    $hub = $this->actingAs($actor)->get(route('setup.programs.hub', ['program' => $program, 'cycle_id' => $cycle->id, 'tab' => 'quotas']));
    $hub->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('can_manage_quota', false)
        ->where('quota.slots', 42));
    $settingsUrl = $hub->viewData('page')['props']['quota_settings_url'];
    $settings = $this->get($settingsUrl);
    $settings->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('quota_projections', 1)
        ->where('selected_program.code', 'BSIT')
        ->where('quota_projections.0.quota.slots', 42)
        ->where('quota_projections.0.can_manage', false));

    $returnUrl = $settings->viewData('page')['props']['program_hub_url'];
    parse_str(parse_url($returnUrl, PHP_URL_QUERY), $context);
    expect($context['tab'])->toBe('quotas');
    $this->get($returnUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('program.id', $program->id)
        ->where('selected_cycle_id', $cycle->id)
        ->where('quota.slots', 42));
    $this->post(route('setup.quotas.store'), ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id, 'slots' => 99])
        ->assertForbidden();
});

test('general quota index still exposes all authorized programs without selecting one', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($actor)->get(route('setup.quotas'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_program', null)
            ->where('program_hub_url', null)
            ->where('quota_projections', fn ($rows) => collect($rows)->pluck('program.code')->contains('BSIT')
                && collect($rows)->pluck('program.code')->contains('BASS')));
});

test('quota navigation rejects inaccessible or malformed program context instead of falling back', function (mixed $selection): void {
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $programId = $selection === 'foreign' ? Program::query()->where('code', 'BASS')->value('id') : $selection;

    $this->actingAs($actor)->get(route('setup.quotas', ['program_id' => $programId, 'cycle_id' => $cycle->id]))
        ->assertNotFound();
})->with([
    'foreign program' => ['foreign'],
    'missing program' => [999999],
    'empty program' => [''],
    'array program' => [['13']],
]);

test('closed-cycle quota reads do not advertise writes in either Hub or quota settings', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $cycle->update(['closes_on' => now()->subDay()->toDateString()]);

    $this->actingAs($actor)->get(route('setup.programs.hub', ['program' => $program, 'cycle_id' => $cycle->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_manage_quota', false));
    $this->get(route('setup.quotas', ['program_id' => $program->id, 'cycle_id' => $cycle->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('quota_projections.0.can_manage', false));
    $this->post(route('setup.quotas.store'), ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id, 'slots' => 99])
        ->assertForbidden();
});

test('Hub has no quota navigation or write affordance without an authorized cycle', function (): void {
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    AcademicCycle::query()->update(['is_active' => false]);
    $actor->syncRoles([]);
    $actor->syncPermissions([CapabilityCatalog::PROGRAM_HEAD_VIEW]);

    $this->actingAs($actor)->get(route('setup.programs.hub', ['program' => $program]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selected_cycle_id', null)
        ->where('quota_settings_url', null)
        ->where('can_manage_quota', false));
});

test('quota links and target facts follow the selected academic cycle', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $firstCycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $secondCycle = AcademicCycle::query()->create([
        'institution_id' => $program->institution_id,
        'code' => 'SYN-QUOTA-NAV-SECOND',
        'name' => 'Synthetic second cycle',
        'school_year' => '2027-2028',
        'semester' => '2',
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);
    ProgramQuota::query()->create(['program_id' => $program->id, 'academic_cycle_id' => $firstCycle->id, 'slots' => 42]);
    ProgramQuota::query()->create(['program_id' => $program->id, 'academic_cycle_id' => $secondCycle->id, 'slots' => 17]);

    $hub = $this->actingAs($actor)->get(route('setup.programs.hub', ['program' => $program, 'cycle_id' => $secondCycle->id, 'tab' => 'quotas']));
    $hub->assertOk();
    $settings = $this->get($hub->viewData('page')['props']['quota_settings_url']);
    $settings->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('cycle.id', $secondCycle->id)
        ->where('quota_projections.0.quota.slots', 17));
    $this->get($settings->viewData('page')['props']['program_hub_url'])
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selected_cycle_id', $secondCycle->id)
        ->where('quota.slots', 17));
});

test('authorized historical quota context returns to Hub without enabling closed-cycle writes', function (): void {
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $cycle->update(['is_active' => false, 'closes_on' => now()->subDay()->toDateString()]);

    $settings = $this->actingAs($actor)->get(route('setup.quotas', ['program_id' => $program->id, 'cycle_id' => $cycle->id]));
    $settings->assertOk();
    $hub = $this->get($settings->viewData('page')['props']['program_hub_url']);
    $hub->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selected_cycle_id', $cycle->id)
        ->where('can_manage_quota', false));
    $this->get($hub->viewData('page')['props']['quota_settings_url'])
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('cycle.id', $cycle->id)
        ->where('quota_projections.0.can_manage', false));
});

test('revoking a program assignment invalidates previously projected quota links', function (): void {
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $hub = $this->actingAs($actor)->get(route('setup.programs.hub', ['program' => $program, 'cycle_id' => $cycle->id]));
    $hub->assertOk();
    $settingsUrl = $hub->viewData('page')['props']['quota_settings_url'];

    $actor->staffProfile()->update(['program_codes' => ['BASS']]);

    $this->get($settingsUrl)->assertNotFound();
    $this->get(route('setup.programs.hub', ['program' => $program, 'cycle_id' => $cycle->id]))->assertForbidden();
});
