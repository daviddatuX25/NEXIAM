<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('setup hub allows registrar clerk with shared setup capability', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('capabilities.setup', true)
            ->where('capabilities.assessment', false)
            ->has('readiness.categories', 8)
            ->where('readiness.categories.5.key', 'aptitude')
            ->where('readiness.categories.5.capability', 'assessment')
            ->where('readiness.categories.3.key', 'privacy')
            ->where('readiness.categories.3.href', '/setup/privacy')
            ->where('readiness.categories.5.href', '/setup/aptitude')
            ->where('readiness.categories.7.key', 'omr')
            ->where('readiness.categories.7.href', '/setup/omr')
        );
});

test('registrar cannot open aptitude configure (TA-only)', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.aptitude'))
        ->assertForbidden();
});

test('registrar can open privacy configure separately from aptitude', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/Privacy'));
});

test('setup hub allows guidance counselor under D-087 shared setup', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('capabilities.setup', true)
            ->where('capabilities.assessment', true)
            ->where('capabilities.quota', true)
        );
});

test('guidance counselor can open aptitude catalog', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.aptitude'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/Aptitude'));
});

test('setup hub denies users without setup assessment or quota capability', function (): void {
    seedE2Foundation();
    $user = User::factory()->create(['email' => 'nosetup@nexiam.test']);

    $this->actingAs($user)
        ->get(route('setup.index'))
        ->assertForbidden();
});
