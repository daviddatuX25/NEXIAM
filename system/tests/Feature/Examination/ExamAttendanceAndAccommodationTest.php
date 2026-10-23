<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Support\ApplicantQrCode;
use App\Assessment\Support\GradingRoster;
use App\Examination\Actions\AccommodateApplicantExamSession;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\SessionQrCheckinAction;
use App\Examination\Actions\StartExamSession;
use App\Examination\Actions\UnpublishExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

uses(RefreshDatabase::class);

use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\AssessmentConversionRevision;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    seedE2Foundation();

    $this->admin = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->admin->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
});

test('session qr checkin verifies signed qr token and marks present', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-qr-token@example.test', 'QrToken');
    $session = eamaPublishedSession($this->admin, 'EAMA-QR-TOKEN', [$attempt]);
    $token = ApplicantQrCode::issue($attempt);

    eamaStartSession($session);
    $result = app(SessionQrCheckinAction::class)->handle($session, $this->admin, $token, 'checkin-key-1');

    expect($result['status'])->toBe('ok');
    $record = ExamAttendanceRecord::query()
        ->whereHas('examAssignment', fn ($query) => $query->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $attempt->id))
        ->firstOrFail();
    expect($record->outcome)->toBe(ExamAttendanceRecord::OUTCOME_PRESENT);
});
test('session qr checkin accepts a canonical same-origin applicant entry URL', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-qr-entry-url@example.test', 'QrEntryUrl');
    $session = eamaPublishedSession($this->admin, 'EAMA-QR-ENTRY-URL', [$attempt]);

    eamaStartSession($session);
    $result = app(SessionQrCheckinAction::class)->handle(
        $session,
        $this->admin,
        ApplicantQrCode::entryUrl($attempt),
        'checkin-key-entry-url',
    );

    expect($result['status'])->toBe('ok');
});

test('session qr checkin accepts valid manual reference code fallback', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-qr-reference@example.test', 'QrReference');
    $session = eamaPublishedSession($this->admin, 'EAMA-QR-REFERENCE', [$attempt]);
    eamaStartSession($session);

    $result = app(SessionQrCheckinAction::class)->handle(
        $session,
        $this->admin,
        '  '.strtolower($attempt->reference_code).'  ',
        'checkin-reference-1',
    );

    expect($result['status'])->toBeIn(['ok', 'already_present']);
});

test('session qr checkin handles already present and conflicts on absent or void', function (): void {
    $action = app(SessionQrCheckinAction::class);
    $presentAttempt = eamaAcceptedAttempt($this->admin, 'eama-qr-present@example.test', 'QrPresent');
    $presentSession = eamaPublishedSession($this->admin, 'EAMA-QR-PRESENT', [$presentAttempt]);
    eamaStartSession($presentSession);
    $presentToken = ApplicantQrCode::issue($presentAttempt);
    $action->handle($presentSession, $this->admin, $presentToken, 'checkin-present-first');

    expect($action->handle($presentSession, $this->admin, $presentToken, 'checkin-present-again')['status'])
        ->toBe('already_present');

    foreach ([
        ExamAttendanceRecord::OUTCOME_ABSENT => 'Absent',
        ExamAttendanceRecord::OUTCOME_VOID => 'Void',
    ] as $outcome => $name) {
        $attempt = eamaAcceptedAttempt($this->admin, 'eama-qr-'.strtolower($name).'@example.test', 'Qr'.$name);
        $room = Room::factory()->create(['institution_id' => Institution::current()->id, 'capacity' => 40, 'is_active' => true]);
        $session = eamaPublishedSession($this->admin, 'EAMA-QR-'.strtoupper($name), [$attempt], room: $room, startsAt: now()->subMinutes(15));
        eamaStartSession($session);
        $assignment = $session->assignments()->active()->firstOrFail();
        app(RecordExamAttendance::class)->handle($assignment, $this->admin, $outcome, 'checkin-mark-'.$outcome);

        $result = $action->handle(
            $session,
            $this->admin,
            ApplicantQrCode::issue($attempt),
            'checkin-retry-'.$outcome,
        );

        expect($result['status'])->toBe('conflict');
        if ($outcome === ExamAttendanceRecord::OUTCOME_ABSENT) {
            expect($result['message'])->toContain('already recorded as absent');
        }
    }
});

