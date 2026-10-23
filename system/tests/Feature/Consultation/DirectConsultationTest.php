<?php

use App\Consultation\Actions\PublishConsultationSession;
use App\Consultation\Queries\ConsultationDashboardQuery;
use App\Consultation\Queries\ReleaseDaySessionMonitoringQuery;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;

beforeEach(function (): void {
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
});

function actingAsDirectConsultationStaff(User $staff): void
{
    $staff = User::query()->with('authenticationState')->findOrFail($staff->id);
    test()->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $staff->authenticationState->session_version,
    ]);
}

function acceptDirectConsultationApplicant(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    actingAsDirectConsultationStaff($registrar);
    test()->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function makeDirectConsultationResultReady(array $result, bool $finalized = true, bool $printed = true): void
{
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail()->id,
        'room_id' => Room::query()->where('code', 'RM-101')->firstOrFail()->id,
        'code' => 'EX-DIRECT-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'version' => 1,
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => $finalized ? GradingSession::STATUS_FINALIZED : GradingSession::STATUS_OPEN,
        'opened_at' => now()->subDays(4),
        'finalized_at' => $finalized ? now()->subDays(3) : null,
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'direct-attendance-'.$attempt->id,
        'recorded_at' => now(),
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'idempotency_key' => 'direct-submission-'.$attempt->id,
        'submitted_at' => now(),
    ]);

    if ($finalized) {
        foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
            ScoreRecord::query()->create([
                'grading_session_id' => $grading->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $area->id,
                'score' => min(20, (int) $area->max_items),
                'converted_score' => '85.00',
                'source' => ScoreRecord::SOURCE_MANUAL,
                'recorded_by_user_id' => $guidance->id,
                'idempotency_key' => 'direct-score-'.$attempt->id.'-'.$area->id,
            ]);
        }
    }

    $examSession->update(['status' => ExamSession::STATUS_COMPLETED]);
    if (! $finalized) {
        return;
    }

    if (ResultSheetTemplate::active() === null) {
        ResultSheetTemplate::query()->create([
            'name' => 'Direct Consultation Test Sheet',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'content' => '<h1>{{applicant_name}}</h1><table><tbody class="scores-rows-placeholder"></tbody></table>',
            'is_active' => true,
        ]);
    }
    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);
    if ($printed) {
        app(MarkResultSheetsPrinted::class)->handle(
            $guidance,
            [(int) $document->id],
            true,
            idempotencyKey: 'direct-print-'.$attempt->id,
        );
    }
}

function createDirectConsultationSession(ConsultationSession $session, AdmissionAttempt $attempt, string $assignmentStatus = ConsultationAssignment::STATUS_ASSIGNED): ConsultationAssignment
{
    return ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => $assignmentStatus,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
}

test('authorized counselor can lookup candidates via GET /consultation/lookup', function (): void {
    $result = acceptDirectConsultationApplicant('lookup.reference@example.test', 'LookupReference');
    $attempt = $result['attempt'];
    actingAsDirectConsultationStaff($this->guidance);

    $byReference = $this->getJson(route('consultation.lookup', ['q' => $attempt->reference_code]))
        ->assertOk()
        ->assertJsonStructure([
            'results' => [
                ['attempt_id', 'reference_code', 'name', 'status_label', 'badge_variant', 'can_consult', 'url'],
            ],
        ]);

    expect($byReference->json('results'))->toHaveCount(1)
        ->and($byReference->json('results.0.reference_code'))->toBe($attempt->reference_code)
        ->and($byReference->json('results.0.name'))->toBe('Test LookupReference')
        ->and($byReference->json('results.0.can_consult'))->toBeFalse()
        ->and($byReference->json('results.0.url'))->toBe(route('admission-support.show', $attempt));

    $byName = $this->getJson(route('consultation.lookup', ['q' => 'LookupReference']))
        ->assertOk();

    expect($byName->json('results'))->toHaveCount(1)
        ->and($byName->json('results.0.attempt_id'))->toBe($attempt->id);
    $auditCount = AuditEvent::query()->where('event_type', 'consultation.applicant_lookup')->count();
    $this->getJson(route('consultation.lookup', ['q' => 'é']))
        ->assertOk()
        ->assertExactJson(['results' => []]);
    expect(AuditEvent::query()->where('event_type', 'consultation.applicant_lookup')->count())->toBe($auditCount);
});

