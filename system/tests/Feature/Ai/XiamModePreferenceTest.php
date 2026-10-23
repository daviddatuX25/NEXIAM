<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Ai\Context\InvocationContext;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Gateway\ToolPolicyGate;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Policy\XiamModePreference;
use App\Ai\Proposals\ProposalService;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withCredentials();
    config([
        'xiam.enabled' => true,
        'xiam.mode' => 'degraded',
        'xiam.emergency_stop' => false,
        'xiam.process_inline' => true,
        'xiam.ai.gateway' => 'fake',
    ]);
});

test('mode API acknowledges preferences independently for applicant and staff sessions', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($staff)
        ->getJson(route('xiam.mode.show'))
        ->assertOk()
        ->assertHeaderContains('Cache-Control', 'private')->assertHeaderContains('Cache-Control', 'no-store')
        ->assertJsonPath('active_mode', 'inquiry')
        ->assertJsonPath('interaction_modes.0.key', 'inquiry')
        ->assertJsonPath('interaction_modes.1.key', 'assistant')
        ->assertJsonStructure(['usage' => ['state', 'used', 'limit', 'remaining', 'credit_balance', 'unit', 'period', 'resets_at']]);
    $staleSession = session();
    $staleSessionAttributes = $staleSession->all();
    $sessionId = $staleSession->getId();
    $this->withCookie(config('session.cookie'), $sessionId);

    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => null,
    ])->assertOk()
        ->assertHeaderContains('Cache-Control', 'private')->assertHeaderContains('Cache-Control', 'no-store')
        ->assertJsonPath('active_mode', 'assistant');
    $this->withCookie(config('session.cookie'), $sessionId);
    $staleSession->replace($staleSessionAttributes);
    $staleSession->save();
    $this->withCookie(config('session.cookie'), $sessionId)
        ->getJson(route('xiam.mode.show'))
        ->assertOk()
        ->assertJsonPath('active_mode', 'assistant');

    $applicant = submitSyntheticApplicant('mode-preference@example.test', 'ModeApplicant');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicant['access_token'],
        '2001-01-01',
    );

    $this->actingAs($applicantUser)
        ->getJson(route('xiam.mode.show'))
        ->assertOk()
        ->assertJsonPath('active_mode', 'inquiry')
        ->assertJsonPath('interaction_modes.0.key', 'inquiry')
        ->assertJsonPath('interaction_modes.1.key', 'assistant');

    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => null,
    ])->assertOk()->assertJsonPath('active_mode', 'assistant');
    xiamKeepCurrentSessionCookie();

    config(['xiam.applicant_self_booking_enabled' => false]);
    $this->getJson(route('xiam.mode.show'))
        ->assertOk()
        ->assertJsonPath('active_mode', 'inquiry')
        ->assertJsonCount(1, 'interaction_modes');
    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => null,
    ])->assertForbidden()
        ->assertHeaderContains('Cache-Control', 'private')->assertHeaderContains('Cache-Control', 'no-store')
        ->assertJsonPath('error', 'mode_unavailable');
    config(['xiam.applicant_self_booking_enabled' => true]);

    $this->actingAs($staff)
        ->getJson(route('xiam.mode.show'))
        ->assertOk()
        ->assertJsonPath('active_mode', 'assistant');
    expect(XiamTurn::query()->count())->toBe(0)
        ->and(XiamRun::query()->count())->toBe(0);

});

test('forged request mode cannot elevate a turn and inquiry keeps authorized reads only', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $response = $this->actingAs($staff)->postJson(route('xiam.turns.store'), [
        'message' => 'Show today’s exam sessions.',
        'idempotency_key' => 'forged-assistant-mode',
        'page_pack_key' => 'staff.exams.sessions.index@1',
        'page_pack_version' => 1,
        'mode' => 'assistant',
    ])->assertAccepted();

    $turn = XiamTurn::query()->findOrFail($response->json('turn_id'));
    expect($turn->mode)->toBe('inquiry')
        ->and($response->json('usage'))->toHaveKeys([
            'state', 'used', 'limit', 'remaining', 'credit_balance', 'unit', 'period', 'resets_at',
        ])
        ->and(XiamProposal::query()->count())->toBe(0);

    $context = new InvocationContext(
        actorId: (string) $staff->id,
        actorType: 'staff',
        institutionId: $staff->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'inquiry',
        contextEpoch: (string) ($response->json('context_epoch') ?: 'forged-mode-test-epoch'),
        purpose: 'policy_inquiry',
        correlationId: 'inquiry-gateway-test',
    );

    $read = app(AssistantToolGateway::class)->invoke(
        actor: $staff,
        context: $context,
        toolId: 'exams.sessions.today@1',
        input: [],
    );
    $proposal = app(AssistantToolGateway::class)->invoke(
        actor: $staff,
        context: $context,
        toolId: 'exams.session.reservation.open@1',
        input: [],
    );

    expect(app(ToolPolicyGate::class)->resolveEnvelope($context, $staff))
        ->toBe(ToolPolicyGate::ENVELOPE_READ_ALLOWED)
        ->and($read->ok)->toBeTrue()
        ->and($proposal->ok)->toBeFalse()
        ->and($proposal->reason)->toBe('envelope_denied:read_allowed')
        ->and(XiamProposal::query()->count())->toBe(0);
});

