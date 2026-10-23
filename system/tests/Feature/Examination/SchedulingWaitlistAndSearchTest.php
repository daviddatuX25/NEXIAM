<?php

use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\StartExamSession;
use App\Examination\Queries\MonitoringRosterQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Scheduling\Queries\SchedulingWaitlistQuery;
use Illuminate\Foundation\Testing\TestCase;

beforeEach(function (): void {
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
});

function authenticatedExamSchedulingStaff(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) ($user->authenticationState?->session_version ?? 1),
    ]);
}

function createExamSchedulingAttempt(
    AcademicCycle $cycle,
    string $referenceCode,
    string $status = AdmissionAttempt::STATUS_ACCEPTED,
    array $attributes = [],
): AdmissionAttempt {
    $institutionId = $cycle->institution_id;
    $firstName = $attributes['first_name'] ?? 'Test';
    $lastName = $attributes['last_name'] ?? 'Applicant';

    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institutionId,
        'normalized_key' => strtolower($referenceCode).'-'.uniqid(),
        'first_name' => $firstName,
        'middle_name' => $attributes['middle_name'] ?? null,
        'last_name' => $lastName,
        'birthdate' => '2005-01-01',
        'sex' => 'unspecified',
        'email' => "{$referenceCode}@example.test",
    ]);

    return AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institutionId,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => $referenceCode,
        'status' => $status,
        'accepted_at' => $attributes['accepted_at'] ?? ($status === AdmissionAttempt::STATUS_ACCEPTED ? now() : null),
        'payload' => ['first_name' => $firstName, 'last_name' => $lastName],
        'version' => 1,
    ]);
}

function createExamSchedulingSession(
    AcademicCycle $cycle,
    Room $room,
    string $code,
    string $status = ExamSession::STATUS_DRAFT,
): ExamSession {
    return ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => $status,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(3)->setTime(9, 0),
        'effective_end_at' => now()->addDays(3)->setTime(11, 0),
        'version' => 1,
        'lock_version' => 1,
    ]);
}

