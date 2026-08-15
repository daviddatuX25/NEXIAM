<?php

use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\User;

function acceptConsultationApplicant(string $email, string $lastName = 'Applicant'): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function consultationGuidanceUser(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function loginConsultationApplicant(array $result): void
{
    if (auth()->check()) {
        test()->post(route('logout'));
    }

    test()->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.schedule'));
}

function createConsultationDraftSession(
    User $guidance,
    string $code,
    ?Room $room = null,
    int $daysAhead = 10,
    string $bookingMode = ConsultationSession::BOOKING_STAFF_ONLY,
): ConsultationSession {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room ??= Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('consultation.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'scheduled_at' => now()->addDays($daysAhead)->format('Y-m-d H:i:s'),
            'booking_mode' => $bookingMode,
        ])
        ->assertRedirect();

    return ConsultationSession::query()->where('code', $code)->firstOrFail();
}

function makeConsultationResultReady(array $result): void
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-RDY-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $result['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);

    ResultDocument::query()->create([
        'admission_attempt_id' => $result['attempt']->id,
        'template_version' => 'e2-v1',
        'checksum' => hash('sha256', 'doc-'.$result['attempt']->id),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/test-'.$result['attempt']->id.'.html',
        'preview_path' => 'results/test-'.$result['attempt']->id.'.html',
    ]);
}

function publishConsultationSession(User $guidance, ConsultationSession $session): ConsultationSession
{
    $session->refresh();
    test()->actingAs($guidance)
        ->post(route('consultation.sessions.publish', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    return $session->fresh();
}

test('create requires an explicit booking mode; both modes are legal', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'CONS-NOMODE-1',
            'scheduled_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ])
        ->assertSessionHasErrors('booking_mode');

    createConsultationDraftSession($guidance, 'CONS-MODE-STAFF', bookingMode: ConsultationSession::BOOKING_STAFF_ONLY);
    expect(ConsultationSession::query()->where('code', 'CONS-MODE-STAFF')->first()->booking_mode)
        ->toBe(ConsultationSession::BOOKING_STAFF_ONLY);

    createConsultationDraftSession($guidance, 'CONS-MODE-OPEN', bookingMode: ConsultationSession::BOOKING_OPEN_FOR_RESERVATION);
    expect(ConsultationSession::query()->where('code', 'CONS-MODE-OPEN')->first()->booking_mode)
        ->toBe(ConsultationSession::BOOKING_OPEN_FOR_RESERVATION);
});

test('assign is blocked until the attempt is result-ready, then allowed', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-GATE-1');
    $result = acceptConsultationApplicant('cons-gate@example.test', 'Gate');

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertSessionHasErrors('assign');

    makeConsultationResultReady($result);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    expect(ConsultationAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->first())
        ->source->toBe(ConsultationAssignment::SOURCE_STAFF_ASSIGNED);
});

test('publish requires at least one assignment; lifecycle runs publish/unpublish/complete', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-LC-1');
    $result = acceptConsultationApplicant('cons-lc@example.test', 'Life');
    makeConsultationResultReady($result);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.publish', $session), ['expected_version' => $session->version])
        ->assertSessionHasErrors('publish');

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $result['attempt']->id])
        ->assertRedirect();

    $session = publishConsultationSession($guidance, $session);
    expect($session->status)->toBe(ConsultationSession::STATUS_PUBLISHED);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.unpublish', $session), [
            'expected_version' => $session->version,
            'reason' => 'Fix roster',
        ])
        ->assertRedirect();
    expect($session->fresh()->status)->toBe(ConsultationSession::STATUS_DRAFT);

    $session = publishConsultationSession($guidance, $session);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.complete', $session), ['expected_version' => $session->version])
        ->assertRedirect();
    expect($session->fresh()->status)->toBe(ConsultationSession::STATUS_COMPLETED)
        ->and(ConsultationAssignment::query()->active()->where('consultation_session_id', $session->id)->count())->toBe(1);
});

