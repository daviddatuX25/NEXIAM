<?php

declare(strict_types=1);
use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Streaming\VercelUiMessageStreamProjector;
use App\Assessment\Queries\UnscoredExamineesQuery;
use App\Examination\Support\CreateExamSessionEvaluator;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'laravel', 'xiam.enabled' => true]);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
});

function authenticateFullPipelineStaff(User $user): void
{
    $state = StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail();

    test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
}

/** @return array{run: XiamRun, events: Collection<int, XiamRunEvent>, conversation_id: string} */
function fullPipelineScenarioTurn(string $message, string $key, ?string $conversationId = null): array
{
    $payload = [
        'message' => $message,
        'idempotency_key' => $key,
        'page_pack_key' => 'staff.exams.sessions.index@1',
        'page_pack_version' => 1,
    ];
    if ($conversationId !== null) {
        $payload['conversation_id'] = $conversationId;
    }

    test()->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();

    $accepted = test()->withHeader('X-Xiam-Browser-Session', 'full-pipeline-scenario-session')->postJson(route('xiam.turns.store'), $payload)->assertAccepted()->json();
    $run = XiamRun::query()->findOrFail($accepted['run_id']);

    return [
        'run' => $run,
        'events' => XiamRunEvent::query()->where('run_id', $run->id)->orderBy('seq')->get(),
        'conversation_id' => $accepted['conversation_id'],
    ];
}

function fullPipelineGuidance(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

test('Scenario 1: Daily workspace briefing retrieves today sessions and summary', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('institution_id', $guidance->actorScope()->institutionId)->firstOrFail();
    $sessionCode = 'EX-DAILY-BRIEFING';
    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $sessionCode,
        'starts_at' => now()->startOfDay()->addHours(10),
        'effective_end_at' => now()->startOfDay()->addHours(12),
        'status' => ExamSession::STATUS_DRAFT,
        'version' => 1,
    ]);
    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        new ToolCall('daily-summary', 'workspace_summary_1', []),
        new ToolCall('daily-sessions', 'exams_sessions_today_1', []),
        'Operational briefing: today includes '.$sessionCode.' and current workspace workload.',
    ]);

    $turn = fullPipelineScenarioTurn('Prepare my daily workspace briefing and include today\'s exam sessions.', 'scenario-daily-briefing');
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))->assertOk()->json();
    $calls = $turn['events']->where('type', 'tool.call')->pluck('payload.tool')->all();
    $results = $turn['events']->where('type', 'tool.result')->keyBy('payload.tool');
    $message = $turn['events']->firstWhere('type', 'assistant.message')?->payload['content'] ?? '';

    expect($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($calls)->toBe(['workspace.summary@1', 'exams.sessions.today@1'])
        ->and($results->keys()->all())->toBe(['workspace.summary@1', 'exams.sessions.today@1'])
        ->and($results->every(fn (XiamRunEvent $event): bool => $event->payload['successful'] === true))->toBeTrue()
        ->and($message)->toContain('Operational briefing', $sessionCode)
        ->and($snapshot['messages'])->toHaveCount(2)
        ->and($snapshot['messages'][0]['content'])->toContain('daily workspace briefing')
        ->and($snapshot['messages'][1]['content'])->toContain($sessionCode);
});

