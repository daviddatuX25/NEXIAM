<?php

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Gateway\AssistantToolGateway;
use App\Application\Capabilities\CapabilityDefinitions;
use App\Application\Capabilities\CapabilityKind;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use Illuminate\Contracts\Auth\Authenticatable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('assistant tool catalog includes setup readiness read and rejects duplicates', function (): void {
    $catalog = new AssistantToolCatalog(CapabilityDefinitions::all());

    expect($catalog->get('setup.readiness.read', 1))->not->toBeNull()
        ->and($catalog->get('setup.readiness.read', 1)?->kind)->toBe(CapabilityKind::Read)
        ->and($catalog->get('setup.readiness.read', 1)?->id())->toBe('setup.readiness.read@1');

    expect(fn () => new AssistantToolCatalog([
        ...CapabilityDefinitions::all(),
        ...CapabilityDefinitions::all(),
    ]))->toThrow(RuntimeException::class);
});

test('gateway returns readiness parity with ComputeSetupReadinessQuery for authorized staff', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $queryResult = app(ComputeSetupReadinessQuery::class)->handle();
    $blockingCount = 0;
    foreach ($queryResult['groups'] as $group) {
        foreach ($group['categories'] as $category) {
            foreach ($category['checks'] as $check) {
                if (! $check['passed'] && ($check['severity'] ?? '') === 'critical') {
                    $blockingCount++;
                }
            }
        }
    }
    $expected = [
        ...$queryResult,
        'blocking_count' => $blockingCount,
        'ready' => $blockingCount === 0,
    ];

    $context = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: $guidance->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'readiness-test',
    );

    $result = app(AssistantToolGateway::class)->invoke(
        actor: $guidance,
        context: $context,
        toolId: 'setup.readiness.read@1',
        input: [],
    );

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toEqual($expected)
        ->and($result->meta['pack_used_for_authz'] ?? null)->toBeFalse();
});

test('gateway allows authorized staff with invocation context', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $context = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: $guidance->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'readiness-test-2',
    );

    $result = app(AssistantToolGateway::class)->invoke(
        actor: $guidance,
        context: $context,
        toolId: 'setup.readiness.read@1',
        input: [],
    );

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toHaveKeys(['overall_percent', 'passed', 'total', 'groups'])
        ->and($result->meta['pack_used_for_authz'] ?? null)->toBeFalse();
});

test('gateway denies staff without setup assessment or quota capability', function (): void {
    seedE2Foundation();
    $user = User::factory()->create(['email' => 'kernel.nosetup@nexiam.test']);

    $context = new InvocationContext(
        actorId: (string) $user->id,
        actorType: 'staff',
        institutionId: null,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'readiness-test-denied',
    );

    $result = app(AssistantToolGateway::class)->invoke(
        actor: $user,
        context: $context,
        toolId: 'setup.readiness.read@1',
        input: [],
    );

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('denied')
        ->and(in_array($result->reason, ['capability_missing', 'envelope_denied:blocked'], true))->toBeTrue();
});

test('gateway denies non-staff authenticatable principal for readiness', function (): void {
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

    $context = new InvocationContext(
        actorId: 'applicant-principal',
        actorType: 'applicant',
        institutionId: null,
        surfaceProfile: 'applicant',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'readiness-principal-denied',
    );

    $result = app(AssistantToolGateway::class)->invoke(
        actor: $actor,
        context: $context,
        toolId: 'setup.readiness.read@1',
        input: [],
    );

    expect($result->ok)->toBeFalse()->and($result->status)->toBe('denied');
});

test('gateway rejects unknown input fields', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $context = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: $guidance->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'readiness-schema-test',
    );

    $result = app(AssistantToolGateway::class)->invoke(
        actor: $guidance,
        context: $context,
        toolId: 'setup.readiness.read@1',
        input: ['authority' => 'forged'],
    );

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('invalid');
});

test('setup hub readiness loads through query', function (): void {
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
            ->where('readiness.groups.1.categories', fn ($categories): bool => collect($categories)
                ->contains(fn ($category): bool => $category['key'] === 'xiam_knowledge_governance'))
        );
});

test('knowledge mutations stay HTTP-only proposals and are not provider tools', function (): void {
    $byKey = collect(CapabilityDefinitions::all())->keyBy(fn ($definition) => $definition->key);

    expect($byKey->get('setup.readiness.read')?->kind)->toBe(CapabilityKind::Read)
        ->and($byKey->get('knowledge.sources.list')?->kind)->toBe(CapabilityKind::Read)
        ->and($byKey->get('knowledge.source.publish')?->kind)->toBe(CapabilityKind::Propose)
        ->and($byKey->get('knowledge.source.revoke')?->kind)->toBe(CapabilityKind::Propose)
        ->and($byKey->get('knowledge.source.publish')?->channels)->toBe(['http_page'])
        ->and($byKey->get('knowledge.source.revoke')?->channels)->toBe(['http_page']);
});