test('lookup matches PostgreSQL wildcard and escape characters literally and audits only minimized scope', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL LIKE escape semantics are the acceptance target.');
    }

    $literal = acceptDirectConsultationApplicant('literal-wildcard@example.test', 'Wildcard%_\\Needle');
    acceptDirectConsultationApplicant('wildcard-decoy@example.test', 'WildcardXXNeedle');
    $otherInstitution = Institution::query()->create(['code' => 'CONS-OTHER', 'name' => 'Other Consultation Institution']);
    $differentInstitution = acceptDirectConsultationApplicant('other-institution@example.test', 'ForeignWildcard%_\\Needle');
    $differentInstitution['attempt']->update(['institution_id' => $otherInstitution->id]);
    submitSyntheticApplicant('pending-wildcard@example.test', 'PendingWildcard%_\\Needle');

    actingAsDirectConsultationStaff($this->guidance);

    $this->getJson(route('consultation.lookup', ['q' => '%_\\N']))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.attempt_id', $literal['attempt']->id)
        ->assertJsonStructure(['results' => [[
            'attempt_id', 'reference_code', 'name', 'status_label', 'badge_variant',
            'can_consult', 'can_start_online_consultation', 'online_unavailable_reason', 'url',
        ]]]);

    $this->get(route('consultation.index', ['q' => '%_\\N']))
        ->assertRedirect(route('admission-support.show', $literal['attempt']));

    $audit = AuditEvent::query()->where('event_type', 'consultation.applicant_lookup')->latest('id')->firstOrFail();
    expect($audit->actor)->toBe((string) $this->guidance->id)
        ->and($audit->payload['attempt_ids'])->toBe([$literal['attempt']->id])
        ->and($audit->payload['scope']['institution_id'])->toBe($this->guidance->actorScope()->institutionId)
        ->and($audit->payload['relationship_basis'])->toBe('accepted_attempt_institution_workspace')
        ->and($audit->payload['purpose'])->toBe('consultation.applicant_lookup')
        ->and($audit->payload['correlation_id'])->not->toBeEmpty()
        ->and($audit->payload['outcome'])->toBe('matched')
        ->and($audit->payload)->not->toHaveKey('query')
        ->and($audit->payload)->not->toHaveKey('name');
});

test('short index query returns no applicant result or protected-view audit', function (): void {
    acceptDirectConsultationApplicant('short-query@example.test', 'ShortQuery');
    actingAsDirectConsultationStaff($this->guidance);
    $this->get(route('consultation.index', ['q' => 'é']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultation/Dashboard')
            ->where('search_results', []));

    expect(AuditEvent::query()->where('event_type', 'consultation.applicant_lookup')->exists())->toBeFalse();
});

test('index query audit failure prevents protected applicant redirect', function (): void {
    $result = acceptDirectConsultationApplicant('index-audit-failure@example.test', 'IndexAuditFailure');
    $failedAudit = Mockery::mock(RecordAuditEvent::class);
    $failedAudit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $failedAudit);
    $this->withoutExceptionHandling();
    actingAsDirectConsultationStaff($this->guidance);
    expect(fn () => $this->get(route('consultation.index', ['q' => $result['attempt']->reference_code])))
        ->toThrow(RuntimeException::class, 'audit unavailable');
});

test('lookup fails closed when mandatory protected-view audit cannot be appended', function (): void {
    $result = acceptDirectConsultationApplicant('lookup-audit-failure@example.test', 'LookupAuditFailure');
    $failedAudit = Mockery::mock(RecordAuditEvent::class);
    $failedAudit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $failedAudit);
    $this->withoutExceptionHandling();
    actingAsDirectConsultationStaff($this->guidance);
    expect(fn () => $this->getJson(route('consultation.lookup', ['q' => $result['attempt']->reference_code])))
        ->toThrow(RuntimeException::class, 'audit unavailable');
});

test('staff without consultation capability cannot access /consultation/lookup', function (): void {
    $unauthorized = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    actingAsDirectConsultationStaff($unauthorized);

    $this->getJson(route('consultation.lookup', ['q' => 'candidate']))
        ->assertForbidden();
});