test('session qr checkin detects session mismatch when assigned elsewhere', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-qr-mismatch@example.test', 'QrMismatch');
    $room101 = Room::query()->where('code', 'RM-101')->firstOrFail();
    $roomLab = Room::query()->where('code', 'RM-LabA')->firstOrFail();
    $otherSession = eamaPublishedSession($this->admin, 'EAMA-QR-OTHER', [$attempt], room: $room101, startsAt: now()->subMinutes(15));
    $currentApplicant = eamaAcceptedAttempt($this->admin, 'eama-qr-current@example.test', 'QrCurrent');
    $unassignedSession = eamaPublishedSession($this->admin, 'EAMA-QR-CURRENT', [$currentApplicant], room: $roomLab, startsAt: now()->subMinutes(15));
    eamaStartSession($otherSession);

    $result = app(SessionQrCheckinAction::class)->handle(
        $unassignedSession,
        $this->admin,
        ApplicantQrCode::issue($attempt),
        'checkin-mismatch-1',
    );

    expect($result['status'])->toBe('mismatch')
        ->and($result['session_code'])->toBe($otherSession->code)
        ->and($result['room'])->toBe($room101->name);
});

test('records attendance outcomes present, absent, and void, and allows corrections before closure', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-attendance@example.test', 'Attendance');
    $session = eamaPublishedSession($this->admin, 'EAMA-ATTENDANCE', [$attempt]);
    eamaStartSession($session);
    $assignment = $session->assignments()->active()->firstOrFail();
    $action = app(RecordExamAttendance::class);

    $record = $action->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'eama-attendance-present');
    expect($record->exists)->toBeTrue()
        ->and($record->outcome)->toBe('present');

    $record = $action->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_VOID, 'eama-attendance-void');
    expect($record->fresh()->outcome)->toBe('void');

    $record = $action->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_ABSENT, 'eama-attendance-absent');
    expect($record->fresh()->outcome)->toBe('absent');
});

function eamaStartSession(ExamSession $session): ExamSession
{
    app(StartExamSession::class)->handle($session, test()->admin, $session->fresh()->version);

    return $session->refresh();
}

test('auto-submits present examinees on close and creates one grading ledger without physical allocations', function (): void {
    $attempts = [
        eamaAcceptedAttempt($this->admin, 'eama-close-present@example.test', 'Present'),
        eamaAcceptedAttempt($this->admin, 'eama-close-absent@example.test', 'Absent'),
        eamaAcceptedAttempt($this->admin, 'eama-close-void@example.test', 'Void'),
    ];
    $session = eamaPublishedSession($this->admin, 'EAMA-CLOSE', $attempts);
    eamaStartSession($session);
    $assignments = $session->assignments()->active()->orderBy('id')->get()->keyBy('admission_attempt_id');
    $attendance = app(RecordExamAttendance::class);
    $outcomes = ['present', 'absent', 'void'];

    foreach ($attempts as $index => $attempt) {
        $attendance->handle($assignments[$attempt->id], $this->admin, $outcomes[$index], "eama-close-attendance-{$index}");
    }

    app(CloseExamSession::class)->handle($session, $this->admin, $session->version);

    $present = $assignments[$attempts[0]->id]->fresh();
    $absent = $assignments[$attempts[1]->id]->fresh();
    $void = $assignments[$attempts[2]->id]->fresh();
    $submission = ExamSubmissionRecord::query()->where('exam_assignment_id', $present->id)->firstOrFail();

    expect($session->currentLogisticsPlan->seats()->count())->toBe(0)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and($submission->seat_allocation_id)->toBeNull()
        ->and($absent->submissionRecord()->exists())->toBeFalse()
        ->and($void->submissionRecord()->exists())->toBeFalse()
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(1)
        ->and(GradingRoster::isEligible($present->load(['attendanceRecord', 'submissionRecord'])))->toBeTrue()
        ->and(GradingRoster::isEligible($absent->load(['attendanceRecord', 'submissionRecord'])))->toBeFalse()
        ->and(GradingRoster::isEligible($void->load(['attendanceRecord', 'submissionRecord'])))->toBeFalse();
});

