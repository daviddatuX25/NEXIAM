<?php

use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Context\InvocationContext;
use App\Ai\Evaluation\XiamEvalObserver;
use App\Ai\Policy\InputMinimizer;
use App\Examination\Support\ExamSessionPresenter;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamTaskGrant;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'fake', 'xiam.enabled' => true]);
});

function os8GuidanceUser(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function os8EnableAssistantMode(User $actor): void
{
    test()->actingAs($actor)
        ->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])
        ->assertOk();
    xiamKeepCurrentSessionCookie();
}

function os8CreateDraftExamSession(
    User $guidance,
    string $code = 'EX-AI-1',
    string $startTime = '09:00',
    string $endTime = '11:00',
    ?string $date = null,
): ExamSession {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => $date ?? now()->addDays(5)->toDateString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ])
        ->assertRedirect();

    return ExamSession::query()->where('code', $code)->firstOrFail();
}

test('exam session packs expose assistant mode and skill start intent', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-PACK');

    $this->actingAs($guidance)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('xiam.session.page_pack.key', 'staff.exams.sessions.show@1')
            ->where('xiam.session.availability', 'assistant_available')
            ->where('xiam.session.manual_fallbacks', fn ($intents) => collect($intents)->contains(
                fn ($i) => ($i['type'] ?? null) === 'skill_start'
                    && ($i['skill_key'] ?? null) === 'schedule.exam_session@1'
            ))
        );
});

test('skill allowlist rejects off-skill capability even when actor holds unrelated caps', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);

    $start = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.index@1',
            'page_pack_version' => 1,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'knowledge.sources.list@1',
            'input' => [],
        ])
        ->assertStatus(422)
        ->assertJson(['error' => 'capability_not_on_skill_allowlist']);
});

test('confirm open reservation assign and publish use same actions as the page', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $session = attachPublishReadyExamMaterials(os8CreateDraftExamSession($guidance, 'EX-AI-MUT'), $guidance);
    $start = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertOk();

    $openPropose = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.reservation.open@1',
            'input' => [
                'session_id' => $session->id,
                'expected_version' => $session->version,
            ],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertOk()
        ->json();

    expect($openPropose['proposal']['id'] ?? null)->not->toBeNull();

    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $openPropose['proposal']['id']), [
            'idempotency_key' => 'ai-open-1',
        ])
        ->assertOk();

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);

    $accepted = submitSyntheticApplicant('ai.exam.assistant@example.test');
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $accepted['attempt']), ['expected_version' => 1]);

    $session = $session->fresh();
    $assignPropose = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.assign@1',
            'input' => [
                'session_id' => $session->id,
                'admission_attempt_id' => $accepted['attempt']->id,
            ],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertOk()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $assignPropose['proposal']['id']), [
            'idempotency_key' => 'ai-assign-1',
        ])
        ->assertOk();

    $session = $session->fresh();
    $publishPropose = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.publish@1',
            'input' => [
                'session_id' => $session->id,
                'expected_version' => $session->version,
            ],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertOk()
        ->json();

    expect($publishPropose['result']['evidence']['checks'] ?? [])->toContain(
        'draft',
        'room_active',
        'schedule_valid',
        'has_assignments',
        'materials_selected',
        'booklet_usable',
        'seating_arrangement_compatible',
        'required_sets_available',
        'roster_within_desk_capacity',
        'version_match',
    )->and($publishPropose['result']['impact']['roster_material_fingerprint'] ?? null)->toBeString()->not->toBeEmpty();

    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $publishPropose['proposal']['id']), [
            'idempotency_key' => 'ai-publish-1',
        ])
        ->assertOk();

    $publishedSession = $session->fresh(['currentMaterialSnapshot']);
    expect($publishedSession->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($publishedSession->currentMaterialSnapshot)->not->toBeNull();
});

