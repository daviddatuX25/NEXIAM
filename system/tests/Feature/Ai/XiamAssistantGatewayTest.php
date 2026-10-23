<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Ai\Applicant\ApplicantOwnDataProjector;
use App\Ai\Connectors\ConnectorCatalog;
use App\Ai\Connectors\SecretVault;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Gateway\ToolPolicyGate;
use App\Ai\Knowledge\EvidenceBundle;
use App\Ai\Knowledge\GroundingRouter;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Proposals\ProposalService;
use App\Ai\Skills\ExamSessionSchedulingSkill;
use App\Ai\Skills\SkillEngine;
use App\Ai\Skills\SkillToolInvoker;
use App\Ai\Streaming\VercelUiMessageStreamProjector;
use App\Application\Capabilities\CapabilityDefinition;
use App\Application\Capabilities\CapabilityKind;
use App\Application\Capabilities\Handlers\Exams\ExamSessionReservationOpenHandler;
use App\Application\Capabilities\Handlers\SetupReadinessReadHandler;
use App\Application\Capabilities\Schema\CapabilityInputValidator;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamRunStep;
use App\Models\Ai\XiamTurn;
use App\Models\Ai\XiamUsageReservation;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function xiamInvocationContext(string $actorId = '1'): InvocationContext
{
    return new InvocationContext(
        actorId: $actorId,
        actorType: 'staff',
        institutionId: 1,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'correlation-1',
    );
}

test('assistant catalog publishes read and propose tools only', function (): void {
    $catalog = new AssistantToolCatalog([
        new CapabilityDefinition(
            key: 'test.read',
            version: 1,
            kind: CapabilityKind::Read,
            applicationCapabilities: [CapabilityCatalog::SETUP_INSTITUTION_MANAGE],
            authorizationMode: 'any',
            inputSchema: ['type' => 'object', 'additionalProperties' => false, 'properties' => []],
            handlerClass: SetupReadinessReadHandler::class,
            xiamExposure: ['human_path' => 'setup.index', 'privacy_projection' => 'SetupReadinessReadHandler:xiam_safe', 'confirmation' => 'none', 'idempotency' => 'read_only', 'owner' => 'Registrar'],
        ),
        new CapabilityDefinition(
            key: 'test.command',
            version: 1,
            kind: CapabilityKind::Command,
            applicationCapabilities: [CapabilityCatalog::SETUP_INSTITUTION_MANAGE],
            authorizationMode: 'any',
            inputSchema: ['type' => 'object', 'additionalProperties' => false, 'properties' => []],
            handlerClass: SetupReadinessReadHandler::class,
            channels: ['http_page'],
        ),
    ]);

    expect($catalog->getById('test.read@1'))->not->toBeNull()
        ->and($catalog->getById('test.command@1'))->toBeNull();
});

test('xiam-safe registry entries reject missing parity and privacy contracts', function (): void {
    expect(fn () => new CapabilityDefinition(
        key: 'test.unreviewed',
        version: 1,
        kind: CapabilityKind::Read,
        applicationCapabilities: [CapabilityCatalog::SETUP_INSTITUTION_MANAGE],
        authorizationMode: 'any',
        inputSchema: ['type' => 'object', 'additionalProperties' => false, 'properties' => []],
        handlerClass: SetupReadinessReadHandler::class,
    ))->toThrow(InvalidArgumentException::class);
});

test('published xiam tools retain human routes and declare action receipt policy', function (): void {
    $actionReceiptCapabilities = [
        'exams.session.reservation.open@1',
        'exams.session.assign@1',
        'exams.session.publish@1',
        'exams.slot.reserve@1',
        'exams.session.create@1',
        'consultation.session.create@1',
        'consultation.session.reservation.open@1',
        'admissions.attempt.accept@1',
        'admissions.attempt.bulk_accept@1',
    ];

    foreach (app(AssistantToolCatalog::class)->all() as $tool) {
        $definition = $tool->capability;
        if (! in_array('xiam_safe', $definition->channels, true)) {
            continue;
        }

        $contract = $definition->xiamExposure;
        $expectedIdempotency = $definition->kind === CapabilityKind::Read
            ? 'read_only'
            : (in_array($definition->id(), $actionReceiptCapabilities, true)
                ? 'proposal_and_action_execution_receipt'
                : 'proposal_execution_receipt');

        expect(Route::has($contract['human_path']))->toBeTrue()
            ->and($contract['privacy_projection'])->toBe(class_basename($definition->handlerClass).':xiam_safe')
            ->and($contract['confirmation'])->toBe($definition->kind === CapabilityKind::Read ? 'none' : 'explicit_proposal')
            ->and($contract['idempotency'])->toBe($expectedIdempotency);
    }
});