test('closing a no-kit session permits present attendance without a physical seat allocation', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-no-seat@example.test', 'NoSeat');
    $session = eamaPublishedSession($this->admin, 'EAMA-NO-SEAT', [$attempt]);
    eamaStartSession($session);
    $assignment = $session->assignments()->active()->firstOrFail();
    app(RecordExamAttendance::class)->handle($assignment, $this->admin, 'present', 'eama-no-seat-attendance');

    $closed = app(CloseExamSession::class)->handle($session, $this->admin, $session->version);

    expect($closed->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and($assignment->fresh()->submissionRecord)->not->toBeNull()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(1);
});

test('accommodates a future-session traveler without creating material seats or allocation history', function (): void {
    $traveler = eamaAcceptedAttempt($this->admin, 'eama-early-traveler@example.test', 'Traveler');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-target-placeholder@example.test', 'Placeholder');
    $targetSession = eamaPublishedSession($this->admin, 'EAMA-TARGET-EARLY', [$placeholder]);
    $targetSession = eamaStartSession($targetSession);
    $sourceSession = eamaPublishedSession(
        $this->admin,
        'EAMA-SOURCE-EARLY',
        [$traveler],
        Room::query()->where('code', 'RM-LabA')->firstOrFail(),
        now()->addDay(),
    );
    $sourceSession = eamaStartSession($sourceSession);
    $oldAssignment = $sourceSession->assignments()->active()->firstOrFail();
    $earlyStartNotice = NotificationDelivery::query()
        ->where('event_type', 'exam.session_started_early.v1')
        ->where('recipient_id', $traveler->applicant_identity_id)
        ->firstOrFail();

    $newAssignment = app(AccommodateApplicantExamSession::class)->handle(
        $targetSession,
        $traveler,
        $this->admin,
        'travel_distance',
        'Arrived early from distant town',
    );
    $displacedAssignment = $oldAssignment->fresh();

    expect($newAssignment->exam_session_id)->toBe($targetSession->id)
        ->and($newAssignment->isActive())->toBeTrue()
        ->and($displacedAssignment->status)->toBe(ExamAssignment::STATUS_REMOVED)
        ->and($displacedAssignment->ended_at)->not->toBeNull()
        ->and($displacedAssignment->end_reason)->toContain('travel_distance')
        ->and($displacedAssignment->end_reason)->toContain('Arrived early from distant town')
        ->and($targetSession->currentLogisticsPlan->seats()->count())->toBe(0)
        ->and(ExamSessionMaterialSnapshot::query()->whereIn('exam_session_id', [$targetSession->id, $sourceSession->id])->exists())->toBeFalse()
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $newAssignment->id)->exists())->toBeFalse();
    $reassignmentNotice = NotificationDelivery::query()
        ->where('event_type', 'exam.session_reassigned.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $traveler->applicant_identity_id)
        ->firstOrFail();

    expect(app(NotificationBusinessValidity::class)->isCurrent($reassignmentNotice))->toBeTrue()
        ->and($reassignmentNotice->payload['action_url'])->toBe(route('applicant.schedule'))
        ->and($reassignmentNotice->payload['body'])->toContain($targetSession->code);
    expect(app(NotificationBusinessValidity::class)->isCurrent($earlyStartNotice))->toBeFalse();
});