test('walk-in candidate without consultation assignment succeeds and records direct_walk_in origin', function (): void {
    $result = acceptDirectConsultationApplicant('walk-in@example.test', 'WalkIn');
    $attempt = $result['attempt'];
    makeDirectConsultationResultReady($result);

    expect(ConsultationAssignment::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();

    $decision = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'approve',
        'Walk-in consultation',
        'direct-walk-in-'.$attempt->id,
    );

    $audit = AuditEvent::query()->where('event_type', 'consultation.decision_recorded')->latest('id')->firstOrFail();

    expect($decision->admission_attempt_id)->toBe($attempt->id)
        ->and($decision->decision_type)->toBe('approve')
        ->and($audit->payload['origin'])->toBe('direct_walk_in')
        ->and($audit->payload['attempt_id'])->toBe($attempt->id);
});

test('scheduled candidate transitions assignment to consulted with timestamp and scheduled origin', function (): void {
    $result = acceptDirectConsultationApplicant('scheduled@example.test', 'Scheduled');
    $attempt = $result['attempt'];
    makeDirectConsultationResultReady($result);
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CONS-DIRECT-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDay(),
        'version' => 1,
    ]);
    $assignment = createDirectConsultationSession($session, $attempt);

    app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'approve',
        null,
        'scheduled-consultation-'.$attempt->id,
    );

    $assignment->refresh();
    $audit = AuditEvent::query()->where('event_type', 'consultation.decision_recorded')->latest('id')->firstOrFail();

    expect($assignment->status)->toBe(ConsultationAssignment::STATUS_CONSULTED)
        ->and($assignment->consulted_at)->not->toBeNull()
        ->and($audit->payload['origin'])->toBe('scheduled')
        ->and($audit->payload['assignment_id'])->toBe($assignment->id);
});

test('consultation decision rejects attempt when grading is not finalized', function (): void {
    $result = acceptDirectConsultationApplicant('unfinalized@example.test', 'Unfinalized');
    makeDirectConsultationResultReady($result, finalized: false);

    expect(fn () => app(RecordConsultationDecision::class)->handle(
        $result['attempt'],
        $this->guidance,
        'approve',
        null,
        'unfinalized-consultation-'.$result['attempt']->id,
    ))->toThrow(RuntimeException::class, 'Consultation decision requires a frozen individual result and prepared document.');
});

test('consultation decision rejects unaccepted attempt', function (): void {
    $result = submitSyntheticApplicant('draft-consultation@example.test', 'DraftConsultation');

    expect($result['attempt']->fresh()->status)->not->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and(fn () => app(RecordConsultationDecision::class)->handle(
            $result['attempt'],
            $this->guidance,
            'approve',
            null,
            'draft-consultation-'.$result['attempt']->id,
        ))->toThrow(RuntimeException::class, 'accepted admission attempts');
});

test('dashboard retains consulted examinees on today schedule with advised badge', function (): void {
    $result = acceptDirectConsultationApplicant('today-consultation@example.test', 'TodayConsultation');
    $attempt = $result['attempt'];
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CONS-TODAY-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->setTime(10, 0),
        'version' => 1,
    ]);
    $assignment = createDirectConsultationSession($session, $attempt, ConsultationAssignment::STATUS_CONSULTED);
    $assignment->forceFill(['consulted_at' => now()])->save();

    $dashboard = app(ConsultationDashboardQuery::class)->for((int) $this->cycle->institution_id, $this->cycle->code);
    $todaySession = collect($dashboard['today_schedule'])->firstWhere('id', $session->id);
    $applicant = collect($todaySession['applicants'])->firstWhere('attempt_id', $attempt->id);

    expect($todaySession)->not->toBeNull()
        ->and($applicant)->not->toBeNull()
        ->and($applicant['advised'])->toBeTrue()
        ->and($applicant['assignment_status'])->toBe(ConsultationAssignment::STATUS_CONSULTED);
});