test('assistant gateway rejects caller selected execution before kernel dispatch', function (): void {
    $actor = new User;
    $actor->id = 1;
    $gateway = app(AssistantToolGateway::class);

    $result = $gateway->invoke(
        actor: $actor,
        context: xiamInvocationContext(),
        toolId: 'setup.readiness.read@1',
        input: ['execute' => true],
    );

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('invalid')
        ->and($result->reason)->toBe('forbidden_field:execute');
});

test('assistant gateway rejects forged actor context', function (): void {
    $actor = new User;
    $actor->id = 1;
    $gateway = app(AssistantToolGateway::class);

    $result = $gateway->invoke(
        actor: $actor,
        context: xiamInvocationContext('2'),
        toolId: 'setup.readiness.read@1',
        input: [],
    );

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('denied')
        ->and($result->reason)->toBe('invocation_actor_mismatch');
});
test('staff tool envelope is server-derived and permits proposal preflight', function (): void {
    seedE2Foundation();
    config(['xiam.emergency_stop' => false]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-GW-POLICY-GATE',
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-GW-POLICY-GATE')->firstOrFail();
    $context = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: (int) $guidance->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'policy-gate-epoch',
        purpose: 'assistant_turn',
        correlationId: 'policy-gate-correlation',
    );
    $envelope = app(ToolPolicyGate::class)->resolveEnvelope($context, $guidance);
    $result = app(AssistantToolGateway::class)->invoke(
        actor: $guidance,
        context: $context,
        toolId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $session->id,
            'expected_version' => (int) $session->version,
        ],
    );

    expect($envelope)->toBe(ToolPolicyGate::ENVELOPE_PROPOSE_ALLOWED)
        ->and($result->ok)->toBeTrue()
        ->and($result->data['mode'] ?? null)->toBe('propose')
        ->and($session->fresh()->booking_mode)->not->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);
});

test('context epoch advances with a server-owned route transition', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $epochs = app(ContextEpochService::class);

    $first = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $actor->id,
        institutionId: $actor->actorScope()->institutionId,
        surfaceKey: 'staff',
        routeName: 'setup.index',
        browserSessionId: 'browser-1',
        surfaceProfileId: 'staff',
    );

    $second = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $actor->id,
        institutionId: $actor->actorScope()->institutionId,
        surfaceKey: 'staff',
        routeName: 'exams.sessions.index',
        browserSessionId: 'browser-1',
        surfaceProfileId: 'staff',
    );

    expect($second->id)->toBe($first->id)
        ->and($second->context_epoch)->not->toBe($first->context_epoch)
        ->and((int) $second->epoch_version)->toBe(2);
});

test('clean AI persistence exposes Xiam tables without legacy Xiam tables', function (): void {
    expect(Schema::hasTable('xiam_conversations'))->toBeTrue()
        ->and(Schema::hasTable('xiam_runs'))->toBeTrue()
        ->and(Schema::hasTable('xiam_run_steps'))->toBeTrue()
        ->and(Schema::hasTable('xiam_proposals'))->toBeTrue()
        ->and(Schema::hasTable('nexi_conversations'))->toBeFalse()
        ->and(Schema::hasTable('nexi_runs'))->toBeFalse()
        ->and(Schema::hasTable('nexi_proposals'))->toBeFalse();
});

test('Xiam run models expose typed step relations', function (): void {
    $conversation = XiamConversation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => 'model-test',
        'institution_id' => null,
        'surface_key' => 'staff',
        'partition_key' => 'staff:model-test:staff',
        'context_epoch' => (string) Str::uuid(),
        'status' => 'open',
    ]);
    $turn = XiamTurn::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'mode' => 'assistant',
        'input_text' => 'model test',
    ]);
    $run = XiamRun::query()->create([
        'id' => (string) Str::uuid(),
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'status' => XiamRun::STATUS_SUCCEEDED,
        'gateway_route' => 'fake',
    ]);
    $step = XiamRunStep::query()->create([
        'id' => (string) Str::uuid(),
        'run_id' => $run->id,
        'conversation_id' => $conversation->id,
        'seq' => 1,
        'step_kind' => XiamRunStep::KIND_RUN,
        'status' => XiamRunStep::STATUS_SUCCEEDED,
        'output' => ['ok' => true],
    ]);

    expect($conversation->runs()->first()->is($run))->toBeTrue()
        ->and($run->steps()->first()->is($step))->toBeTrue()
        ->and($step->output)->toBe(['ok' => true]);
});
test('input validation rejects implicit scalar coercion and nested unknown fields', function (): void {
    $validator = app(CapabilityInputValidator::class);
    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['session_id', 'options'],
        'properties' => [
            'session_id' => ['type' => 'integer'],
            'options' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => ['dry_run' => ['type' => 'boolean']],
            ],
        ],
    ];

    expect($validator->validate($schema, ['session_id' => '42', 'options' => []]))
        ->toBe('$.session_id:type_mismatch');
    expect($validator->validate($schema, ['session_id' => 42, 'options' => ['extra' => true]]))
        ->toBe('$.options.extra:unknown_field');
    expect($validator->validate($schema, ['session_id' => 42, 'options' => ['dry_run' => false]]))
        ->toBeNull();
});

