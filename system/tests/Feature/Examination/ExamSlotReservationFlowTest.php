<?php

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Notifications\NotificationDelivery;
use App\Models\User;

function acceptApplicant(string $email, string $lastName = 'Applicant'): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function guidanceUser(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function loginAsApplicant(array $result): void
{
    if (auth()->check()) {
        test()->post(route('logout'));
    }

    test()->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.schedule'));
}

function createDraftSession(User $guidance, string $code, ?Room $room = null, int $daysAhead = 10, string $start = '09:00', string $end = '11:00'): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room ??= Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => now()->addDays($daysAhead)->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
        ])
        ->assertRedirect();

    return ExamSession::query()->where('code', $code)->firstOrFail();
}

test('new sessions default to staff_only booking mode', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $session = createDraftSession($guidance, 'EX-DEFAULT-1');

    expect($session->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY);
});

test('unpublish returns published session to draft with reason; cancel is terminal', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $result = acceptApplicant('unpub-cancel@example.test', 'Unpub');
    $session = createDraftSession($guidance, 'EX-UNPUB-1');

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    // NT-8 — the applicant must hold a portal account to receive the publish
    // notification; login creates it.
    loginAsApplicant($result);
    $this->post(route('logout'));

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED);

    $applicant = User::query()->where('email', 'unpub-cancel@example.test')->firstOrFail();
    expect(NotificationDelivery::forUser($applicant)
        ->where('event_type', 'exam.session_published.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->exists())->toBeTrue();

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.unpublish', $session), [
            'expected_version' => $session->version,
            'reason' => 'Fix roster before announce',
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and(ExamAssignment::query()->active()->where('exam_session_id', $session->id)->count())->toBe(1);

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.cancel', $session), [
            'expected_version' => $session->version,
            'reason' => 'Campus closed',
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and(ExamAssignment::query()->active()->where('exam_session_id', $session->id)->count())->toBe(0);
});

test('open reservation lets applicant reserve into assignment; close blocks new reserves', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $result = acceptApplicant('reserve-flow@example.test', 'Reserve');
    $session = createDraftSession($guidance, 'EX-RES-1');

    expect($session->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY);

    loginAsApplicant($result);
    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('page_state', 'waiting_for_reservation_open'));

    $this->post(route('logout'));

    $this->actingAs($guidance)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);

    loginAsApplicant($result);
    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('page_state', 'calendar_open')
            ->has('calendar_days', 1)
            ->where('pipeline.1.status', 'current') // Schedule / reserve
            ->where('pipeline.2.status', 'upcoming')); // seat not dual-Now

    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'reserve-'.$result['attempt']->id.'-'.$session->id,
    ])->assertRedirect(route('applicant.schedule'));

    $assignment = ExamAssignment::query()
        ->active()
        ->where('admission_attempt_id', $result['attempt']->id)
        ->firstOrFail();

    expect($assignment->exam_session_id)->toBe($session->id)
        ->and($assignment->source)->toBe(ExamAssignment::SOURCE_APPLICANT_RESERVED);

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'assigned_published')
            ->where('assignment.source', ExamAssignment::SOURCE_APPLICANT_RESERVED));

    $this->post(route('logout'));

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.close-reservation', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and(ExamAssignment::query()->active()->where('exam_session_id', $session->id)->count())->toBe(1);

    $other = acceptApplicant('reserve-blocked@example.test', 'Blocked');
    loginAsApplicant($other);
    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $other['attempt']->id,
    ])->assertSessionHasErrors('reserve');
});

