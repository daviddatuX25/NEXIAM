<?php

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use Database\Seeders\ProgramQuotaSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('admission.quota_writes_enabled', true);
});

test('program head cannot directly set quota for program', function (): void {
    seedE2Foundation();
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $quotaQuery = ProgramQuota::query()
        ->where('program_id', $bsn->id)
        ->where('academic_cycle_id', $cycle->id);
    $quotaPayload = [
        'program_id' => $bsn->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 48,
        'reason' => 'BSN intake cap for 2026',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ];

    $this->actingAs($ph)
        ->post(route('setup.quotas.store'), $quotaPayload)
        ->assertForbidden();

    expect($quotaQuery->exists())->toBeFalse();

    $existingQuota = ProgramQuota::query()->create([
        'program_id' => $bsn->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 24,
        'last_notice' => 'Existing quota notice',
        'last_mutated_by_user_id' => $guidance->id,
    ]);
    $quotaBefore = $existingQuota->fresh()->getAttributes();

    $this->actingAs($ph)
        ->post(route('setup.quotas.store'), $quotaPayload)
        ->assertForbidden();

    expect($existingQuota->fresh()->getAttributes())->toBe($quotaBefore);
});

test('program head can view quotas for assigned program with read-only capability', function (): void {
    seedE2Foundation();
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $bsit = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('setup.quotas.store'), [
            'program_id' => $bsit->id,
            'academic_cycle_id' => $cycle->id,
            'slots' => 48,
            'reason' => 'BSIT intake cap for 2026',
            'expected_version' => 0,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();

    $this->actingAs($ph)
        ->get(route('setup.quotas', ['cycle_id' => $cycle->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Quotas')
            ->where('capabilities.quota_read', true)
            ->where('capabilities.quota', true)
            ->has('quotas', 1)
            ->where('quotas.0.program_code', 'BSIT')
            ->where('quotas.0.slots', 48)
        );
});

test('program head cannot set quota for another program', function (): void {
    seedE2Foundation();
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $psy = Program::query()->where('code', 'BSPSY')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($ph)
        ->post(route('setup.quotas.store'), [
            'program_id' => $psy->id,
            'academic_cycle_id' => $cycle->id,
            'slots' => 20,
        ])
        ->assertForbidden();

    expect(ProgramQuota::query()->where('program_id', $psy->id)->exists())->toBeFalse();
});

test('guidance counselor can set quota with notice to program head', function (): void {
    seedE2Foundation();
    $ta = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $psy = Program::query()->where('code', 'BSPSY')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($ta)
        ->post(route('setup.quotas.store'), [
            'program_id' => $psy->id,
            'academic_cycle_id' => $cycle->id,
            'slots' => 30,
            'reason' => 'Aligned with room Lab A capacity — please confirm.',
            'expected_version' => 0,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();

    $this->actingAs($ta)
        ->get(route('setup.quotas'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Quotas')
            ->has('quotas', 1)
            ->where('quotas.0.slots', 30)
            ->where('quotas.0.last_notice', 'Aligned with room Lab A capacity — please confirm.')
            ->has('quotas.0.changes', 1)
        );

    expect(AuditEvent::query()->where('event_type', 'setup.program_quota_upserted')->exists())->toBeTrue();
});

test('registrar clerk is denied quota mutation by default', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.quotas'))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('setup.quotas.store'), [
            'program_id' => $bsn->id,
            'academic_cycle_id' => $cycle->id,
            'slots' => 10,
        ])
        ->assertForbidden();
});

test('program quota seeder configures offered programs and preserves existing overrides', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $programs = Program::query()->where('institution_id', $cycle->institution_id)->active()->admissionOffered()->get();

    expect(ProgramQuota::query()->where('academic_cycle_id', $cycle->id)->count())->toBe(0);

    (new ProgramQuotaSeeder)->run();

    expect(ProgramQuota::query()->where('academic_cycle_id', $cycle->id)->count())->toBe($programs->count())
        ->and(ProgramQuota::query()->where('academic_cycle_id', $cycle->id)->where('last_notice', 'Baseline institutional cycle quota allocation.')->count())->toBe($programs->count());

    $bsit = $programs->firstWhere('code', 'BSIT');
    $quota = ProgramQuota::query()->where('program_id', $bsit->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
    $quota->update(['slots' => 1, 'last_notice' => 'Intentional over-quota fixture.']);

    (new ProgramQuotaSeeder)->run();

    expect($quota->fresh()->slots)->toBe(1)
        ->and($quota->fresh()->last_notice)->toBe('Intentional over-quota fixture.');
});

test('authorized staff can bulk set quotas for scoped programs', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $programs = Program::query()->whereIn('code', ['BSIT', 'BSN'])->get();

    $this->actingAs($guidance)
        ->post(route('setup.quotas.bulk-store'), [
            'academic_cycle_id' => $cycle->id,
            'idempotency_key' => (string) Str::uuid(),
            'quotas' => $programs->map(fn ($p) => [
                'program_id' => $p->id,
                'slots' => 55,
                'reason' => 'Bulk capacity allocation.',
                'expected_version' => 0,
            ])->all(),
        ])
        ->assertRedirect();

    foreach ($programs as $program) {
        $quota = ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
        expect($quota->slots)->toBe(55)
            ->and($quota->last_notice)->toBe('Bulk capacity allocation.')
            ->and($quota->changes()->count())->toBe(1);
    }
});

test('bulk quota changes deny a program outside the actor governance scope', function (): void {
    seedE2Foundation();
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();

    $this->actingAs($head)
        ->post(route('setup.quotas.bulk-store'), [
            'academic_cycle_id' => $cycle->id,
            'idempotency_key' => (string) Str::uuid(),
            'quotas' => [
                ['program_id' => $bsn->id, 'slots' => 55, 'reason' => 'Unauthorized bulk allocation', 'expected_version' => 0],
            ],
        ])
        ->assertForbidden();
});
