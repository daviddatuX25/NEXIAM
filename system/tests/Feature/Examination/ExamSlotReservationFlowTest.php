<?php

use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Examination\Actions\ReserveExamSlot;
use App\Examination\Support\ActionExecutionIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Database\Seeders\JourneyFixtureSeeder;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    ])->assertRedirect(route('applicant.portal'));
}

function createDraftSession(
    User $guidance,
    string $code,
    ?Room $room = null,
    int $daysAhead = 10,
    string $start = '09:00',
    string $end = '11:00',
    ?int $bookletVersionId = null,
    ?int $seatingArrangementId = null,
): ExamSession {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room ??= Room::query()->where('code', 'RM-101')->firstOrFail();
    $payload = [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'date' => now()->addDays($daysAhead)->toDateString(),
        'start_time' => $start,
        'end_time' => $end,
    ];
    if ($bookletVersionId !== null) {
        $payload['booklet_version_id'] = $bookletVersionId;
    }
    if ($seatingArrangementId !== null) {
        $payload['seating_arrangement_id'] = $seatingArrangementId;
    }

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), $payload)
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
    seedE2OmrFoundation();
    $guidance = guidanceUser();
    $result = acceptApplicant('unpub-cancel@example.test', 'Unpub');
    $booklet = canonicalGradingBooklet($guidance);
    $arrangement = SeatingArrangement::query()->where('is_active', true)->firstOrFail();
    $session = createDraftSession(
        $guidance,
        'EX-UNPUB-1',
        bookletVersionId: $booklet->booklet_version_id,
        seatingArrangementId: $arrangement->id,
    );

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
    ])->assertRedirect(route('applicant.schedule'))->assertSessionHasNoErrors();

    $assignment = ExamAssignment::query()
        ->active()
        ->where('admission_attempt_id', $result['attempt']->id)
        ->firstOrFail();

    expect($assignment->exam_session_id)->toBe($session->id)
        ->and($assignment->source)->toBe(ExamAssignment::SOURCE_APPLICANT_RESERVED)
        ->and($assignment->origin)->toBe(ExamAssignment::ORIGIN_RESERVATION);

    $delivery = NotificationDelivery::query()
        ->where('event_type', 'exam.slot_reserved.v1')
        ->where('recipient_id', $result['attempt']->applicant_identity_id)
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->sole();
    expect($delivery->payload['event_resource']['assignment_id'])->toBe($assignment->id)
        ->and($delivery->payload['portal']['body'])->toContain($session->code, $session->starts_at->format('F j, Y'))
        ->and($delivery->payload['portal']['body'])->not->toContain('{{');

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'assigned_tentative')
            ->where('assignment.session.status', ExamSession::STATUS_DRAFT)
            ->where('assignment.session.published', false)
            ->where('pipeline.3.status', 'current')
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

test('publishing closes self-reservation while preserving reservations made in draft', function (): void {
    seedE2Foundation();
    seedE2OmrFoundation();
    $guidance = guidanceUser();
    $assigned = acceptApplicant('frozen-reservation-assigned@example.test', 'Assigned');
    $reserving = acceptApplicant('frozen-reservation-next@example.test', 'Next');
    $blocked = acceptApplicant('frozen-reservation-blocked@example.test', 'Blocked');
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $room->update(['capacity' => 2]);
    $booklet = canonicalGradingBooklet($guidance);
    $session = createDraftSession($guidance, 'EX-FROZEN-RESERVATION', $room, bookletVersionId: $booklet->booklet_version_id);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $assigned['attempt']->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
    $session->refresh();
    $this->post(route('exams.sessions.open-reservation', $session), [
        'expected_version' => $session->version,
    ])->assertRedirect()->assertSessionHasNoErrors();

    loginAsApplicant($reserving);
    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $reserving['attempt']->id,
        'idempotency_key' => 'frozen-reservation-next',
    ])->assertRedirect(route('applicant.schedule'))->assertSessionHasNoErrors();
    $reservation = ExamAssignment::query()->active()
        ->where('admission_attempt_id', $reserving['attempt']->id)->sole();
    $this->post(route('logout'));

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->version,
        ])->assertRedirect()->assertSessionHasNoErrors();
    $session->refresh();
    $snapshot = ExamSessionMaterialSnapshot::query()
        ->where('exam_session_id', $session->id)->where('is_current', true)->firstOrFail();
    $originalAllocation = ExamAssignment::query()->active()
        ->where('admission_attempt_id', $assigned['attempt']->id)->firstOrFail()->activeSeatAllocation;
    $reservation = $reservation->fresh();
    $allocation = $reservation->activeSeatAllocation;
    $setA = $snapshot->sets()->where('set_code', 'A')->sole();

    expect($session->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($session->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and($session->auto_assign_enabled)->toBeFalse()
        ->and($session->reservation_closed_at)->not->toBeNull()
        ->and($snapshot->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($snapshot->capacity_limit)->toBe(2)
        ->and($snapshot->seats()->count())->toBe(2)
        ->and($reservation->source)->toBe(ExamAssignment::SOURCE_APPLICANT_RESERVED)
        ->and($allocation)->not->toBeNull()
        ->and($allocation->effective_to)->toBeNull()
        ->and($allocation->exam_session_seat_id)->not->toBe($originalAllocation->exam_session_seat_id)
        ->and($allocation->sessionSeat->material_snapshot_id)->toBe($snapshot->id)
        ->and($allocation->sessionSeat->booklet_set_id)->toBe($setA->booklet_set_id);

    $room->update(['capacity' => 0]);
    loginAsApplicant($blocked);
    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $blocked['attempt']->id,
        'idempotency_key' => 'frozen-reservation-blocked',
    ])->assertSessionHasErrors('reserve');
    expect($session->assignments()->active()->count())->toBe(2)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $blocked['attempt']->id)->exists())->toBeFalse()
        ->and($snapshot->fresh()->capacity_limit)->toBe(2);
});