test('connector catalog resolves only deployment-owned profiles', function (): void {
    $catalog = app(ConnectorCatalog::class);

    expect($catalog->resolve('openai')->driver)->toBe('openai')
        ->and($catalog->resolve('openai')->baseUrl)->toBe('https://api.openai.com/v1');
    expect(fn () => $catalog->resolve('https://attacker.example/metadata'))
        ->toThrow(RuntimeException::class, 'connector_not_allowlisted');
});

test('secret vault returns opaque handles and masks credentials', function (): void {
    $vault = app(SecretVault::class);
    $handle = $vault->put('openai', 'test-secret');

    expect($handle)->toStartWith('xv_')
        ->and($handle)->not->toContain('test-secret')
        ->and($vault->mask())->toBe('••••••••')
        ->and($vault->exists($handle))->toBeTrue();

    $vault->revoke($handle);
    expect($vault->exists($handle))->toBeFalse();
});

test('unknown usage reservations hold quota until provider reconciliation', function (): void {
    seedE2Foundation();
    config(['xiam.usage.limit' => 1]);
    $budgets = app(UsageBudgetService::class);

    $first = $budgets->reserve('staff', 'quota-actor', 1, (string) Str::uuid());
    expect($first)->toBeInstanceOf(XiamUsageReservation::class);

    $budgets->markUnknown((string) $first->run_id, 'provider-req-1');
    expect($budgets->remaining('staff', 'quota-actor'))->toBe(0)
        ->and($budgets->reserve('staff', 'quota-actor', 1, (string) Str::uuid()))->toBeNull();

    $budgets->reconcileUnknown((string) $first->run_id, charged: false, providerRequestId: 'provider-req-1');
    expect($budgets->remaining('staff', 'quota-actor'))->toBe(1)
        ->and($budgets->reserve('staff', 'quota-actor', 1, (string) Str::uuid()))->toBeInstanceOf(XiamUsageReservation::class);
});

test('applicant grounding does not query the indexed corpus', function (): void {
    seedE2Foundation();
    $applicant = submitSyntheticApplicant('grounding@example.test', 'Grounding');
    $actor = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');
    $index = new class implements KnowledgeIndexPort
    {
        public int $retrieveCalls = 0;

        public function upsert(array $document): void {}

        public function remove(string $versionId): void {}

        public function retrieve(string $query, int $limit = 5, array $filters = []): array
        {
            $this->retrieveCalls++;

            $sourceClass = (string) ($filters['source_classes'][0] ?? '');

            return $sourceClass === '' ? [] : [[
                'version_id' => 'version-'.$sourceClass,
                'source_id' => 'source-'.$sourceClass,
                'source_title' => 'Grounding '.$sourceClass,
                'excerpt' => 'Approved evidence for '.$query,
                'score' => 1.0,
                'source_class' => $sourceClass,
            ]];
        }

        public function rebuildFromActive(): int
        {
            return 0;
        }
    };
    $router = new GroundingRouter($index, app(ApplicantOwnDataProjector::class));
    $context = new InvocationContext(
        actorId: (string) $actor->id,
        actorType: 'applicant',
        institutionId: $actor->actorScope()->institutionId,
        surfaceProfile: 'applicant',
        mode: 'assistant',
        contextEpoch: 'grounding-epoch',
        purpose: 'policy_inquiry',
        correlationId: 'grounding-correlation',
    );

    $bundle = $router->resolve('application status', $context);

    expect($bundle->citations)->toBeEmpty()
        ->and($index->retrieveCalls)->toBe(0)
        ->and($bundle->projections)->toHaveKey('actor_owned_record')
        ->and($bundle->hasSupport())->toBeTrue();
});