test('xiam publish proposal rejects missing-material session with same preflight reason as human presenter', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-NO-MAT');

    $accepted = submitSyntheticApplicant('ai.exam.nomat@example.test');
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $accepted['attempt']), ['expected_version' => 1]);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $accepted['attempt']->id,
        ])
        ->assertRedirect();

    $session = $session->fresh();
    $humanBlockReason = app(ExamSessionPresenter::class)->publishBlockReason($session);
    expect($humanBlockReason)->toBe('Select a finalized Booklet and compatible seating arrangement before publish.');

    $start = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertOk();

    $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.publish@1',
            'input' => [
                'session_id' => $session->id,
                'expected_version' => $session->version,
            ],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertStatus(422)
        ->assertJson([
            'error' => $humanBlockReason,
        ]);

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and(XiamProposal::query()->where('capability_id', 'exams.session.publish@1')->count())->toBe(0);
});

test('stale session version invalidates proposal on confirm', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-STALE');

    $start = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
        ])
        ->assertOk();

    $propose = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.reservation.open@1',
            'input' => [
                'session_id' => $session->id,
                'expected_version' => $session->version,
            ],
        ])
        ->assertOk()
        ->json();

    // Drift the domain version after propose.
    $session->forceFill(['version' => $session->version + 1])->save();

    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $propose['proposal']['id']), [
            'idempotency_key' => 'ai-stale-1',
        ])
        ->assertStatus(422);

    expect(XiamProposal::query()->findOrFail($propose['proposal']['id'])->status)->toBe('invalidated');
});

test('cross-user proposal confirm is denied', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $other = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-XUSER');

    $start = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
        ])
        ->assertOk();

    $propose = $this->actingAs($guidance)
        ->postJson(route('xiam.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.reservation.open@1',
            'input' => [
                'session_id' => $session->id,
                'expected_version' => $session->version,
            ],
        ])
        ->assertOk()
        ->json();

    $this->actingAs($other)
        ->postJson(route('xiam.proposals.confirm', $propose['proposal']['id']), [
            'idempotency_key' => 'ai-xuser-1',
        ])
        ->assertForbidden();

    expect(XiamProposal::query()->findOrFail($propose['proposal']['id'])->status)->toBe('pending');
    expect(XiamTaskGrant::query()->findOrFail($start['grant_id'])->isUsable())->toBeTrue();
});

test('assistant turn proposes open reservation with skill events', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-TURN');

    config([
        'xiam.ai.gateway' => 'laravel',
        'xiam.process_inline' => true,
    ]);

    $this->withHeader('X-Xiam-Browser-Session', 'exam-assistant-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated();
    $observer = new XiamEvalObserver(app(InputMinimizer::class));
    app()->instance(XiamEvalObserver::class, $observer);
    $observer->beginTrial('open-reservation-test');
    XiamAgent::fake([
        new ToolCall('call-assistant-turn-show-1', 'exams_session_show_1', ['session_id' => $session->id]),
        new ToolCall(
            'call-assistant-turn-open-1',
            'exams_session_reservation_open_1',
            ['session_id' => $session->id, 'expected_version' => (int) $session->version]
        ),
        'I have prepared a proposal to open reservation for this session.',
    ]);

    $accepted = $this->withHeader('X-Xiam-Browser-Session', 'exam-assistant-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Please open reservation for this session',
            'idempotency_key' => 'ai-turn-open-1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
        ])
        ->assertAccepted()
        ->json();

    $run = XiamRun::query()->findOrFail($accepted['run_id']);
    $events = XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->orderBy('seq')
        ->get();
    $types = $events->pluck('type')->all();
    $observations = $observer->endTrial();
    $reservationObservation = collect($observations)->firstWhere('capability_id', 'exams.session.reservation.open@1');

    expect($reservationObservation['status'] ?? null)->toBe('ok')
        ->and($reservationObservation['safe_proposal'] ?? null)->not->toBeEmpty();
    expect($types)->toContain('tool.call')
        ->and($types)->toContain('tool.result')
        ->and($types)->toContain('skill.proposal')
        ->and($types)->toContain('assistant.message');

    $proposalEvent = XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'skill.proposal')
        ->firstOrFail();

    $proposalId = $proposalEvent->payload['proposal_id'] ?? null;

    expect($proposalId)->not->toBeNull();
    $proposal = XiamProposal::query()->findOrFail($proposalId);
    expect($proposal->execution_id)->toBeString()->not->toBeEmpty()
        ->and($proposal->canonical_digest)->toBeString()->not->toBeEmpty()
        ->and($proposal->summary['execution_id'] ?? null)->toBe($proposal->execution_id);

    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $proposalId), [
            'idempotency_key' => 'ai-turn-confirm-1',
        ])
        ->assertOk()
        ->assertJsonPath('proposal.status', 'executed');

    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $proposalId), [
            'idempotency_key' => 'ai-turn-confirm-1',
        ])
        ->assertOk()
        ->assertJsonPath('proposal.status', 'executed');

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);
});

