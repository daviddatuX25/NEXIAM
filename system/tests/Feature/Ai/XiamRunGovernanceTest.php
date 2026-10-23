<?php

use App\Admission\Support\ApplicantCapabilityGrants;
use App\Ai\Adapters\Fake\FakeAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Conversation\TurnSubmitter;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Orchestration\OutputFirewall;
use App\Ai\Orchestration\ProcessXiamRun;
use App\Ai\Orchestration\RunEventAppender;
use App\Ai\Policy\UsageBudgetService;
use App\Jobs\Ai\ProcessXiamRunJob;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamTaskGrant;
use App\Models\Ai\XiamTurn;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

function xiamRunTestGateway(callable $stream): AiGateway
{
    return new class(Closure::fromCallable($stream)) implements AiGateway
    {
        public int $streamCalls = 0;

        public bool $cancelRequested = false;

        public function __construct(private readonly Closure $stream) {}

        public function generate(array $request): array
        {
            return ['text' => '', 'finish_reason' => 'stop'];
        }

        public function stream(array $request): iterable
        {
            $this->streamCalls++;
            yield from ($this->stream)($request);
        }

        public function health(): array
        {
            return ['ok' => true];
        }

        public function requestCancel(string $runId): bool
        {
            $this->cancelRequested = true;

            return true;
        }
    };
}

beforeEach(function (): void {
    config([
        'xiam.emergency_stop' => false,
        'xiam.usage.limit' => 50,
        'xiam.process_inline' => true,
    ]);
});

test('fake ai gateway fails closed without fabricating output', function (): void {
    $gateway = new FakeAiGateway;
    $request = [
        'prompt' => 'Hello readiness check',
        'conversation_id' => (string) Str::uuid(),
        'run_id' => (string) Str::uuid(),
        'mode' => 'assistant',
    ];

    expect(fn () => $gateway->generate($request))
        ->toThrow(RuntimeException::class, 'provider_unavailable')
        ->and(fn () => iterator_to_array($gateway->stream($request)))
        ->toThrow(RuntimeException::class, 'provider_unavailable')
        ->and($gateway->health())->toMatchArray([
            'ok' => false,
            'degraded' => true,
            'reason' => 'provider_unavailable',
        ]);
});

test('run event cursor is gapless across runs in one conversation', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $conversation = app(ContextEpochService::class)->resolveOpenConversation(
        'staff',
        (string) $actor->id,
        $actor->actorScope()->institutionId,
        'staff',
        'staff.home',
        'cursor-test',
        'staff',
    );

    $runs = [];
    foreach ([1, 2] as $turnSeq) {
        $turn = XiamTurn::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'seq' => $turnSeq,
            'mode' => 'assistant',
            'input_text' => 'cursor '.$turnSeq,
        ]);
        $runs[] = XiamRun::query()->create([
            'id' => (string) Str::uuid(),
            'turn_id' => $turn->id,
            'conversation_id' => $conversation->id,
            'status' => XiamRun::STATUS_RUNNING,
            'gateway_route' => 'fake',
        ]);
    }

    $events = app(RunEventAppender::class);
    $events->append($runs[0], 'run.started', []);
    $events->append($runs[1], 'run.started', []);
    $events->append($runs[0], 'run.completed', []);

    expect(XiamRunEvent::query()
        ->where('conversation_id', $conversation->id)
        ->orderBy('seq')
        ->pluck('seq')
        ->all())->toBe([1, 2, 3]);
});

test('staff turn submit records unavailable provider without fabricating an answer', function (): void {
    seedE2Foundation();
    config(['xiam.ai.gateway' => 'fake']);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'What is setup readiness?',
            'idempotency_key' => 'test-turn-provider-unavailable',
            'page_pack_key' => null,
        ])
        ->assertAccepted()
        ->assertJsonStructure(['conversation_id', 'turn_id', 'run_id', 'context_epoch', 'cursor']);

    $run = XiamRun::query()->firstOrFail();
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();

    expect($run->status)->toBe(XiamRun::STATUS_FAILED)
        ->and($run->failure_reason)->toBe('provider_unavailable')
        ->and($events->where('type', 'assistant.message'))->toBeEmpty()
        ->and($events->where('type', 'assistant.refusal'))->toBeEmpty()
        ->and($events->where('type', 'assistant.delta'))->toBeEmpty();
});
test('provider exception after split fragments persists neither deltas nor generated message', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $gateway = xiamRunTestGateway(function (array $request): iterable {
        yield ['type' => 'delta', 'text' => 'credential:'];
        yield ['type' => 'delta', 'text' => '12345'];
        throw new RuntimeException('provider_timeout:fixture');
    });
    app()->instance(AiGateway::class, $gateway);

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'What is setup readiness?',
            'idempotency_key' => 'output-failure-after-fragments',
        ])
        ->assertAccepted();

    $run = XiamRun::query()->firstOrFail();
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $payloads = json_encode($events->pluck('payload')->all(), JSON_THROW_ON_ERROR);

    expect($gateway->streamCalls)->toBe(1)
        ->and($run->status)->toBe(XiamRun::STATUS_FAILED)
        ->and($run->failure_reason)->toBe('provider_timeout')
        ->and($events->where('type', 'assistant.delta'))->toBeEmpty()
        ->and($events->where('type', 'assistant.message'))->toBeEmpty()
        ->and($payloads)->not->toContain('credential:', '12345');
});