test('vercel stream projects accepted message without replaying historical raw deltas', function (): void {
    $events = [
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'run.started',
            'payload' => [],
        ]),
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'skill.plan',
            'payload' => ['capability_id' => 'setup.readiness.read@1', 'phase' => 'plan'],
        ]),
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'assistant.citations',
            'payload' => ['citations' => [['source_title' => 'Policy']]],
        ]),
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'assistant.diff',
            'payload' => ['kind' => 'status', 'label' => 'Status', 'value' => 'ready'],
        ]),
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'assistant.delta',
            'payload' => ['text' => 'raw-secret-fragment'],
        ]),
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'assistant.message',
            'payload' => ['content' => 'Hello'],
        ]),
        new XiamRunEvent([
            'run_id' => 'run-1',
            'type' => 'run.completed',
            'payload' => [],
        ]),
    ];

    $projector = new VercelUiMessageStreamProjector;
    $stream = implode('', iterator_to_array($projector->project($events)));

    expect($projector->headers()['x-vercel-ai-ui-message-stream'])->toBe('v1')
        ->and($stream)->toContain('data: {"type":"text-start","id":"xiam-run-1"}')
        ->and($stream)->toContain('data: {"type":"text-delta","id":"xiam-run-1","delta":"Hello"}')
        ->and($stream)->toContain('data: {"type":"text-end","id":"xiam-run-1"}')
        ->and($stream)->toContain('data: {"type":"data-xiam-avatar","data":{"emotion":"thinking","event_type":"run.started"}}')
        ->and($stream)->toContain('data: {"type":"data-xiam-steps"')
        ->and($stream)->toContain('data: {"type":"data-xiam-citation"')
        ->and($stream)->toContain('data: {"type":"data-xiam-diff"')
        ->and($stream)->toContain("data: [DONE]\n\n")
        ->and($stream)->not->toContain('data-xiam-mode-request')
        ->and($stream)->not->toContain('raw-secret-fragment')
        ->and($stream)->not->toContain('selector')
        ->and($stream)->not->toContain('path');
});

test('evidence bundle rejects malformed citation metadata', function (): void {
    expect(fn () => new EvidenceBundle(
        decisionId: 'decision-1',
        actorId: 'actor-1',
        contextEpoch: 'epoch-1',
        sourceClasses: [EvidenceBundle::SOURCE_PUBLIC_FAQ],
        citations: [[
            'citation_id' => 'citation-1',
            'source_class' => 'not-a-source-class',
            'version_id' => 'version-1',
            'source_id' => 'source-1',
            'title' => 'Invalid',
            'excerpt' => 'Invalid',
            'score' => 1.0,
        ]],
    ))->toThrow(InvalidArgumentException::class, 'evidence_source_class_invalid');
});

test('emergency stop blocks gateway tool invocation and confirmation of an already-created proposal', function (): void {
    seedE2Foundation();
    config(['xiam.emergency_stop' => false, 'xiam.process_inline' => true, 'xiam.enabled' => true]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-GW-STOP',
            'starts_at' => '2026-06-05 08:00:00',
            'effective_end_at' => '2026-06-05 11:00:00',
            'capacity' => 20,
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-GW-STOP')->firstOrFail();
    $epochs = app(ContextEpochService::class);
    $skills = app(SkillEngine::class);
    $invoker = app(SkillToolInvoker::class);
    $proposals = app(ProposalService::class);
    $gateway = app(AssistantToolGateway::class);

    $conversation = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $guidance->id,
        institutionId: $guidance->actorScope()->institutionId,
        surfaceKey: 'staff',
    );
    $grant = $skills->startExamSessionSkill(
        actor: $guidance,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'staff.exams.sessions.show@1',
        originPackVersion: 1,
        sessionId: $session->id,
    );

    $proposed = $invoker->invoke(
        actor: $guidance,
        grantId: $grant->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $session->id,
            'expected_version' => (int) $session->version,
        ],
        conversationId: $conversation->id,
    );

    $proposalId = (string) ($proposed['proposal']['id'] ?? '');
    expect($proposalId)->not->toBeEmpty();

    config(['xiam.emergency_stop' => true]);

    $gatewayResult = $gateway->invoke(
        actor: $guidance,
        context: new InvocationContext(
            actorId: (string) $guidance->id,
            actorType: 'staff',
            institutionId: $guidance->actorScope()->institutionId,
            surfaceProfile: 'staff',
            mode: 'assistant',
            contextEpoch: $conversation->context_epoch,
            purpose: 'test',
            correlationId: 'stop-check',
        ),
        toolId: 'setup.readiness.read@1',
        input: [],
    );

    expect($gatewayResult->ok)->toBeFalse()
        ->and($gatewayResult->status)->toBe('denied')
        ->and($gatewayResult->reason)->toBe('emergency_stop');

    expect(fn () => $proposals->confirm($guidance, $proposalId, 'stop-confirm-1'))
        ->toThrow(RuntimeException::class, 'emergency_stop');

    $proposal = XiamProposal::query()->findOrFail($proposalId);
    expect($proposal->status)->toBe(ProposalService::STATUS_INVALIDATED)
        ->and($proposal->failure_reason)->toBe('emergency_stop')
        ->and($session->fresh()->booking_mode)->not->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);

    config(['xiam.emergency_stop' => false]);
});

