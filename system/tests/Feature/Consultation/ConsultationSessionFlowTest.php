<?php

use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';

beforeEach(function (): void {
    $this->withoutVite();
});

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
    ])->assertRedirect(route('applicant.portal'));
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

function recordCompleteConsultationScoring(TestCase $test, array $result): void
{
    static $scoringFixtures;
    $scoringFixtures ??= new WeakMap;

    $attempt = $result['attempt'];
    $guidance = consultationGuidanceUser();
    if (! isset($scoringFixtures[$test])) {
        $guidance->givePermissionTo([
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        ]);
        $profile = createTestApprovedProfileVersion($test, $guidance, 'CONSULTATION-FLOW-'.Str::upper(Str::random(10)));
        $scoringFixtures[$test] = [
            'kit_version' => createTestActiveKitVersion($guidance, $profile),
        ];
    }

    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $assessment = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: [(int) $attempt->id],
        actor: $guidance,
        label: 'Consultation individual scoring '.$attempt->reference_code,
        kitVersion: $scoringFixtures[$test]['kit_version'],
    );
    app(RecordScores::class)->handle(
        gradingSession: $assessment['grading_session'],
        attempt: $attempt,
        scores: [[
            'metric_code' => 'RAW_A',
            'typed_outputs' => [
                'percentile_rank' => '82',
                'stanine' => '7',
            ],
            'external_provenance' => 'Synthetic software-test external final; no institutional result claim.',
        ]],
        actor: $guidance,
        idempotencyKey: 'consultation-flow-individual-score-'.$attempt->id,
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $scoringFixtures[$test]['kit_version']->id,
    );
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

test('assign is blocked until individual scoring is complete, then allowed', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-GATE-1');
    $result = acceptConsultationApplicant('cons-gate@example.test', 'Gate');

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertSessionHasErrors('assign');

    recordCompleteConsultationScoring($this, $result);

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
    recordCompleteConsultationScoring($this, $result);

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
    recordCompleteConsultationScoring($this, $filler);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $filler['attempt']->id])
        ->assertRedirect();

    $session->refresh();

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();
    expect($session->fresh()->booking_mode)->toBe(ConsultationSession::BOOKING_OPEN_FOR_RESERVATION);

    $result = acceptConsultationApplicant('cons-res@example.test', 'Reserve');
    recordCompleteConsultationScoring($this, $result);

    loginConsultationApplicant($result);
    expect(route('applicant.consultation', [], false))->toBe('/applicant/release-schedule')
        ->and(route('applicant.consultation.reserve', [], false))->toBe('/applicant/release-schedule/reserve');
    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ReleaseSchedule')
            ->where('page_state', 'booking_open')
            ->where('sessions.0.code', 'CONS-RES-1')
            ->has('calendar_days'));

    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'cons-res-'.$result['attempt']->id.'-'.$session->id,
    ])->assertRedirect('/applicant/release-schedule');

    expect(ConsultationAssignment::query()
        ->active()
        ->where('admission_attempt_id', $result['attempt']->id)
        ->firstOrFail()
        ->origin)->toBe(ConsultationAssignment::ORIGIN_RESERVATION);

    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'assigned_or_reserved')
            ->where('assignment.source', ConsultationAssignment::SOURCE_APPLICANT_RESERVED)
            ->where('assignment.session.status', ConsultationSession::STATUS_DRAFT)
            ->where('assignment.session.published', false));

    $this->post(route('logout'));

    $session->refresh();
    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.close', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    $other = acceptConsultationApplicant('cons-blocked@example.test', 'Blocked');
    recordCompleteConsultationScoring($this, $other);
    loginConsultationApplicant($other);
    $this->post(route('applicant.consultation.reserve'), [
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $other['attempt']->id,
    ])->assertSessionHasErrors('reserve');
});