test('confirmed open and assignment actions replay their original receipts', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $session = createDraftSession($guidance, 'EX-RECEIPT-STAFF');
    $openAction = app(OpenExamSessionReservation::class);
    $openIdentity = new ActionExecutionIdentity(
        executionId: (string) Str::uuid(),
        capabilityId: 'exams.session.reservation.open@1',
        canonicalDigest: str_repeat('a', 64),
    );

    $opened = $openAction->handleConfirmed($session, $guidance, (int) $session->version, 'Assistant confirmed', $openIdentity);
    $openedVersion = (int) $session->fresh()->version;
    $openedAuditCount = DB::table('audit_events')
        ->where('event_type', 'exam.session_reservation_opened')
        ->where('payload->exam_session_id', $session->id)
        ->count();
    $openReplay = $openAction->handleConfirmed(
        $session->fresh(),
        $guidance,
        (int) $session->version,
        'Assistant confirmed',
        $openIdentity,
    );

    expect($openReplay->replayed)->toBeTrue()
        ->and($openReplay->safeResult)->toEqual($opened->safeResult)
        ->and((int) $session->fresh()->version)->toBe($openedVersion)
        ->and($openedAuditCount)->toBe(1)
        ->and(DB::table('audit_events')
            ->where('event_type', 'exam.session_reservation_opened')
            ->where('payload->exam_session_id', $session->id)
            ->count())->toBe($openedAuditCount);

    expect(fn () => $openAction->handleConfirmed(
        $session->fresh(),
        $guidance,
        (int) $session->version,
        'Different reason',
        $openIdentity,
    ))->toThrow(RuntimeException::class, 'action_execution_receipt_conflict');
    $digestMismatch = new ActionExecutionIdentity(
        executionId: $openIdentity->executionId,
        capabilityId: $openIdentity->capabilityId,
        canonicalDigest: str_repeat('e', 64),
    );
    expect(fn () => $openAction->handleConfirmed(
        $session->fresh(),
        $guidance,
        (int) $session->version,
        'Assistant confirmed',
        $digestMismatch,
    ))->toThrow(RuntimeException::class, 'action_execution_receipt_conflict');

    $otherActor = User::factory()->create();
    expect(fn () => $openAction->handleConfirmed(
        $session->fresh(),
        $otherActor,
        (int) $session->version,
        'Assistant confirmed',
        $openIdentity,
    ))->toThrow(RuntimeException::class, 'action_execution_receipt_conflict');

    $failedIdentity = new ActionExecutionIdentity(
        executionId: (string) Str::uuid(),
        capabilityId: 'exams.session.reservation.open@1',
        canonicalDigest: str_repeat('f', 64),
    );
    expect(fn () => $openAction->handleConfirmed(
        $session->fresh(),
        $guidance,
        1,
        'Stale confirmation',
        $failedIdentity,
    ))->toThrow(RuntimeException::class, 'Stale exam session version.')
        ->and(DB::table('examination_action_execution_receipts')
            ->where('execution_id', $failedIdentity->executionId)
            ->count())->toBe(0);

    $accepted = acceptApplicant('assignment-receipt@example.test', 'Assignment');
    $assignAction = app(AssignApplicantToExamSession::class);
    $assignIdentity = new ActionExecutionIdentity(
        executionId: (string) Str::uuid(),
        capabilityId: 'exams.session.assign@1',
        canonicalDigest: str_repeat('b', 64),
    );
    $assigned = $assignAction->handleConfirmed($session->fresh(), $accepted['attempt'], $guidance, $assignIdentity);
    $assignmentAuditCount = DB::table('audit_events')
        ->where('event_type', 'exam.assignment_created')
        ->where('payload->attempt_id', $accepted['attempt']->id)
        ->count();
    $assignmentReplay = $assignAction->handleConfirmed(
        $session->fresh(),
        $accepted['attempt']->fresh(),
        $guidance,
        $assignIdentity,
    );

    expect($assignmentReplay->replayed)->toBeTrue()
        ->and($assignmentReplay->safeResult)->toEqual($assigned->safeResult)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $accepted['attempt']->id)->count())->toBe(1)
        ->and($assignmentAuditCount)->toBe(1)
        ->and(DB::table('audit_events')
            ->where('event_type', 'exam.assignment_created')
            ->where('payload->attempt_id', $accepted['attempt']->id)
            ->count())->toBe($assignmentAuditCount);

    $other = acceptApplicant('assignment-receipt-conflict@example.test', 'Conflict');
    expect(fn () => $assignAction->handleConfirmed(
        $session->fresh(),
        $other['attempt'],
        $guidance,
        $assignIdentity,
    ))->toThrow(RuntimeException::class, 'action_execution_receipt_conflict');
});

