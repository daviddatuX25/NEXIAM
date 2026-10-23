<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\ConfirmAdmissionClearance;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\RevokeAdmissionClearance;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Support\ScoreConverter;
use App\Auth\Actions\EstablishStaffSessionLease;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffSessionLease;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use App\Notifications\Templates\AdmissionClearanceNotificationTemplate;
use App\Printing\PrintRenderer;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\Support\SensitiveAssetStore;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutVite();
});
function eligibilityAuthenticatedStaff(User $staff): TestCase
{
    $staff = User::query()->with('authenticationState')->findOrFail($staff->id);
    $state = StaffAuthenticationState::query()->firstOrCreate(
        ['user_id' => $staff->id],
        ['session_version' => 1, 'mfa_required' => false],
    );

    $state->forceFill([
        'activation_completed_at' => $state->activation_completed_at ?? now(),
        'password_set_at' => $state->password_set_at ?? now(),
        'must_change_password' => false,
        'mfa_required' => false,
        'two_factor_secret' => $state->two_factor_secret ?? 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => $state->two_factor_confirmed_at ?? now(),
        'two_factor_generation' => $state->two_factor_generation ?? (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();

    if (! session()->isStarted()) {
        session()->start();
    }

    $sessionId = (string) session()->getId();
    $lease = StaffSessionLease::activeForSession((int) $staff->id, $sessionId, now())
        ?? app(EstablishStaffSessionLease::class)->handle($staff, $sessionId, now());

    session()->put([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => $lease->inactivity_generation,
    ]);

    return test()->actingAs($staff);
}

function eligibilityReadyAttempt(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    eligibilityAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    expect($result['attempt']->fresh()->isAccepted())->toBeTrue();
    $result['attempt']->refresh();

    return $result;
}

function eligibilityFinishFlow(array $result, ?string $decisionType = 'approve'): void
{
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-ELIG-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);

    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $result['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_at' => now(),
        'idempotency_key' => 'eligibility-attendance-'.$assignment->id,
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'submitted_at' => now(),
        'idempotency_key' => 'eligibility-submission-'.$assignment->id,
    ]);

    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);
    foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
        $rawScore = min(20, (int) $area->max_items);
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $result['attempt']->id,
            'aptitude_area_id' => $area->id,
            'score' => $rawScore,
            'converted_score' => app(ScoreConverter::class)->convert($area, $rawScore),
            'source' => ScoreRecord::SOURCE_MANUAL,
            'recorded_by_user_id' => $guidance->id,
            'idempotency_key' => 'eligibility-score-'.$grading->id.'-'.$area->id,
        ]);
    }

    $document = app(PrepareResultDocument::class)->handle($result['attempt'], $guidance);

    app(ReleaseResult::class)->handle(
        $result['attempt'],
        $document,
        $guidance,
        'eligibility-release-'.$result['attempt']->id,
        ReleaseResult::MODE_F2F,
    );
    Queue::fake();
    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$document->id], copies: 1);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );
    app(MarkResultSheetsPrinted::class)->handle(
        $guidance,
        [$result['attempt']->fresh()->current_result_document_id],
        printed: true,
        idempotencyKey: 'eligibility-physical-print-'.$result['attempt']->id,
    );

    if ($decisionType !== null) {
        $consSession = ConsultationSession::query()->create([
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'CONS-ELIG-'.$result['attempt']->id,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'status' => ConsultationSession::STATUS_PUBLISHED,
            'scheduled_at' => now()->addDays(2),
            'version' => 1,
        ]);
        ConsultationAssignment::query()->create([
            'consultation_session_id' => $consSession->id,
            'admission_attempt_id' => $result['attempt']->id,
            'status' => ConsultationAssignment::STATUS_ASSIGNED,
            'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
        ]);

        app(RecordConsultationDecision::class)->handle(
            $result['attempt'],
            $guidance,
            $decisionType,
            $decisionType === 'defer' ? 'Decision deferred for follow-up.' : null,
            'eligibility-decision-'.$result['attempt']->id,
        );
    }
}

test('eligibility card shows all facts and eligible after release + confirmed decision', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-ok@example.test', 'Ok');
    eligibilityFinishFlow($result, 'approve');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    eligibilityAuthenticatedStaff($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registrar/EnrollmentEligibility')
            ->where('page_state', 'card')
            ->where('card.eligible', true)
            ->where('card.missing', [])
            ->where('card.facts.accepted', true)
            ->where('card.facts.grading_finalized', true)
            ->where('card.facts.result_released', true)
            ->where('card.facts.advised', true));
});

test('eligibility card names the missing requirement when not released or advised', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-missing@example.test', 'Missing');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    eligibilityAuthenticatedStaff($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registrar/EnrollmentEligibility')
            ->where('card.eligible', false)
            ->where('card.facts.accepted', true)
            ->where('card.facts.grading_finalized', false)
            ->where('card.facts.result_released', false)
            ->where('card.facts.advised', false)
            ->where('card.missing', ['result_ready', 'result_released', 'advised']));
});

test('deferred decision does not mark advised', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-deferred@example.test', 'Deferred');
    eligibilityFinishFlow($result, 'defer');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    eligibilityAuthenticatedStaff($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('card.facts.advised', false)
            ->where('card.eligible', false));
});

test('every registrar eligibility lookup is audited', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-audit@example.test', 'Audit');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    eligibilityAuthenticatedStaff($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk();
    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'registrar.eligibility_lookup',
        'actor' => (string) $registrar->id,
    ]);
});

test('enrollment sheet issue route is retired', function (): void {
    expect(Route::has('registrar.clearance.sheet.issue'))->toBeFalse();
});