test('open reservation lets applicant reserve into assignment; close blocks new reserves', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-RES-1');
    $filler = acceptConsultationApplicant('cons-res-filler@example.test', 'Filler');
    makeConsultationResultReady($filler);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $filler['attempt']->id])
        ->assertRedirect();

    $session = publishConsultationSession($guidance, $session);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();
    expect($session->fresh()->booking_mode)->toBe(ConsultationSession::BOOKING_OPEN_FOR_RESERVATION);

    $result = acceptConsultationApplicant('cons-res@example.test', 'Reserve');
    makeConsultationResultReady($result);

    loginConsultationApplicant($result);
    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Consultation')
            ->where('page_state', 'booking_open')
            ->where('sessions.0.code', 'CONS-RES-1'));

    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'cons-res-'.$result['attempt']->id.'-'.$session->id,
    ])->assertRedirect(route('applicant.consultation'));

    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'assigned_or_reserved')
            ->where('assignment.source', ConsultationAssignment::SOURCE_APPLICANT_RESERVED));

    $this->post(route('logout'));

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.close', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    $other = acceptConsultationApplicant('cons-blocked@example.test', 'Blocked');
    makeConsultationResultReady($other);
    loginConsultationApplicant($other);
    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $other['attempt']->id,
    ])->assertSessionHasErrors('reserve');
});

test('reserve requires a published session even when booking is open', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-UNPUB-1');
    $result = acceptConsultationApplicant('cons-unpub@example.test', 'Unpub');
    makeConsultationResultReady($result);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    loginConsultationApplicant($result);
    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
    ])->assertSessionHasErrors('reserve');
});

test('capacity denies reserve when session is full; staff assignment denied too', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $baseRoom = Room::query()->where('code', 'RM-101')->firstOrFail();
    $room = Room::query()->create([
        'institution_id' => $baseRoom->institution_id,
        'code' => 'CONS-CAP-1',
        'name' => 'Tiny consultation room',
        'capacity' => 1,
        'is_active' => true,
    ]);
    $session = createConsultationDraftSession($guidance, 'CONS-CAP-1', $room);
    $a = acceptConsultationApplicant('cons-cap-a@example.test', 'CapA');
    $b = acceptConsultationApplicant('cons-cap-b@example.test', 'CapB');
    makeConsultationResultReady($a);
    makeConsultationResultReady($b);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $a['attempt']->id])
        ->assertRedirect();

    $session = publishConsultationSession($guidance, $session);
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    loginConsultationApplicant($b);
    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $b['attempt']->id,
    ])->assertSessionHasErrors('reserve');

    $this->post(route('logout'));

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $b['attempt']->id])
        ->assertSessionHasErrors('assign');

    $held = ConsultationAssignment::query()->active()->where('admission_attempt_id', $a['attempt']->id)->firstOrFail();
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assignments.cancel', [$session, $held]), ['reason' => 'Force move'])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $b['attempt']->id])
        ->assertRedirect();

    expect(ConsultationAssignment::query()->active()->where('admission_attempt_id', $b['attempt']->id)->first())
        ->source->toBe(ConsultationAssignment::SOURCE_STAFF_ASSIGNED);
});

test('staff cannot silently overwrite an active assignment', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-MOVE-1');
    $otherSession = createConsultationDraftSession($guidance, 'CONS-MOVE-2', daysAhead: 11);
    $a = acceptConsultationApplicant('cons-move-a@example.test', 'MoveA');
    makeConsultationResultReady($a);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $a['attempt']->id])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $otherSession), ['admission_attempt_id' => $a['attempt']->id])
        ->assertSessionHasErrors('assign');
});

test('open reservation locks draft schedule edits until closed', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-LOCK-1');

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    $session->refresh();
    $this->actingAs($guidance)
        ->put(route('consultation.sessions.update', $session), [
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
            'code' => $session->code,
            'scheduled_at' => now()->addDays(12)->format('Y-m-d H:i:s'),
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'expected_version' => $session->version,
        ])
        ->assertSessionHasErrors('schedule');
});

test('capability deny: staff without PLAN cannot manage consultation sessions', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('consultation.sessions.index'))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->get(route('consultation.sessions.create'))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('consultation.sessions.store'), [
            'academic_cycle_id' => 1,
            'room_id' => 1,
            'code' => 'CONS-DENY-1',
            'scheduled_at' => now()->format('Y-m-d H:i:s'),
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        ])
        ->assertForbidden();
});