test('draft-assigned result-ready applicants stay in the unscheduled queue until session publication', function (int $daysFromToday, bool $printed): void {
    $result = acceptDirectConsultationApplicant('draft-sidebar@example.test', 'DraftSidebar');
    $attempt = $result['attempt'];
    makeDirectConsultationResultReady($result, printed: $printed);
    $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $session = ConsultationSession::factory()->for($this->cycle, 'academicCycle')->for($this->room)
        ->create(['scheduled_at' => now()->addDays($daysFromToday)->setTime(10, 0), 'version' => 1]);
    $assignment = createDirectConsultationSession($session, $attempt);
    actingAsDirectConsultationStaff($this->guidance);

    $before = $this->get(route('consultation.index'))->assertOk()->viewData('page')['props']['dashboard'];
    $pending = collect($before['unscheduled_applicants'])->firstWhere('attempt_id', $attempt->id);
    expect($pending)->not->toBeNull()
        ->and($pending['release_day_readiness'])->toBe($printed ? 'ready' : 'needs_print')
        ->and(collect($before['today_schedule'])->pluck('id'))->not->toContain($session->id)
        ->and(collect($before['other_sessions']['items'])->pluck('id'))->not->toContain($session->id)
        ->and($session->fresh()->status)->toBe(ConsultationSession::STATUS_DRAFT)
        ->and($assignment->fresh()->status)->toBe(ConsultationAssignment::STATUS_ASSIGNED);

    app(PublishConsultationSession::class)->handle($session, $this->guidance, 1);
    $after = $this->get(route('consultation.index'))->assertOk()->viewData('page')['props']['dashboard'];
    $group = $daysFromToday === 0 ? $after['today_schedule'] : $after['other_sessions']['items'];
    $otherGroup = $daysFromToday === 0 ? $after['other_sessions']['items'] : $after['today_schedule'];
    $published = collect($group)->firstWhere('id', $session->id);
    expect(collect($after['unscheduled_applicants'])->pluck('attempt_id'))->not->toContain($attempt->id)
        ->and(collect($after['scheduled_applicants'])->pluck('attempt_id'))->toContain($attempt->id)
        ->and($published)->not->toBeNull()
        ->and(collect($published['applicants'])->pluck('attempt_id'))->toContain($attempt->id)
        ->and(collect($otherGroup)->pluck('id'))->not->toContain($session->id)
        ->and($assignment->fresh()->status)->toBe(ConsultationAssignment::STATUS_ASSIGNED)
        ->and($attempt->fresh()->current_result_document_id)->toBe($document->id);
})->with([
    'today, unprinted' => [0, false],
    'today, printed' => [0, true],
    'future, unprinted' => [2, false],
    'future, printed' => [2, true],
]);

test('Release Day dashboard keeps printed walk-ins visible and exposes published sessions outside today', function (): void {
    $walkIn = acceptDirectConsultationApplicant('release-day-walkin@example.test', 'ReleaseDayWalkIn');
    makeDirectConsultationResultReady($walkIn);

    $scheduled = acceptDirectConsultationApplicant('release-day-future@example.test', 'ReleaseDayFuture');
    makeDirectConsultationResultReady($scheduled);

    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CONS-FUTURE-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDay(),
        'version' => 1,
    ]);
    createDirectConsultationSession($session, $scheduled['attempt']);

    actingAsDirectConsultationStaff($this->guidance);

    $this->get(route('consultation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultation/Dashboard')
            ->where('dashboard.other_sessions.total_count', 1)
            ->where('dashboard.other_sessions.items.0.id', $session->id)
            ->where('dashboard.today_schedule', function ($sessions) use ($session, $scheduled): bool {
                $sessionRows = collect($sessions);

                return ! $sessionRows->contains(fn (array $row): bool => $row['id'] === $session->id)
                    && ! $sessionRows->flatMap(fn (array $row): array => $row['applicants'])
                        ->contains(fn (array $applicant): bool => $applicant['attempt_id'] === $scheduled['attempt']->id);
            })
            ->where('dashboard.unscheduled_applicants', function ($rows) use ($walkIn, $scheduled): bool {
                $walkInRow = collect($rows)->firstWhere('attempt_id', $walkIn['attempt']->id);

                return $walkInRow !== null
                    && $walkInRow['release_day_readiness'] === 'ready'
                    && ! collect($rows)->contains(fn (array $row): bool => $row['attempt_id'] === $scheduled['attempt']->id);
            })
            ->where('dashboard.scheduled_applicants', fn ($rows): bool => collect($rows)->contains(
                fn (array $row): bool => $row['attempt_id'] === $scheduled['attempt']->id,
            )));

    $this->getJson(route('consultation.lookup', ['q' => $walkIn['attempt']->reference_code]))
        ->assertOk()
        ->assertJsonPath('results.0.status_label', 'Ready for Release Day')
        ->assertJsonPath('results.0.can_consult', true);

    $this->get(route('consultation.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultation/ConsultationSessionShow')
            ->where('session.id', $session->id)
            ->where('assigned_applicants.0.admission_attempt_id', $scheduled['attempt']->id));

    expect($session->fresh()->status)->toBe(ConsultationSession::STATUS_PUBLISHED);
});