test('Scenario 2: Unscored examinees query reports exact count without pivoting to readiness', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('institution_id', $guidance->actorScope()->institutionId)->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-UNSCORED-SCENARIO',
        'starts_at' => now()->subDay(),
        'effective_end_at' => now()->subDay()->addHours(2),
        'status' => ExamSession::STATUS_COMPLETED,
        'version' => 1,
    ]);
    foreach (['One', 'Two', 'Three'] as $index => $name) {
        $applicant = submitSyntheticApplicant('unscored-scenario-'.strtolower($name).'@example.test', 'Unscored'.$name);
        $attempt = $applicant['attempt'];
        $attempt->update([
            'academic_cycle_id' => $cycle->id,
            'institution_id' => $cycle->institution_id,
            'status' => AdmissionAttempt::STATUS_ACCEPTED,
        ]);
        $assignment = ExamAssignment::factory()->create([
            'exam_session_id' => $session->id,
            'admission_attempt_id' => $attempt->id,
        ]);
        ExamAttendanceRecord::query()->create([
            'exam_assignment_id' => $assignment->id,
            'recorded_by_user_id' => $guidance->id,
            'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
            'idempotency_key' => 'unscored-scenario-'.$index,
            'recorded_at' => now(),
        ]);
    }
    $queryResult = app(UnscoredExamineesQuery::class)->handle(
        (int) $guidance->actorScope()->institutionId,
        (int) $cycle->id,
    );
    expect($queryResult['unscored_examinees_count'])->toBe(3)
        ->and($queryResult['completed_sessions_count'])->toBe(1)
        ->and($queryResult['sessions_awaiting_scores'][0]['unscored_count'])->toBe(3);

    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        new ToolCall('unscored-count', 'assessment_unscored_examinees_query_1', []),
        'There are exactly 3 unscored examinees across 1 completed session, awaiting scoring.',
    ]);
    $turn = fullPipelineScenarioTurn('How many examinees completed exams but have not been scored?', 'scenario-unscored-count');
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))->assertOk()->json();
    $calls = $turn['events']->where('type', 'tool.call')->pluck('payload.tool')->all();
    $result = $turn['events']->firstWhere('type', 'tool.result');
    $message = $turn['events']->firstWhere('type', 'assistant.message')?->payload['content'] ?? '';

    expect($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($calls)->toBe(['assessment.unscored_examinees.query@1'])
        ->and($result?->payload['successful'])->toBeTrue()
        ->and($message)->toContain('exactly 3', '1 completed session')
        ->and($turn['events']->where('type', 'tool.call')->pluck('payload.tool'))->not->toContain('setup.readiness.read@1')
        ->and($snapshot['messages'][1]['content'])->toContain('exactly 3')
        ->and($snapshot['proposals'])->toBeEmpty();
});

test('Scenario 3: Candidate pool query gracefully resolves cycle code SYN-CYCLE-2026', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        new ToolCall('candidate-pool-by-cycle-code', 'scheduling_candidate_pool_list_1', [
            'academic_cycle_id' => 'SYN-CYCLE-2026',
            'limit' => 10,
        ]),
        'The candidate pool for SYN-CYCLE-2026 was retrieved successfully.',
    ]);

    $turn = fullPipelineScenarioTurn('Show the candidate pool for cycle SYN-CYCLE-2026.', 'scenario-cycle-code');
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))->assertOk()->json();
    $call = $turn['events']->firstWhere('type', 'tool.call');
    $result = $turn['events']->firstWhere('type', 'tool.result');
    $message = $turn['events']->firstWhere('type', 'assistant.message')?->payload['content'] ?? '';

    expect($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($call?->payload['tool'])->toBe('scheduling.candidate_pool.list@1')
        ->and($result?->payload['successful'])->toBeTrue()
        ->and($message)->toContain('SYN-CYCLE-2026', 'retrieved successfully')
        ->and($snapshot['messages'])->toHaveCount(2)
        ->and($snapshot['messages'][1]['content'])->toBe($message)
        ->and($snapshot['proposals'])->toBeEmpty();
});

