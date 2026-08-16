<?php

use App\Ai\Skills\ApplicantChooseSlotSkill;
use App\Models\Ai\NexiProposal;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiRunEvent;
use App\Models\Ai\NexiTaskGrant;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

beforeEach(function (): void {
    config(['nexi.process_inline' => true, 'nexi.ai.gateway' => 'fake', 'nexi.enabled' => true]);
});

function slotSkillGuidance(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function slotSkillSetup(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function slotSkillLogin(array $result): void
{
    if (auth()->check()) {
        test()->post(route('logout'));
    }

    test()->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));
}

function slotSkillSession(User $guidance, string $code): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => now()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();

    return ExamSession::query()->where('code', $code)->firstOrFail();
}

function slotSkillOpenReservation(User $guidance, ExamSession $session): void
{
    test()->actingAs($guidance)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);
}

function slotSkillStart(array $overrides = []): array
{
    return test()->postJson(route('nexi.skills.start'), array_merge([
        'skill_key' => 'applicant.choose_slot@1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ], $overrides))->json();
}

function slotSkillInvoke(string $grantId, string $capabilityId, array $input = [], ?string $conversationId = null): \Illuminate\Testing\TestResponse
{
    $payload = [
        'grant_id' => $grantId,
        'capability_id' => $capabilityId,
        'input' => $input,
    ];

    if ($conversationId !== null) {
        $payload['conversation_id'] = $conversationId;
    }

    return test()->postJson(route('nexi.skills.invoke'), $payload);
}

test('applicant skill start issues choose_slot grant with allowlist and guidance', function (): void {
    seedE2Foundation();
    $result = slotSkillSetup('skill-start@example.test', 'SkillStart');
    slotSkillLogin($result);

    $start = slotSkillStart();

    expect($start['ok'])->toBeTrue()
        ->and($start['skill_key'])->toBe('applicant.choose_slot@1')
        ->and($start['cursor'])->toBe(ApplicantChooseSlotSkill::CURSOR_STARTED)
        ->and($start['allowed_capability_ids'])->toBe([
            'exams.slots.list@1',
            'exams.slot.reserve@1',
        ])
        ->and($start['guidance_digest'])->toBeString()->not->toBeEmpty();

    $grant = NexiTaskGrant::query()->findOrFail($start['grant_id']);
    expect($grant->actor_type)->toBe('applicant')
        ->and($grant->surface_key)->toBe('applicant')
        ->and($grant->skill_key)->toBe(ApplicantChooseSlotSkill::KEY)
        ->and($grant->skill_version)->toBe(ApplicantChooseSlotSkill::VERSION);
});

test('applicant skill start is denied for staff without applicant account', function (): void {
    seedE2Foundation();
    $staff = slotSkillGuidance();
    $this->actingAs($staff);

    $this->postJson(route('nexi.skills.start'), [
        'skill_key' => 'applicant.choose_slot@1',
        'page_pack_key' => 'applicant.schedule@1',
    ])->assertStatus(403)->assertJson(['error' => 'surface_denied']);
});

test('skill invoke lists own open slots and advances cursor', function (): void {
    seedE2Foundation();
    $guidance = slotSkillGuidance();
    $result = slotSkillSetup('skill-list@example.test', 'SkillList');
    $session = slotSkillSession($guidance, 'EX-SKILL-L1');
    slotSkillOpenReservation($guidance, $session);

    slotSkillLogin($result);
    $start = slotSkillStart();

    $this->actingAs(User::query()->where('email', 'skill-list@example.test')->firstOrFail());

    $response = slotSkillInvoke($start['grant_id'], 'exams.slots.list@1', [], $start['conversation_id'])
        ->assertOk()
        ->json();

    expect($response['result']['mode'])->toBe('open')
        ->and($response['result']['slots'])->not->toBeEmpty()
        ->and($response['result']['slots'][0]['id'])->toBe($session->id);

    $grant = NexiTaskGrant::query()->findOrFail($start['grant_id']);
    expect($grant->scope_bounds['cursor'])->toBe(ApplicantChooseSlotSkill::CURSOR_SLOTS_LOADED);
});

test('skill propose never writes; confirm reserves idempotently', function (): void {
    seedE2Foundation();
    $guidance = slotSkillGuidance();
    $result = slotSkillSetup('skill-propose@example.test', 'SkillPropose');
    $session = slotSkillSession($guidance, 'EX-SKILL-P1');
    slotSkillOpenReservation($guidance, $session);

    slotSkillLogin($result);
    $user = User::query()->where('email', 'skill-propose@example.test')->firstOrFail();
    $start = slotSkillStart();
    $this->actingAs($user);

    // Load slots first — reserve is only legal at slots_loaded cursor.
    slotSkillInvoke($start['grant_id'], 'exams.slots.list@1', [], $start['conversation_id'])->assertOk();

    $propose = slotSkillInvoke($start['grant_id'], 'exams.slot.reserve@1', [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'skill-propose-'.$result['attempt']->id,
    ], $start['conversation_id'])
        ->assertOk()
        ->json();

    expect($propose['result']['mode'])->toBe('propose')
        ->and($propose['proposal']['id'] ?? null)->not->toBeNull()
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(0);

    // Cursor locked while awaiting confirm.
    $grant = NexiTaskGrant::query()->findOrFail($start['grant_id']);
    expect($grant->scope_bounds['cursor'])->toBe(ApplicantChooseSlotSkill::CURSOR_AWAITING_CONFIRM);

    $this->postJson(route('nexi.proposals.confirm', $propose['proposal']['id']), [
        'idempotency_key' => 'skill-confirm-1',
    ])->assertOk();

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1);

    // Same proposal again → already executed, no duplicate.
    $this->postJson(route('nexi.proposals.confirm', $propose['proposal']['id']), [
        'idempotency_key' => 'skill-confirm-2',
    ])->assertStatus(422);

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1);
});

test('cursor gates block further invokes while awaiting confirm', function (): void {
    seedE2Foundation();
    $guidance = slotSkillGuidance();
    $result = slotSkillSetup('skill-cursor@example.test', 'SkillCursor');
    $session = slotSkillSession($guidance, 'EX-SKILL-C1');
    slotSkillOpenReservation($guidance, $session);

    slotSkillLogin($result);
    $user = User::query()->where('email', 'skill-cursor@example.test')->firstOrFail();
    $start = slotSkillStart();
    $this->actingAs($user);

    slotSkillInvoke($start['grant_id'], 'exams.slots.list@1', [], $start['conversation_id'])->assertOk();

    $propose = slotSkillInvoke($start['grant_id'], 'exams.slot.reserve@1', [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
    ], $start['conversation_id'])
        ->assertOk()
        ->json();

    expect($propose['proposal']['id'] ?? null)->not->toBeNull();

    // While awaiting confirm, neither read nor reserve is legal.
    slotSkillInvoke($start['grant_id'], 'exams.slots.list@1', [], $start['conversation_id'])
        ->assertStatus(422)
        ->assertJson(['error' => 'capability_illegal_for_cursor']);

    slotSkillInvoke($start['grant_id'], 'exams.slot.reserve@1', [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
    ], $start['conversation_id'])
        ->assertStatus(422)
        ->assertJson(['error' => 'capability_illegal_for_cursor']);
});

test('skill invoke denies an attempt that is not the actor own', function (): void {
    seedE2Foundation();
    $guidance = slotSkillGuidance();
    $victim = slotSkillSetup('skill-victim@example.test', 'SkillVictim');
    $attacker = slotSkillSetup('skill-attacker@example.test', 'SkillAttacker');
    $session = slotSkillSession($guidance, 'EX-SKILL-V1');
    slotSkillOpenReservation($guidance, $session);

    // Attacker: a real applicant whose own attempt is NOT the victim's.
    slotSkillLogin($attacker);
    $attackerUser = User::query()->where('email', 'skill-attacker@example.test')->firstOrFail();
    $this->actingAs($attackerUser);
    $start = slotSkillStart();

    slotSkillInvoke($start['grant_id'], 'exams.slots.list@1', [], $start['conversation_id'])->assertOk();

    $response = slotSkillInvoke($start['grant_id'], 'exams.slot.reserve@1', [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $victim['attempt']->id,
        'execute' => true,
    ], $start['conversation_id'])
        ->assertOk()
        ->json();

    expect($response['result']['mode'])->toBe('denied')
        ->and($response['result']['reason'])->toBe('not_own_attempt')
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $victim['attempt']->id)->count())->toBe(0);
});

