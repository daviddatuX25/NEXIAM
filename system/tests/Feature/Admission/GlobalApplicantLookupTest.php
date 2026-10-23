<?php

use App\Admission\Support\ApplicantQrCode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;

test('registrar lookup opens pending clearance without changing applicant state', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-clearance@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->postJson('/staff/scan/resolve', ['code' => ApplicantQrCode::issue($attempt)])
        ->assertOk()
        ->assertJsonPath('scan_result.state', 'resolved')
        ->assertJsonPath('default_destination.key', 'enrollment_eligibility')
        ->assertJsonPath('default_destination.url', route('registrar.enrollment-eligibility.show', $attempt))
        ->assertJsonPath('requires_choice', false);

    expect($attempt->fresh()->status)->toBe($attempt->status)
        ->and($attempt->fresh()->version)->toBe($attempt->version);
});

test('academic lookup and name search disclose no unrelated applicant identity', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-foreign@example.test', 'Foreigncandidate')['attempt'];
    $attempt->update(['status' => 'accepted', 'program_preference_1_code' => 'BA-Psych']);
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $this->actingAs($head)->postJson('/staff/scan/resolve', ['code' => ApplicantQrCode::issue($attempt)])
        ->assertOk()->assertJsonPath('scan_result.state', 'no_destination')->assertJsonMissingPath('scanned_attempt');
    $this->getJson('/staff/scan/search?q=Foreign')->assertOk()->assertJsonCount(0, 'results');
    $this->getJson("/staff/scan/applicants/{$attempt->id}/actions")
        ->assertOk()->assertJsonMissingPath('scanned_attempt')->assertJsonCount(0, 'destinations');
});

test('scheduling-only staff and counselors reach their own furthest viewable applicant surface', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-scheduling@example.test')['attempt'];
    $attempt->update(['status' => 'accepted']);
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $lookup = $this->actingAs($clerk)->postJson('/staff/scan/resolve', ['code' => $attempt->reference_code])
        ->assertOk()->assertJsonPath('default_destination.key', 'exam_waitlist')->json();
    expect(array_column($lookup['destinations'], 'key'))->not->toContain('admission_support');
    expect($lookup['default_destination']['url'])->toContain('focus_attempt_id='.$attempt->id);

    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($counselor)->postJson('/staff/scan/resolve', ['code' => $attempt->reference_code])
        ->assertOk()->assertJsonPath('default_destination.key', 'admission_support');
});

test('name discovery disambiguates identities and keeps current and historical cycles separate', function (): void {
    seedE2Foundation();
    $current = submitSyntheticApplicant('qr-name-current@example.test', 'Samecandidate')['attempt'];
    $historical = submitSyntheticApplicant('qr-name-old@example.test', 'Othercandidate')['attempt'];
    $historical->identity->update(['last_name' => 'Samecandidate', 'birthdate' => '2002-02-02']);
    $homonym = submitSyntheticApplicant('qr-name-homonym@example.test', 'Differentcandidate')['attempt'];
    $homonym->identity->update(['last_name' => 'Samecandidate', 'birthdate' => '2003-03-03']);
    $oldCycle = AcademicCycle::query()->findOrFail($current->academic_cycle_id)->replicate();
    $oldCycle->forceFill(['code' => 'QR-OLD-CYCLE', 'school_year' => '2024-2025', 'is_active' => false])->save();
    $historical->update(['academic_cycle_id' => $oldCycle->id]);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->getJson('/staff/scan/search?q=Samecandidate')
        ->assertOk()->assertJsonCount(2, 'results')->assertJsonPath('results.0.id', $current->id)
        ->assertJsonPath('results.1.id', $homonym->id)
        ->assertJsonMissingPath('results.0.birthdate')->assertJsonMissingPath('results.0.contact_email');
    $this->getJson('/staff/scan/search?q=Samecandidate&academic_cycle_id='.$oldCycle->id)
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', $historical->id);
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $this->actingAs($head)->getJson('/staff/scan/search?q=Samecandidate&academic_cycle_id='.$oldCycle->id)
        ->assertOk()->assertJsonCount(0, 'results');
});

test('Dean must choose between legitimate programs instead of an arbitrary first program', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-dean@example.test')['attempt'];
    $attempt->update(['status' => 'accepted', 'program_preference_2_code' => 'BA-Psych']);
    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
    $this->actingAs($dean)->postJson('/staff/scan/resolve', ['code' => $attempt->reference_code])
        ->assertOk()->assertJsonPath('requires_choice', true)->assertJsonPath('default_destination', null);
    $this->postJson('/staff/scan/resolve', ['code' => $attempt->reference_code, 'program_code' => 'BSIT'])
        ->assertOk()->assertJsonPath('requires_choice', false)->assertJsonPath('default_destination.key', 'program_record_BSIT')
        ->assertJsonFragment(['key' => 'program_record_BA-Psych']);
});

test('manual scorer gets exact score context without being granted counseling', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-scorer@example.test')['attempt'];
    $attempt->update(['status' => 'accepted']);
    $scorer = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $scorer->syncRoles([]);
    $scorer->syncPermissions([CapabilityCatalog::APPLICANT_QR_RESOLVE, CapabilityCatalog::GUIDANCE_SCORING_MANUAL]);
    $exam = ExamSession::factory()->create(['academic_cycle_id' => $attempt->academic_cycle_id, 'status' => 'completed']);
    $assignment = ExamAssignment::factory()->create(['exam_session_id' => $exam->id, 'admission_attempt_id' => $attempt->id]);
    GradingSession::query()->create(['exam_session_id' => $exam->id, 'status' => 'open', 'version' => 1, 'opened_by_user_id' => $scorer->id, 'opened_at' => now()]);
    ExamAttendanceRecord::query()->create(['exam_assignment_id' => $assignment->id, 'recorded_by_user_id' => $scorer->id, 'outcome' => 'present', 'idempotency_key' => 'qr-attendance', 'recorded_at' => now()]);
    ExamSubmissionRecord::query()->create(['exam_assignment_id' => $assignment->id, 'recorded_by_user_id' => $scorer->id, 'idempotency_key' => 'qr-submission', 'submitted_at' => now()]);

    $payload = $this->actingAs($scorer)->postJson('/staff/scan/resolve', ['code' => $attempt->reference_code])
        ->assertOk()->assertJsonPath('default_destination.url', route('scoring.sessions.attempts.scores', ['examSession' => $exam->id, 'attempt' => $attempt->id]))->json();
    expect(array_column($payload['destinations'], 'key'))->not->toContain('admission_support', 'case_analysis');
});

test('focused applicant navigation does not reconcile an overdue exam session', function (string $destination): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-overdue@example.test')['attempt'];
    $attempt->update(['status' => 'accepted']);
    $exam = ExamSession::factory()->create([
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'status' => 'published',
        'starts_at' => now()->subHours(3),
        'effective_end_at' => now()->subHour(),
    ]);
    $assignment = ExamAssignment::factory()->create(['exam_session_id' => $exam->id, 'admission_attempt_id' => $attempt->id]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($guidance)->get(route($destination, ['session' => $exam->id, 'focus_assignment_id' => $assignment->id]))->assertOk();
    expect($exam->fresh()->status)->toBe('published')
        ->and(ExamAttendanceRecord::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeFalse();
})->with(['exams.sessions.show', 'exams.sessions.monitoring']);

test('Direct Assessment submission retains its validation boundary after adding applicant focus', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($guidance)->postJson('/exams/direct-assessment', [])
        ->assertUnprocessable()->assertJsonValidationErrors('applicant_ids');
});
