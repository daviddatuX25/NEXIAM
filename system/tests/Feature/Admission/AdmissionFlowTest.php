<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Exceptions\DuplicateAdmissionAttemptException;
use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Database\Seeders\PublicIntakeConsentDemoSeeder;

test('public submit creates attempt and hashed challenge only', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant();

    expect($result['attempt']->status)->toBe('pending')
        ->and($result['attempt']->version)->toBe(1)
        ->and(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $result['attempt']->id)->where('is_current', true)->exists())->toBeTrue()
        ->and($result['access_token'])->toHaveLength(9)
        ->and($result['access_token'])->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/');

    $this->assertDatabaseMissing('applicant_access_challenges', [
        'token_hash' => $result['access_token'],
    ]);

    $this->assertDatabaseHas('applicant_access_challenges', [
        'token_hash' => ApplicantAccessKey::hash($result['access_token']),
    ]);
});

test('duplicate identity in same cycle returns conflict with existing attempt', function (): void {
    seedE2Foundation();

    $first = submitSyntheticApplicant('dup@example.test');

    try {
        submitSyntheticApplicant('dup@example.test');
        $this->fail('Expected DuplicateAdmissionAttemptException was not thrown');
    } catch (DuplicateAdmissionAttemptException $e) {
        expect($e->existingAttempt)->not->toBeNull()
            ->and($e->existingAttempt->reference_code)->toBe($first['attempt']->reference_code);
    }
});

test('returning applicant from prior academic cycle is allowed to apply for new cycle', function (): void {
    seedE2Foundation();

    // 1. Submit first attempt
    $first = submitSyntheticApplicant('returning@example.test');
    $identity = $first['attempt']->identity;

    // 2. Simulate the first attempt belonging to an archived prior cycle
    $priorCycle = AcademicCycle::query()->create([
        'institution_id' => $first['attempt']->institution_id,
        'code' => 'PRIOR-CYCLE-2025',
        'name' => 'Academic Year 2025–2026',
        'is_active' => false,
        'opens_on' => now()->subYear(),
        'closes_on' => now()->subMonths(6),
    ]);
    $first['attempt']->forceFill(['academic_cycle_id' => $priorCycle->id])->save();

    // 3. Re-apply with same identity in current cycle -> must succeed and create fresh attempt
    $second = submitSyntheticApplicant('returning@example.test');

    expect($second['attempt']->id)->not->toBe($first['attempt']->id)
        ->and($second['attempt']->applicant_identity_id)->toBe($identity->id)
        ->and($identity->admissionAttempts()->count())->toBe(2);
});

test('registrar accept requires capability', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertForbidden();
});

test('registrar accept succeeds with grant', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    expect($result['attempt']->fresh()->status)->toBe('accepted');
});

test('attendance idempotency returns same record', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1]);

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-TEST-1',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'effective_end_at' => now()->addHour()->toDateTimeString(),
    ]);

    $session = ExamSession::query()->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $result['attempt']->id,
    ]);

    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), [
        'expected_version' => 1,
    ])->assertSessionHasNoErrors();
    $session->refresh();
    $this->actingAs($guidance)->post(route('exams.sessions.start', $session), [
        'expected_version' => $session->version,
        'duration_minutes' => 60,
    ])->assertSessionHasNoErrors();

    // The assigned applicant is conducted by the unassigned Test Administrator.

    $assignment = ExamAssignment::query()->firstOrFail();
    $session = $assignment->examSession;

    $this->actingAs($guidance)->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
        'outcome' => 'present',
        'idempotency_key' => 'att-1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $first = ExamAttendanceRecord::query()->sole();
    expect($first->outcome)->toBe('present');

    $this->actingAs($guidance)->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
        'outcome' => 'present',
        'idempotency_key' => 'att-1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ExamAttendanceRecord::query()->sole()->id)->toBe($first->id);
});

test('staff without test administrator capability is denied attendance recording', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($registrar)->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1]);

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-TEST-2',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'effective_end_at' => now()->addHour()->toDateTimeString(),
    ]);

    $session = ExamSession::query()->latest('id')->firstOrFail();
    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $result['attempt']->id,
    ]);
    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), ['expected_version' => 1])->assertSessionHasNoErrors();
    $session->refresh();
    $this->actingAs($guidance)->post(route('exams.sessions.start', $session), [
        'expected_version' => $session->version,
        'duration_minutes' => 60,
    ])->assertSessionHasNoErrors();

    $assignment = ExamAssignment::query()->latest('id')->firstOrFail();
    $session = $assignment->examSession;

    $this->actingAs($hr)->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
        'outcome' => 'present',
        'idempotency_key' => 'att-deny',
    ])->assertForbidden();
});

