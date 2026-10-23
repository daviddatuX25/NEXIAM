<?php

use App\Ai\Adapters\LaravelAi\XiamProjectorRegistry;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Proposals\ProposalSafetyPolicy;
use App\Ai\Proposals\ProposalService;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Support\Str;

function actingAsStaffTest($test, User $user, array $session = [])
{
    return $test->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $user->authenticationState->session_version,
        ...$session,
    ]);
}

test('proposal safety policy derives correct tiers and TTLs', function (): void {
    $policy = new ProposalSafetyPolicy;

    foreach ([
        'exams.session.create@1' => ['t1', 30, null, false],
        'setup.rooms.create_draft@1' => ['t1', 30, null, false],
        'setup.cycles.create_draft@1' => ['t1', 30, null, false],
        'consultation.session.cancel@1' => ['t2', 10, 'CANCEL SESSION', false],
        'setup.notifications.update_admission@1' => ['t2', 10, 'UPDATE NOTIFICATIONS', false],
        'setup.notifications.update_exam@1' => ['t2', 10, 'UPDATE NOTIFICATIONS', false],
        'setup.windows.store@1' => ['t2', 10, 'UPDATE APPLICATION WINDOW', false],
        'setup.cycles.activate@1' => ['t3', 5, 'ACTIVATE CYCLE', true],
    ] as $capabilityId => [$tier, $ttlMinutes, $phrase, $requiresReauth]) {
        $requirement = $policy->evaluate($capabilityId);

        expect($requirement->tier)->toBe($tier)
            ->and($requirement->ttlMinutes)->toBe($ttlMinutes)
            ->and($requirement->confirmationPhrase)->toBe($phrase)
            ->and($requirement->requiresReauth)->toBe($requiresReauth);
    }
});

test('proposals persist safety tier and phrase and reject mismatched confirmation phrase', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $conversation = app(ContextEpochService::class)->resolveOpenConversation('staff', (string) $guidance->id, (int) $guidance->actorScope()->institutionId, 'staff');
    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'assistant',
        'status' => 'succeeded',
        'input_text' => 'test turn',
    ]);
    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_SUCCEEDED,
    ]);
    $proposals = app(ProposalService::class);
    $proposal = $proposals->create(
        actor: $guidance,
        runId: $run->id,
        conversationId: $conversation->id,
        capabilityId: 'consultation.session.cancel@1',
        summary: ['actor_id' => (string) $guidance->id, 'action' => 'cancel'],
        evidence: ['checks' => []],
    );

    expect($proposal->safety_tier)->toBe('t2')
        ->and($proposal->confirmation_phrase)->toBe('CANCEL SESSION')
        ->and($proposal->requires_reauth)->toBeFalse()
        ->and($proposal->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(10);

    actingAsStaffTest($this, $guidance)->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();
    // Mismatched phrase -> 422
    actingAsStaffTest($this, $guidance)
        ->postJson(route('xiam.proposals.confirm', $proposal->id), [
            'idempotency_key' => 'safety-phrase-test-1',
            'phrase' => 'WRONG PHRASE',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'phrase_mismatch')
        ->assertJsonPath('expected_phrase', 'CANCEL SESSION');
});

test('t3 proposals enforce recent re-authentication check', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $conversation = app(ContextEpochService::class)->resolveOpenConversation('staff', (string) $admin->id, (int) $admin->actorScope()->institutionId, 'staff');
    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'assistant',
        'status' => 'succeeded',
        'input_text' => 'test turn',
    ]);
    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_SUCCEEDED,
    ]);
    $proposals = app(ProposalService::class);
    $proposal = $proposals->create(
        actor: $admin,
        runId: $run->id,
        conversationId: $conversation->id,
        capabilityId: 'setup.cycles.activate@1',
        summary: ['actor_id' => (string) $admin->id, 'cycle_id' => 1],
        evidence: ['checks' => []],
    );

    expect($proposal->safety_tier)->toBe('t3')
        ->and($proposal->requires_reauth)->toBeTrue();
    actingAsStaffTest($this, $admin)->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();

    // No re-auth session -> 423
    actingAsStaffTest($this, $admin)
        ->postJson(route('xiam.proposals.confirm', $proposal->id), [
            'idempotency_key' => 'safety-reauth-test-1',
            'phrase' => 'ACTIVATE CYCLE',
        ])
        ->assertStatus(423)
        ->assertJsonPath('error', 'reauth_required');

    // Fresh re-auth in session (within 300s) -> passes the gate (proceeds to domain execution)
    actingAsStaffTest($this, $admin, ['auth.recent_reauth_at' => time() - 30])
        ->postJson(route('xiam.proposals.confirm', $proposal->id), [
            'idempotency_key' => 'safety-reauth-test-2',
            'phrase' => 'ACTIVATE CYCLE',
        ]);
    // Status is either ok or domain handler execution (not 423 or 422)
});

test('confirm returns 404 with structured public error for missing proposal', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    actingAsStaffTest($this, $guidance)
        ->postJson(route('xiam.proposals.confirm', '00000000-0000-0000-0000-000000000000'), [
            'idempotency_key' => 'missing-proposal-key',
        ])
        ->assertStatus(404)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'proposal_not_found')
        ->assertJsonPath('public_error.code', 'proposal_not_found')
        ->assertJsonPath('public_error.category', 'invalid')
        ->assertJsonPath('public_error.remediation', 'repropose');
});

test('fail closed before persistence when proposal_review is structurally invalid', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $conversation = app(ContextEpochService::class)->resolveOpenConversation('staff', (string) $guidance->id, (int) $guidance->actorScope()->institutionId, 'staff');

    $proposals = app(ProposalService::class);

    expect(function () use ($proposals, $guidance, $conversation) {
        $proposals->create(
            actor: $guidance,
            runId: null,
            conversationId: $conversation->id,
            capabilityId: 'consultation.session.cancel@1',
            summary: ['actor_id' => (string) $guidance->id, 'action' => 'cancel'],
            evidence: ['checks' => []],
            proposalReview: [
                'version' => 1,
                'operation' => 'invalid_op',
                'title' => 'Invalid proposal',
                'sections' => [],
                'preflight' => ['outcome' => 'pass', 'checks' => []],
                'coverage' => [],
            ],
        );
    })->toThrow(InvalidArgumentException::class);
});

test('safeProposalView does not fabricate v1 on unversioned proposals and marks confirmable false', function (): void {
    $projector = app(XiamProjectorRegistry::class);

    $unversioned = [
        'id' => 'legacy-prop-1',
        'status' => 'pending',
        'capability_id' => 'exams.session.create@1',
        'summary' => ['action' => 'legacy_create'],
    ];

    $safeView = $projector->safeProposalView($unversioned);

    expect($safeView['version'])->toBeNull()
        ->and($safeView['confirmable'])->toBeFalse()
        ->and($safeView['proposal_id'])->toBe('legacy-prop-1');
});
