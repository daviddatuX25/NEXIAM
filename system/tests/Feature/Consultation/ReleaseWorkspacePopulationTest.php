<?php

use App\Authorization\CapabilityCatalog;
use App\Consultation\Actions\AssignApplicantToConsultationSession;
use App\Consultation\Actions\CreateConsultationSession;
use App\Consultation\Actions\PublishConsultationSession;
use App\Consultation\Queries\ConsultationDashboardQuery;
use App\Consultation\Queries\EnrollmentEligibilityQuery;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\ReleaseResult;
use App\Release\Actions\UnreleaseResult;
use App\ResultSheet\Actions\ConfirmPhysicalPrintAction;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $this->staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->staff->givePermissionTo([
        CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
        CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
        CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
        CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
    ]);
    $this->fixture = individualReleaseReadinessFixture($this, $this->staff, 'workspace-population');
    recordIndividualReleaseExternalFinal($this->fixture, 'complete', $this->staff);
});

test('pending release membership survives tentative scheduling publication and confirmed printing until actual release', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->where('dashboard.stats.released', 0)
            ->has('dashboard.scheduled_applicants', 0)
            ->has('dashboard.unscheduled_applicants', 1)
            ->where('dashboard.unscheduled_applicants.0.attempt_id', $attempt->id)
            ->where('dashboard.unscheduled_applicants.0.document_status', 'not_prepared'));

    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = app(CreateConsultationSession::class)->handle(
        $cycle, $room, 'RELEASE-POPULATION', now()->addDay()->toDateTimeString(),
        ConsultationSession::BOOKING_STAFF_ONLY, $this->staff,
    );
    app(AssignApplicantToConsultationSession::class)->handle($session, $attempt, $this->staff);
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->has('dashboard.unscheduled_applicants', 1)
            ->where('dashboard.unscheduled_applicants.0.appointment_status', 'tentative'));

    $session->refresh();
    app(PublishConsultationSession::class)->handle($session, $this->staff, (int) $session->version);
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->has('dashboard.scheduled_applicants', 1)
            ->has('dashboard.unscheduled_applicants', 0));

    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->staff);
    app(ConfirmPhysicalPrintAction::class)->confirmPrint($document, $this->staff, 'workspace-print');
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->where('dashboard.stats.released', 0)
            ->where('dashboard.scheduled_applicants.0.print_status', 'printed'));

    $release = app(ReleaseResult::class)->handle($attempt->fresh(), $document, $this->staff, 'workspace-release', ReleaseResult::MODE_ONLINE);
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 0)
            ->where('dashboard.stats.released', 1)
            ->has('dashboard.scheduled_applicants', 0)
            ->where('dashboard.released_applicants.0.attempt_id', $attempt->id));

    app(UnreleaseResult::class)->handle($release, $this->staff, 'Synthetic release correction review.');
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->where('dashboard.stats.released', 0)
            ->where('dashboard.scheduled_applicants.0.attempt_id', $attempt->id)
            ->where('dashboard.scheduled_applicants.0.print_status', 'printed')
            ->has('dashboard.released_applicants', 0));
});

test('a released applicant remains in follow ups while any program still has outstanding work', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->staff);
    app(ReleaseResult::class)->handle($attempt->fresh(), $document, $this->staff, 'workspace-followup-release', ReleaseResult::MODE_ONLINE);
    $programs = Program::query()->where('institution_id', $attempt->institution_id)
        ->whereIn('code', ['BSIT', 'BA-Psych'])->get()->keyBy('code');
    $outstanding = ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $programs['BSIT']->id,
        'opened_by_user_id' => $this->staff->id,
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'BSIT still needs its program follow-up.',
        'opened_at' => now(),
    ]);
    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $programs['BA-Psych']->id,
        'opened_by_user_id' => $this->staff->id,
        'status' => ProgramFollowUp::STATUS_CLEARED_BY_PH,
        'reason' => 'Separate program review already completed.',
        'opened_at' => now(),
        'resolved_at' => now(),
    ]);

    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.released', 1)
            ->where('dashboard.stats.followups', 1)
            ->has('dashboard.followup_applicants', 1)
            ->where('dashboard.followup_applicants.0.attempt_id', $attempt->id));

    $outstanding->update(['status' => ProgramFollowUp::STATUS_CLEARED_BY_PH, 'resolved_at' => now()]);
    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.released', 1)
            ->where('dashboard.stats.followups', 0)
            ->has('dashboard.followup_applicants', 0));
});