test('empty, truncated, and missing-terminal provider streams fail without publishing buffered text', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $gateway = xiamRunTestGateway(function (array $request): iterable {
        if ($request['prompt'] === 'missing terminal marker') {
            yield ['type' => 'delta', 'text' => 'buffered-without-terminal'];

            return;
        }

        if ($request['prompt'] === 'truncated response marker') {
            yield ['type' => 'delta', 'text' => 'truncated provider response'];
            yield ['type' => 'done', 'finish_reason' => 'length'];

            return;
        }

        yield ['type' => 'done', 'finish_reason' => 'stop'];
    });
    app()->instance(AiGateway::class, $gateway);

    $missingTerminal = $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'missing terminal marker',
            'idempotency_key' => 'output-missing-terminal',
        ])
        ->assertAccepted()
        ->json();
    $truncated = $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'truncated response marker',
            'idempotency_key' => 'output-truncated',
        ])
        ->assertAccepted()
        ->json();
    $empty = $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'empty response marker',
            'idempotency_key' => 'output-empty-response',
        ])
        ->assertAccepted()
        ->json();

    $missingRun = XiamRun::query()->findOrFail($missingTerminal['run_id']);
    $truncatedRun = XiamRun::query()->findOrFail($truncated['run_id']);
    $emptyRun = XiamRun::query()->findOrFail($empty['run_id']);
    $missingEvents = XiamRunEvent::query()->where('run_id', $missingRun->id)->get();
    $truncatedEvents = XiamRunEvent::query()->where('run_id', $truncatedRun->id)->get();
    $emptyEvents = XiamRunEvent::query()->where('run_id', $emptyRun->id)->get();

    expect($gateway->streamCalls)->toBe(3)
        ->and($missingRun->failure_reason)->toBe('incomplete_gateway_output')
        ->and($missingEvents->where('type', 'assistant.delta'))->toBeEmpty()
        ->and($missingEvents->where('type', 'assistant.message'))->toBeEmpty()
        ->and(json_encode($missingEvents->pluck('payload')->all(), JSON_THROW_ON_ERROR))->not->toContain('buffered-without-terminal')
        ->and($truncatedRun->failure_reason)->toBe('incomplete_gateway_output')
        ->and($truncatedEvents->where('type', 'assistant.delta'))->toBeEmpty()
        ->and($truncatedEvents->where('type', 'assistant.message'))->toBeEmpty()
        ->and(json_encode($truncatedEvents->pluck('payload')->all(), JSON_THROW_ON_ERROR))->not->toContain('truncated provider response')
        ->and($emptyRun->failure_reason)->toBe('empty_gateway_output')
        ->and($emptyEvents->where('type', 'assistant.delta'))->toBeEmpty()
        ->and($emptyEvents->where('type', 'assistant.message'))->toBeEmpty();
});

test('cancellation after streamed fragments suppresses all buffered content', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $gateway = xiamRunTestGateway(function (array $request): iterable {
        yield ['type' => 'delta', 'text' => 'private-fragment-one'];
        XiamRun::query()->whereKey($request['run_id'])->update(['cancel_requested_at' => now()]);
        yield ['type' => 'delta', 'text' => 'private-fragment-two'];
        yield ['type' => 'done', 'finish_reason' => 'stop'];
    });
    app()->instance(AiGateway::class, $gateway);

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'What is setup readiness?',
            'idempotency_key' => 'output-cancel-after-fragments',
        ])
        ->assertAccepted();

    $run = XiamRun::query()->firstOrFail();
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $payloads = json_encode($events->pluck('payload')->all(), JSON_THROW_ON_ERROR);

    expect($gateway->streamCalls)->toBe(1)
        ->and($gateway->cancelRequested)->toBeTrue()
        ->and($run->status)->toBe(XiamRun::STATUS_CANCELLED)
        ->and($events->where('type', 'assistant.delta'))->toBeEmpty()
        ->and($events->where('type', 'assistant.message'))->toBeEmpty()
        ->and($payloads)->not->toContain('private-fragment-one', 'private-fragment-two');
});

