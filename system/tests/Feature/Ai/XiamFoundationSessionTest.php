<?php

use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Policy\EffectiveXiamAvailabilityResolver;
use App\Ai\Surface\SurfaceProfileCatalog;
use App\Ai\Surface\SurfaceProfileKey;
use App\Models\Ai\XiamTurn;
use App\Models\Ai\XiamUsageReservation;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('availability resolver fails closed without pack and degrades without provider', function (): void {
    $resolver = new EffectiveXiamAvailabilityResolver;
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
            ->where('xiam.enabled', true)
            ->where('xiam.session.contract_version', '2')
            ->where('xiam.session.surface.key', 'staff')
            ->where('xiam.session.page_pack.key', 'staff.workspace.home@1')
            ->where('xiam.session.availability', 'degraded')
            ->where('xiam.session.context_epoch', fn ($epoch) => $epoch === null || (is_string($epoch) && $epoch !== ''))
            ->where('xiam.session.usage.state', 'available')
            ->has('xiam.session.proposals')
        );
});

test('setup hub receives setup readiness pack under staff surface', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('xiam.session.contract_version', '2')
            ->where('xiam.session.surface.key', 'staff')
            ->where('xiam.session.page_pack.key', 'staff.setup.readiness@1')
            ->where('xiam.session.availability', 'degraded')
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
            ->where('xiam.session.contract_version', '2')
            ->where('xiam.session.surface.key', 'applicant')
            ->where('xiam.session.page_pack.key', 'applicant.schedule@1')
            ->where('xiam.session.availability', 'assistant_available')
            ->where('xiam.session.context_epoch', fn ($epoch) => $epoch === null || (is_string($epoch) && $epoch !== ''))
            ->where('xiam.session.usage.state', 'available')
            ->has('xiam.session.proposals')
        );
});

test('public and login shells omit authenticated xiam session', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('xiam', null));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('xiam', null));

    $this->get(route('applicant.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('xiam', null));
});

test('unregistered staff route keeps degraded dock without inheriting a page pack', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('xiam.session.surface.key', 'staff')
            ->where('xiam.session.page_pack', null)
            ->where('xiam.session.availability', 'degraded')
            ->where('xiam.session.contract_version', '2')
            ->where('xiam.enabled', true)
        );
});

test('an exhausted applicant sees the enforced daily allowance on the page and rejection without losing ordinary access', function (): void {
    seedE2Foundation();
    config([
        'xiam.usage.limit' => null,
        'xiam.usage.applicant_limit' => 15,
        'xiam.usage.staff_limit' => 200,
        'xiam.usage.period' => 'today',
    ]);

    $result = submitSyntheticApplicant();
    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect();
    $actor = auth()->user();

    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'applicant',
        'actor_id' => (string) $actor->id,
        'institution_id' => null,
        'run_id' => null,
        'unit' => 'asks',
        'amount' => 15,
        'state' => XiamUsageReservation::STATE_SETTLED,
    ]);

    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('xiam.session.availability', 'quota_exhausted')
            ->where('xiam.session.usage.used', 15)
            ->where('xiam.session.usage.limit', 15)
            ->where('xiam.session.usage.remaining', 0)
            ->where('xiam.session.usage.state', 'exhausted')
            ->where('xiam.session.usage.period', 'today')
            ->where('xiam.session.usage.resets_at', today()->addDay()->toIso8601String())
        );

    $this->postJson(route('xiam.turns.store'), [
        'message' => 'What should I do next?',
        'idempotency_key' => 'exhausted-applicant-allowance',
    ])->assertStatus(429)
        ->assertJsonPath('error', 'quota_exhausted')
        ->assertJsonPath('usage.used', 15)
        ->assertJsonPath('usage.limit', 15)
        ->assertJsonPath('usage.remaining', 0)
        ->assertJsonPath('usage.state', 'exhausted');

    $this->get(route('applicant.schedule'))->assertOk();
    expect(XiamTurn::query()->count())->toBe(0);
});
