<?php

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('program head can set quota for assigned program', function (): void {
    seedE2Foundation();
    $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($ph)
        ->post(route('setup.quotas.store'), [
            'program_id' => $bsn->id,
            'academic_cycle_id' => $cycle->id,
            'slots' => 48,
            'notice' => 'BSN intake cap for 2026',
        ])
        ->assertRedirect();

    $quota = ProgramQuota::query()
        ->where('program_id', $bsn->id)
        ->where('academic_cycle_id', $cycle->id)
        ->first();

    expect($quota)->not->toBeNull()
        ->and($quota->slots)->toBe(48)
        ->and($quota->last_notice)->toBe('BSN intake cap for 2026');

    expect(AuditEvent::query()->where('event_type', 'setup.program_quota_upserted')->exists())->toBeTrue();
});

test('program head cannot set quota for another program', function (): void {
    seedE2Foundation();
    $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();
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
            'notice' => 'Aligned with room Lab A capacity — please confirm.',
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