test('Scenario 4: Exam session creation proposal card generates preflight checks and diff', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()
        ->examRooms()
        ->where('institution_id', $guidance->actorScope()->institutionId)
        ->firstOrFail();
    expect($room->isExamRoom())->toBeTrue();
    $start = now()->addDays(2)->setTime(9, 0);
    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        new ToolCall('create-session-proposal', 'exams_session_create_1', [
            'academic_cycle_id' => (int) $cycle->id,
            'room_id' => (int) $room->id,
            'starts_at' => $start->toIso8601String(),
            'effective_end_at' => $start->copy()->addHours(2)->toIso8601String(),
            'code' => 'EX-SCENARIO-CREATE',
        ]),
        'I prepared a draft exam session proposal for EX-SCENARIO-CREATE. Review the checks and diff before confirming.',
    ]);

    $turn = fullPipelineScenarioTurn('Create an exam session EX-SCENARIO-CREATE in the available room two days from now at 9 AM for two hours.', 'scenario-session-create');
    $eventTypes = $turn['events']->pluck('type')->all();
    expect(array_search('tool.call', $eventTypes, true))
        ->toBeLessThan(array_search('skill.proposal', $eventTypes, true))
        ->and(array_search('skill.proposal', $eventTypes, true))
        ->toBeLessThan(array_search('tool.result', $eventTypes, true))
        ->and(array_search('tool.result', $eventTypes, true))
        ->toBeLessThan(array_search('assistant.message', $eventTypes, true));
    $proposalEvent = $turn['events']->firstWhere('type', 'skill.proposal');
    $proposal = XiamProposal::query()->findOrFail($proposalEvent->payload['proposal_id']);
    $assistantStream = implode('', iterator_to_array(app(VercelUiMessageStreamProjector::class)->project($turn['events'])));
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))->assertOk()->json();

    expect($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($turn['events']->where('type', 'tool.call')->pluck('payload.tool')->all())->toBe(['exams.session.create@1'])
        ->and($turn['events']->firstWhere('type', 'tool.result')?->payload['successful'])->toBeTrue()
        ->and($proposal->status)->toBe('pending')
        ->and($proposal->summary['impact']['_command_input']['seating_mode'] ?? null)->toBe('simple_capacity')
        ->and($proposalEvent->payload)->toHaveKeys(['proposal_id', 'summary', 'diff', 'preflight_checks'])
        ->and($proposalEvent->payload['diff'])->not->toBeEmpty()
        ->and($proposalEvent->payload['preflight_checks'])->not->toBeEmpty()
        ->and($assistantStream)->toContain('data-xiam-proposal', 'preflight_checks', 'EX-SCENARIO-CREATE')
        ->and($snapshot['proposals'])->toContain(['id' => $proposal->id, 'status' => 'pending'])
        ->and(ExamSession::query()->where('code', 'EX-SCENARIO-CREATE')->exists())->toBeFalse();
});