test('oversized provider output is refused without buffering or releasing its body', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $oversized = str_repeat('x', OutputFirewall::MAX_BUFFER_BYTES + 1);
    $gateway = xiamRunTestGateway(function (array $request) use ($oversized): iterable {
        yield ['type' => 'delta', 'text' => $oversized];
        yield ['type' => 'done', 'finish_reason' => 'stop'];
    });
    app()->instance(AiGateway::class, $gateway);

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'What is setup readiness?',
            'idempotency_key' => 'output-over-limit',
        ])
        ->assertAccepted();

    $run = XiamRun::query()->firstOrFail();
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $refusal = $events->where('type', 'assistant.refusal')->last();
    $message = $events->firstWhere('type', 'assistant.message');

    expect($gateway->cancelRequested)->toBeTrue()
        ->and($refusal?->payload['reason'] ?? null)->toBe('output_limit_exceeded')
        ->and($message?->payload['content'] ?? '')->toContain('cannot safely provide')
        ->and(json_encode($events->pluck('payload')->all(), JSON_THROW_ON_ERROR))->not->toContain(str_repeat('x', 100));
});

test('idempotent turn submit does not double charge usage', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $budgets = app(UsageBudgetService::class);

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Hello',
            'idempotency_key' => 'idem-abc',
        ])
        ->assertAccepted();

    $usedAfterFirst = $budgets->usedAsks('staff', (string) $guidance->id);

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Hello again ignored',
            'idempotency_key' => 'idem-abc',
        ])
        ->assertAccepted()
        ->assertJsonStructure(['conversation_id', 'turn_id', 'run_id', 'context_epoch', 'cursor']);

    expect($budgets->usedAsks('staff', (string) $guidance->id))->toBe($usedAfterFirst)
        ->and(XiamRun::query()->count())->toBe(1);
});

test('conversation snapshot is denied across actors', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $submit = $this->actingAs($guidance)->postJson(route('xiam.turns.store'), [
        'message' => 'Private staff note',
    ])->assertAccepted()->json();

    $this->actingAs($registrar)
        ->getJson(route('xiam.conversations.snapshot', $submit['conversation_id']))
        ->assertForbidden();

    $this->actingAs($guidance)
        ->getJson(route('xiam.conversations.snapshot', $submit['conversation_id']))
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
    config(['xiam.usage.limit' => 1]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), ['message' => 'First ask'])
        ->assertAccepted();

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), ['message' => 'Second ask'])
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
        ->postJson(route('xiam.emergency-stop'))
        ->assertOk()
        ->assertJsonPath('emergency_stop', true);

    $grant->refresh();
    expect($grant->status)->toBe(XiamTaskGrant::STATUS_REVOKED);

    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), ['message' => 'blocked'])
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

    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'assistant',
        'input_text' => 'cancel me',
    ]);

    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_QUEUED,
        'gateway_route' => 'fake',
    ]);

    expect(app(TurnSubmitter::class)->cancelRun($run->id, $guidance))->toBeTrue();
    $run->refresh();
    expect($run->cancel_requested_at)->not->toBeNull();
});

test('xiam session view reports durable usage after a turn', function (): void {
    seedE2Foundation();
    config(['xiam.ai.gateway' => 'laravel', 'xiam.process_inline' => true]);
    XiamAgent::fake(['Operational orientation ready.']);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), ['message' => 'Orient me'])
        ->assertAccepted();

    $this->actingAs($guidance)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('xiam.session.conversation.id', fn ($id) => is_string($id) && $id !== '')
            ->where('xiam.session.usage.used', 1)
        );
});

test('ai gateway default binding matches config', function (): void {
    config(['xiam.ai.gateway' => 'fake']);
    app()->forgetInstance(AiGateway::class);
    expect(app(AiGateway::class))->toBeInstanceOf(FakeAiGateway::class);
});