test('Release Day monitoring excludes sessions outside the active cycle and institution', function (): void {
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $this->cycle->institution_id,
        'code' => 'OTHER-RELEASE-'.str_replace('.', '', uniqid('', true)),
        'name' => 'Other Release Day Cycle',
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $otherCycleSession = ConsultationSession::query()->create([
        'academic_cycle_id' => $otherCycle->id,
        'room_id' => $this->room->id,
        'code' => 'CONS-OTHER-CYCLE-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDay(),
        'version' => 1,
    ]);
    $otherInstitution = Institution::query()->create([
        'code' => 'CONS-FOREIGN-'.str_replace('.', '', uniqid('', true)),
        'name' => 'Foreign Release Day Institution',
    ]);

    expect(app(ReleaseDaySessionMonitoringQuery::class)
        ->for($otherInstitution->id, $this->cycle->code))
        ->toBe(['total_count' => 0, 'items' => []]);

    actingAsDirectConsultationStaff($this->guidance);

    $this->get(route('consultation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.other_sessions.items', fn ($items): bool => ! collect($items)->contains(
                fn (array $item): bool => $item['id'] === $otherCycleSession->id,
            )));
});

test('staff without Release Day decision capability receive no session or applicant projection', function (): void {
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    actingAsDirectConsultationStaff($clerk);

    $this->get(route('consultation.index'))->assertForbidden();
});

test('direct lookup requires current print receipt rather than legacy printed flag', function (): void {
    $result = acceptDirectConsultationApplicant('legacy-print-lookup@example.test', 'LegacyPrintLookup');
    makeDirectConsultationResultReady($result);

    $attempt = $result['attempt'];
    $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $release = ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $this->guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'mode' => 'f2f',
        'idempotency_key' => 'legacy-print-lookup-release-'.$attempt->id,
    ]);
    app(MarkResultSheetsPrinted::class)->handle(
        $this->guidance,
        [(int) $document->id],
        false,
        'Corrected test receipt',
    );

    actingAsDirectConsultationStaff($this->guidance);

    $this->getJson(route('consultation.lookup', ['q' => $result['attempt']->reference_code]))
        ->assertOk()
        ->assertJsonPath('results.0.status_label', 'Needs Result Sheet Print')
        ->assertJsonPath('results.0.can_consult', false);
});

test('walk-in readiness distinguishes a prepared result without current print confirmation', function (): void {
    $result = acceptDirectConsultationApplicant('unprinted-walkin@example.test', 'UnprintedWalkIn');
    makeDirectConsultationResultReady($result, printed: false);

    actingAsDirectConsultationStaff($this->guidance);

    $this->get(route('consultation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.unscheduled_applicants', function ($rows) use ($result): bool {
                $row = collect($rows)->firstWhere('attempt_id', $result['attempt']->id);

                return $row !== null && $row['release_day_readiness'] === 'needs_print';
            }));

    $this->getJson(route('consultation.lookup', ['q' => $result['attempt']->reference_code]))
        ->assertOk()
        ->assertJsonPath('results.0.status_label', 'Needs Result Sheet Print')
        ->assertJsonPath('results.0.can_consult', false);
});