test('applicant reservation receipt replay does not repeat assignment or notification', function (): void {
    seedE2Foundation();
    $guidance = guidanceUser();
    $session = createDraftSession($guidance, 'EX-RECEIPT-APPLICANT');
    $accepted = acceptApplicant('reservation-receipt@example.test', 'Reservation');
    app(OpenExamSessionReservation::class)->handle($session, $guidance, (int) $session->version);

    loginAsApplicant($accepted);
    $applicant = auth()->user();
    expect($applicant)->toBeInstanceOf(User::class);

    $action = app(ReserveExamSlot::class);
    $identity = new ActionExecutionIdentity(
        executionId: (string) Str::uuid(),
        capabilityId: 'exams.slot.reserve@1',
        canonicalDigest: str_repeat('c', 64),
    );
    $first = $action->handleConfirmed(
        $session->fresh(),
        $accepted['attempt'],
        $applicant,
        'reservation-receipt-key',
        $identity,
    );
    $assignmentId = (int) $first->safeResult['assignment_id'];
    $eventId = hash('sha256', "exam.slot_reserved:{$assignmentId}:v1");
    $auditCount = DB::table('audit_events')
        ->where('event_type', 'exam.slot_reserved')
        ->where('payload->assignment_id', $assignmentId)
        ->count();
    $deliveryCount = DB::table('notification_deliveries')->where('event_id', $eventId)->count();

    $replay = $action->handleConfirmed(
        $session->fresh(),
        $accepted['attempt']->fresh(),
        $applicant,
        'reservation-receipt-key',
        $identity,
    );

    expect($replay->replayed)->toBeTrue()
        ->and($replay->safeResult)->toEqual($first->safeResult)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $accepted['attempt']->id)->count())->toBe(1)
        ->and(DB::table('audit_events')
            ->where('event_type', 'exam.slot_reserved')
            ->where('payload->assignment_id', $assignmentId)
            ->count())->toBe($auditCount)
        ->and(DB::table('notification_deliveries')->where('event_id', $eventId)->count())->toBe($deliveryCount);

    expect(fn () => $action->handleConfirmed(
        $session->fresh(),
        $accepted['attempt']->fresh(),
        $applicant,
        'different-idempotency-key',
        $identity,
    ))->toThrow(RuntimeException::class, 'action_execution_receipt_conflict');
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
            'seating_mode' => $session->seating_mode,
            'seating_arrangement_id' => $session->seating_arrangement_id,
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
    (new PublicIntakeConsentDemoSeeder)->run();
    $this->seed(JourneyFixtureSeeder::class);

    $demo = config('seed.applicants.accepted');
    expect($demo)->not->toBeNull();

    if (auth()->check()) {
        $this->post(route('logout'));
    }

    $this->post(route('applicant.login.store'), [
        'access_key' => $demo['access_key'],
        'birthdate' => $demo['birthdate'],
    ])->assertRedirect(route('applicant.portal'));

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('page_state', fn ($state) => in_array($state, [
                'waiting_for_reservation_open',
                'calendar_open',
                'assigned_tentative',
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

    $response = $this->actingAs($guidance)
        ->post(route('exams.sessions.reassign', $from), [
            'assignment_ids' => [$assignmentA->id, $assignmentB->id],
            'target_session_id' => $to->id,
            'reason' => 'Room conflict',
        ]);
    $response->assertRedirect()->assertSessionDoesntHaveErrors();

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
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('page_state', 'ready'));
});
