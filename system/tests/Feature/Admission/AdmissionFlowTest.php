<?php

use App\Admission\Support\ApplicantAccessKey;
use App\Models\User;

test('public submit creates attempt and hashed challenge only', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant();

    expect($result['attempt']->status)->toBe('pending')
        ->and($result['access_token'])->toHaveLength(9)
        ->and($result['access_token'])->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/');

    $this->assertDatabaseMissing('applicant_access_challenges', [
        'token_hash' => $result['access_token'],
    ]);

    $this->assertDatabaseHas('applicant_access_challenges', [
        'token_hash' => ApplicantAccessKey::hash($result['access_token']),
    ]);
});

test('duplicate identity in same cycle returns conflict', function (): void {
    seedE2Foundation();

    submitSyntheticApplicant('dup@example.test');

    expect(fn () => submitSyntheticApplicant('dup@example.test'))
        ->toThrow(\App\Admission\Exceptions\DuplicateAdmissionAttemptException::class);
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

    $cycle = \App\Models\Foundation\AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = \App\Models\Foundation\Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-TEST-1',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'effective_end_at' => now()->addHour()->toDateTimeString(),
    ]);

    $session = \App\Models\Examination\ExamSession::query()->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $result['attempt']->id,
    ]);

    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), [
        'expected_version' => 1,
    ]);

    // D-015: only Guidance clerk/support may hold a proctor duty; the counselor
    // holds GUIDANCE_PROCTOR_ASSIGN (may assign) but is not an eligible assignee.
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)->post(route('proctor-duty.assign', $session), [
        'proctor_user_id' => $clerk->id,
        'idempotency_key' => 'proctor-1',
    ]);

    $assignment = \App\Models\Examination\ExamAssignment::query()->firstOrFail();
    $session = $assignment->examSession;

    $this->actingAs($clerk)->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
        'outcome' => 'present',
        'idempotency_key' => 'att-1',
    ])->assertRedirect();

    $first = \App\Models\Examination\ExamAttendanceRecord::query()->count();

    $this->actingAs($clerk)->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
        'outcome' => 'present',
        'idempotency_key' => 'att-1',
    ])->assertRedirect();

    expect(\App\Models\Examination\ExamAttendanceRecord::query()->count())->toBe($first);
});

test('proctor without duty is denied', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($registrar)->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1]);

    $cycle = \App\Models\Foundation\AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = \App\Models\Foundation\Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-TEST-2',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'effective_end_at' => now()->addHour()->toDateTimeString(),
    ]);

    $session = \App\Models\Examination\ExamSession::query()->latest('id')->firstOrFail();
    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $result['attempt']->id,
    ]);
    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), ['expected_version' => 1]);

    $assignment = \App\Models\Examination\ExamAssignment::query()->latest('id')->firstOrFail();
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
        ->and(\App\Models\Admission\ApplicantIdentity::query()->whereKey($identityId)->exists())->toBeTrue();
});

test('submit persists academic and program preference fields from legacy intake contract', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant('fields@example.test');
    $attempt = $result['attempt']->fresh();

    expect($attempt->last_school_enrolled)->toBe('Sample Senior High School')
        ->and($attempt->strand)->toBe('STEM')
        ->and((string) $attempt->gwa)->toStartWith('1.75')
        ->and($attempt->gradeValues()->where('field_code', 'ENG')->value('value'))->toStartWith('88')
        ->and($attempt->gradeValues()->where('field_code', 'MATH')->value('value'))->toStartWith('85')
        ->and($attempt->program_preference_1_code)->toBe('BSN')
        ->and($attempt->applicant_type)->toBe('new')
        ->and($attempt->city)->toBe('Tagudin')
        ->and($attempt->privacy_policy_id)->not->toBeNull();
});

test('apply form exposes active privacy policy for consent dialog', function (): void {
    seedE2Foundation();

    $this->get(route('apply.form'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Apply')
            ->has('privacy_policy.title')
            ->has('privacy_policy.content')
            ->where('privacy_policy.title', 'Privacy Policy')
            ->has('grade_fields')
            ->where('grade_fields.0.code', 'GWA')
        );
});