test('capacity denies reserve when session is full; staff may assign leftovers while open', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $baseRoom = Room::query()->where('code', 'RM-101')->firstOrFail();
    $room = Room::query()->create([
        'institution_id' => $baseRoom->institution_id,
        'code' => 'RM-CAP-1',
        'name' => 'Tiny room',
        'capacity' => 1,
        'is_active' => true,
    ]);
    $session = createDraftSession($guidance, 'EX-CAP-1', $room);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => $session->fresh()->version,
        ])
        ->assertRedirect();

    $a = acceptApplicant('cap-a@example.test', 'CapA');
    $b = acceptApplicant('cap-b@example.test', 'CapB');

    loginAsApplicant($a);
    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $a['attempt']->id,
    ])->assertRedirect(route('applicant.schedule'));

    loginAsApplicant($b);
    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $b['attempt']->id,
    ])->assertSessionHasErrors('reserve');

    $this->post(route('logout'));

    // Full while open — staff assign also denied
    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $b['attempt']->id,
        ])
        ->assertSessionHasErrors('assign');

    // Free the seat, then staff can assign leftover while still open
    $held = ExamAssignment::query()->active()->where('admission_attempt_id', $a['attempt']->id)->firstOrFail();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.remove', [$session, $held]), [
            'reason' => 'Force move',
        ])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $b['attempt']->id,
        ])
        ->assertRedirect();

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $b['attempt']->id)->first())
        ->source->toBe(ExamAssignment::SOURCE_STAFF_ASSIGNED);
});

test('staff cannot silently overwrite an active assignment', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $session = createDraftSession($guidance, 'EX-MOVE-1');
    $a = acceptApplicant('move-a@example.test', 'MoveA');
    $otherSession = createDraftSession($guidance, 'EX-MOVE-2', daysAhead: 11);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $a['attempt']->id,
        ])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $otherSession), [
            'admission_attempt_id' => $a['attempt']->id,
        ])
        ->assertSessionHasErrors('assign');
});

test('open reservation locks draft schedule edits until closed', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $session = createDraftSession($guidance, 'EX-LOCK-1');

    $this->actingAs($guidance)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    $session->refresh();
    $this->actingAs($guidance)
        ->put(route('exams.sessions.update', $session), [
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
            'code' => $session->code,
            'date' => now()->addDays(12)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'expected_version' => $session->version,
        ])
        ->assertSessionHasErrors('schedule');
});

test('capability deny blocks open reservation', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $session = createDraftSession($guidance, 'EX-DENY-1');
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => $session->version,
        ])
        ->assertForbidden();
});

test('accepted demo applicant sees waiting or calendar schedule states', function (): void {
    seedE2Foundation();
    $this->seed(\Database\Seeders\JourneyFixtureSeeder::class);

    $demo = config('seed.applicants.accepted');
    expect($demo)->not->toBeNull();

    if (auth()->check()) {
        $this->post(route('logout'));
    }

    $this->post(route('applicant.login.store'), [
        'access_key' => $demo['access_key'],
        'birthdate' => $demo['birthdate'],
    ])->assertRedirect(route('applicant.schedule'));

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('page_state', fn ($state) => in_array($state, [
                'waiting_for_reservation_open',
                'calendar_open',
                'assigned_published',
            ], true)));
});

test('staff can reassign a reserved seat to another session; bulk path moves multiple', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $from = createDraftSession($guidance, 'EX-REFROM-1', daysAhead: 10);
    $to = createDraftSession($guidance, 'EX-RETO-1', daysAhead: 11);

    $a = acceptApplicant('reassign-a@example.test', 'ReA');
    $b = acceptApplicant('reassign-b@example.test', 'ReB');

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $from), ['admission_attempt_id' => $a['attempt']->id])
        ->assertRedirect();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $from), ['admission_attempt_id' => $b['attempt']->id])
        ->assertRedirect();

    $assignmentA = ExamAssignment::query()->active()->where('admission_attempt_id', $a['attempt']->id)->firstOrFail();
    $assignmentB = ExamAssignment::query()->active()->where('admission_attempt_id', $b['attempt']->id)->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.reassign', $from), [
            'assignment_ids' => [$assignmentA->id, $assignmentB->id],
            'target_session_id' => $to->id,
            'reason' => 'Room conflict',
        ])
        ->assertRedirect();

    expect(ExamAssignment::query()->active()->where('exam_session_id', $from->id)->count())->toBe(0)
        ->and(ExamAssignment::query()->active()->where('exam_session_id', $to->id)->count())->toBe(2)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $a['attempt']->id)->first()->source)
        ->toBe(ExamAssignment::SOURCE_STAFF_ASSIGNED);
});

test('applicant portal home redirects to status schedule', function (): void {
    seedE2Foundation();
    $result = acceptApplicant('home-redirect@example.test', 'Home');
    loginAsApplicant($result);

    $this->get(route('applicant.portal'))
        ->assertRedirect(route('applicant.schedule'));
});