test('live assistant turn routes through XiamAgent with HasTools and creates proposal', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-LIVE-1');

    config([
        'xiam.ai.gateway' => 'laravel',
        'xiam.process_inline' => true,
    ]);

    $start = $this->withHeader('X-Xiam-Browser-Session', 'exam-live-harness-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    XiamAgent::fake([
        new ToolCall('call-live-show-1', 'exams_session_show_1', ['session_id' => $session->id]),
        new ToolCall(
            'call-live-1',
            'exams_session_reservation_open_1',
            ['session_id' => $session->id, 'expected_version' => (int) $session->version]
        ),
        'I have prepared a proposal to open reservation for this session.',
    ]);

    $accepted = $this->withHeader('X-Xiam-Browser-Session', 'exam-live-harness-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Please open reservation for this session',
            'idempotency_key' => 'ai-live-turn-1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
        ])
        ->assertAccepted()
        ->json();

    $run = XiamRun::query()->findOrFail($accepted['run_id']);
    expect($run->status)->toBe(XiamRun::STATUS_SUCCEEDED);

    $events = XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->orderBy('seq')
        ->get();

    $toolCalls = $events->where('type', 'tool.call')->pluck('payload.tool')->all();
    $toolResults = $events->where('type', 'tool.result')->pluck('payload')->all();
    $proposalEvent = $events->firstWhere('type', 'skill.proposal');
    $assistantMsg = $events->firstWhere('type', 'assistant.message');

    expect($toolCalls)->toBe(['exams.session.show@1', 'exams.session.reservation.open@1'])
        ->and($toolResults)->toHaveCount(2)
        ->and($toolResults[0]['tool'])->toBe('exams.session.show@1')
        ->and($toolResults[0]['successful'])->toBeTrue()
        ->and($toolResults[1]['tool'])->toBe('exams.session.reservation.open@1')
        ->and($toolResults[1]['successful'])->toBeTrue()
        ->and($proposalEvent)->not->toBeNull()
        ->and($assistantMsg)->not->toBeNull()
        ->and($assistantMsg->payload['content'])->toContain('prepared a proposal')
        ->and($assistantMsg->payload['proposal_id'])->not->toBeNull();

    $proposalId = $proposalEvent->payload['proposal_id'];
    $proposal = XiamProposal::query()->findOrFail($proposalId);
    expect($proposal->execution_id)->toBeString()->not->toBeEmpty()
        ->and($proposal->canonical_digest)->toBeString()->not->toBeEmpty()
        ->and($proposal->status)->toBe('pending');

    // Verify confirmation executes through the ordinary Action
    $this->actingAs($guidance)
        ->postJson(route('xiam.proposals.confirm', $proposalId), [
            'idempotency_key' => 'ai-live-confirm-1',
        ])
        ->assertOk()
        ->assertJsonPath('proposal.status', 'executed');

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);
});