test('inquiry demotion invalidates pending work even from another session', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($staff);
    $epochs = app(ContextEpochService::class);
    $proposals = app(ProposalService::class);
    $conversation = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $staff->id,
        institutionId: $staff->actorScope()->institutionId,
        surfaceKey: 'staff',
        routeName: 'staff.home',
        browserSessionId: 'prior-assistant-session',
        surfaceProfileId: 'staff',
    );
    $pendingBeforePreference = $proposals->create(
        actor: $staff,
        runId: null,
        conversationId: $conversation->id,
        capabilityId: 'exams.session.create@1',
        summary: ['_command_input' => ['code' => 'EX-MODE-PENDING-DEFAULT']],
        evidence: [],
    );

    $this->postJson(route('xiam.proposals.confirm', $pendingBeforePreference->id), [
        'idempotency_key' => 'inquiry-before-first-turn',
        'mode' => 'assistant',
    ])->assertForbidden()->assertJsonPath('error', 'mode_inquiry_read_only');

    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => (string) $conversation->context_epoch,
    ])->assertOk()->assertJsonPath('active_mode', 'assistant');
    xiamKeepCurrentSessionCookie();

    $pending = $proposals->create(
        actor: $staff,
        runId: null,
        conversationId: $conversation->id,
        capabilityId: 'exams.session.create@1',
        summary: ['_command_input' => ['code' => 'EX-MODE-PENDING']],
        evidence: [],
    );

    $demoted = $this->postJson(route('xiam.mode.update'), [
        'mode' => 'inquiry',
        'context_epoch' => (string) $conversation->context_epoch,
    ])->assertOk()->assertJsonPath('active_mode', 'inquiry');
    xiamKeepCurrentSessionCookie();

    expect((string) $demoted->json('context_epoch'))->not->toBe((string) $conversation->context_epoch)
        ->and($pendingBeforePreference->fresh()->status)->toBe(ProposalService::STATUS_INVALIDATED)
        ->and($pending->fresh()->status)->toBe(ProposalService::STATUS_INVALIDATED);

    $this->postJson(route('xiam.proposals.confirm', $pending->id), [
        'idempotency_key' => 'inquiry-old-proposal',
        'mode' => 'assistant',
    ])->assertForbidden()->assertJsonPath('error', 'mode_inquiry_read_only');

});

test('busy and stale mode updates never acknowledge assistant', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($staff)->getJson(route('xiam.mode.show'))->assertOk();
    xiamKeepCurrentSessionCookie();

    $conversation = app(ContextEpochService::class)->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $staff->id,
        institutionId: $staff->actorScope()->institutionId,
        surfaceKey: 'staff',
        routeName: 'staff.home',
        browserSessionId: session()->getId(),
        surfaceProfileId: 'staff',
    );
    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'inquiry',
        'input_text' => 'A queued read request',
    ]);
    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_QUEUED,
        'publication_version' => 'mode-test.v1',
        'gateway_route' => 'fake',
    ]);

    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => (string) $conversation->context_epoch,
    ])->assertStatus(409)->assertJsonPath('error', 'mode_change_busy');

    $run->update(['status' => XiamRun::STATUS_SUCCEEDED]);

    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => '00000000-0000-4000-8000-000000000000',
    ])->assertStatus(409)->assertJsonPath('error', 'context_epoch_conflict');

    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => (string) $conversation->context_epoch,
    ])->assertOk()->assertJsonPath('active_mode', 'assistant');
});

test('mode switch does not acknowledge when preference cache write fails', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $route = app('router')->getRoutes()->getByName('xiam.mode.update');
    $request = Request::create(route('xiam.mode.update', [], false), 'POST');
    $request->setRouteResolver(static fn () => $route);
    $request->setUserResolver(static fn () => $staff);
    $request->setLaravelSession(session()->driver());

    Cache::shouldReceive('get')->twice()->andReturn(null);
    Cache::shouldReceive('put')->once()->andReturn(false);

    $preference = app(XiamModePreference::class);
    expect(fn () => $preference->change($request, 'assistant', null, false))
        ->toThrow(RuntimeException::class, 'mode_session_unavailable');
    expect($preference->view($request)['active_mode'])->toBe('inquiry');
});

test('page keeps ordinary staff work available when cached mode cannot be read', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $expectedUsage = app(UsageBudgetService::class)->view('staff', (string) $staff->id);

    $this->actingAs($staff)->getJson(route('xiam.mode.show'))->assertOk();
    xiamKeepCurrentSessionCookie();
    $sessionId = session()->getId();
    $cacheKey = 'xiam:mode_preference:'.hash('sha256', implode("\0", [
        'staff',
        (string) $staff->id,
        'staff',
        $sessionId,
    ]));

    Cache::partialMock()
        ->shouldReceive('get')
        ->once()
        ->with($cacheKey)
        ->andThrow(new RuntimeException('backend cache detail must not be shared'));

    $this->withCookie(config('session.cookie'), $sessionId)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('xiam.session.availability', 'suspended')
            ->where('xiam.session.active_mode', 'inquiry')
            ->where('xiam.session.interaction_modes', [])
            ->where('xiam.session.usage', $expectedUsage)
            ->where('xiam.session', fn (Collection $session): bool => ! str_contains(
                json_encode($session, JSON_THROW_ON_ERROR),
                'backend cache detail must not be shared',
            ))
        );
});