test('assistant turn proposes slot reservation with skill events', function (): void {
    seedE2Foundation();
    $guidance = slotSkillGuidance();
    $result = slotSkillSetup('skill-turn@example.test', 'SkillTurn');
    $session = slotSkillSession($guidance, 'EX-SKILL-T1');
    slotSkillOpenReservation($guidance, $session);

    slotSkillLogin($result);
    $user = User::query()->where('email', 'skill-turn@example.test')->firstOrFail();
    $start = slotSkillStart();

    // Load slots first so reserve is legal at slots_loaded cursor.
    $this->actingAs($user);
    slotSkillInvoke($start['grant_id'], 'exams.slots.list@1', [], $start['conversation_id'])->assertOk();

    $this->actingAs($user)
        ->postJson(route('nexi.turns.store'), [
            'message' => "reserve slot {$session->id} for attempt {$result['attempt']->id}",
            'idempotency_key' => 'skill-turn-1',
            'page_pack_key' => 'applicant.schedule@1',
            'page_pack_version' => 1,
            'mode' => 'assistant',
            'conversation_id' => $start['conversation_id'],
        ])->assertAccepted();

    $run = NexiRun::query()->where('conversation_id', $start['conversation_id'])->latest('created_at')->firstOrFail();
    $types = NexiRunEvent::query()
        ->where('run_id', $run->id)
        ->orderBy('seq')
        ->pluck('type')
        ->all();

    expect($types)->toContain('skill.plan')
        ->and($types)->toContain('skill.tool')
        ->and($types)->toContain('skill.result')
        ->and($types)->toContain('skill.proposal')
        ->and($types)->toContain('assistant.message');

    $proposalEvent = NexiRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'skill.proposal')
        ->firstOrFail();

    $proposalId = $proposalEvent->payload['proposal_id'] ?? null;
    expect($proposalId)->not->toBeNull();

    $this->actingAs($user)
        ->postJson(route('nexi.proposals.confirm', $proposalId), [
            'idempotency_key' => 'skill-turn-confirm-1',
        ])
        ->assertOk();

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1);
});

test('degraded manual calendar reserve still works when Nexi disabled', function (): void {
    seedE2Foundation();
    $guidance = slotSkillGuidance();
    $result = slotSkillSetup('skill-manual@example.test', 'SkillManual');
    $session = slotSkillSession($guidance, 'EX-SKILL-M1');
    slotSkillOpenReservation($guidance, $session);

    config(['nexi.enabled' => false]);

    slotSkillLogin($result);

    $this->get(route('applicant.schedule'))
        ->assertOk();

    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'skill-manual-'.$result['attempt']->id,
    ])->assertRedirect(route('applicant.schedule'));

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1);
});
