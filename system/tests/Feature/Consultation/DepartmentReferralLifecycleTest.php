<?php

use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Queries\ConsultationDashboardQuery;
use App\Consultation\Queries\EnrollmentEligibilityQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
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
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use Database\Seeders\ReferenceCatalogSeeder;

beforeEach(function (): void {
    seedE2Foundation();
    (new ReferenceCatalogSeeder)->run();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $historicalHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $historicalHead->staffProfile->update([
        'program_codes' => array_values(array_unique([...$historicalHead->staffProfile->program_codes, 'BSN'])),
    ]);
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
});

/** @return array{attempt: AdmissionAttempt, result: array<string, mixed>} */
function departmentReferralReadyAttempt(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $attempt = $result['attempt'];

    test()->actingAs(test()->registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REFERRAL-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => test()->guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'referral-attendance-'.$attempt->id,
        'recorded_at' => now(),
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => test()->guidance->id,
        'idempotency_key' => 'referral-submission-'.$attempt->id,
        'submitted_at' => now(),
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);
    foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id,
            'score' => min(20, (int) $area->max_items),
            'converted_score' => '85.00',
            'source' => ScoreRecord::SOURCE_MANUAL,
            'recorded_by_user_id' => test()->guidance->id,
            'idempotency_key' => 'referral-score-'.$attempt->id.'-'.$area->id,
        ]);
    }

    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), test()->guidance);
    expect($document->status)->toBe(ResultDocument::STATUS_READY);
    app(MarkResultSheetsPrinted::class)->handle(
        test()->guidance,
        [(int) $document->id],
        true,
        idempotencyKey: 'referral-printed-'.$attempt->id,
    );

    return ['attempt' => $attempt->fresh(), 'result' => $result];
}

function departmentReferralFollowUp(AdmissionAttempt $attempt, User $guidance, string $programCode, string $reason): ProgramFollowUp
{
    $program = Program::query()->where('code', $programCode)->firstOrFail();

    return ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'opened_by_user_id' => $guidance->id,
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => $reason,
        'idempotency_key' => 'referral-test-'.$attempt->id.'-'.$programCode,
        'version' => 1,
    ]);
}

test('guidance counselor can refer candidate to department head atomically creating open follow-up', function (): void {
    $fixture = departmentReferralReadyAttempt('referral-open@example.test', 'ReferralOpen');
    $attempt = $fixture['attempt'];
    $reason = 'Borderline Math; Dean interview requested';
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();

    $this->actingAs($this->guidance)
        ->post(route('consultation.decision', $attempt), [
            'decision_type' => 'refer',
            'target_program_code' => 'BSN',
            'referral_reason' => $reason,
            'idempotency_key' => 'referral-open-'.$attempt->id,
        ])
        ->assertRedirect();

    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $followUp = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();

    expect($decision->decision_type)->toBe('refer')
        ->and($followUp->status)->toBe(ProgramFollowUp::STATUS_OPEN)
        ->and($followUp->program_id)->toBe($bsn->id)
        ->and($followUp->reason)->toBe($reason)
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeFalse();
});

test('referred candidate displays with Referred status badge on consultation dashboard', function (): void {
    $fixture = departmentReferralReadyAttempt('referral-dashboard@example.test', 'ReferralDashboard');
    $attempt = $fixture['attempt'];
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CONS-REFERRAL-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->setTime(10, 0),
        'version' => 1,
    ]);
    ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'refer',
        null,
        'referral-dashboard-'.$attempt->id,
        'BSN',
        'Department review requested',
    );

    $dashboard = app(ConsultationDashboardQuery::class)->for((int) $this->cycle->institution_id, $this->cycle->code);
    $schedule = collect($dashboard['today_schedule'])->firstWhere('id', $session->id);
    $applicant = collect($schedule['applicants'])->firstWhere('attempt_id', $attempt->id);

    expect($applicant['outcome'])->toBe('referred')
        ->and($applicant['status_label'])->toBe('Referred to Department')
        ->and($applicant['advised'])->toBeFalse();
});