test('queued assistant run resolves actor and scope from stored run context without ambient http auth', function (): void {
    seedE2Foundation();
    config(['xiam.emergency_stop' => false, 'xiam.process_inline' => false, 'xiam.enabled' => true, 'xiam.ai.gateway' => 'laravel']);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-WORKER-CTX',
            'starts_at' => '2026-06-11 08:00:00',
            'effective_end_at' => '2026-06-11 11:00:00',
            'capacity' => 20,
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-WORKER-CTX')->firstOrFail();

    XiamAgent::fake([
        new ToolCall('call-queued-show', 'exams_session_show_1', ['session_id' => $session->id]),
        new ToolCall('call-queued-open', 'exams_session_reservation_open_1', [
            'session_id' => $session->id,
            'expected_version' => (int) $session->version,
        ]),
        'Reservation opened.',
    ]);

    $this->actingAs($guidance)->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();

    $start = $this->withHeader('X-Xiam-Browser-Session', 'worker-ctx-session')
        ->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $conversation = XiamConversation::query()->findOrFail($start['conversation_id']);
    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'assistant',
        'page_pack_key' => 'staff.exams.sessions.show@1',
        'page_pack_version' => 1,
        'input_text' => 'open reservation for this session',
    ]);
    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_QUEUED,
        'publication_version' => 'openspec3.v1',
        'gateway_route' => 'laravel',
    ]);
    app(UsageBudgetService::class)->reserve('staff', (string) $guidance->id, $guidance->actorScope()->institutionId, $run->id);

    // Clear ambient HTTP auth completely before running the queued worker job.
    Auth::logout();
    Auth::forgetUser();
    expect(Auth::user())->toBeNull();

    (new ProcessXiamRunJob($run->id))->handle(app(ProcessXiamRun::class));

    expect(Auth::user())->toBeNull()
        ->and($run->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED);

    $proposalEvent = XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'skill.proposal')
        ->first();

    $toolCalls = XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'tool.call')
        ->orderBy('seq')
        ->get()
        ->pluck('payload.tool')
        ->all();
    $toolResults = XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'tool.result')
        ->orderBy('seq')
        ->get();
    expect($toolCalls)->toBe(['exams.session.show@1', 'exams.session.reservation.open@1'])
        ->and($toolResults)->toHaveCount(2)
        ->and($toolResults[0]->payload['successful'])->toBeTrue()
        ->and($toolResults[1]->payload['successful'])->toBeTrue()
        ->and($proposalEvent)->not->toBeNull()
        ->and($proposalEvent->payload['capability_id'] ?? null)->toBe('exams.session.reservation.open@1');

    // If the actor is deactivated before a queued run executes, the worker fails closed and revokes grants.
    $turnRevoked = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => ((int) XiamTurn::query()->where('conversation_id', $conversation->id)->max('seq')) + 1,
        'mode' => 'assistant',
        'input_text' => 'show session status',
    ]);
    $runRevoked = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turnRevoked->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_QUEUED,
        'publication_version' => 'openspec3.v1',
        'gateway_route' => 'fake',
    ]);
    app(UsageBudgetService::class)->reserve('staff', (string) $guidance->id, $guidance->actorScope()->institutionId, $runRevoked->id);

    // Set an unrelated ambient user and deactivate the conversation actor.
    Auth::setUser($registrar);
    $guidance->staffProfile()->update(['status' => StaffProfile::STATUS_INACTIVE]);

    try {
        (new ProcessXiamRunJob($runRevoked->id))->handle(app(ProcessXiamRun::class));

        expect($runRevoked->fresh()->status)->toBe(XiamRun::STATUS_FAILED)
            ->and($runRevoked->fresh()->failure_reason)->toBe('actor_revoked')
            ->and(XiamTaskGrant::query()->findOrFail($start['grant_id'])->status)->toBe(XiamTaskGrant::STATUS_REVOKED);
    } finally {
        $guidance->staffProfile()->update(['status' => StaffProfile::STATUS_ACTIVE]);
        Auth::forgetUser();
    }
});

test('assistant gateway rejects handler-level denial as a typed denied result without ambient auth', function (): void {
    seedE2Foundation();

    $user = User::factory()->create(['email' => 'gw.noidentity@example.test']);
    ApplicantCapabilityGrants::sync($user);
    Auth::logout();
    Auth::forgetUser();

    $gateway = app(AssistantToolGateway::class);
    $result = $gateway->invoke(
        actor: $user,
        context: new InvocationContext(
            actorId: (string) $user->id,
            actorType: 'applicant',
            institutionId: 1,
            surfaceProfile: 'applicant',
            mode: 'assistant',
            contextEpoch: 'epoch-app-1',
            purpose: 'skill_tool',
            correlationId: 'corr-app-1',
        ),
        toolId: 'exams.slots.list@1',
        input: [],
    );

    expect($result->ok)->toBeFalse()
        ->and(in_array($result->reason, ['no_applicant_identity', 'envelope_denied:blocked'], true))->toBeTrue()
        ->and(Auth::user())->toBeNull();
});
