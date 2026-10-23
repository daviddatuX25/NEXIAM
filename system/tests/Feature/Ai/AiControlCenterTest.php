<?php

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\EmergencyStop;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamTaskGrant;
use App\Models\Ai\XiamTurn;
use App\Models\Ai\XiamUsageReservation;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['xiam.emergency_stop' => false]);
});

test('ai control center denies unauthenticated users', function (): void {
    $this->get(route('setup.ai-center'))
        ->assertRedirect(route('login'));
});

test('ai control center denies users without AI governance capabilities', function (): void {
    seedE2Foundation();
    $registrarClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrarClerk)
        ->get(route('setup.ai-center'))
        ->assertForbidden();
});

test('guidance counselor can view ai control center telemetry and proposal ledger', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institutionId = (int) $guidance->actorScope()->institutionId;

    // Seed sample usage reservations
    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => (string) $guidance->id,
        'institution_id' => $institutionId,
        'run_id' => (string) Str::uuid(),
        'unit' => 'asks',
        'amount' => 5,
        'state' => XiamUsageReservation::STATE_SETTLED,
        'office' => 'guidance',
    ]);

    $conversation = XiamConversation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => (string) $guidance->id,
        'institution_id' => $institutionId,
        'surface_key' => 'staff',
        'partition_key' => 'staff:'.(string) $guidance->id.':staff',
        'context_epoch' => (string) Str::uuid(),
        'epoch_version' => 1,
        'status' => 'open',
    ]);

    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'assistant',
        'input_text' => 'open reservation',
    ]);

    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => 'succeeded',
        'publication_version' => 'xiam.v1',
    ]);

    XiamProposal::query()->create([
        'id' => (string) Str::uuid(),
        'run_id' => $run->id,
        'conversation_id' => $conversation->id,
        'status' => 'pending',
        'capability_id' => 'exams.session.reservation.open@1',
        'execution_id' => (string) Str::uuid(),
        'canonical_digest' => 'sha256-sample-digest',
        'summary' => ['action' => 'open_reservation'],
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($guidance)
        ->get(route('setup.ai-center'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/AiCenter')
            ->where('usage.limit', (int) config('xiam.usage.staff_limit', 200))
            ->where('usage.total_used', fn ($used) => $used >= 5)
            ->where('usage.breakdown.guidance.asks', fn ($asks) => $asks >= 5)
            ->where('model_info.emergency_stop', false)
            ->has('proposals', 1)
        );
});

test('institutional AI governor can view the full AI control center umbrella', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('setup.ai-center'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/AiCenter')
            ->where('switchboard', fn ($items) => collect($items)->contains('governance_scope', 'guidance'))
            ->where('switchboard', fn ($items) => collect($items)->contains('governance_scope', 'registrar'))
            ->where('switchboard', fn ($items) => collect($items)->contains('governance_scope', 'applicant_central'))
            ->has('model_info')
        );
});

test('institutional AI governor can trip and disarm emergency stop while office governor is denied', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $grants = app(TaskGrantService::class);
    $stop = app(EmergencyStop::class);

    expect($stop->active())->toBeFalse();

    $grant = $grants->issue(
        actorType: 'staff',
        actorId: (string) $guidance->id,
        sessionId: null,
        institutionId: (int) $guidance->actorScope()->institutionId,
        surfaceKey: 'staff',
        skillKey: 'schedule.exam_session@1',
        skillVersion: 1,
        allowedCapabilityIds: ['exams.session.reservation.open@1'],
        contextEpoch: 'sample-epoch-toggle',
    );
    expect($grant->fresh()->status)->toBe(XiamTaskGrant::STATUS_ACTIVE);

    $this->actingAs($guidance)
        ->post(route('setup.ai-center.emergency-stop'))
        ->assertForbidden();
    expect($stop->active())->toBeFalse()
        ->and($grant->fresh()->status)->toBe(XiamTaskGrant::STATUS_ACTIVE);

    $this->actingAs($admin)
        ->post(route('setup.ai-center.emergency-stop'))
        ->assertRedirect();

    expect($stop->active())->toBeTrue()
        ->and($grant->fresh()->status)->toBe(XiamTaskGrant::STATUS_REVOKED)
        ->and($grant->fresh()->revoke_reason)->toBe('emergency_stop');

    $this->actingAs($admin)
        ->post(route('setup.ai-center.emergency-stop'))
        ->assertRedirect();

    expect($stop->active())->toBeFalse();
});
test('authorized staff can view friendly office switchboard on ai control center', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.ai-center'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/AiCenter')
            ->has('switchboard')
            ->where('switchboard', fn ($items) => collect($items)->contains('title', 'List Exam Sessions'))
            ->where('switchboard', fn ($items) => collect($items)->contains('governance_scope', 'guidance'))
        );
});

test('office governor can toggle Guidance tools but cannot toggle Registrar or Applicant tools', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institutionId = (int) $guidance->actorScope()->institutionId;
    $policy = XiamInstitutionPolicy::forInstitution($institutionId);

    expect($policy->isToolEnabled('exams.session.publish@1'))->toBeTrue();

    $this->actingAs($guidance)
        ->post(route('setup.ai-center.tools.toggle'), [
            'tool_id' => 'exams.session.publish@1',
            'enabled' => false,
        ])
        ->assertRedirect();

    expect(XiamInstitutionPolicy::forInstitution($institutionId)->isToolEnabled('exams.session.publish@1'))->toBeFalse();

    foreach (['admissions.attempt.accept@1', 'exams.slot.reserve@1'] as $toolId) {
        $this->actingAs($guidance)
            ->post(route('setup.ai-center.tools.toggle'), [
                'tool_id' => $toolId,
                'enabled' => false,
            ])
            ->assertForbidden();
    }
});

test('institutional AI governor can toggle Registrar and Applicant tools', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $institutionId = (int) $admin->actorScope()->institutionId;

    foreach (['admissions.attempt.accept@1', 'exams.slot.reserve@1'] as $toolId) {
        $this->actingAs($admin)
            ->post(route('setup.ai-center.tools.toggle'), [
                'tool_id' => $toolId,
                'enabled' => false,
            ])
            ->assertRedirect();
        expect(XiamInstitutionPolicy::forInstitution($institutionId)->isToolEnabled($toolId))->toBeFalse();
    }
});

test('disabled tool is blocked fail-closed in assistant tool gateway', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institutionId = (int) $guidance->actorScope()->institutionId;
    $policy = XiamInstitutionPolicy::forInstitution($institutionId);
    if (! $policy->exists) {
        $policy->save();
    }
    // Disable the tool in institutional policy
    $policy->update(['disabled_tools' => ['exams.sessions.list@1']]);
    expect($policy->fresh()->isToolEnabled('exams.sessions.list@1'))->toBeFalse();

    $gateway = app(AssistantToolGateway::class);
    $context = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: $institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-disabled-test',
        purpose: 'assistant_turn',
        correlationId: 'run-test-disabled',
    );

    $result = $gateway->invoke($guidance, $context, 'exams.sessions.list@1', []);

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toBe('tool_disabled_by_institution');
});
