<?php

use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Policy\EffectiveNexiAvailabilityResolver;
use App\Ai\Surface\SurfaceProfileCatalog;
use App\Ai\Surface\SurfaceProfileKey;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('surface catalog includes all six profiles and precedence order', function (): void {
    $catalog = new SurfaceProfileCatalog;

    expect($catalog->all())->toHaveCount(6)
        ->and(SurfaceProfileCatalog::precedence()[0])->toBe(SurfaceProfileKey::BreakGlass)
        ->and(SurfaceProfileCatalog::precedence()[5])->toBe(SurfaceProfileKey::Staff);

    expect($catalog->get(SurfaceProfileKey::Staff)->mountable)->toBeTrue()
        ->and($catalog->get(SurfaceProfileKey::BreakGlass)->defaultAvailability)->toBe('hidden');
});

test('page pack registry maps first foundation routes and rejects unknown routes', function (): void {
    $registry = new PageCapabilityPackRegistry;

    expect($registry->forRoute('staff.home')?->key)->toBe('staff.workspace.home@1')
        ->and($registry->forRoute('setup.index')?->key)->toBe('staff.setup.readiness@1')
        ->and($registry->forRoute('applicant.schedule')?->key)->toBe('applicant.schedule@1')
        ->and($registry->forRoute('applicant.portal'))->toBeNull()
        ->and($registry->forRoute('admissions.index'))->toBeNull()
        ->and($registry->forRoute(null))->toBeNull();
});

test('availability resolver fails closed without pack and degrades without provider', function (): void {
    $resolver = new EffectiveNexiAvailabilityResolver;
    $catalog = new SurfaceProfileCatalog;
    $staff = $catalog->get(SurfaceProfileKey::Staff);
    $pack = (new PageCapabilityPackRegistry)->forRoute('staff.home');

    expect($resolver->resolve($staff, null, true, 'degraded'))->toBe('degraded')
        ->and($resolver->resolve($staff, $pack, true, 'degraded'))->toBe('degraded')
        ->and($resolver->resolve($staff, $pack, false, 'degraded'))->toBe('hidden')
        ->and($resolver->resolve($staff, $pack, true, 'disabled'))->toBe('hidden');
});

test('staff home receives staff surface and workspace page pack session', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Home')
            ->where('nexi.enabled', true)
            ->where('nexi.session.contract_version', '1')
            ->where('nexi.session.surface.key', 'staff')
            ->where('nexi.session.page_pack.key', 'staff.workspace.home@1')
            ->where('nexi.session.availability', 'degraded')
            ->where('nexi.session.active_mode', 'inquiry')
            ->where('nexi.default_interaction', 'inquiry')
        );
});

test('setup hub receives setup readiness pack under staff surface', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nexi.session.surface.key', 'staff')
            ->where('nexi.session.page_pack.key', 'staff.setup.readiness@1')
            ->where('nexi.session.availability', 'degraded')
        );
});

test('applicant schedule receives applicant surface and slot skill pack', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant();
    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect();

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nexi.session.contract_version', '1')
            ->where('nexi.session.surface.key', 'applicant')
            ->where('nexi.session.page_pack.key', 'applicant.schedule@1')
            ->where('nexi.session.availability', 'assistant_available')
            ->where('nexi.session.modes', fn ($modes) => collect($modes)->contains('assistant'))
            ->where('nexi.session.active_mode', 'assistant')
        );
});

test('public and login shells omit authenticated nexi session', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('nexi', null));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('nexi', null));

    $this->get(route('applicant.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('nexi', null));
});

test('unregistered staff route keeps degraded dock without inheriting a page pack', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nexi.session.surface.key', 'staff')
            ->where('nexi.session.page_pack', null)
            ->where('nexi.session.availability', 'degraded')
            ->where('nexi.session.active_mode', 'inquiry')
            ->where('nexi.enabled', true)
        );
});

test('disclosure placeholders are versioned on the session view', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nexi.session.disclosure.key', 'nexi.foundation.disclosure')
            ->where('nexi.session.disclosure.version', 1)
            ->where('nexi.session.disclosure.text', fn ($text) => is_string($text) && str_contains($text, 'assistant'))
        );
});