test('ready pool lists result-ready applicants only', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-POOL-1');
    $ready = acceptConsultationApplicant('cons-pool-ready@example.test', 'Ready');
    acceptConsultationApplicant('cons-pool-not@example.test', 'NotReady');
    makeConsultationResultReady($ready);

    $this->actingAs($guidance)
        ->get(route('consultation.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultation/ConsultationSessionShow')
            ->where('ready_pool', fn ($pool) => $pool->count() === 1 && $pool->first()['id'] === $ready['attempt']->id)
            ->where('is_publishable', false));
});

test('reassign moves a seat to another session and keeps history', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $from = createConsultationDraftSession($guidance, 'CONS-RE-1');
    $to = createConsultationDraftSession($guidance, 'CONS-RE-2', daysAhead: 11);
    $a = acceptConsultationApplicant('cons-re-a@example.test', 'ReA');
    makeConsultationResultReady($a);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $from), ['admission_attempt_id' => $a['attempt']->id])
        ->assertRedirect();

    $assignment = ConsultationAssignment::query()->active()->where('admission_attempt_id', $a['attempt']->id)->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reassign', $from), [
            'target_session_id' => $to->id,
            'assignment_ids' => [$assignment->id],
            'reason' => 'Move to later session',
        ])
        ->assertRedirect();

    expect(ConsultationAssignment::query()->active()->where('admission_attempt_id', $a['attempt']->id)->first())
        ->consultation_session_id->toBe($to->id);

    expect(ConsultationAssignment::query()->where('id', $assignment->id)->first())
        ->status->toBe(ConsultationAssignment::STATUS_CANCELLED)
        ->and(ConsultationAssignment::query()->where('id', $assignment->id)->first()->cancel_reason)->toBe('Move to later session');
});

test('cancel session is terminal and cancels active assignments', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-CANCEL-1');
    $a = acceptConsultationApplicant('cons-cancel-a@example.test', 'CancelA');
    makeConsultationResultReady($a);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $a['attempt']->id])
        ->assertRedirect();

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.cancel', $session), [
            'expected_version' => $session->version,
            'reason' => 'Venue unavailable',
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ConsultationSession::STATUS_CANCELLED)
        ->and(ConsultationAssignment::query()->active()->where('consultation_session_id', $session->id)->count())->toBe(0);
});

test('idempotent reserve reuses the existing assignment', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-IDEM-1');
    $result = acceptConsultationApplicant('cons-idem@example.test', 'Idem');
    makeConsultationResultReady($result);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $result['attempt']->id])
        ->assertRedirect();

    $session = publishConsultationSession($guidance, $session);
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    // Applicant already holds an active assignment on this session — the guard
    // reuses it idempotently; source stays staff_assigned, no second row.
    loginConsultationApplicant($result);
    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'cons-idem-key-1',
    ])->assertRedirect(route('applicant.consultation'));

    expect(ConsultationAssignment::query()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1)
        ->and(ConsultationAssignment::query()->where('admission_attempt_id', $result['attempt']->id)->first()->source)
        ->toBe(ConsultationAssignment::SOURCE_STAFF_ASSIGNED);
});

test('portal shows not_result_ready until grading and document are ready', function (): void {
    seedE2Foundation();
    $result = acceptConsultationApplicant('cons-portal-notready@example.test', 'NotReady');

    loginConsultationApplicant($result);
    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Consultation')
            ->where('page_state', 'not_result_ready'));
});

test('portal shows no_open_sessions when nothing is published and open', function (): void {
    seedE2Foundation();
    $result = acceptConsultationApplicant('cons-portal-closed@example.test', 'Closed');
    makeConsultationResultReady($result);

    loginConsultationApplicant($result);
    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Consultation')
            ->where('page_state', 'no_open_sessions'));
});

test('portal denies a staff account without an applicant identity', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Consultation')
            ->where('page_state', 'denied'));
});

test('reserve denies an attempt that is not the applicants own', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-OWN-1');
    $owner = acceptConsultationApplicant('cons-own-a@example.test', 'OwnA');
    $other = acceptConsultationApplicant('cons-own-b@example.test', 'OwnB');
    makeConsultationResultReady($owner);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $owner['attempt']->id])
        ->assertRedirect();

    $session = publishConsultationSession($guidance, $session);
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    loginConsultationApplicant($other);
    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $owner['attempt']->id,
    ])->assertForbidden();
});