test('Deterministic fake-model integration: session creation stays pending until confirmation and replays its receipt', function (): void {
    // The fake fixes model output only; the authenticated turn and confirmation use the ordinary server path.
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $institutionId = $guidance->actorScope()->institutionId;
    $cycle = AcademicCycle::query()
        ->where('institution_id', $institutionId)
        ->where('is_active', true)
        ->firstOrFail();
    $room = Room::query()
        ->examRooms()
        ->where('institution_id', $institutionId)
        ->firstOrFail();
    $startsAt = now()->addDays(400)->setTime(9, 0)->addMinutes(random_int(0, 1439));
    $endsAt = $startsAt->copy()->addHours(3);
    $code = 'EX-EVAL-CONFIRM-'.Str::upper(Str::random(10));

    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        new ToolCall('create-session-proposal', 'exams_session_create_1', [
            'academic_cycle_id' => (int) $cycle->id,
            'room_id' => (int) $room->id,
            'starts_at' => $startsAt->toIso8601String(),
            'effective_end_at' => $endsAt->toIso8601String(),
            'code' => $code,
            'seating_mode' => 'simple_capacity',
        ]),
        'Review this pending session creation proposal before confirmation.',
    ]);

    $turn = fullPipelineScenarioTurn("Create a three-hour exam session {$code}.", 'scenario-session-create-confirm');
    $proposalEvent = $turn['events']->firstWhere('type', 'skill.proposal');
    $proposal = XiamProposal::query()->findOrFail($proposalEvent->payload['proposal_id']);
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))
        ->assertOk()
        ->json();

    expect($proposal->status)->toBe('pending')
        ->and($proposal->capability_id)->toBe('exams.session.create@1')
        ->and($proposal->summary['impact']['_command_input']['seating_mode'] ?? null)->toBe('simple_capacity')
        ->and($proposal->summary['impact']['_command_input']['starts_at'] ?? null)->toBe($startsAt->utc()->format('Y-m-d\TH:i:s\Z'))
        ->and($proposal->summary['impact']['_command_input']['effective_end_at'] ?? null)->toBe($endsAt->utc()->format('Y-m-d\TH:i:s\Z'))
        ->and($snapshot['proposals'])->toContain(['id' => $proposal->id, 'status' => 'pending'])
        ->and(ExamSession::query()->where('code', $code)->exists())->toBeFalse();

    $confirmationKey = 'scenario-session-create-confirm-card';
    $confirmation = test()->postJson(route('xiam.proposals.confirm', $proposal->id), [
        'idempotency_key' => $confirmationKey,
    ])
        ->assertOk()
        ->assertJsonPath('proposal.status', 'executed');
    $confirmationJson = $confirmation->json();
    $executedProposal = $proposal->fresh();
    $session = ExamSession::query()->where('code', $code)->firstOrFail();
    $receipt = DB::table('examination_action_execution_receipts')
        ->where('execution_id', $proposal->execution_id)
        ->first();
    $auditCountAfterConfirmation = DB::table('audit_events')
        ->where('event_type', 'exam.session_created')
        ->where('payload->code', $code)
        ->count();
    $canonicalizeReceiptInput = function (array $value) use (&$canonicalizeReceiptInput): array {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $canonicalizeReceiptInput($item) : $item,
                $value,
            );
        }

        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $canonicalizeReceiptInput($item);
            }
        }

        return $value;
    };
    $expectedReceiptFingerprint = hash('sha256', json_encode(
        $canonicalizeReceiptInput([
            'action_key' => 'exams.session.create@1',
            'command' => $proposal->summary['impact']['_command_input'],
            'target' => ['academic_cycle_id' => (int) $cycle->id, 'room_id' => (int) $room->id],
        ]),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ));

    $overlapEvaluation = app(CreateExamSessionEvaluator::class)
        ->evaluate($cycle, $room, CarbonImmutable::parse($startsAt), CarbonImmutable::parse($endsAt), $code.'-STALE');
    expect($executedProposal->status)->toBe('executed')
        ->and(ExamSession::query()->where('code', $code)->count())->toBe(1)
        ->and($session->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and($session->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and((int) $session->academic_cycle_id)->toBe((int) $cycle->id)
        ->and((int) $session->room_id)->toBe((int) $room->id)
        ->and($session->starts_at->equalTo($startsAt))->toBeTrue()
        ->and($session->effective_end_at->equalTo($endsAt))->toBeTrue()
        ->and($confirmationJson['result']['seating_mode'] ?? null)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($confirmationJson['result']['starts_at'] ?? null)->toBe($startsAt->utc()->format('Y-m-d\TH:i:s\Z'))
        ->and($confirmationJson['result']['effective_end_at'] ?? null)->toBe($endsAt->utc()->format('Y-m-d\TH:i:s\Z'))
        ->and(DB::table('examination_action_execution_receipts')->where('execution_id', $proposal->execution_id)->count())->toBe(1)
        ->and($receipt)->not->toBeNull()
        ->and($receipt->request_fingerprint)->toBe($expectedReceiptFingerprint)
        ->and($receipt->completed_at)->not->toBeNull()
        ->and($executedProposal->receipt['receipt_id'] ?? null)->not->toBeEmpty()
        ->and($executedProposal->receipt['execution_id'] ?? null)->toBe($proposal->execution_id)
        ->and($confirmationJson['result']['session_id'] ?? null)->toBe($session->id)
        ->and($overlapEvaluation->outcome)->toBe('fail')
        ->and(collect($overlapEvaluation->checks)->firstWhere('key', 'no_room_collision')['status'] ?? null)->toBe('fail')
        ->and($auditCountAfterConfirmation)->toBe(1);

    $versionAfterConfirmation = (int) $session->version;
    $replay = test()->postJson(route('xiam.proposals.confirm', $proposal->id), [
        'idempotency_key' => $confirmationKey,
    ])
        ->assertOk()
        ->assertJsonPath('proposal.status', 'executed')
        ->json();
    $receiptAfterReplay = DB::table('examination_action_execution_receipts')
        ->where('execution_id', $proposal->execution_id)
        ->first();

    expect($replay)->toEqual($confirmationJson)
        ->and($proposal->fresh()->receipt['receipt_id'] ?? null)->toBe($executedProposal->receipt['receipt_id'])
        ->and($receiptAfterReplay->id)->toBe($receipt->id)
        ->and($receiptAfterReplay->completed_at)->toBe($receipt->completed_at)
        ->and(ExamSession::query()->where('code', $code)->count())->toBe(1)
        ->and((int) $session->fresh()->version)->toBe($versionAfterConfirmation)
        ->and(DB::table('audit_events')
            ->where('event_type', 'exam.session_created')
            ->where('payload->code', $code)
            ->count())->toBe($auditCountAfterConfirmation);
});