test('reserve rejects a published session even when booking is open', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-UNPUB-1');
    $result = acceptConsultationApplicant('cons-unpub@example.test', 'Unpub');
    recordCompleteConsultationScoring($this, $result);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.reservation.open', $session), ['expected_version' => $session->version])
        ->assertRedirect();

    $session->update(['status' => ConsultationSession::STATUS_PUBLISHED]);

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
    recordCompleteConsultationScoring($this, $a);
    recordCompleteConsultationScoring($this, $b);

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
    recordCompleteConsultationScoring($this, $a);

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

test('Release Day create page provides suggestions only for offered institution cycles', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    ConsultationSession::factory()->for($cycle, 'academicCycle')->create([
        'code' => 'Release-07',
        'status' => ConsultationSession::STATUS_CANCELLED,
    ]);

    $this->actingAs($guidance)
        ->get(route('consultation.sessions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultation/ConsultationSessionCreate')
            ->where('session_code_suggestions.'.$cycle->id, 'Release-08')
        );
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

test('auto-assign toggle requires the consultation planning capability', function (): void {
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $session = createConsultationDraftSession(consultationGuidanceUser(), 'CONS-TOGGLE-RELEASE-ONLY');
    $releaseOnly = User::factory()->create();
    $releaseOnly->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'department_id' => null,
        'program_codes' => [],
    ]);
    $releaseOnly->givePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);

    $releaseOnly->authenticationState()->create([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => false,
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $this->actingAs($releaseOnly)
        ->withSession([
            'auth.surface' => 'staff',
            'auth.session_version' => (int) $releaseOnly->authenticationState->session_version,
        ])
        ->patch(route('consultation.sessions.toggle-auto-assign', $session), [
            'auto_assign_enabled' => true,
        ])
        ->assertForbidden();

    expect($session->fresh()->auto_assign_enabled)->toBeFalse();
});

test('consultation session routes hide sessions from another institution', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOR-CONS-SESSION',
        'name' => 'Foreign Consultation University',
        'slug' => 'foreign-consultation-session-university',
        'is_active' => true,
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'FOR-CONS-SESSION-CYCLE',
        'name' => 'Foreign Consultation Cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'school_year' => '2026-2027',
        'semester' => '1',
        'is_active' => true,
    ]);
    $foreignRoom = Room::factory()->create(['institution_id' => $foreignInstitution->id]);
    $foreignSession = ConsultationSession::factory()->create([
        'academic_cycle_id' => $foreignCycle->id,
        'room_id' => $foreignRoom->id,
    ]);

    $this->actingAs($guidance)
        ->get(route('consultation.sessions.show', $foreignSession))
        ->assertNotFound();

    $this->get(route('consultation.sessions.edit', $foreignSession))
        ->assertNotFound();

    $this->patch(route('consultation.sessions.toggle-auto-assign', $foreignSession), [
        'auto_assign_enabled' => true,
    ])->assertNotFound();

    $this->put(route('consultation.sessions.update', $foreignSession), [
        'academic_cycle_id' => $foreignCycle->id,
        'room_id' => $foreignRoom->id,
        'code' => $foreignSession->code,
        'scheduled_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'expected_version' => $foreignSession->version,
    ])->assertNotFound();

    $this->post(route('consultation.sessions.bulk-publish'), [
        'session_ids' => [$foreignSession->id],
    ])->assertNotFound();

    $this->post(route('consultation.sessions.store'), [
        'academic_cycle_id' => $foreignCycle->id,
        'room_id' => $foreignRoom->id,
        'code' => 'CONS-FOREIGN-STORE',
        'scheduled_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
    ])->assertNotFound();
});

test('ready pool lists individually scored applicants without requiring documents', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-POOL-1');
    $ready = acceptConsultationApplicant('cons-pool-ready@example.test', 'Ready');
    acceptConsultationApplicant('cons-pool-not@example.test', 'NotReady');
    recordCompleteConsultationScoring($this, $ready);

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
    recordCompleteConsultationScoring($this, $a);

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
    recordCompleteConsultationScoring($this, $a);

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
    recordCompleteConsultationScoring($this, $result);

    $this->actingAs($guidance)
        ->post(route('consultation.sessions.assign', $session), ['admission_attempt_id' => $result['attempt']->id])
        ->assertRedirect();

    $session->refresh();
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

test('portal blocks appointment scheduling until individual scoring is complete', function (): void {
    seedE2Foundation();
    $result = acceptConsultationApplicant('cons-portal-notready@example.test', 'NotReady');

    loginConsultationApplicant($result);
    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ReleaseSchedule')
            ->where('page_state', 'not_result_ready'));
});

test('portal shows no_open_sessions when nothing is published and open', function (): void {
    seedE2Foundation();
    $result = acceptConsultationApplicant('cons-portal-closed@example.test', 'Closed');
    recordCompleteConsultationScoring($this, $result);

    loginConsultationApplicant($result);
    $this->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ReleaseSchedule')
            ->where('page_state', 'no_open_sessions'));
});

test('portal denies a staff account without an applicant identity', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('applicant.consultation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ReleaseSchedule')
            ->where('page_state', 'denied'));
});

test('reserve denies an attempt that is not the applicants own', function (): void {
    seedE2Foundation();
    $guidance = consultationGuidanceUser();
    $session = createConsultationDraftSession($guidance, 'CONS-OWN-1');
    $owner = acceptConsultationApplicant('cons-own-a@example.test', 'OwnA');
    $other = acceptConsultationApplicant('cons-own-b@example.test', 'OwnB');
    recordCompleteConsultationScoring($this, $owner);

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