test('staff without the registrar capability cannot use the eligibility surface', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    eligibilityAuthenticatedStaff($guidance)
        ->get(route('registrar.enrollment-eligibility.index'))
        ->assertForbidden();
});

test('registrar search returns matching applicants by reference and name', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-search@example.test', 'Searchable');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    eligibilityAuthenticatedStaff($registrar)
        ->get(route('registrar.enrollment-eligibility.index', ['q' => $result['attempt']->reference_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registrar/EnrollmentEligibility')
            ->where('page_state', 'search')
            ->where('results', fn ($rows) => $rows->contains(fn ($row) => $row['id'] === $result['attempt']->id)));
    eligibilityAuthenticatedStaff($registrar)
        ->get(route('registrar.enrollment-eligibility.index', ['q' => 'Searchable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('results', fn ($rows) => $rows->contains(fn ($row) => $row['id'] === $result['attempt']->id)));
});
test('registrar can confirm admission clearance via http without 500 error', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-confirm-http@example.test', 'Confirmable');
    eligibilityFinishFlow($result, 'approve');

    $attempt = $result['attempt']->fresh();
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle(
        attempt: $attempt,
        counselor: $guidance,
        defaultProgramCode: DEFAULT_PROGRAM,
        options: [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'qualifies'],
            ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'qualifies'],
        ],
        reason: 'Qualified for both programs',
        idempotencyKey: 'pool-test-key-http',
    );

    app(SelectApplicantProgram::class)->handle($attempt->fresh(), $applicant, DEFAULT_PROGRAM, 'http-test-sel');
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt->fresh(),
        $registrar,
        (int) $attempt->fresh()->version,
        'http-test-init',
    );

    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        app(VerifyAdmissionAttemptDocument::class)->handle(
            attempt: $attempt->fresh(),
            requirementSetId: $set->id,
            requirementItemId: $item->id,
            expectedLatestVerificationId: null,
            status: AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            actor: $registrar,
            expectedVersion: (int) $attempt->fresh()->version,
            applicantInstruction: null,
            internalRemarks: 'Verified in HTTP test',
            idempotencyKey: 'http-test-verify-'.$item->id,
        );
    }

    $attempt->refresh();

    eligibilityAuthenticatedStaff($registrar)
        ->post(route('registrar.clearance.confirm', $attempt), [
            'expected_version' => (int) $attempt->version,
            'idempotency_key' => 'http-test-confirm-key',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Admission clearance confirmed.')
        ->assertSessionHasNoErrors();

    expect(AttemptProgramFacts::isClearanceConfirmed($attempt->fresh()))->toBeTrue();

    // Verify notification was created for the applicant
    $delivery = NotificationDelivery::query()
        ->where('event_type', AdmissionClearanceNotificationTemplate::EVENT_KEY)
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and((int) ($delivery->payload['attempt_id'] ?? $delivery->payload['event_resource']['attempt_id'] ?? 0))->toBe($attempt->id);

    // Verify applicant sees the notification in their projection
    $projection = app(NotificationProjection::class)->forUser($applicant);
    expect($projection['unread_count'])->toBeGreaterThanOrEqual(1);

    $notification = collect($projection['recent'])->firstWhere('event_type', AdmissionClearanceNotificationTemplate::EVENT_KEY);
    expect($notification)->not->toBeNull()
        ->and($notification['subject'])->toContain('Admission Clearance Confirmed')
        ->and($notification['action_url'])->toContain('/applicant/admission-clearance');
});

test('reopening admission clearance hides the clearance notification from applicant', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-notif-revoke@example.test', 'Revokable');
    eligibilityFinishFlow($result, 'approve');

    $attempt = $result['attempt']->fresh();
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle(
        attempt: $attempt,
        counselor: $guidance,
        defaultProgramCode: DEFAULT_PROGRAM,
        options: [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'qualifies'],
        ],
        reason: 'Qualified',
        idempotencyKey: 'pool-revoke-notif',
    );
    app(SelectApplicantProgram::class)->handle($attempt->fresh(), $applicant, DEFAULT_PROGRAM, 'sel-revoke-notif');

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt->fresh(),
        $registrar,
        (int) $attempt->fresh()->version,
        'init-revoke-notif',
    );
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        app(VerifyAdmissionAttemptDocument::class)->handle(
            attempt: $attempt->fresh(),
            requirementSetId: $set->id,
            requirementItemId: $item->id,
            expectedLatestVerificationId: null,
            status: AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            actor: $registrar,
            expectedVersion: (int) $attempt->fresh()->version,
            applicantInstruction: null,
            internalRemarks: 'Verified',
            idempotencyKey: 'verify-revoke-notif-'.$item->id,
        );
    }

    $attempt->refresh();
    app(ConfirmAdmissionClearance::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'confirm-revoke-notif',
    );

    // Verify notification is visible to applicant
    $projection = app(NotificationProjection::class)->forUser($applicant);
    $notification = collect($projection['recent'])->firstWhere('event_type', AdmissionClearanceNotificationTemplate::EVENT_KEY);
    expect($notification)->not->toBeNull();

    // Now reopen / revoke clearance
    app(RevokeAdmissionClearance::class)->handle(
        $attempt->fresh(),
        $registrar,
        (int) $attempt->fresh()->version,
        'Need certificate update',
        'reopen-revoke-notif',
    );

    // Verify notification is no longer visible to applicant
    $projectionAfter = app(NotificationProjection::class)->forUser($applicant);
    $notificationAfter = collect($projectionAfter['recent'])->firstWhere('event_type', AdmissionClearanceNotificationTemplate::EVENT_KEY);
    expect($notificationAfter)->toBeNull();
});