test('task grant target binding, stale epoch, and actor demotion block tool calls and proposal confirmation', function (): void {
    seedE2Foundation();
    config(['xiam.emergency_stop' => false, 'xiam.process_inline' => true, 'xiam.enabled' => true]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    foreach (['EX-GW-TGT-A' => '2026-06-06', 'EX-GW-TGT-B' => '2026-06-07'] as $code => $date) {
        $this->actingAs($guidance)
            ->post(route('exams.sessions.store'), [
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'starts_at' => $date.' 08:00:00',
                'effective_end_at' => $date.' 11:00:00',
                'capacity' => 20,
            ])
            ->assertRedirect();
    }

    $sessionA = ExamSession::query()->where('code', 'EX-GW-TGT-A')->firstOrFail();
    $sessionB = ExamSession::query()->where('code', 'EX-GW-TGT-B')->firstOrFail();

    $epochs = app(ContextEpochService::class);
    $skills = app(SkillEngine::class);
    $invoker = app(SkillToolInvoker::class);
    $proposals = app(ProposalService::class);

    $conversation = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $guidance->id,
        institutionId: $guidance->actorScope()->institutionId,
        surfaceKey: 'staff',
    );
    $grant = $skills->startExamSessionSkill(
        actor: $guidance,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'staff.exams.sessions.show@1',
        originPackVersion: 1,
        sessionId: $sessionA->id,
    );

    // Cross-target tool invoke is denied before mutating or creating a proposal.
    expect(fn () => $invoker->invoke(
        actor: $guidance,
        grantId: $grant->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $sessionB->id,
            'expected_version' => (int) $sessionB->version,
        ],
        conversationId: $conversation->id,
    ))->toThrow(RuntimeException::class, 'grant_target_mismatch');

    // Create valid proposal for sessionA, then drift grant scope_bounds target to sessionB before confirm.
    $proposed = $invoker->invoke(
        actor: $guidance,
        grantId: $grant->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $sessionA->id,
            'expected_version' => (int) $sessionA->version,
        ],
        conversationId: $conversation->id,
    );
    $proposalId = (string) $proposed['proposal']['id'];

    $bounds = $grant->fresh()->scope_bounds ?? [];
    $bounds['exam_session_id'] = $sessionB->id;
    $grant->forceFill(['scope_bounds' => $bounds])->save();

    expect(fn () => $proposals->confirm($guidance, $proposalId, 'target-mismatch-confirm'))
        ->toThrow(RuntimeException::class, 'grant_target_mismatch');

    expect(XiamProposal::query()->findOrFail($proposalId)->status)
        ->toBe(ProposalService::STATUS_INVALIDATED)
        ->and($sessionA->fresh()->booking_mode)->not->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);

    // Restore grant bound to sessionA, create a second proposal, then deactivate staff profile before confirm.
    $bounds = $grant->fresh()->scope_bounds ?? [];
    $bounds['exam_session_id'] = $sessionA->id;
    $bounds['cursor'] = ExamSessionSchedulingSkill::CURSOR_SESSION_LOADED;
    unset($bounds['pending_proposal_id']);
    $grant->forceFill(['scope_bounds' => $bounds])->save();

    $proposedDemote = $invoker->invoke(
        actor: $guidance,
        grantId: $grant->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $sessionA->id,
            'expected_version' => (int) $sessionA->version,
        ],
        conversationId: $conversation->id,
    );
    $demoteProposalId = (string) $proposedDemote['proposal']['id'];

    $guidance->staffProfile()->update(['status' => StaffProfile::STATUS_INACTIVE]);

    try {
        expect(fn () => $proposals->confirm($guidance->fresh(), $demoteProposalId, 'demoted-confirm'))
            ->toThrow(RuntimeException::class, 'actor_revoked');

        expect(XiamProposal::query()->findOrFail($demoteProposalId)->status)
            ->toBe(ProposalService::STATUS_INVALIDATED)
            ->and($sessionA->fresh()->booking_mode)->not->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);
    } finally {
        $guidance->staffProfile()->update(['status' => StaffProfile::STATUS_ACTIVE]);
    }
});