test('accommodation does not create an assessment administration in a no-kit session', function (): void {
    $traveler = eamaAcceptedAttempt($this->admin, 'eama-bound-traveler@example.test', 'BoundTraveler');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-bound-placeholder@example.test', 'BoundPlaceholder');
    $targetSession = eamaPublishedSession($this->admin, 'EAMA-BOUND-WALKIN', [$placeholder]);
    $targetSession = eamaStartSession($targetSession);

    $assignment = app(AccommodateApplicantExamSession::class)->handle(
        $targetSession,
        $traveler,
        $this->admin,
        'travel_distance',
        'Arrived early from distant town',
    );

    expect($assignment->isActive())->toBeTrue()
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeFalse()
        ->and($targetSession->test_kit_version_id)->toBeNull();
});

test('simple accommodation uses frozen session capacity without materials despite room capacity changes', function (): void {
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-simple-placeholder@example.test', 'Placeholder');
    $traveler = eamaAcceptedAttempt($this->admin, 'eama-simple-traveler@example.test', 'Traveler');
    $blocked = eamaAcceptedAttempt($this->admin, 'eama-simple-full@example.test', 'Full');
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $room->update(['capacity' => 2]);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EAMA-SIMPLE-FROZEN',
        now()->subHour()->toDateTimeString(),
        now()->addHours(2)->toDateTimeString(),
        $this->admin,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    app(AssignApplicantToExamSession::class)->handle($session, $placeholder, $this->admin);
    $session = app(PublishExamSession::class)->handle($session, $this->admin, $session->version)->fresh();
    $session = eamaStartSession($session);
    $plan = $session->currentLogisticsPlan;

    $room->update(['capacity' => 0]);
    $assignment = app(AccommodateApplicantExamSession::class)->handle(
        $session,
        $traveler,
        $this->admin,
        'travel_distance',
        'Arrived from distant town',
    );

    expect($plan->capacity_limit)->toBe(2)
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($plan->seating_arrangement_id)->toBeNull()
        ->and($plan->seats()->count())->toBe(0)
        ->and($assignment->isActive())->toBeTrue()
        ->and($assignment->exam_session_id)->toBe($session->id)
        ->and($session->assignments()->active()->count())->toBe(2)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();

    $room->update(['capacity' => 10]);
    expect(fn () => app(AccommodateApplicantExamSession::class)->handle(
        $session,
        $blocked,
        $this->admin,
        'administrative_override',
        'Frozen session is already full',
    ))->toThrow(RuntimeException::class, 'maximum published capacity');
    expect($session->assignments()->active()->count())->toBe(2)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $blocked->id)->exists())->toBeFalse();
});
test('accommodation requires a supported reason category and substantive notes', function (): void {
    $traveler = eamaAcceptedAttempt($this->admin, 'eama-invalid-reason-traveler@example.test', 'ReasonTraveler');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-invalid-reason-placeholder@example.test', 'ReasonPlaceholder');
    $session = eamaPublishedSession($this->admin, 'EAMA-INVALID-REASON', [$placeholder]);
    $session = eamaStartSession($session);
    $action = app(AccommodateApplicantExamSession::class);

    expect(fn () => $action->handle($session, $traveler, $this->admin, 'unsupported', 'Reason text'))
        ->toThrow(RuntimeException::class, 'Invalid accommodation reason category.');
    expect(fn () => $action->handle($session, $traveler, $this->admin, 'travel_distance', '  '))
        ->toThrow(RuntimeException::class, 'Accommodation notes are required.');

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $traveler->id)->exists())->toBeFalse()
        ->and($session->assignments()->active()->count())->toBe(1);
});