test('reopen is non-destructive and restores pending without deleting identity', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $identityId = $result['attempt']->applicant_identity_id;

    $this->actingAs($registrar)
        ->post(route('admissions.dismiss', $result['attempt']), [
            'expected_version' => 1,
            'reason' => 'Incomplete documents',
        ])
        ->assertRedirect();

    $this->actingAs($registrar)
        ->post(route('admissions.reopen', $result['attempt']->fresh()), [
            'expected_version' => 2,
            'reason' => 'Documents received',
        ])
        ->assertRedirect();

    expect($result['attempt']->fresh()->status)->toBe('pending')
        ->and(ApplicantIdentity::query()->whereKey($identityId)->exists())->toBeTrue();
});

test('submit preserves school and program fields without persisting catalog grades', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant('fields@example.test');
    $attempt = $result['attempt']->fresh();

    expect($attempt->last_school_enrolled)->toBe('Sample Senior High School')
        ->and($attempt->strand)->toBe('STEM')
        ->and($attempt->gwa)->toBeNull()
        ->and($attempt->gradeValues()->exists())->toBeFalse()
        ->and($attempt->program_preference_1_code)->toBe('BSIT')
        ->and($attempt->applicant_type)->toBe('new')
        ->and($attempt->city)->toBe('Tagudin')
        ->and($attempt->contact_email)->toBe('fields@example.test')
        ->and($attempt->privacy_policy_id)->toBeNull()
        ->and($attempt->public_policy_id)->not->toBeNull()
        ->and($attempt->payload['consent']['source'])->toBe('public_policy')
        ->and($attempt->payload['consent']['revision_id'])->not->toBeNull()
        ->and($attempt->payload['consent']['digest'])->toMatch('/\\A[a-f0-9]{64}\\z/');
});

test('apply form exposes the current designated published privacy revision for consent dialog', function (): void {
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    $policy = PublicPolicy::designatedIntake((int) Institution::current()->getKey());
    $revision = $policy?->publishedRevision;

    $this->get(route('apply.form'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Apply')
            ->has('privacy_policy.title')
            ->has('privacy_policy.content')
            ->where('privacy_policy.title', $revision->title)
            ->where('privacy_policy.revision_id', $revision->id)
            ->where('privacy_policy.revision_number', $revision->revision)
            ->where('privacy_policy.digest', $revision->contentDigest())
            ->where('grade_fields', [])
        );
});

test('submit ignores legacy GWA values and persists no catalog grades', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    (new PublicIntakeConsentDemoSeeder)->run();
    $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
        ?? throw new RuntimeException('A designated public intake policy is required.');
    $revision = $policy->publishedRevision
        ?? throw new RuntimeException('The designated policy must have a published revision.');

    $intake = new AdmissionIntakeData(
        firstName: 'Optional',
        lastName: 'Grades',
        birthdate: '2002-05-10',
        sex: 'female',
        contactEmail: 'optional.grades.'.uniqid('', true).'@example.test',
        applicantType: 'new',
        programPreferenceCodes: ['BSIT'],
        grades: [
            'GWA' => '1.50',
        ],
        middleName: null,
        suffix: null,
        contactPhone: null,
        lastSchoolEnrolled: 'Province High School',
        strand: 'HUMSS',
        addressLine: null,
        city: 'Tagudin',
        province: 'Ilocos Sur',
        zipCode: '2714',
        termsAccepted: true,
        publicPolicyId: (int) $policy->getKey(),
        publicPolicyRevisionId: (int) $revision->getKey(),
        publicPolicyRevisionDigest: $revision->contentDigest(),
    );

    $result = app(SubmitAdmissionAttempt::class)->handle(
        $institution,
        $cycle,
        $window,
        $intake,
        'test',
    );

    $attempt = $result['attempt']->fresh();
    expect($attempt->status)->toBe('pending')
        ->and($attempt->gwa)->toBeNull()
        ->and($attempt->gradeValues()->exists())->toBeFalse();
});

test('applicant authentication provisions account using synthetic email fallback when email is empty', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant();
    $identity = $result['attempt']->identity;
    $accessKey = $result['access_token'];

    $identity->forceFill(['email' => null])->save();

    $authenticatedUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $accessKey,
        '2001-01-01'
    );

    expect($authenticatedUser)->not->toBeNull()
        ->and($authenticatedUser->email)->toBe(sprintf('applicant.%d@internal.nexiam.local', (int) $identity->id));
});

test('applicant portal shares applicant_qr payload with attempt and issue token', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant();
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->has('applicant_qr.attempt_id')
            ->has('applicant_qr.reference_code')
            ->has('applicant_qr.qr')
            ->where('applicant_qr.attempt_id', $result['attempt']->id)
        );
});