test('registrar result readiness does not wait for the parent batch after individual release and advice', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->staff);
    app(ReleaseResult::class)->handle($attempt->fresh(), $document, $this->staff, 'workspace-registrar-release', ReleaseResult::MODE_ONLINE);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->staff->id,
        'decision_type' => 'approve',
        'notes' => 'Existing completed advice for this status-card scenario.',
        'version' => 1,
        'idempotency_key' => 'workspace-registrar-advice',
    ]);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $card = app(EnrollmentEligibilityQuery::class)->cardFor($attempt->fresh(), $registrar);

    expect($this->fixture['grading_session']->fresh()->status)->toBe('open')
        ->and($card['facts']['grading_finalized'])->toBeFalse()
        ->and($card['facts']['result_ready'])->toBeTrue()
        ->and($card['eligible'])->toBeTrue()
        ->and($card['missing'])->toBe([]);
});

test('dashboard reads fail closed when the active cycle is missing or belongs to another institution', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $query = app(ConsultationDashboardQuery::class);
    expect($query->for((int) $attempt->institution_id, $cycle->code)['stats']['pending_release'])->toBe(1);

    foreach ([
        $query->for((int) $attempt->institution_id, null),
        $query->for((int) $attempt->institution_id, 'MISSING-ACTIVE-CYCLE'),
        $query->for((int) $attempt->institution_id + 1000, $cycle->code),
    ] as $dashboard) {
        expect($dashboard['stats']['pending_release'])->toBe(0)
            ->and($dashboard['today_schedule'])->toBe([])
            ->and($dashboard['pending_release_applicants'])->toBe([])
            ->and($dashboard['released_applicants'])->toBe([])
            ->and($dashboard['followup_applicants'])->toBe([]);
    }
});

test('a later department decline stays outstanding even when its referral was opened before a completed advising decision', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $adviceAt = CarbonImmutable::parse('2026-10-20 10:00:00');
    $advice = new ConsultationDecision([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->staff->id,
        'decision_type' => 'approve',
        'notes' => 'Completed advice before the later department response.',
        'version' => 1,
        'idempotency_key' => 'workspace-decline-advice-1',
    ]);
    $advice->setCreatedAt($adviceAt);
    $advice->setUpdatedAt($adviceAt);
    $advice->save();
    $programs = Program::query()->where('institution_id', $attempt->institution_id)
        ->whereIn('code', ['BSIT', 'BA-Psych'])->get()->keyBy('code');
    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $programs['BSIT']->id,
        'opened_by_user_id' => $this->staff->id,
        'status' => ProgramFollowUp::STATUS_DECLINED_BY_PH,
        'reason' => 'The earlier referral received a later department decline.',
        'created_at' => $adviceAt->subDays(2),
        'opened_at' => $adviceAt->subDays(2),
        'updated_at' => $adviceAt->addHour(),
        'resolved_at' => $adviceAt->addHour(),
    ]);
    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $programs['BA-Psych']->id,
        'opened_by_user_id' => $this->staff->id,
        'status' => ProgramFollowUp::STATUS_DECLINED_BY_PH,
        'reason' => 'The newer referral was already addressed by the completed advice.',
        'created_at' => $adviceAt->subDay(),
        'opened_at' => $adviceAt->subDay(),
        'updated_at' => $adviceAt->subHour(),
        'resolved_at' => $adviceAt->subHour(),
    ]);
    $query = app(ConsultationDashboardQuery::class);
    $before = $query->for((int) $attempt->institution_id, $cycle->code);
    expect($before['stats']['followups'])->toBe(1)
        ->and($before['followup_applicants'][0]['reason'])->toBe('program_declined_by_department');

    $laterAdvice = new ConsultationDecision([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->staff->id,
        'decision_type' => 'modify',
        'notes' => 'Alternative guidance completed after both department outcomes.',
        'version' => 2,
        'idempotency_key' => 'workspace-decline-advice-2',
    ]);
    $laterAdvice->setCreatedAt($adviceAt->addHours(2));
    $laterAdvice->setUpdatedAt($adviceAt->addHours(2));
    $laterAdvice->save();
    expect($query->for((int) $attempt->institution_id, $cycle->code)['stats']['followups'])->toBe(0);
});