test('proposal confirm enforces idempotent receipt replay, crash-after-commit recovery, and handler exception rollback', function (): void {
    seedE2Foundation();
    config(['xiam.emergency_stop' => false, 'xiam.process_inline' => true, 'xiam.enabled' => true]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    foreach (['EX-GW-IDEMP' => '2026-06-08', 'EX-GW-CRASH' => '2026-06-09', 'EX-GW-ROLL' => '2026-06-10'] as $code => $date) {
        $this->actingAs($guidance)
            ->post(route('exams.sessions.store'), [
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'starts_at' => $date.' 08:00:00',
                'effective_end_at' => $date.' 11:00:00',
                'capacity' => 20,
            ])
            ->assertRedirect();
    }

    $sessionIdemp = ExamSession::query()->where('code', 'EX-GW-IDEMP')->firstOrFail();
    $sessionCrash = ExamSession::query()->where('code', 'EX-GW-CRASH')->firstOrFail();
    $sessionRoll = ExamSession::query()->where('code', 'EX-GW-ROLL')->firstOrFail();

    $epochs = app(ContextEpochService::class);
    $skills = app(SkillEngine::class);
    $invoker = app(SkillToolInvoker::class);
    $proposals = app(ProposalService::class);

    $conversation = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $guidance->id,
        institutionId: $guidance->actorScope()->institutionId,
        surfaceKey: 'staff',
    );
    $grantIdemp = $skills->startExamSessionSkill(
        actor: $guidance,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'staff.exams.sessions.show@1',
        originPackVersion: 1,
        sessionId: $sessionIdemp->id,
    );

    $proposedIdemp = $invoker->invoke(
        actor: $guidance,
        grantId: $grantIdemp->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $sessionIdemp->id,
            'expected_version' => (int) $sessionIdemp->version,
        ],
        conversationId: $conversation->id,
    );
    $idempProposalId = (string) $proposedIdemp['proposal']['id'];

    $firstConfirm = $proposals->confirm($guidance, $idempProposalId, 'replay-key-1');
    $versionAfterFirst = (int) $sessionIdemp->fresh()->version;

    // Same-key replay returns the exact durable receipt without re-running the handler/Action.
    $secondConfirm = $proposals->confirm($guidance, $idempProposalId, 'replay-key-1');
    expect($secondConfirm['proposal']->id)->toBe($idempProposalId)
        ->and($secondConfirm['proposal']->receipt['receipt_id'])->toBe($firstConfirm['proposal']->receipt['receipt_id'])
        ->and($secondConfirm['proposal']->receipt['execution_id'])->toBe($firstConfirm['proposal']->execution_id)
        ->and($secondConfirm['proposal']->receipt['canonical_digest'])->toBe($firstConfirm['proposal']->canonical_digest)
        ->and($secondConfirm['result'])->toEqual($firstConfirm['result'])
        ->and((int) $sessionIdemp->fresh()->version)->toBe($versionAfterFirst);

    // Different-key replay on an executed proposal fails closed.
    expect(fn () => $proposals->confirm($guidance, $idempProposalId, 'replay-key-different'))
        ->toThrow(RuntimeException::class, 'proposal_not_confirmable');

    // Unrelated pre-existing domain Action mutation while proposal is `executing` without receipt must not synthesize a recovered receipt.
    $grantCrash = $skills->startExamSessionSkill(
        actor: $guidance,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'staff.exams.sessions.show@1',
        originPackVersion: 1,
        sessionId: $sessionCrash->id,
    );
    $proposedCrash = $invoker->invoke(
        actor: $guidance,
        grantId: $grantCrash->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $sessionCrash->id,
            'expected_version' => (int) $sessionCrash->version,
        ],
        conversationId: $conversation->id,
    );
    $crashProposalId = (string) $proposedCrash['proposal']['id'];

    app(OpenExamSessionReservation::class)->handle(
        $sessionCrash,
        $guidance,
        (int) $sessionCrash->version,
        'unrelated pre-existing action mutation',
    );
    $committedVersion = (int) $sessionCrash->fresh()->version;

    XiamProposal::query()->whereKey($crashProposalId)->update([
        'status' => ProposalService::STATUS_EXECUTING,
        'confirmation_idempotency_key' => 'crash-key-1',
        'claimed_at' => now(),
        'receipt' => null,
    ]);

    // Different key while executing conflicts without mutating.
    expect(fn () => $proposals->confirm($guidance, $crashProposalId, 'crash-key-conflict'))
        ->toThrow(RuntimeException::class, 'proposal_claim_conflict');

    // Same key refuses the stale version through AssistantToolGateway rather than synthesizing a receipt from unrelated DB state.
    expect(fn () => $proposals->confirm($guidance, $crashProposalId, 'crash-key-1'))
        ->toThrow(RuntimeException::class, 'stale_session_version');

    $unrecoveredProposal = XiamProposal::query()->findOrFail($crashProposalId);
    expect($unrecoveredProposal->status)->toBe(ProposalService::STATUS_INVALIDATED)
        ->and($unrecoveredProposal->failure_reason)->toBe('stale_session_version')
        ->and($unrecoveredProposal->receipt)->toBeNull()
        ->and((int) $sessionCrash->fresh()->version)->toBe($committedVersion)
        ->and($grantCrash->fresh()->scope_bounds['pending_proposal_id'] ?? null)->toBeNull();

    // Handler exception rollback: partial DB write inside handler is rolled back and proposal transitions deterministically to failed.
    $grantRoll = $skills->startExamSessionSkill(
        actor: $guidance,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'staff.exams.sessions.show@1',
        originPackVersion: 1,
        sessionId: $sessionRoll->id,
    );
    $proposedRoll = $invoker->invoke(
        actor: $guidance,
        grantId: $grantRoll->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $sessionRoll->id,
            'expected_version' => (int) $sessionRoll->version,
        ],
        conversationId: $conversation->id,
    );
    $rollProposalId = (string) $proposedRoll['proposal']['id'];

    app()->bind(ExamSessionReservationOpenHandler::class, function () use ($sessionRoll) {
        return new class($sessionRoll->id)
        {
            public function __construct(private readonly int $sessionId) {}

            /**
             * @param  array<string, mixed>  $input
             * @return array<string, mixed>
             */
            public function handle(array $input = []): array
            {
                ExamSession::query()
                    ->whereKey($this->sessionId)
                    ->update(['booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION]);

                throw new LogicException('simulated_handler_explosion');
            }
        };
    });

    try {
        expect(fn () => $proposals->confirm($guidance, $rollProposalId, 'roll-key-1'))
            ->toThrow(RuntimeException::class, 'simulated_handler_explosion');
    } finally {
        app()->offsetUnset(ExamSessionReservationOpenHandler::class);
    }

    $rolledProposal = XiamProposal::query()->findOrFail($rollProposalId);
    expect($rolledProposal->status)->toBe(ProposalService::STATUS_FAILED)
        ->and($rolledProposal->failure_reason)->toBe('simulated_handler_explosion')
        ->and($sessionRoll->fresh()->booking_mode)->not->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION)
        ->and($grantRoll->fresh()->scope_bounds['pending_proposal_id'] ?? null)->toBeNull();
});