test('rescues a tardy applicant marked absent from a completed session into current session', function (): void {
    $lateApplicant = eamaAcceptedAttempt($this->admin, 'eama-late-applicant@example.test', 'Late');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-afternoon-placeholder@example.test', 'Afternoon');
    $morningSession = eamaPublishedSession($this->admin, 'EAMA-MORNING', [$lateApplicant]);
    eamaStartSession($morningSession);
    $oldAssignment = $morningSession->assignments()->active()->firstOrFail();
    app(RecordExamAttendance::class)->handle($oldAssignment, $this->admin, 'absent', 'eama-late-absent');
    app(CloseExamSession::class)->handle($morningSession, $this->admin, $morningSession->version);
    $afternoonSession = eamaPublishedSession(
        $this->admin,
        'EAMA-AFTERNOON',
        [$placeholder],
        Room::query()->where('code', 'RM-LabA')->firstOrFail(),
    );
    $afternoonSession = eamaStartSession($afternoonSession);

    $newAssignment = app(AccommodateApplicantExamSession::class)->handle(
        $afternoonSession,
        $lateApplicant,
        $this->admin,
        'tardiness_consideration',
        'Delayed by flood',
    );

    expect($newAssignment->isActive())->toBeTrue()
        ->and($newAssignment->exam_session_id)->toBe($afternoonSession->id)
        ->and($oldAssignment->fresh()->status)->toBe(ExamAssignment::STATUS_REMOVED);
});

test('rejects accommodation if applicant already submitted an exam paper', function (): void {
    $applicant = eamaAcceptedAttempt($this->admin, 'eama-submitted@example.test', 'Submitted');
    $placeholderOne = eamaAcceptedAttempt($this->admin, 'eama-submitted-placeholder-one@example.test', 'One');
    $placeholderTwo = eamaAcceptedAttempt($this->admin, 'eama-submitted-placeholder-two@example.test', 'Two');
    $sourceSession = eamaPublishedSession($this->admin, 'EAMA-SUBMITTED-SOURCE', [$applicant, $placeholderOne]);
    eamaStartSession($sourceSession);
    $assignment = $sourceSession->assignments()->active()->where('admission_attempt_id', $applicant->id)->firstOrFail();
    app(RecordExamAttendance::class)->handle($assignment, $this->admin, 'present', 'eama-submitted-attendance');
    app(RecordExamSubmission::class)->handle($assignment, $this->admin, 'eama-submitted-paper');
    $targetSession = eamaPublishedSession(
        $this->admin,
        'EAMA-SUBMITTED-TARGET',
        [$placeholderTwo],
        Room::query()->where('code', 'RM-LabA')->firstOrFail(),
    );

    expect(fn () => app(AccommodateApplicantExamSession::class)->handle(
        $targetSession,
        $applicant,
        $this->admin,
        'administrative_override',
        'Do not repeat submitted attempt',
    ))->toThrow(RuntimeException::class, 'already participated/submitted');
});