test('current released and outstanding follow-up records remain discoverable without a new scheduling-ready sitting', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->staff);
    app(ReleaseResult::class)->handle(
        $attempt->fresh(), $document, $this->staff, 'workspace-historical-release', ReleaseResult::MODE_ONLINE,
    );
    $program = Program::query()->where('institution_id', $attempt->institution_id)->where('code', 'BSIT')->firstOrFail();
    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'opened_by_user_id' => $this->staff->id,
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'Existing program work remains outstanding after the historical sitting ends.',
        'opened_at' => now(),
    ]);
    $this->fixture['assignments']['complete']->update(['status' => ExamAssignment::STATUS_REMOVED]);

    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 0)
            ->where('dashboard.stats.released', 1)
            ->where('dashboard.stats.followups', 1)
            ->has('dashboard.scheduled_applicants', 0)
            ->has('dashboard.unscheduled_applicants', 0)
            ->where('dashboard.released_applicants.0.attempt_id', $attempt->id)
            ->where('dashboard.released_applicants.0.scoring_ready', false)
            ->where('dashboard.released_applicants.0.document_status', 'prepared')
            ->where('dashboard.followup_applicants.0.attempt_id', $attempt->id)
            ->where('dashboard.followup_applicants.0.scoring_ready', false));
});

test('scheduled applicants remain searchable beyond twenty earlier published non-today session groups', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->findOrFail($this->fixture['session']->room_id);
    for ($index = 1; $index <= 20; $index++) {
        ConsultationSession::query()->create([
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'RELEASE-EARLIER-'.$index,
            'status' => ConsultationSession::STATUS_PUBLISHED,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'scheduled_at' => now()->addDays($index),
            'version' => 1,
        ]);
    }
    $session = app(CreateConsultationSession::class)->handle(
        $cycle, $room, 'RELEASE-BEYOND-SAMPLE', now()->addDays(30)->toDateTimeString(),
        ConsultationSession::BOOKING_STAFF_ONLY, $this->staff,
    );
    app(AssignApplicantToConsultationSession::class)->handle($session, $attempt, $this->staff);
    $session->refresh();
    app(PublishConsultationSession::class)->handle($session, $this->staff, (int) $session->version);

    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->where('dashboard.scheduled_applicants.0.attempt_id', $attempt->id)
            ->has('dashboard.unscheduled_applicants', 0)
            ->where('dashboard.other_sessions.items.20.id', $session->id)
            ->where('dashboard.other_sessions.items.20.applicants.0.attempt_id', $attempt->id));
});

test('completed advice awaiting a release remains pending without being offered repeat advising', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->staff);
    app(ConfirmPhysicalPrintAction::class)->confirmPrint($document, $this->staff, 'workspace-advised-print');
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->staff->id,
        'decision_type' => 'approve',
        'notes' => 'Completed advice while the separately issued portal copy is still pending.',
        'version' => 1,
        'idempotency_key' => 'workspace-advised-awaiting-release',
    ]);

    $this->actingAs($this->staff)->get(route('consultation.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.stats.pending_release', 1)
            ->where('dashboard.stats.released', 0)
            ->where('dashboard.unscheduled_applicants.0.attempt_id', $attempt->id)
            ->where('dashboard.unscheduled_applicants.0.advising_status', 'advised_awaiting_release')
            ->where('dashboard.unscheduled_applicants.0.decision_type', 'approve'));
});