test('test_waitlist_query_segregates_ready_vs_not_ready_applicants: waitlist query segregates ready vs not ready applicants', function (): void {
    $readyFirstTime = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-READY-FT',
        AdmissionAttempt::STATUS_ACCEPTED,
        ['first_name' => 'Aaron', 'last_name' => 'Zulueta', 'accepted_at' => now()->subDays(5)]
    );

    $readyReschedule = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-READY-RS',
        AdmissionAttempt::STATUS_ACCEPTED,
        ['first_name' => 'Zara', 'last_name' => 'Alvarez', 'accepted_at' => now()->subDays(2)]
    );

    $priorSession = createExamSchedulingSession($this->cycle, $this->room, 'EX-SCHED-PRIOR-'.uniqid());
    ExamAssignment::query()->create([
        'exam_session_id' => $priorSession->id,
        'admission_attempt_id' => $readyReschedule->id,
        'status' => ExamAssignment::STATUS_CANCELLED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'assigned_at' => now()->subDays(3),
    ]);

    $pendingApplicant = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-NOTRDY-PEND',
        AdmissionAttempt::STATUS_PENDING,
        ['first_name' => 'Charlie', 'last_name' => 'Pending']
    );

    $needsCorrectionApplicant = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-NOTRDY-NC',
        AdmissionAttempt::STATUS_NEEDS_CORRECTION,
        ['first_name' => 'David', 'last_name' => 'Correction']
    );

    $query = app(SchedulingWaitlistQuery::class);

    // 1. Ready bucket assertions
    $readyResult = $query->forDomain('exam', $this->institution->id, $this->cycle->id, ['readiness' => 'ready']);

    expect($readyResult['readiness'])->toBe('ready')
        ->and($readyResult['counts']['ready'])->toBeGreaterThanOrEqual(2)
        ->and($readyResult['counts']['not_ready'])->toBeGreaterThanOrEqual(2)
        ->and($readyResult['counts']['blockers']['pending_registrar_review'])->toBeGreaterThanOrEqual(1)
        ->and($readyResult['counts']['blockers']['needs_applicant_correction'])->toBeGreaterThanOrEqual(1);

    $readyFtItem = collect($readyResult['applicants']['data'])->firstWhere('attempt_id', $readyFirstTime->id);
    expect($readyFtItem)->not->toBeNull()
        ->and($readyFtItem['stage_code'])->toBe('ready_first_time')
        ->and($readyFtItem['can_select_for_assignment'])->toBeTrue();

    $readyRsItem = collect($readyResult['applicants']['data'])->firstWhere('attempt_id', $readyReschedule->id);
    expect($readyRsItem)->not->toBeNull()
        ->and($readyRsItem['stage_code'])->toBe('ready_reschedule')
        ->and($readyRsItem['can_select_for_assignment'])->toBeTrue();

    // 2. Not Ready bucket assertions
    $notReadyResult = $query->forDomain('exam', $this->institution->id, $this->cycle->id, ['readiness' => 'not_ready']);

    expect($notReadyResult['readiness'])->toBe('not_ready');

    $pendingItem = collect($notReadyResult['applicants']['data'])->firstWhere('attempt_id', $pendingApplicant->id);
    expect($pendingItem)->not->toBeNull()
        ->and($pendingItem['stage_code'])->toBe('pending_registrar_review')
        ->and($pendingItem['can_select_for_assignment'])->toBeFalse();

    $ncItem = collect($notReadyResult['applicants']['data'])->firstWhere('attempt_id', $needsCorrectionApplicant->id);
    expect($ncItem)->not->toBeNull()
        ->and($ncItem['stage_code'])->toBe('needs_applicant_correction')
        ->and($ncItem['can_select_for_assignment'])->toBeFalse();

    // 3. pool_search matches across whitespace tokens using TokenizedInnerMatchScope
    $searchResult = $query->forDomain('exam', $this->institution->id, $this->cycle->id, [
        'readiness' => 'ready',
        'pool_search' => 'Aaron READY-FT',
    ]);
    $searchIds = collect($searchResult['applicants']['data'])->pluck('attempt_id')->all();
    expect($searchIds)->toContain($readyFirstTime->id)
        ->and($searchIds)->not->toContain($readyReschedule->id);

    // 4. pool_sort: FCFS orders by accepted_at ASC; alphabetical orders by last_name, first_name ASC
    $fcfsResult = $query->forDomain('exam', $this->institution->id, $this->cycle->id, [
        'readiness' => 'ready',
        'pool_sort' => 'fcfs',
    ]);
    $fcfsIds = collect($fcfsResult['applicants']['data'])->pluck('attempt_id')->all();
    $idxFtFcfs = array_search($readyFirstTime->id, $fcfsIds, true);
    $idxRsFcfs = array_search($readyReschedule->id, $fcfsIds, true);
    expect($idxFtFcfs)->toBeLessThan($idxRsFcfs);

    $alphaResult = $query->forDomain('exam', $this->institution->id, $this->cycle->id, [
        'readiness' => 'ready',
        'pool_sort' => 'alphabetical',
    ]);
    $alphaIds = collect($alphaResult['applicants']['data'])->pluck('attempt_id')->all();
    $idxFtAlpha = array_search($readyFirstTime->id, $alphaIds, true);
    $idxRsAlpha = array_search($readyReschedule->id, $alphaIds, true);
    // Alvarez (readyReschedule) precedes Zulueta (readyFirstTime)
    expect($idxRsAlpha)->toBeLessThan($idxFtAlpha);
});

test('test_bulk_assign_route_atomically_assigns_multiple_applicants: bulk assign route atomically assigns multiple applicants', function (): void {
    $session = createExamSchedulingSession($this->cycle, $this->room, 'EX-SCHED-BULK1-'.uniqid());
    $attempt1 = createExamSchedulingAttempt($this->cycle, 'REF-BULK-1-'.uniqid());
    $attempt2 = createExamSchedulingAttempt($this->cycle, 'REF-BULK-2-'.uniqid());

    $response = authenticatedExamSchedulingStaff($this->guidance)
        ->from(route('exams.sessions.show', $session))
        ->post(route('exams.sessions.bulk-assign', $session), [
            'admission_attempt_ids' => [$attempt1->id, $attempt2->id],
        ]);

    $response->assertRedirect(route('exams.sessions.show', $session));
    $response->assertSessionHas('success');

    expect($session->assignments()->count())->toBe(2);
    expect(ExamAssignment::query()->active()->where('exam_session_id', $session->id)->where('admission_attempt_id', $attempt1->id)->exists())->toBeTrue();
    expect(ExamAssignment::query()->active()->where('exam_session_id', $session->id)->where('admission_attempt_id', $attempt2->id)->exists())->toBeTrue();
});