test('action receipt recovers an executing proposal without repeating its action', function (): void {
    seedE2Foundation();
    config(['xiam.emergency_stop' => false, 'xiam.process_inline' => true, 'xiam.enabled' => true]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-GW-ACTION-RECEIPT',
            'starts_at' => now()->addDays(5)->setTime(8, 0)->toDateTimeString(),
            'effective_end_at' => now()->addDays(5)->setTime(11, 0)->toDateTimeString(),
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-GW-ACTION-RECEIPT')->firstOrFail();
    $epochs = app(ContextEpochService::class);
    $skills = app(SkillEngine::class);
    $invoker = app(SkillToolInvoker::class);
    $proposals = app(ProposalService::class);
    $conversation = $epochs->resolveOpenConversation(
        actorType: 'staff',
        actorId: (string) $guidance->id,
        institutionId: $guidance->actorScope()->institutionId,
        surfaceKey: 'staff',
    );
    $grant = $skills->startExamSessionSkill(
        actor: $guidance,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'staff.exams.sessions.show@1',
        originPackVersion: 1,
        sessionId: $session->id,
    );
    $proposed = $invoker->invoke(
        actor: $guidance,
        grantId: $grant->id,
        capabilityId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $session->id,
            'expected_version' => (int) $session->version,
        ],
        conversationId: $conversation->id,
    );
    $proposal = XiamProposal::query()->findOrFail((string) $proposed['proposal']['id']);
    $idempotencyKey = 'action-receipt-recovery-key';
    $proposal->forceFill([
        'status' => ProposalService::STATUS_EXECUTING,
        'confirmation_idempotency_key' => $idempotencyKey,
        'claimed_at' => now(),
        'receipt' => null,
    ])->save();

    $context = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: (string) $conversation->actor_type,
        institutionId: (int) $conversation->institution_id,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: (string) $conversation->context_epoch,
        purpose: 'proposal_confirmation',
        correlationId: (string) $proposal->id,
        executionId: (string) $proposal->execution_id,
        canonicalDigest: (string) $proposal->canonical_digest,
        grantId: $grant->id,
    );
    $auditCount = DB::table('audit_events')
        ->where('event_type', 'exam.session_reservation_opened')
        ->count();

    $firstExecution = app(AssistantToolGateway::class)->invoke(
        actor: $guidance,
        context: $context,
        toolId: 'exams.session.reservation.open@1',
        input: [
            'session_id' => $session->id,
            'expected_version' => (int) $session->version,
        ],
        serverConfirmed: true,
    );

    expect($firstExecution->ok)->toBeTrue()
        ->and(DB::table('examination_action_execution_receipts')
            ->where('execution_id', $proposal->execution_id)
            ->count())->toBe(1);

    $versionAfterFirstExecution = (int) $session->fresh()->version;
    $recovered = $proposals->confirm($guidance, (string) $proposal->id, $idempotencyKey);

    expect($recovered['proposal']->status)->toBe(ProposalService::STATUS_EXECUTED)
        ->and($recovered['result'])->toEqual($firstExecution->data)
        ->and((int) $session->fresh()->version)->toBe($versionAfterFirstExecution)
        ->and(DB::table('audit_events')
            ->where('event_type', 'exam.session_reservation_opened')
            ->count())->toBe($auditCount + 1)
        ->and(DB::table('examination_action_execution_receipts')
            ->where('execution_id', $proposal->execution_id)
            ->count())->toBe(1);
});

