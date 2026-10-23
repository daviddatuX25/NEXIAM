<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';

beforeEach(function (): void {
    seedE2Foundation();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->testAdministrator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $profileVersion = createTestApprovedProfileVersion($this, $this->testAdministrator, 'DIRECT-'.strtoupper(bin2hex(random_bytes(3))));
    $kitVersion = createTestActiveKitVersion($this->testAdministrator, $profileVersion);
    $this->kitVersionId = (int) $kitVersion->id;
});

function directAssessmentAcceptedApplicant(string $email, string $lastName = 'Candidate'): AdmissionAttempt
{
    $attempt = submitSyntheticApplicant($email, $lastName)['attempt'];

    return app(AcceptAdmissionAttempt::class)->handle(
        $attempt,
        User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail(),
        1,
    );
}

function directAssessmentPayload(
    AcademicCycle $cycle,
    AdmissionAttempt $attempt,
    int $kitVersionId,
    array $overrides = [],
): array {
    return [
        'academic_cycle_id' => $cycle->id,
        'applicant_ids' => [$attempt->id],
        'label' => 'Walk-in assessment cohort',
        'date' => '2026-10-15',
        'start_time' => '09:30',
        'end_time' => '11:00',
        'test_kit_version_id' => $kitVersionId,
        ...$overrides,
    ];
}

test('authorized staff can view the direct assessment create page', function (): void {
    $this->actingAs($this->testAdministrator)
        ->get(route('exams.direct-assessment.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/DirectAssessment/Create')
            ->has('applicants')
            ->has('kitVersions')
        );
});

test('unauthorized user is denied access to direct assessment', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('exams.direct-assessment.create'))
        ->assertForbidden();
});

test('test admin creates direct assessment and is redirected to grading session', function (): void {
    $attempt = directAssessmentAcceptedApplicant('direct.assessment.admin@example.test');

    $response = $this->actingAs($this->testAdministrator)
        ->post(route('exams.direct-assessment.store'), directAssessmentPayload($this->cycle, $attempt, $this->kitVersionId));

    $session = ExamSession::query()->where('label', 'Walk-in assessment cohort')->firstOrFail();
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();
    $gradingSession = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();

    $response->assertRedirect("/grading/sessions/{$gradingSession->id}");

    expect($session->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and($session->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and($session->starts_at->toDateTimeString())->toBe('2026-10-15 09:30:00')
        ->and($session->effective_end_at->toDateTimeString())->toBe('2026-10-15 11:00:00')
        ->and($session->test_kit_version_id)->toBe($this->kitVersionId)
        ->and($assignment->admission_attempt_id)->toBe($attempt->id)
        ->and(ExamAttendanceRecord::query()->where('exam_assignment_id', $assignment->id)->value('outcome'))
        ->toBe(ExamAttendanceRecord::OUTCOME_PRESENT)
        ->and(ExamSubmissionRecord::query()->where('exam_assignment_id', $assignment->id)->whereNotNull('submitted_at')->exists())->toBeTrue()
        ->and($gradingSession->status)->toBe(GradingSession::STATUS_OPEN);
});

test('registrar creates direct assessment and is redirected back with flash', function (): void {
    $attempt = directAssessmentAcceptedApplicant('direct.assessment.registrar@example.test');

    $response = $this->actingAs($this->registrar)
        ->from('/exams/direct-assessment')
        ->post(route('exams.direct-assessment.store'), directAssessmentPayload($this->cycle, $attempt, $this->kitVersionId));

    $response->assertRedirect('/exams/direct-assessment')
        ->assertSessionHas('direct_assessment_created', true);

    expect(ExamSession::query()->where('label', 'Walk-in assessment cohort')->exists())->toBeTrue();
});

test('cannot create direct assessment for an unaccepted applicant', function (string $status): void {
    $attempt = submitSyntheticApplicant("direct.assessment.{$status}@example.test")['attempt'];
    if ($status === AdmissionAttempt::STATUS_DISMISSED) {
        $attempt->update(['status' => AdmissionAttempt::STATUS_DISMISSED]);
    }

    $sessionsBefore = ExamSession::query()->count();
    $assignmentsBefore = ExamAssignment::query()->count();

    expect(fn () => $this->withoutExceptionHandling()
        ->actingAs($this->testAdministrator)
        ->post(route('exams.direct-assessment.store'), directAssessmentPayload($this->cycle, $attempt, $this->kitVersionId)))
        ->toThrow(DomainException::class, "Applicant {$attempt->reference_code} is not in accepted status ({$status}).");

    expect(ExamSession::query()->count())->toBe($sessionsBefore)
        ->and(ExamAssignment::query()->count())->toBe($assignmentsBefore)
        ->and($attempt->fresh()->status)->toBe($status);
})->with([
    'pending' => AdmissionAttempt::STATUS_PENDING,
    'dismissed' => AdmissionAttempt::STATUS_DISMISSED,
]);

test('cannot create direct assessment for an applicant with an active assignment', function (): void {
    $attempt = directAssessmentAcceptedApplicant('direct.assessment.assigned@example.test');
    $existingSession = ExamSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'code' => 'EX-DIR-EXISTING',
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'version' => 1,
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $existingSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
        'version' => 1,
    ]);

    $sessionsBefore = ExamSession::query()->count();
    $assignmentsBefore = ExamAssignment::query()->count();

    expect(fn () => $this->withoutExceptionHandling()
        ->actingAs($this->testAdministrator)
        ->post(route('exams.direct-assessment.store'), directAssessmentPayload($this->cycle, $attempt, $this->kitVersionId)))
        ->toThrow(DomainException::class, "Applicant {$attempt->reference_code} already has an active examination assignment.");

    expect(ExamSession::query()->count())->toBe($sessionsBefore)
        ->and(ExamAssignment::query()->count())->toBe($assignmentsBefore);
});

test('admissions bulk direct assessment endpoint assigns a cohort', function (): void {
    $attempt = directAssessmentAcceptedApplicant('direct.assessment.bulk@example.test');

    $response = $this->actingAs($this->testAdministrator)
        ->post(route('admissions.bulk-direct-assessment'), directAssessmentPayload($this->cycle, $attempt, $this->kitVersionId, [
            'label' => 'Bulk direct assessment cohort',
        ]));

    $session = ExamSession::query()->where('label', 'Bulk direct assessment cohort')->firstOrFail();

    $response->assertRedirect('/grading/sessions/'.GradingSession::query()->where('exam_session_id', $session->id)->value('id'));
    expect($session->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and($session->assignments()->where('admission_attempt_id', $attempt->id)->exists())->toBeTrue();
});