test('Scenario 5: Boundary defense refuses human-only operations and links to console without interrogation loops', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        'Live attendance is handled through the official console. I can help with session scheduling or review instead.',
    ]);

    $turn = fullPipelineScenarioTurn('Take attendance for every examinee in today\'s exam session.', 'scenario-human-only-operation');
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))->assertOk()->json();
    $message = $turn['events']->firstWhere('type', 'assistant.message')?->payload['content'] ?? '';

    expect($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($turn['events']->whereIn('type', ['tool.call', 'tool.result', 'skill.proposal']))->toBeEmpty()
        ->and($message)->not->toMatch('/\\b(what date|which room|how many|what time)\\b/i')
        ->and($snapshot['messages'])->toHaveCount(2)
        ->and($snapshot['messages'][1]['content'])->toBe($message)
        ->and($snapshot['proposals'])->toBeEmpty();
});

test('Scenario 6: Multi-turn parameter assembly maintains context across 3 turns', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()
        ->examRooms()
        ->where('institution_id', $guidance->actorScope()->institutionId)
        ->firstOrFail();
    expect($room->isExamRoom())->toBeTrue();
    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        'I can prepare that. Which academic cycle should I use?',
        new ToolCall('resolve-cycle-and-room', 'foundation_academic_cycles_list_1', []),
        'I found SYN-CYCLE-2026. What date and start time should I use?',
        new ToolCall('assemble-session-create', 'exams_session_create_1', [
            'academic_cycle_id' => (int) $cycle->id,
            'room_id' => (int) $room->id,
            'starts_at' => now()->addDays(4)->setTime(13, 0)->toIso8601String(),
            'effective_end_at' => now()->addDays(4)->setTime(15, 0)->toIso8601String(),
            'code' => 'EX-THREE-TURN-CONTEXT',
        ]),
        'The proposal uses academic cycle SYN-CYCLE-2026, the selected exam room, and the date and time you provided. Review it before confirming.',
    ]);

    $first = fullPipelineScenarioTurn('Please prepare a new exam session.', 'scenario-assembly-1');
    $second = fullPipelineScenarioTurn('Use academic cycle SYN-CYCLE-2026.', 'scenario-assembly-2', $first['conversation_id']);
    $third = fullPipelineScenarioTurn('Schedule it four days from now at 1 PM for two hours.', 'scenario-assembly-3', $first['conversation_id']);
    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $first['conversation_id']))->assertOk()->json();
    expect($third['events']->pluck('type')->all())->toContain('tool.call');
    $proposalEvent = $third['events']->firstWhere('type', 'skill.proposal');
    expect($proposalEvent)->not->toBeNull();
    $toolCall = $third['events']->firstWhere('type', 'tool.call');
    $toolResult = $third['events']->firstWhere('type', 'tool.result');
    $proposal = XiamProposal::query()->findOrFail($proposalEvent->payload['proposal_id']);

    expect($first['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($second['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($third['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($first['conversation_id'])->toBe($second['conversation_id'])->toBe($third['conversation_id'])
        ->and($first['events']->where('type', 'skill.proposal'))->toBeEmpty()
        ->and($second['events']->where('type', 'skill.proposal'))->toBeEmpty()
        ->and($second['events']->where('type', 'tool.call')->pluck('payload.tool'))->toContain('foundation.academic_cycles.list@1')
        ->and($third['events']->where('type', 'tool.call')->pluck('payload.tool'))->toContain('exams.session.create@1')
        ->and($toolCall->payload['tool'])->toBe('exams.session.create@1')
        ->and($toolResult?->payload['successful'])->toBeTrue()
        ->and($proposal->status)->toBe('pending')
        ->and($proposalEvent->payload)->toHaveKeys(['proposal_id', 'summary', 'diff', 'preflight_checks'])
        ->and($proposalEvent->payload['diff'])->not->toBeEmpty()
        ->and($proposalEvent->payload['preflight_checks'])->not->toBeEmpty()
        ->and(data_get($proposal->summary, 'impact.academic_cycle_id'))->toBe((int) $cycle->id)
        ->and(data_get($proposal->summary, 'impact._command_input.room_id'))->toBe((int) $room->id)
        ->and(ExamSession::query()->where('code', 'EX-THREE-TURN-CONTEXT')->exists())->toBeFalse()
        ->and(collect($snapshot['messages'])->where('role', 'user')->pluck('content')->all())->toBe([
            'Please prepare a new exam session.',
            'Use academic cycle SYN-CYCLE-2026.',
            'Schedule it four days from now at 1 PM for two hours.',
        ])
        ->and($snapshot['messages'])->toHaveCount(6)
        ->and($snapshot['proposals'])->toContain(['id' => $proposal->id, 'status' => 'pending']);
});

test('Non-exam rooms receive a typed tool denial without becoming provider failures', function (): void {
    seedE2Foundation();
    $guidance = fullPipelineGuidance();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()
        ->where('institution_id', $guidance->actorScope()->institutionId)
        ->where('space_kind', '!=', SpaceKind::Room->value)
        ->firstOrFail();
    expect($room->isExamRoom())->toBeFalse();
    $input = [
        'academic_cycle_id' => (int) $cycle->id,
        'room_id' => (int) $room->id,
        'starts_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
        'effective_end_at' => now()->addDays(2)->setTime(11, 0)->toIso8601String(),
        'code' => 'EX-UNSUITABLE-ROOM',
    ];
    $typedResult = app(AssistantToolGateway::class)->invoke(
        actor: $guidance,
        context: new InvocationContext(
            actorId: (string) $guidance->id,
            actorType: 'staff',
            institutionId: (int) $guidance->actorScope()->institutionId,
            surfaceProfile: 'staff',
            mode: 'assistant',
            contextEpoch: 'scenario-unsuitable-room',
            purpose: 'assistant_turn',
            correlationId: 'scenario-unsuitable-room-direct',
        ),
        toolId: 'exams.session.create@1',
        input: $input,
    );
    expect($typedResult->status)->toBe('denied')
        ->and($typedResult->reason)->toBe('room_not_suitable_for_exam');

    authenticateFullPipelineStaff($guidance);
    XiamAgent::fake([
        new ToolCall('reject-unsuitable-room', 'exams_session_create_1', $input),
        'That space is not available for exam session scheduling.',
    ]);

    $turn = fullPipelineScenarioTurn('Create an exam session in the selected space.', 'scenario-unsuitable-room');
    $toolResult = $turn['events']->firstWhere('type', 'tool.result');

    expect($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($turn['events']->pluck('type')->all())->not->toContain('run.failed')
        ->and($turn['events']->where('type', 'tool.call')->pluck('payload.tool')->all())->toBe(['exams.session.create@1'])
        ->and($toolResult?->payload['successful'])->toBeFalse()
        ->and($turn['events']->firstWhere('type', 'skill.proposal'))->toBeNull()
        ->and(ExamSession::query()->where('code', 'EX-UNSUITABLE-ROOM')->exists())->toBeFalse();
});

test('Local Ollama readiness accepts keyless loopback and allowlisted container endpoints only', function (): void {
    config([
        'xiam.ai.provider' => 'ollama',
        'xiam.connectors.allowlist.local-ollama.allowed_hosts' => ['ollama'],
        'ai.providers.ollama' => [
            'driver' => 'ollama',
            'key' => '',
            'url' => 'http://ollama:11434',
        ],
    ]);
    $gateway = new LaravelAiGateway;

    expect($gateway->health()['ok'])->toBeTrue();
    config(['ai.providers.ollama.url' => 'http://127.0.0.1:11434']);
    expect($gateway->health()['ok'])->toBeTrue();

    config(['ai.providers.ollama.url' => 'http://ollama.remote.example:11434']);

    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);

    config([
        'xiam.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
        'ai.providers.openai.url' => 'http://127.0.0.1:11434',
    ]);

    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'missing_api_key']);
});