test('modern external conversion history blocks accommodation and unpublication before changing assignment history', function (): void {
    $applicant = eamaAcceptedAttempt($this->admin, 'eama-modern-use@example.test', 'ModernUse');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-modern-placeholder@example.test', 'Placeholder');
    $sourceSession = eamaPublishedSession(
        $this->admin,
        'EAMA-MODERN-USE-SOURCE',
        [$applicant],
        startsAt: now()->addHour(),
    );
    $targetSession = eamaPublishedSession(
        $this->admin,
        'EAMA-MODERN-USE-TARGET',
        [$placeholder],
        Room::query()->where('code', 'RM-LabA')->firstOrFail(),
    );
    $assignment = $sourceSession->assignments()->active()->firstOrFail();
    $profile = createTestApprovedProfileVersion($this, $this->admin, 'EAMA-MODERN-USE-'.$applicant->id);
    $kitVersion = createTestActiveKitVersion($this->admin, $profile);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_IMPORT, 'web');
    $importer = $this->admin;
    $importer->syncRoles([]);
    $importer->syncPermissions([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);
    $administration = app(BindAssessmentAdministration::class)->handle(
        $assignment, $kitVersion, $importer, 'external_final', acquisitionChannel: 'import',
    );
    $this->admin->syncPermissions([
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
    ]);
    DB::table('assessment_conversion_revisions')->insert([
        'administration_id' => $administration->id,
        'norm_package_version_id' => null,
        'revision_no' => 1,
        'typed_outputs_json' => json_encode(['RAW_A' => ['percentile_rank' => '76', 'stanine' => '6']], JSON_THROW_ON_ERROR),
        'source' => AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED,
        'provenance_json' => json_encode(['source' => 'import', 'reference' => 'synthetic historical final'], JSON_THROW_ON_ERROR),
        'raw_dependency_sha256' => null,
        'status' => 'valid',
        'converted_by_user_id' => $importer->id,
        'converted_at' => now(),
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $allocationCount = DB::table('exam_assignment_logistics_seat_allocations')->count();

    expect(fn () => app(AccommodateApplicantExamSession::class)->handle(
        $targetSession,
        $applicant,
        $this->admin,
        'administrative_override',
        'Modern assessment already used',
    ))->toThrow(RuntimeException::class, 'already participated/submitted');
    expect(fn () => app(UnpublishExamSession::class)->handle(
        $sourceSession->fresh(),
        $this->admin,
        $sourceSession->version,
        'Modern assessment already used',
    ))->toThrow(RuntimeException::class, 'attendance, submission, or capture evidence');

    expect($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_ASSIGNED)
        ->and($administration->fresh()->superseded_at)->toBeNull()
        ->and($sourceSession->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($targetSession->fresh()->assignments()->active()->where('admission_attempt_id', $applicant->id)->exists())->toBeFalse()
        ->and(DB::table('exam_assignment_logistics_seat_allocations')->count())->toBe($allocationCount);
});

test('same-second accommodation preserves one active assignment without physical allocation history', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
    $applicant = eamaAcceptedAttempt($this->admin, 'eama-rapid@example.test', 'RapidApplicant');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-rapid-target@example.test', 'RapidTargetPlaceholder');
    $sourceSession = eamaPublishedSession($this->admin, 'EAMA-RAPID-SOURCE', [$applicant]);
    $targetSession = eamaPublishedSession(
        $this->admin,
        'EAMA-RAPID-TARGET',
        [$placeholder],
        Room::query()->where('code', 'RM-LabA')->firstOrFail(),
    );
    $oldAssignment = $sourceSession->assignments()->active()->firstOrFail();

    $accommodated = app(AccommodateApplicantExamSession::class)->handle(
        $targetSession,
        $applicant,
        $this->admin,
        'travel_distance',
        'Early traveler same second reallocation',
    );

    expect($accommodated->exam_session_id)->toBe($targetSession->id)
        ->and($accommodated->isActive())->toBeTrue()
        ->and($oldAssignment->fresh()->status)->toBe(ExamAssignment::STATUS_REMOVED)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $applicant->id)->count())->toBe(1)
        ->and(ExamSessionMaterialSnapshot::query()->whereIn('exam_session_id', [$sourceSession->id, $targetSession->id])->exists())->toBeFalse();

    Carbon::setTestNow();
});