test('program head can clear referral which atomically confirms program selection and marks applicant advised for registrar enrollment', function (): void {
    $fixture = departmentReferralReadyAttempt('referral-clear@example.test', 'ReferralClear');
    $attempt = $fixture['attempt'];
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 5],
    );
    app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'approve',
        null,
        'referral-prior-advice-'.$attempt->id,
    );
    app(SetGuidanceProgramPool::class)->handle($attempt, $this->guidance, 'BSN', [
        ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'guidance_option'],
    ], null, 'referral-pool-'.$attempt->id);
    $selectionBeforeClear = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $poolReceiptBeforeClear = Operation::query()
        ->where('idempotency_key', $this->guidance->id.':guidance.pool.set:attempt:'.$attempt->id.':referral-pool-'.$attempt->id)
        ->firstOrFail();
    app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'refer',
        null,
        'referral-clear-'.$attempt->id,
        'BSN',
        'Interview requested',
    );
    $followUp = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $this->actingAs($programHead)
        ->post(route('program-head.follow-ups.resolve', ['program' => 'BSN', 'followUp' => $followUp->id]), [
            'outcome' => ProgramFollowUp::STATUS_CLEARED_BY_PH,
            'reason' => 'Cleared after interview',
            'idempotency_key' => 'referral-clear-'.$followUp->id,
        ])
        ->assertRedirect();

    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $poolReceiptAfterClear = Operation::query()->whereKey($poolReceiptBeforeClear->id)->firstOrFail();
    $eligibility = app(EnrollmentEligibilityQuery::class);
    $visibleApplicant = $eligibility->search('ReferralClear')->firstWhere('id', $attempt->id);
    $card = $eligibility->cardFor($attempt->fresh(), $this->registrar);

    expect($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($selectionBeforeClear->status)->toBe(ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT)
        ->and($selection->id)->toBe($selectionBeforeClear->id)
        ->and($selection->status)->toBe(ProgramSelection::STATUS_ACTIVE)
        ->and($selection->source)->toBe(ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
        ->and($selection->source)->toBe($selectionBeforeClear->source)
        ->and($selection->actor_user_id)->toBe($selectionBeforeClear->actor_user_id)
        ->and($selection->idempotency_key)->toBe($selectionBeforeClear->idempotency_key)
        ->and($selection->commitment_identity)->toBe($selectionBeforeClear->commitment_identity)
        ->and($selection->applicant_acknowledged_at)->toBeNull()
        ->and($selection->version)->toBe($selectionBeforeClear->version + 1)
        ->and($poolReceiptAfterClear->id)->toBe($poolReceiptBeforeClear->id)
        ->and($poolReceiptAfterClear->idempotency_key)->toBe($poolReceiptBeforeClear->idempotency_key)
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeTrue()
        ->and($visibleApplicant)->not->toBeNull()
        ->and($card['eligible'])->toBeTrue()
        ->and($card['facts']['advised'])->toBeTrue();
});

test('program head declining referral creates decline record and flags candidate in guidance re-advising queue', function (): void {
    $fixture = departmentReferralReadyAttempt('referral-decline@example.test', 'ReferralDecline');
    $attempt = $fixture['attempt'];
    app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'refer',
        null,
        'referral-decline-'.$attempt->id,
        'BSN',
        'Interview requested',
    );
    $followUp = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    $this->actingAs($programHead)
        ->post(route('program-head.follow-ups.resolve', ['program' => 'BSN', 'followUp' => $followUp->id]), [
            'outcome' => ProgramFollowUp::STATUS_DECLINED_BY_PH,
            'reason' => 'Capacity full, alternative recommended',
            'idempotency_key' => 'referral-decline-'.$followUp->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $decline = ProgramDecline::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $dashboard = app(ConsultationDashboardQuery::class)->for((int) $this->cycle->institution_id, $this->cycle->code);
    $reAdvising = collect($dashboard['followup_applicants'])->firstWhere('attempt_id', $attempt->id);

    expect($decline->reason)->toBe('Capacity full, alternative recommended')
        ->and($reAdvising)->not->toBeNull()
        ->and($reAdvising['reason'])->toBe('program_declined_by_department')
        ->and($reAdvising['url'])->toBe(route('admission-support.show', $attempt))
        ->and($reAdvising)->not->toHaveKeys(['can_start_online_consultation', 'online_unavailable_reason']);
});

test('college dean scoped to multiple programs can access candidate follow-ups across their college', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $bsit = Program::query()->updateOrCreate(['institution_id' => $institution->id, 'code' => 'BSIT'], ['name' => 'BSIT', 'is_active' => true]);
    $bscs = Program::query()->updateOrCreate(['institution_id' => $institution->id, 'code' => 'BSCS'], ['name' => 'BSCS', 'is_active' => true]);
    $bsitFixture = departmentReferralReadyAttempt('dean-bsit@example.test', 'DeanBsit');
    $bscsFixture = departmentReferralReadyAttempt('dean-bscs@example.test', 'DeanBscs');
    $bsitFollowUp = departmentReferralFollowUp($bsitFixture['attempt'], $this->guidance, 'BSIT', 'BSIT referral');
    $bscsFollowUp = departmentReferralFollowUp($bscsFixture['attempt'], $this->guidance, 'BSCS', 'BSCS referral');

    $dean = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $dean->staffProfile->forceFill(['program_codes' => ['BSIT', 'BSCS']])->save();
    $dean->refresh();

    $this->actingAs($dean)
        ->get('/staff/programs/BSIT')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ProgramHead/ProgramShow')
            ->where('page_state', 'ready')
            ->where('lists.program.code', 'BSIT')
            ->where('lists.follow_ups.0.id', $bsitFollowUp->id));

    $this->actingAs($dean)
        ->get('/staff/programs/BSCS')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ProgramHead/ProgramShow')
            ->where('page_state', 'ready')
            ->where('lists.program.code', 'BSCS')
            ->where('lists.follow_ups.0.id', $bscsFollowUp->id));
});