test('XiamAgent filters exposed tools by actor GF-21 capabilities', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $guidanceContext = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: (int) $guidance->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-filter-test',
        purpose: 'assistant_turn',
        correlationId: 'corr-filter-1',
    );

    $registrarContext = new InvocationContext(
        actorId: (string) $registrar->id,
        actorType: 'staff',
        institutionId: (int) $registrar->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'epoch-filter-test',
        purpose: 'assistant_turn',
        correlationId: 'corr-filter-2',
    );

    $guidanceAgent = XiamAgent::forRun($guidance, $guidanceContext);
    $registrarAgent = XiamAgent::forRun($registrar, $registrarContext);

    $guidanceToolNames = array_map(fn ($t) => $t->name(), $guidanceAgent->tools());
    $registrarToolNames = array_map(fn ($t) => $t->name(), $registrarAgent->tools());

    // Guidance Counselor has guidance.exam.schedule, so open/assign/publish are present
    expect($guidanceToolNames)->toContain('exams_session_reservation_open_1')
        ->and($guidanceToolNames)->toContain('exams_session_publish_1');

    // Registrar Clerk lacks guidance.exam.schedule, so mutation tools must NOT be exposed
    expect($registrarToolNames)->not->toContain('exams_session_reservation_open_1')
        ->and($registrarToolNames)->not->toContain('exams_session_publish_1');
});

test('live assistant turn binds TaskGrant and rejects target mismatch at gateway', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $sessionA = os8CreateDraftExamSession($guidance, 'EX-TARGET-A', '09:00', '11:00');
    $sessionB = os8CreateDraftExamSession($guidance, 'EX-TARGET-B', '14:00', '16:00');

    config([
        'xiam.ai.gateway' => 'laravel',
        'xiam.process_inline' => true,
    ]);

    // Grant is bound to sessionA
    $start = $this->withHeader('X-Xiam-Browser-Session', 'target-mismatch-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $sessionA->id,
        ])
        ->assertCreated()
        ->json();

    // LLM attempts to call tool targeting sessionB (out of grant bounds)
    XiamAgent::fake([
        new ToolCall('call-mismatch-show-1', 'exams_session_show_1', ['session_id' => $sessionA->id]),
        new ToolCall(
            'call-mismatch-1',
            'exams_session_reservation_open_1',
            ['session_id' => $sessionB->id, 'expected_version' => (int) $sessionB->version]
        ),
        'Target mismatch prevented.',
    ]);

    $accepted = $this->withHeader('X-Xiam-Browser-Session', 'target-mismatch-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Open session B',
            'idempotency_key' => 'ai-mismatch-turn-1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
        ])
        ->assertAccepted()
        ->json();

    $events = XiamRunEvent::query()
        ->where('run_id', $accepted['run_id'])
        ->orderBy('seq')
        ->get();
    $toolCalls = $events->where('type', 'tool.call')->pluck('payload.tool')->all();
    $toolResults = $events->where('type', 'tool.result')->pluck('payload')->all();
    expect($toolCalls)->toBe(['exams.session.show@1', 'exams.session.reservation.open@1'])
        ->and($toolResults)->toHaveCount(2)
        ->and($toolResults[0]['successful'])->toBeTrue()
        ->and($toolResults[1]['successful'])->toBeFalse();

    // No proposal should have been created for sessionB
    expect(XiamProposal::query()->where('run_id', $accepted['run_id'])->count())->toBe(0);
});

test('assistant turn without skill grant permits read tools under assistant mode', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    os8EnableAssistantMode($guidance);
    $session = os8CreateDraftExamSession($guidance, 'EX-READ-ONLY-1');

    config([
        'xiam.ai.gateway' => 'laravel',
        'xiam.process_inline' => true,
    ]);

    // No skill is started, but user is on exams list and invokes read tool
    XiamAgent::fake([
        new ToolCall(
            'call-read-1',
            'exams_sessions_list_1',
            []
        ),
        'Here is the list of active exam sessions.',
    ]);

    $accepted = $this->withHeader('X-Xiam-Browser-Session', 'no-grant-read-test')
        ->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'What exam sessions exist?',
            'idempotency_key' => 'ai-no-grant-read-1',
            'page_pack_key' => 'staff.exams.sessions.index@1',
            'page_pack_version' => 1,
        ])
        ->assertAccepted()
        ->json();

    $events = XiamRunEvent::query()
        ->where('run_id', $accepted['run_id'])
        ->get();

    $toolResult = $events->firstWhere('type', 'tool.result');
    expect($toolResult)->not->toBeNull()
        ->and($toolResult->payload['successful'])->toBeTrue();
});