test('test_bulk_assign_rejects_when_exceeding_capacity: bulk assign rejects when exceeding capacity', function (): void {
    $smallRoom = Room::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'RM-TINY-'.uniqid(),
        'name' => 'Tiny Test Room',
        'capacity' => 1,
        'is_active' => true,
    ]);

    $session = createExamSchedulingSession($this->cycle, $smallRoom, 'EX-SCHED-TINY-'.uniqid());
    $attemptA = createExamSchedulingAttempt($this->cycle, 'REF-OVER-A-'.uniqid());
    $attemptB = createExamSchedulingAttempt($this->cycle, 'REF-OVER-B-'.uniqid());

    $response = authenticatedExamSchedulingStaff($this->guidance)
        ->from(route('exams.sessions.show', $session))
        ->post(route('exams.sessions.bulk-assign', $session), [
            'admission_attempt_ids' => [$attemptA->id, $attemptB->id],
        ]);

    $response->assertRedirect(route('exams.sessions.show', $session));
    $response->assertSessionHasErrors(['admission_attempt_id']);

    expect($session->assignments()->count())->toBe(0);
    expect(ExamAssignment::query()->where('exam_session_id', $session->id)->count())->toBe(0);
});

test('test_search_suggestions_returns_three_applicant_states_for_scheduling: search suggestions returns three applicant states for scheduling', function (): void {
    $uniqueTag = 'Trio'.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
    $session = createExamSchedulingSession($this->cycle, $this->room, 'EX-SUGG-'.uniqid());

    $scheduledAttempt = createExamSchedulingAttempt($this->cycle, "REF-{$uniqueTag}-SCHED", AdmissionAttempt::STATUS_ACCEPTED, [
        'first_name' => 'Alpha',
        'last_name' => $uniqueTag,
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $scheduledAttempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'assigned_at' => now(),
        'assigned_by_user_id' => $this->guidance->id,
    ]);

    $waitlistReadyAttempt = createExamSchedulingAttempt($this->cycle, "REF-{$uniqueTag}-WAIT", AdmissionAttempt::STATUS_ACCEPTED, [
        'first_name' => 'Beta',
        'last_name' => $uniqueTag,
    ]);

    $notReadyAttempt = createExamSchedulingAttempt($this->cycle, "REF-{$uniqueTag}-NOTRDY", AdmissionAttempt::STATUS_PENDING, [
        'first_name' => 'Gamma',
        'last_name' => $uniqueTag,
    ]);

    $response = authenticatedExamSchedulingStaff($this->guidance)
        ->getJson(route('exams.search-suggestions', [
            'q' => $uniqueTag,
            'academic_cycle_id' => $this->cycle->id,
            'domain_context' => 'scheduling',
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'q',
            'academic_cycle_id',
            'domain_context',
            'items',
        ]);

    $items = collect($response->json('items'));

    $scheduledItem = $items->firstWhere('pipeline_state', 'scheduled');
    expect($scheduledItem)->not->toBeNull()
        ->and($scheduledItem['kind'])->toBe('examinee')
        ->and($scheduledItem['target_tab'])->toBe('sessions')
        ->and($scheduledItem['destination']['url'])->toContain(route('exams.sessions.show', $session));

    $waitlistReadyItem = $items->firstWhere('pipeline_state', 'waitlist_ready');
    expect($waitlistReadyItem)->not->toBeNull()
        ->and($waitlistReadyItem['kind'])->toBe('waitlist_applicant')
        ->and($waitlistReadyItem['target_tab'])->toBe('waitlist')
        ->and($waitlistReadyItem['readiness_bucket'])->toBe('ready')
        ->and($waitlistReadyItem['destination']['url'])->toContain(route('exams.sessions.waitlist'))
        ->and($waitlistReadyItem['destination']['url'])->toContain('readiness=ready');

    $notReadyItem = $items->firstWhere('pipeline_state', 'not_ready');
    expect($notReadyItem)->not->toBeNull()
        ->and($notReadyItem['kind'])->toBe('pipeline_applicant')
        ->and($notReadyItem['target_tab'])->toBe('waitlist')
        ->and($notReadyItem['readiness_bucket'])->toBe('not_ready')
        ->and($notReadyItem['destination']['url'])->toContain(route('exams.sessions.waitlist'))
        ->and($notReadyItem['destination']['url'])->toContain('readiness=not_ready');
});

test('test_dedicated_exam_waitlist_page_renders_waitlist_and_index_returns_counts: dedicated exam waitlist page renders waitlist and index returns counts', function (): void {
    $indexResponse = authenticatedExamSchedulingStaff($this->guidance)
        ->get(route('exams.sessions.index', ['academic_cycle_id' => $this->cycle->id]))
        ->assertOk();

    $indexResponse->assertInertia(fn ($page) => $page
        ->component('Examination/Index')
        ->has('waitlist_counts')
        ->where('waitlist_counts.ready', fn ($val) => is_int($val))
        ->where('waitlist_counts.not_ready', fn ($val) => is_int($val))
        ->missing('waitlist')
        ->missing('tab')
    );

    $waitlistResponse = authenticatedExamSchedulingStaff($this->guidance)
        ->get(route('exams.sessions.waitlist', ['academic_cycle_id' => $this->cycle->id]))
        ->assertOk();

    $waitlistResponse->assertInertia(fn ($page) => $page
        ->component('Examination/Waitlist')
        ->has('waitlist')
        ->has('programs')
        ->has('sessions')
        ->where('active_cycle_id', $this->cycle->id)
    );
});

test('absentees from auto-cancelled zero-present sessions and completed mixed-attendance sessions return to ready waitlist and can be reassigned', function (): void {
    $absentZeroSessionApplicant = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-ABSENT-ZERO-'.uniqid(),
        AdmissionAttempt::STATUS_ACCEPTED,
        ['first_name' => 'Noel', 'last_name' => 'AbsentZero']
    );
    $presentCompletedApplicant = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-PRESENT-COMP-'.uniqid(),
        AdmissionAttempt::STATUS_ACCEPTED,
        ['first_name' => 'Paula', 'last_name' => 'PresentComp']
    );
    $absentCompletedApplicant = createExamSchedulingAttempt(
        $this->cycle,
        'REF-EXAM-ABSENT-COMP-'.uniqid(),
        AdmissionAttempt::STATUS_ACCEPTED,
        ['first_name' => 'Marco', 'last_name' => 'AbsentComp']
    );

    // Session 1: Started, 0 present examinees -> closes as cancelled, no GradingSession
    $zeroSessionCode = 'EX-ZERO-PRESENT-'.uniqid();
    $zeroSession = app(CreateExamSession::class)->handle(
        $this->cycle,
        $this->room,
        $zeroSessionCode,
        now()->subHours(3)->toDateTimeString(),
        now()->subHours(1)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    $zeroAssignment = app(AssignApplicantToExamSession::class)->handle($zeroSession, $absentZeroSessionApplicant, $this->guidance);
    $zeroSession = app(PublishExamSession::class)->handle($zeroSession, $this->guidance, $zeroSession->version);
    $zeroSession = app(StartExamSession::class)->handle($zeroSession, $this->guidance, $zeroSession->version);
    app(RecordExamAttendance::class)->handle($zeroAssignment, $this->guidance, ExamAttendanceRecord::OUTCOME_ABSENT, 'zero-session-absent');
    $closedZeroSession = app(CloseExamSession::class)->handle($zeroSession, $this->guidance, $zeroSession->version);

    expect($closedZeroSession->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and(GradingSession::query()->where('exam_session_id', $zeroSession->id)->exists())->toBeFalse()
        ->and($zeroAssignment->fresh()->status)->toBe(ExamAssignment::STATUS_CANCELLED);

    // Session 2: Started, 1 present + 1 absent -> closes as completed, opens GradingSession, releases absent examinee to waitlist
    $mixedSessionCode = 'EX-MIXED-ATT-'.uniqid();
    $mixedSession = app(CreateExamSession::class)->handle(
        $this->cycle,
        $this->room,
        $mixedSessionCode,
        now()->subHours(3)->toDateTimeString(),
        now()->subHours(1)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    $presentAssignment = app(AssignApplicantToExamSession::class)->handle($mixedSession, $presentCompletedApplicant, $this->guidance);
    $absentAssignment = app(AssignApplicantToExamSession::class)->handle($mixedSession, $absentCompletedApplicant, $this->guidance);
    $mixedSession = app(PublishExamSession::class)->handle($mixedSession, $this->guidance, $mixedSession->version);
    $mixedSession = app(StartExamSession::class)->handle($mixedSession, $this->guidance, $mixedSession->version);
    app(RecordExamAttendance::class)->handle($presentAssignment, $this->guidance, ExamAttendanceRecord::OUTCOME_PRESENT, 'mixed-session-present');
    app(RecordExamAttendance::class)->handle($absentAssignment, $this->guidance, ExamAttendanceRecord::OUTCOME_ABSENT, 'mixed-session-absent');
    $closedMixedSession = app(CloseExamSession::class)->handle($mixedSession, $this->guidance, $mixedSession->version);

    expect($closedMixedSession->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and(GradingSession::query()->where('exam_session_id', $mixedSession->id)->exists())->toBeTrue()
        ->and($presentAssignment->fresh()->status)->toBe(ExamAssignment::STATUS_ASSIGNED)
        ->and($absentAssignment->fresh()->status)->toBe(ExamAssignment::STATUS_REMOVED);

    // Both absentees appear in Ready waitlist with ready_absent_reschedule badge; present examinee does not
    $query = app(SchedulingWaitlistQuery::class);
    $waitlist = $query->forDomain('exam', $this->institution->id, $this->cycle->id, ['readiness' => 'ready']);
    $rowsByAttemptId = collect($waitlist['applicants']['data'])->keyBy('attempt_id');

    expect($rowsByAttemptId->has($absentZeroSessionApplicant->id))->toBeTrue()
        ->and($rowsByAttemptId->get($absentZeroSessionApplicant->id)['stage_code'])->toBe('ready_absent_reschedule')
        ->and($rowsByAttemptId->get($absentZeroSessionApplicant->id)['detail_hint'])->toContain($zeroSessionCode)
        ->and($rowsByAttemptId->has($absentCompletedApplicant->id))->toBeTrue()
        ->and($rowsByAttemptId->get($absentCompletedApplicant->id)['stage_code'])->toBe('ready_absent_reschedule')
        ->and($rowsByAttemptId->get($absentCompletedApplicant->id)['detail_hint'])->toContain($mixedSessionCode)
        ->and($rowsByAttemptId->has($presentCompletedApplicant->id))->toBeFalse();

    // Reassign both absentees into a new draft session via bulk-assign
    $nextSession = createExamSchedulingSession($this->cycle, $this->room, 'EX-NEXT-DRAFT-'.uniqid());
    authenticatedExamSchedulingStaff($this->guidance)
        ->from(route('exams.sessions.waitlist'))
        ->post(route('exams.sessions.bulk-assign', $nextSession), [
            'admission_attempt_ids' => [$absentZeroSessionApplicant->id, $absentCompletedApplicant->id],
        ])
        ->assertRedirect(route('exams.sessions.waitlist'))
        ->assertSessionHas('success');

    expect($nextSession->assignments()->active()->count())->toBe(2);

    // Monitoring history still preserves mixedSession's historical 1 present + 1 absent counts
    $history = app(MonitoringRosterQuery::class)->historySessionsFor(
        $this->cycle->id,
    );
    $mixedHistoryRow = collect($history->items())->firstWhere('id', $mixedSession->id);
    expect($mixedHistoryRow)->not->toBeNull()
        ->and($mixedHistoryRow['present_count'])->toBe(1)
        ->and($mixedHistoryRow['absent_count'])->toBe(1)
        ->and($mixedHistoryRow['assigned_count'])->toBe(2);
});
