<?php

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Models\Ai\NexiProposal;
use App\Models\Ai\NexiTaskGrant;

beforeEach(function (): void {
    config(['nexi.process_inline' => true, 'nexi.ai.gateway' => 'fake', 'nexi.enabled' => true]);
});

function os8GuidanceUser(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function os8CreateDraftExamSession(User $guidance, string $code = 'EX-AI-1'): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
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
            ->where('nexi.session.page_pack.key', 'staff.exams.sessions.show@1')
            ->where('nexi.session.modes', fn ($modes) => collect($modes)->contains('assistant'))
            ->where('nexi.session.availability', 'assistant_available')
            ->where('nexi.session.manual_fallbacks', fn ($intents) => collect($intents)->contains(
                fn ($i) => ($i['type'] ?? null) === 'skill_start'
                    && ($i['skill_key'] ?? null) === 'schedule.exam_session@1'
            ))
        );
});

test('skill allowlist rejects off-skill capability even when actor holds unrelated caps', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();

    $start = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.index@1',
            'page_pack_version' => 1,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
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
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-MUT');

    $start = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
            'conversation_id' => $start['conversation_id'],
        ])
        ->assertOk();

    $openPropose = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
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
        ->postJson(route('nexi.proposals.confirm', $openPropose['proposal']['id']), [
            'idempotency_key' => 'ai-open-1',
        ])
        ->assertOk();

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);

    $accepted = submitSyntheticApplicant('ai.exam.assistant@example.test');
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $accepted['attempt']), ['expected_version' => 1]);

    $session = $session->fresh();
    $assignPropose = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
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
        ->postJson(route('nexi.proposals.confirm', $assignPropose['proposal']['id']), [
            'idempotency_key' => 'ai-assign-1',
        ])
        ->assertOk();

    $session = $session->fresh();
    $publishPropose = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
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

    $this->actingAs($guidance)
        ->postJson(route('nexi.proposals.confirm', $publishPropose['proposal']['id']), [
            'idempotency_key' => 'ai-publish-1',
        ])
        ->assertOk();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED);
});

test('stale session version invalidates proposal on confirm', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-STALE');

    $start = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
        ])
        ->assertOk();

    $propose = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
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
        ->postJson(route('nexi.proposals.confirm', $propose['proposal']['id']), [
            'idempotency_key' => 'ai-stale-1',
        ])
        ->assertStatus(422);

    expect(NexiProposal::query()->findOrFail($propose['proposal']['id'])->status)->toBe('invalidated');
});

test('cross-user proposal confirm is denied', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    $other = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-XUSER');

    $start = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
            'grant_id' => $start['grant_id'],
            'capability_id' => 'exams.session.show@1',
            'input' => ['session_id' => $session->id],
        ])
        ->assertOk();

    $propose = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.invoke'), [
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
        ->postJson(route('nexi.proposals.confirm', $propose['proposal']['id']), [
            'idempotency_key' => 'ai-xuser-1',
        ])
        ->assertForbidden();

    expect(NexiProposal::query()->findOrFail($propose['proposal']['id'])->status)->toBe('proposed');
    expect(NexiTaskGrant::query()->findOrFail($start['grant_id'])->isUsable())->toBeTrue();
});

test('assistant turn proposes open reservation with skill events', function (): void {
    seedE2Foundation();
    $guidance = os8GuidanceUser();
    $session = os8CreateDraftExamSession($guidance, 'EX-AI-TURN');

    $start = $this->actingAs($guidance)
        ->postJson(route('nexi.skills.start'), [
            'skill_key' => 'schedule.exam_session@1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'session_id' => $session->id,
        ])
        ->assertCreated()
        ->json();

    $accepted = $this->withSession(['nexi.active_mode' => 'assistant'])
        ->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), [
            'message' => 'Please open reservation for this session',
            'idempotency_key' => 'ai-turn-open-1',
            'page_pack_key' => 'staff.exams.sessions.show@1',
            'page_pack_version' => 1,
            'mode' => 'assistant',
        ])
        ->assertAccepted()
        ->json();

    $run = \App\Models\Ai\NexiRun::query()->findOrFail($accepted['run_id']);
    $types = \App\Models\Ai\NexiRunEvent::query()
        ->where('run_id', $run->id)
        ->orderBy('seq')
        ->pluck('type')
        ->all();

    expect($types)->toContain('skill.plan')
        ->and($types)->toContain('skill.tool')
        ->and($types)->toContain('skill.result')
        ->and($types)->toContain('skill.proposal')
        ->and($types)->toContain('assistant.message');

    $proposalEvent = \App\Models\Ai\NexiRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'skill.proposal')
        ->firstOrFail();

    $proposalId = $proposalEvent->payload['proposal_id'] ?? null;
    expect($proposalId)->not->toBeNull();

    $this->actingAs($guidance)
        ->postJson(route('nexi.proposals.confirm', $proposalId), [
            'idempotency_key' => 'ai-turn-confirm-1',
        ])
        ->assertOk();

    expect($session->fresh()->booking_mode)->toBe(\App\Models\Examination\ExamSession::BOOKING_OPEN_FOR_RESERVATION);
});