test('newly narrowed applicant policy prevents confirming an existing proposal', function (): void {
    seedE2Foundation();
    config([
        'xiam.emergency_stop' => false,
        'xiam.process_inline' => true,
        'xiam.enabled' => true,
        'xiam.applicant_self_booking_enabled' => true,
    ]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $applicant = submitSyntheticApplicant('applicant.policy.narrow@example.test', 'PolicyNarrow');
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $applicant['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-GW-APP-POL',
            'date' => now()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-GW-APP-POL')->firstOrFail();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => (int) $session->version,
        ])
        ->assertRedirect();

    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicant['access_token'],
        '2001-01-01',
    );

    $epochs = app(ContextEpochService::class);
    $skills = app(SkillEngine::class);
    $invoker = app(SkillToolInvoker::class);
    $proposals = app(ProposalService::class);

    $conversation = $epochs->resolveOpenConversation(
        actorType: 'applicant',
        actorId: (string) $applicantUser->id,
        institutionId: $applicantUser->actorScope()->institutionId,
        surfaceKey: 'applicant',
    );
    $grant = $skills->startApplicantSlotSkill(
        actor: $applicantUser,
        contextEpoch: $conversation->context_epoch,
        originPackKey: 'applicant.schedule@1',
        originPackVersion: 1,
        attemptId: (int) $applicant['attempt']->id,
    );

    $invoker->invoke(
        actor: $applicantUser,
        grantId: $grant->id,
        capabilityId: 'exams.slots.list@1',
        input: [],
        conversationId: $conversation->id,
    );

    $proposed = $invoker->invoke(
        actor: $applicantUser,
        grantId: $grant->id,
        capabilityId: 'exams.slot.reserve@1',
        input: [
            'exam_session_id' => $session->id,
            'admission_attempt_id' => (int) $applicant['attempt']->id,
        ],
        conversationId: $conversation->id,
    );

    $proposalId = (string) ($proposed['proposal']['id'] ?? '');
    expect($proposalId)->not->toBeEmpty();

    config(['xiam.applicant_self_booking_enabled' => false]);

    try {
        expect(fn () => $proposals->confirm($applicantUser, $proposalId, 'narrowed-policy-confirm-1'))
            ->toThrow(RuntimeException::class, 'envelope_denied:read_allowed');

        $proposal = XiamProposal::query()->findOrFail($proposalId);
        expect($proposal->status)->toBe(ProposalService::STATUS_INVALIDATED)
            ->and($proposal->failure_reason)->toBe('envelope_denied:read_allowed')
            ->and($proposal->receipt)->toBeNull()
            ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $applicant['attempt']->id)->count())->toBe(0);
    } finally {
        config(['xiam.applicant_self_booking_enabled' => true]);
    }
});
