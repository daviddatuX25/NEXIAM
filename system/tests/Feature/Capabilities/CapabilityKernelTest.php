<?php

use App\Application\Capabilities\CapabilityDefinitions;
use App\Application\Capabilities\CapabilityInvocationRequest;
use App\Application\Capabilities\CapabilityKernel;
use App\Application\Capabilities\CapabilityKind;
use App\Application\Capabilities\CapabilityRegistry;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use Illuminate\Contracts\Auth\Authenticatable;
use Inertia\Testing\AssertableInertia as Assert;

test('capability registry includes setup readiness read and rejects duplicates', function (): void {
    $registry = new CapabilityRegistry(CapabilityDefinitions::all());

    expect($registry->get('setup.readiness.read', 1))->not->toBeNull()
        ->and($registry->get('setup.readiness.read', 1)?->kind)->toBe(CapabilityKind::Read)
        ->and($registry->get('setup.readiness.read', 1)?->id())->toBe('setup.readiness.read@1');

    expect(fn () => new CapabilityRegistry([
        ...CapabilityDefinitions::all(),
        ...CapabilityDefinitions::all(),
    ]))->toThrow(RuntimeException::class);
});

test('kernel returns readiness parity with ComputeSetupReadinessQuery for authorized staff', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $expected = app(ComputeSetupReadinessQuery::class)->handle();

    $result = app(CapabilityKernel::class)->invoke(new CapabilityInvocationRequest(
        key: 'setup.readiness.read',
        version: 1,
        input: [],
        actor: $guidance,
        channel: 'http_page',
        surfaceKey: 'staff',
        pagePackKey: 'staff.setup.readiness@1',
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toEqual($expected)
        ->and($result->meta['pack_used_for_authz'] ?? null)->toBeFalse();
});

test('kernel allows authorized staff when page_pack is null (pack is not a cage)', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $result = app(CapabilityKernel::class)->invoke(new CapabilityInvocationRequest(
        key: 'setup.readiness.read',
        version: 1,
        input: [],
        actor: $guidance,
        channel: 'http_page',
        surfaceKey: 'staff',
        pagePackKey: null,
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toHaveKeys(['overall_percent', 'passed', 'total', 'categories'])
        ->and($result->meta['pack_used_for_authz'] ?? null)->toBeFalse();
});

test('kernel denies staff without setup assessment or quota capability', function (): void {
    seedE2Foundation();
    $user = User::factory()->create(['email' => 'kernel.nosetup@nexiam.test']);

    $result = app(CapabilityKernel::class)->invoke(new CapabilityInvocationRequest(
        key: 'setup.readiness.read',
        version: 1,
        input: [],
        actor: $user,
        channel: 'http_page',
        surfaceKey: 'staff',
        pagePackKey: 'staff.setup.readiness@1',
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('denied')
        ->and($result->reason)->toBe('capability_missing');
});

test('kernel denies non-staff authenticatable principal for readiness', function (): void {
    $actor = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'applicant-principal';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    $result = app(CapabilityKernel::class)->invoke(new CapabilityInvocationRequest(
        key: 'setup.readiness.read',
        version: 1,
        input: [],
        actor: $actor,
        channel: 'http_page',
        surfaceKey: 'applicant',
        pagePackKey: 'applicant.schedule@1',
    ));

    expect($result->ok)->toBeFalse()->and($result->status)->toBe('denied');
});

test('kernel rejects unknown input fields', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $result = app(CapabilityKernel::class)->invoke(new CapabilityInvocationRequest(
        key: 'setup.readiness.read',
        version: 1,
        input: ['authority' => 'forged'],
        actor: $guidance,
        channel: 'http_page',
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('invalid')
        ->and($result->reason)->toStartWith('unknown_field');
});

test('setup hub readiness loads through capability kernel', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $expected = app(ComputeSetupReadinessQuery::class)->handle();

    $this->actingAs($registrar)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('readiness.overall_percent', $expected['overall_percent'])
            ->where('readiness.passed', $expected['passed'])
            ->where('readiness.total', $expected['total'])
            ->has('readiness.categories', 8)
        );
});

test('registry exposes read and knowledge command kinds after openspec 4', function (): void {
    $byKey = collect(CapabilityDefinitions::all())->keyBy(fn ($d) => $d->key);

    expect($byKey->get('setup.readiness.read')?->kind)->toBe(CapabilityKind::Read)
        ->and($byKey->get('knowledge.sources.list')?->kind)->toBe(CapabilityKind::Read)
        ->and($byKey->get('knowledge.source.publish')?->kind)->toBe(CapabilityKind::Command)
        ->and($byKey->get('knowledge.source.revoke')?->kind)->toBe(CapabilityKind::Command);

    foreach (CapabilityDefinitions::all() as $definition) {
        expect($definition->kind)->toBeIn([
            CapabilityKind::Read,
            CapabilityKind::Propose,
            CapabilityKind::Command,
        ]);
    }
});