test('test administrator accommodates applicants while historical clerk duty remains inert', function (): void {
    $traveler = eamaAcceptedAttempt($this->admin, 'eama-controller-traveler@example.test', 'ControllerTraveler');
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-controller-placeholder@example.test', 'ControllerPlaceholder');
    $sourceSession = eamaPublishedSession($this->admin, 'EAMA-CONTROLLER-SOURCE', [$traveler]);
    $targetSession = eamaPublishedSession(
        $this->admin,
        'EAMA-CONTROLLER-TARGET',
        [$placeholder],
        Room::query()->where('code', 'RM-LabA')->firstOrFail(),
    );
    $targetSession = eamaStartSession($targetSession);
    $dutyOnlyClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $historicalDuty = ProctorDuty::query()->create([
        'exam_session_id' => $targetSession->id,
        'user_id' => $dutyOnlyClerk->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'eama-controller-historical-duty',
        'assigned_at' => now(),
    ]);

    $this->actingAs($dutyOnlyClerk)
        ->get(route('exams.sessions.monitoring.lookup-applicant', $targetSession).'?reference_code='.$traveler->reference_code)
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('exams.sessions.monitoring.lookup-applicant', $targetSession).'?reference_code='.$traveler->reference_code)
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('name', fn (string $name): bool => str_contains($name, 'ControllerTraveler'))
        ->assertJsonPath('current_session_code', $sourceSession->code)
        ->assertJsonPath('current_session_status', $sourceSession->status)
        ->assertJsonPath('has_prior_submission', false);

    $this->actingAs($dutyOnlyClerk)
        ->post(route('exams.sessions.monitoring.accommodate', $targetSession), [
            'reference_code' => $traveler->reference_code,
            'reason_category' => 'travel_distance',
            'reason_notes' => 'Arrived early from distant town',
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.monitoring.accommodate', $targetSession), [
            'reference_code' => $traveler->reference_code,
            'reason_category' => 'travel_distance',
            'reason_notes' => 'Arrived early from distant town',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Applicant successfully accommodated into this session.');
    expect(ExamAssignment::query()
        ->where('exam_session_id', $targetSession->id)
        ->where('admission_attempt_id', $traveler->id)
        ->active()
        ->exists())->toBeTrue();
    expect(ProctorDuty::query()->whereKey($historicalDuty->id)->exists())->toBeTrue();
});

test('test administrator applicant lookup and accommodation hide foreign institution applicants', function (): void {
    $foreignInstitution = Institution::query()->create([
        'code' => 'EAMA-FOREIGN-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Test Institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'EAMA-FOREIGN-CYCLE-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Test Cycle',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => now()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $placeholder = eamaAcceptedAttempt($this->admin, 'eama-foreign-placeholder@example.test', 'Placeholder');
    $foreignAttempt = eamaAcceptedAttempt($this->admin, 'eama-foreign-applicant@example.test', 'ForeignApplicant');
    $foreignAttempt->update([
        'institution_id' => $foreignInstitution->id,
        'academic_cycle_id' => $foreignCycle->id,
    ]);
    $session = eamaPublishedSession($this->admin, 'EAMA-FOREIGN-APPLICANT', [$placeholder]);

    $this->actingAs($this->admin)
        ->get(route('exams.sessions.monitoring.lookup-applicant', $session).'?reference_code='.$foreignAttempt->reference_code)
        ->assertNotFound();
    $this->actingAs($this->admin)
        ->post(route('exams.sessions.monitoring.accommodate', $session), [
            'reference_code' => $foreignAttempt->reference_code,
            'reason_category' => 'travel_distance',
            'reason_notes' => 'Applicant belongs to another institution',
        ])
        ->assertNotFound();
});

test('distribution endpoint enforces authorization and returns no-store header', function (): void {
    $attempt = eamaAcceptedAttempt($this->admin, 'eama-dist@example.test', 'Distribution');
    $session = eamaPublishedSession($this->admin, 'EAMA-DIST', [$attempt]);
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->getJson("/exams/sessions/{$session->id}/monitoring/distribution")
        ->assertForbidden();

    $response = $this->actingAs($this->admin)
        ->getJson("/exams/sessions/{$session->id}/monitoring/distribution");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Booklet distribution requires an exact material snapshot; physical seating is independent of form selection.');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

function eamaAcceptedAttempt(User $actor, string $email, string $lastName): AdmissionAttempt
{
    $result = submitSyntheticApplicant($email, $lastName);

    return app(AcceptAdmissionAttempt::class)->handle($result['attempt'], $actor, 1);
}

/**
 * @param  array<int, AdmissionAttempt>  $attempts
 */
function eamaPublishedSession(
    User $actor,
    string $code,
    array $attempts,
    ?Room $room = null,
    ?Carbon $startsAt = null,
): ExamSession {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room ??= Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt ??= now()->subHour();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        $code,
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHours(3)->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );

    foreach ($attempts as $attempt) {
        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);
    }

    return app(PublishExamSession::class)->handle($session, $actor, $session->version)->fresh();
}
