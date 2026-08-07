<?php

use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('academic years index lists created years without create forms', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.cycles'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Cycles')
            ->has('cycles', 1)
            ->where('cycles.0.school_year', '2025-2026')
            ->where('cycles.0.semester', '1')
            ->where('cycles.0.label', '2025-2026 1st Semester')
            ->where('cycles.0.is_active', true)
            ->missing('windows')
        );
});

test('create academic year is a separate page like legacy', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.cycles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/Cycles/Create'));

    $this->actingAs($registrar)
        ->post(route('setup.cycles.store'), [
            'school_year' => '2026-2027',
            'semester' => '2',
            'application_start_date' => '2026-08-01',
            'application_end_date' => '2027-01-31',
        ])
        ->assertRedirect(route('setup.cycles'));

    $cycle = AcademicCycle::query()
        ->where('school_year', '2026-2027')
        ->where('semester', '2')
        ->first();

    expect($cycle)->not->toBeNull()
        ->and($cycle->is_active)->toBeFalse()
        ->and($cycle->staffLabel())->toBe('2026-2027 2nd Semester')
        ->and($cycle->primaryWindow())->not->toBeNull();
});

test('edit and activate academic year follow legacy list actions', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $active = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.cycles.edit', $active))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Cycles/Edit')
            ->where('cycle.id', $active->id)
            ->where('cycle.is_active', true)
        );

    $this->actingAs($registrar)
        ->post(route('setup.cycles.store'), [
            'school_year' => '2026-2027',
            'semester' => '1',
            'application_start_date' => '2026-08-01',
            'application_end_date' => '2026-12-15',
        ])
        ->assertRedirect(route('setup.cycles'));

    $next = AcademicCycle::query()
        ->where('school_year', '2026-2027')
        ->where('semester', '1')
        ->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('setup.cycles.activate', $next))
        ->assertRedirect(route('setup.cycles'));

    expect($next->fresh()->is_active)->toBeTrue()
        ->and($active->fresh()->is_active)->toBeFalse();
});

test('duplicate school year and semester is rejected', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->from(route('setup.cycles.create'))
        ->post(route('setup.cycles.store'), [
            'school_year' => '2025-2026',
            'semester' => '1',
            'application_start_date' => '2025-08-01',
            'application_end_date' => '2026-01-31',
        ])
        ->assertRedirect(route('setup.cycles.create'))
        ->assertSessionHasErrors('school_year');
});
