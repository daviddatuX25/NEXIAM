<?php

use App\Ai\Adapters\Fake\FakeAiGateway;
use App\Ai\Contracts\AiGateway;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Conversation\TurnSubmitter;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\UsageBudgetService;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiRunEvent;
use App\Models\Ai\NexiTaskGrant;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['nexi.emergency_stop' => false, 'nexi.usage.limit' => 50]);
});

test('fake ai gateway streams fragmented deltas and done', function (): void {
    $gateway = new FakeAiGateway;
    $chunks = iterator_to_array($gateway->stream([
        'prompt' => 'Hello readiness check',
        'conversation_id' => (string) Str::uuid(),
        'run_id' => (string) Str::uuid(),
        'mode' => 'inquiry',
    ]));

    expect($chunks)->not->toBeEmpty()
        ->and(collect($chunks)->pluck('type')->all())->toContain('delta', 'done');
});

test('staff turn submit persists run events via sync fake gateway', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), [
            'message' => 'What is setup readiness?',
            'idempotency_key' => 'test-turn-1',
            'page_pack_key' => null,
        ])
        ->assertAccepted()
        ->assertJsonStructure(['conversation_id', 'turn_id', 'run_id', 'context_epoch']);

    $run = NexiRun::query()->first();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(NexiRun::STATUS_SUCCEEDED);

    expect(NexiRunEvent::query()->where('run_id', $run->id)->where('type', 'assistant.message')->exists())->toBeTrue();
});

test('idempotent turn submit does not double charge usage', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $budgets = app(UsageBudgetService::class);

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), [
            'message' => 'Hello',
            'idempotency_key' => 'idem-abc',
        ])
        ->assertAccepted();

    $usedAfterFirst = $budgets->usedAsks('staff', (string) $guidance->id);

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), [
            'message' => 'Hello again ignored',
            'idempotency_key' => 'idem-abc',
        ])
        ->assertAccepted();

    expect($budgets->usedAsks('staff', (string) $guidance->id))->toBe($usedAfterFirst)
        ->and(NexiRun::query()->count())->toBe(1);
});

test('conversation snapshot is denied across actors', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $submit = $this->actingAs($guidance)->postJson(route('nexi.turns.store'), [
        'message' => 'Private staff note',
    ])->assertAccepted()->json();

    $this->actingAs($registrar)
        ->getJson(route('nexi.conversations.snapshot', $submit['conversation_id']))
        ->assertForbidden();

    $this->actingAs($guidance)
        ->getJson(route('nexi.conversations.snapshot', $submit['conversation_id']))
        ->assertOk()
        ->assertJsonPath('conversation_id', $submit['conversation_id']);
});

test('history survives pack-null navigation partition (same staff surface)', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $epochs = app(ContextEpochService::class);
    $first = $epochs->resolveOpenConversation('staff', (string) $guidance->id, $guidance->actorScope()->institutionId, 'staff');
    $second = $epochs->resolveOpenConversation('staff', (string) $guidance->id, $guidance->actorScope()->institutionId, 'staff');

    expect($first->id)->toBe($second->id)
        ->and($first->context_epoch)->toBe($second->context_epoch);
});

test('quota exhausted denies new turns', function (): void {
    seedE2Foundation();
    config(['nexi.usage.limit' => 1]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), ['message' => 'First ask'])
        ->assertAccepted();

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), ['message' => 'Second ask'])
        ->assertStatus(429)
        ->assertJsonPath('error', 'quota_exhausted');
});

test('task grant revoke on emergency stop', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $grants = app(TaskGrantService::class);

    $grant = $grants->issue(
        actorType: 'staff',
        actorId: (string) $guidance->id,
        sessionId: 'sess-1',
        institutionId: $guidance->actorScope()->institutionId,
        surfaceKey: 'staff',
        skillKey: 'fixture.orient',
        skillVersion: 1,
        allowedCapabilityIds: ['setup.readiness.read@1'],
        contextEpoch: (string) Str::uuid(),
        originPackKey: null,
    );

    expect($grants->usableAfterNavigation($grant, 'staff'))->toBeTrue()
        ->and($grants->usableAfterNavigation($grant, 'applicant'))->toBeFalse();

    $this->actingAs($guidance)
        ->postJson(route('nexi.emergency-stop'))
        ->assertOk()
        ->assertJsonPath('emergency_stop', true);

    $grant->refresh();
    expect($grant->status)->toBe(NexiTaskGrant::STATUS_REVOKED);

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), ['message' => 'blocked'])
        ->assertForbidden()
        ->assertJsonPath('error', 'emergency_stop');
});

test('cancel marks run cancelled when requested before completion in controlled processor', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    // Bypass sync completion by creating queued run manually and cancelling.
    $conversation = app(ContextEpochService::class)->resolveOpenConversation(
        'staff',
        (string) $guidance->id,
        $guidance->actorScope()->institutionId,
        'staff',
    );

    $turn = \App\Models\Ai\NexiTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'inquiry',
        'input_text' => 'cancel me',
    ]);

    $run = NexiRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => NexiRun::STATUS_QUEUED,
        'gateway_route' => 'fake',
    ]);

    expect(app(TurnSubmitter::class)->cancelRun($run->id, $guidance))->toBeTrue();
    $run->refresh();
    expect($run->cancel_requested_at)->not->toBeNull();
});

test('nexi session view reports durable usage after a turn', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), ['message' => 'Orient me'])
        ->assertAccepted();

    $this->actingAs($guidance)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('nexi.session.conversation.id', fn ($id) => is_string($id) && $id !== '')
            ->where('nexi.session.usage.used', 1)
        );
});

test('ai gateway is bound to fake implementation', function (): void {
    expect(app(AiGateway::class))->toBeInstanceOf(FakeAiGateway::class);
});