test('Pending Release Sessions projects inner applicant readiness and time range and can be opened early onto today schedule', function (): void {
    $printedFuture = acceptDirectConsultationApplicant('future-printed@example.test', 'FuturePrinted');
    makeDirectConsultationResultReady($printedFuture, printed: true);

    $unprintedFuture = acceptDirectConsultationApplicant('future-unprinted@example.test', 'FutureUnprinted');
    makeDirectConsultationResultReady($unprintedFuture, printed: false);

    $scheduledAt = now()->addDays(2)->setTime(8, 0, 0);
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CONS-EARLY-OPEN-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => $scheduledAt,
        'published_at' => now(),
        'published_by_user_id' => $this->guidance->id,
        'version' => 1,
    ]);
    createDirectConsultationSession($session, $printedFuture['attempt']);
    createDirectConsultationSession($session, $unprintedFuture['attempt']);

    actingAsDirectConsultationStaff($this->guidance);

    // 1. Before opening early: appears in other_sessions with time_range, version, and inner applicants' readiness
    $this->get(route('consultation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultation/Dashboard')
            ->where('dashboard.other_sessions.items', function ($items) use ($session, $printedFuture, $unprintedFuture): bool {
                $item = collect($items)->firstWhere('id', $session->id);
                if ($item === null) {
                    return false;
                }
                $applicants = collect($item['applicants'] ?? []);
                $printedRow = $applicants->firstWhere('attempt_id', $printedFuture['attempt']->id);
                $unprintedRow = $applicants->firstWhere('attempt_id', $unprintedFuture['attempt']->id);

                return ($item['version'] ?? null) === 1
                    && ($item['time_range'] ?? null) === '08:00 AM – 09:30 AM'
                    && $printedRow !== null
                    && ($printedRow['name'] ?? null) === 'FuturePrinted, Test'
                    && ($printedRow['reference_code'] ?? null) === $printedFuture['attempt']->reference_code
                    && ($printedRow['release_day_readiness'] ?? null) === 'ready'
                    && str_contains((string) ($printedRow['url'] ?? ''), 'session_id='.$session->id)
                    && $unprintedRow !== null
                    && ($unprintedRow['name'] ?? null) === 'FutureUnprinted, Test'
                    && ($unprintedRow['release_day_readiness'] ?? null) === 'needs_print';
            }));

    // 2. Stale version is rejected without mutating session status
    $this->from(route('consultation.index'))
        ->post(route('consultation.sessions.open', $session), ['expected_version' => 99])
        ->assertRedirect(route('consultation.index'))
        ->assertSessionHasErrors('open');
    expect($session->fresh()->status)->toBe(ConsultationSession::STATUS_PUBLISHED);

    // 3. Valid open transitions session to in_progress, preserves scheduled_at, and records audit event
    $this->from(route('consultation.index'))
        ->post(route('consultation.sessions.open', $session), ['expected_version' => 1])
        ->assertRedirect(route('consultation.index'));

    $freshSession = $session->fresh();
    expect($freshSession->status)->toBe(ConsultationSession::STATUS_IN_PROGRESS)
        ->and($freshSession->version)->toBe(2)
        ->and($freshSession->scheduled_at->toIso8601String())->toBe($scheduledAt->toIso8601String());

    $audit = AuditEvent::query()->where('event_type', 'consultation.session.opened')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['consultation_session_id'])->toBe($session->id);

    // 4. After opening early: session moves out of other_sessions and into today_schedule with time_range and applicant readiness
    $this->get(route('consultation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.other_sessions.items', fn ($items): bool => ! collect($items)->contains(
                fn (array $item): bool => $item['id'] === $session->id,
            ))
            ->where('dashboard.today_schedule', function ($sessions) use ($session, $printedFuture, $unprintedFuture): bool {
                $row = collect($sessions)->firstWhere('id', $session->id);
                if ($row === null) {
                    return false;
                }
                $applicants = collect($row['applicants'] ?? []);
                $printedRow = $applicants->firstWhere('attempt_id', $printedFuture['attempt']->id);
                $unprintedRow = $applicants->firstWhere('attempt_id', $unprintedFuture['attempt']->id);

                return ($row['status'] ?? null) === ConsultationSession::STATUS_IN_PROGRESS
                    && ($row['time_range'] ?? null) === '08:00 AM – 09:30 AM'
                    && ($row['is_opened_early'] ?? null) === true
                    && $printedRow !== null
                    && ($printedRow['release_day_readiness'] ?? null) === 'ready'
                    && $unprintedRow !== null
                    && ($unprintedRow['release_day_readiness'] ?? null) === 'needs_print';
            }));
});
