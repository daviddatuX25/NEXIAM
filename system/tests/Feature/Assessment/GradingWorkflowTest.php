<?php

use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;

test('open grading session is idempotent per exam session', function (): void {
    $fixture = buildGradingFixture($this, close: false);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];

    // Completed without going through close (e.g. a session completed before
    // grading existed) — no auto-opened grading session, so the manual path
    // owns creation and records the opener.
    $session->update(['status' => ExamSession::STATUS_COMPLETED]);

    $this->actingAs($guidance)
        ->post(route('grading.store'), ['exam_session_id' => $session->id])
        ->assertRedirect();

    $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    expect($grading->status)->toBe(GradingSession::STATUS_OPEN)
        ->and($grading->opened_by_user_id)->toBe($guidance->id);

    // Re-open returns the same session (unique exam_session_id).
    $this->actingAs($guidance)
        ->post(route('grading.store'), ['exam_session_id' => $session->id])
        ->assertRedirect(route('grading.sessions.show', $grading->id));

    expect(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(1);
});

test('closing an exam session auto-opens a grading session', function (): void {
    $fixture = buildGradingFixture($this);
    expect($fixture['grading'])->not->toBeNull()
        ->and($fixture['grading']->status)->toBe(GradingSession::STATUS_OPEN);
});

test('session roster derives from active exam assignments', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.show', $grading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/GradingSession')
            ->has('applicants', 2)
            ->where('progress_stats.total', 2)
            ->where('progress_stats.scored', 0)
        );

    // Score one domain for one applicant; progress reflects partial completion.
    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => [['aptitude_area_id' => $areas[0], 'score' => 80]],
        'idempotency_key' => 'key-partial',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.show', $grading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('applicants', 2)
            ->where('progress_stats.scored', 0)
            ->where('applicants.0.domains_complete', 1)
            ->where('applicants.0.scored', false)
        );
});

test('multi-domain save is transactional and idempotent', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $payload = [
        'scores' => array_map(fn (int $areaId): array => [
            'aptitude_area_id' => $areaId,
            'score' => 85,
        ], $areas),
        'idempotency_key' => 'scores-idem-1',
    ];

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), $payload)
        ->assertRedirect(route('grading.sessions.show', $grading));

    expect(ScoreRecord::query()->count())->toBe(count($areas));

    // Replay with the same key is a no-op (no duplicate rows, same values).
    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), $payload)
        ->assertRedirect();

    expect(ScoreRecord::query()->count())->toBe(count($areas));

    $records = ScoreRecord::query()->where('admission_attempt_id', $attempt->id)->get();
    expect($records->pluck('score')->every(fn ($score): bool => (float) $score === 85.0))->toBeTrue()
        ->and($records->pluck('recorded_by_user_id')->unique()->all())->toBe([$guidance->id]);
});

test('attempt not in roster is denied', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    $outsider = submitSyntheticApplicant('outsider.'.uniqid().'@example.test', 'Outsider')['attempt'];
    $areas = activeAreas();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.attempts.scores', [$grading, $outsider]))
        ->assertNotFound();

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $outsider]), [
            'scores' => [['aptitude_area_id' => $areas[0], 'score' => 80]],
            'idempotency_key' => 'key-outsider',
        ])
        ->assertNotFound();

    $this->actingAs($guidance)
        ->delete(route('grading.sessions.attempts.scores.destroy', [$grading, $outsider]))
        ->assertNotFound();

    expect(ScoreRecord::query()->where('admission_attempt_id', $outsider->id)->count())->toBe(0);
});

test('scores can be cleared for an attempt in an open session', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 75], $areas),
        'idempotency_key' => 'key-clear-1',
    ])->assertRedirect();

    expect(ScoreRecord::query()->count())->toBe(count($areas));

    $this->actingAs($guidance)
        ->delete(route('grading.sessions.attempts.scores.destroy', [$grading, $attempt]))
        ->assertRedirect(route('grading.sessions.show', $grading));

    expect(ScoreRecord::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);

    $this->actingAs($guidance)
        ->get(route('grading.sessions.show', $grading))
        ->assertInertia(fn ($page) => $page
            ->where('applicants.0.scored', false)
            ->where('applicants.0.domains_complete', 0)
        );
});

test('finalize rejects incomplete scores', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    // Score only one of the active areas.
    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => [['aptitude_area_id' => $areas[0], 'score' => 80]],
        'idempotency_key' => 'key-incomplete',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertSessionHasErrors('finalize');

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('finalize rejects when an attempt has no scores at all', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 90], $areas),
        'idempotency_key' => 'key-one-of-two',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertSessionHasErrors('finalize');

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('finalize rejects an empty roster', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 1);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];
    $attempt = $fixture['attempts'][0];

    // Remove the only assignment so the roster is empty, then open grading.
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.remove', [$session, $assignment]), [
            'reason' => 'no-show fixture',
        ])->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('grading.store'), ['exam_session_id' => $session->id])
        ->assertRedirect();

    $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertSessionHasErrors('finalize');

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('finalize succeeds when complete, bumps version, and locks scores', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempts = $fixture['attempts'];
    $areas = activeAreas();

    foreach ($attempts as $attempt) {
        $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 88], $areas),
            'idempotency_key' => 'key-complete-'.$attempt->id,
        ])->assertRedirect();
    }

    $versionBefore = (int) $grading->version;

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $versionBefore])
        ->assertSessionHasNoErrors();

    $finalized = $grading->fresh();
    expect($finalized->status)->toBe(GradingSession::STATUS_FINALIZED)
        ->and((int) $finalized->version)->toBe($versionBefore + 1)
        ->and($finalized->finalized_at)->not->toBeNull()
        ->and($finalized->finalized_by_user_id)->toBe($guidance->id);

    // Immutable: saves and clears are rejected after finalize.
    $attempt = $attempts[0];
    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => [['aptitude_area_id' => $areas[0], 'score' => 50]],
        'idempotency_key' => 'key-after-finalize',
    ])->assertSessionHasErrors('scores');

    $this->actingAs($guidance)
        ->delete(route('grading.sessions.attempts.scores.destroy', [$grading, $attempt]))
        ->assertSessionHasErrors('scores');
});

test('finalize rejects a stale version', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 88], $areas),
        'idempotency_key' => 'key-stale',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => (int) $grading->version + 99])
        ->assertSessionHasErrors('finalize');

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('finalize requires a positive integer expected version', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    $this->actingAs($guidance)
        ->postJson(route('grading.finalize', $grading), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expected_version');

    $this->actingAs($guidance)
        ->postJson(route('grading.finalize', $grading), ['expected_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expected_version');
});

test('grading session and score routes reject a foreign institution scope', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();
    $foreignInstitution = Institution::query()->create([
        'code' => 'GRADING-FOREIGN-'.uniqid(),
        'name' => 'Foreign grading institution',
    ]);
    $guidance->staffProfile->update(['institution_id' => $foreignInstitution->id]);

    $this->actingAs($guidance)
        ->get(route('grading.sessions.show', $grading))
        ->assertNotFound();
    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertNotFound();
    $this->actingAs($guidance)
        ->get(route('grading.sessions.attempts.scores', [$grading, $attempt]))
        ->assertNotFound();
    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => [['aptitude_area_id' => $areas[0], 'score' => 80]],
            'idempotency_key' => 'foreign-scope-score',
        ])
        ->assertNotFound();
    $this->actingAs($guidance)
        ->delete(route('grading.sessions.attempts.scores.destroy', [$grading, $attempt]))
        ->assertNotFound();
});

test('grading routes require manual scoring capability', function (): void {
    $fixture = buildGradingFixture($this);
    $registrar = $fixture['registrar'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];

    $this->actingAs($registrar)->get(route('grading.index'))->assertForbidden();
    $this->actingAs($registrar)->get(route('grading.sessions.show', $grading))->assertForbidden();
    $this->actingAs($registrar)->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])->assertForbidden();
    $this->actingAs($registrar)->get(route('grading.sessions.attempts.scores', [$grading, $attempt]))->assertForbidden();
});

test('manual scoring works in degraded mode (REQ-VS-017)', function (): void {
    config(['xiam.mode' => 'degraded']);

    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 82], $areas),
            'idempotency_key' => 'key-degraded',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(ScoreRecord::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(count($areas));

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertSessionHasNoErrors();

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_FINALIZED);
});

test('grading index lists sessions with progress', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($guidance)
        ->get(route('grading.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Grading')
            ->has('sessions', 1)
            ->where('sessions.0.applicants_total', 2)
            ->where('sessions.0.applicants_scored', 0)
        );

    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 77], $areas),
        'idempotency_key' => 'key-index',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->get(route('grading.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sessions.0.applicants_scored', 1)
        );
});

test('grading cannot be opened for an exam session that is not completed', function (): void {
    $fixture = buildGradingFixture($this, close: false);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];

    // close:false leaves the session published — still not gradable (GF-06 §6).
    $this->actingAs($guidance)
        ->post(route('grading.store'), ['exam_session_id' => $session->id])
        ->assertRedirect()
        ->assertSessionHasErrors('open');

    expect(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(0);
});

test('grading cannot be opened for draft, in-progress, or cancelled sessions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    foreach ([ExamSession::STATUS_DRAFT, ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_CANCELLED] as $status) {
        $this->actingAs($guidance)->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-GUARD-'.$status.'-'.uniqid(),
            'starts_at' => now()->addHour()->toDateTimeString(),
            'effective_end_at' => now()->addHours(3)->toDateTimeString(),
        ])->assertRedirect();

        $session = ExamSession::query()->orderByDesc('id')->firstOrFail();
        $session->update(['status' => $status]);

        $this->actingAs($guidance)
            ->post(route('grading.store'), ['exam_session_id' => $session->id])
            ->assertRedirect()
            ->assertSessionHasErrors('open');

        expect(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(0);
    }
});

test('grading cannot be opened for a completed session with no active roster', function (): void {
    $fixture = buildGradingFixture($this, close: false);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];
    $attempt = $fixture['attempts'][0];

    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();
    $this->actingAs($guidance)
        ->post(route('exams.sessions.remove', [$session, $assignment]), [
            'reason' => 'no-show fixture',
        ])->assertRedirect();

    // Completed directly (no close) → no auto-opened grading session; roster is empty.
    $session->update(['status' => ExamSession::STATUS_COMPLETED]);

    $this->actingAs($guidance)
        ->post(route('grading.store'), ['exam_session_id' => $session->id])
        ->assertRedirect()
        ->assertSessionHasErrors('open');

    expect(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(0);
});

test('grading index candidates only list completed sessions without a grading session', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    // Draft session → never a candidate.
    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-CAND-D-'.uniqid(),
        'starts_at' => now()->addHour()->toDateTimeString(),
        'effective_end_at' => now()->addHours(3)->toDateTimeString(),
    ])->assertRedirect();
    $draft = ExamSession::query()->orderByDesc('id')->firstOrFail();

    // Completed without a grading session (direct status change, no close) → the only candidate.
    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-CAND-C-'.uniqid(),
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->subHour()->toDateTimeString(),
    ])->assertRedirect();
    $completed = ExamSession::query()->orderByDesc('id')->firstOrFail();
    $completed->update(['status' => ExamSession::STATUS_COMPLETED]);

    $this->actingAs($guidance)
        ->get(route('grading.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Grading')
            ->has('candidate_exam_sessions', 1)
            ->where('candidate_exam_sessions.0.id', $completed->id)
        );
});

test('re-opening an existing grading session is idempotent and reports it', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $session = $fixture['session'];

    $this->actingAs($guidance)
        ->post(route('grading.store'), ['exam_session_id' => $session->id])
        ->assertRedirect(route('grading.sessions.show', $grading->id))
        ->assertSessionHas('status', 'Grading session already exists.');

    expect(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(1);
});

test('closing is rejected until attendance is recorded for every assignment (GRE-3)', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2, close: false);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];

    // Record attendance for only one of the two assignments.
    $assignment = $session->assignments()->first();
    app(RecordExamAttendance::class)
        ->handle($assignment, $guidance, 'present', 'att-guard-'.$assignment->id);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.close', $session), ['expected_version' => 2])
        ->assertRedirect()
        ->assertSessionHasErrors('close');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(0);
});

test('absent applicant is excluded from the roster and finalize succeeds without their scores (GRE-1/4/5)', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2, close: false);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];

    $assignments = $session->assignments()->orderBy('admission_attempt_id')->get();
    $present = $assignments->first();
    $absent = $assignments->last();

    $attendanceAction = app(RecordExamAttendance::class);
    $submissionAction = app(RecordExamSubmission::class);
    $attendanceAction->handle($present, $guidance, 'present', 'att-present-'.$present->id);
    $submissionAction->handle($present, $guidance, 'sub-present-'.$present->id);
    $attendanceAction->handle($absent, $guidance, 'absent', 'att-absent-'.$absent->id);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.close', $session), ['expected_version' => 2])
        ->assertRedirect();

    $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.show', $grading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('applicants', 1)
            ->where('applicants.0.attempt_id', $present->admission_attempt_id)
            ->where('roster_stats.assigned', 2)
            ->where('roster_stats.present_and_submitted', 1)
            ->where('roster_stats.absent', 1)
            ->where('progress_stats.total', 1)
        );

    // The absent applicant is not score-eligible.
    $areas = activeAreas();
    $this->actingAs($guidance)
        ->get(route('grading.sessions.attempts.scores', [$grading, $absent->admission_attempt_id]))
        ->assertNotFound();

    // Finalize succeeds with scores for the eligible applicant only.
    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $present->admission_attempt_id]), [
        'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 85], $areas),
        'idempotency_key' => 'key-absent-eligible',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertSessionHasNoErrors();

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_FINALIZED);
});

test('present applicant without submission is excluded from the roster (GRE-1/5)', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2, close: false);
    $guidance = $fixture['guidance'];
    $session = $fixture['session'];

    $assignments = $session->assignments()->orderBy('admission_attempt_id')->get();
    $submitted = $assignments->first();
    $noSubmission = $assignments->last();

    $attendanceAction = app(RecordExamAttendance::class);
    $submissionAction = app(RecordExamSubmission::class);
    $attendanceAction->handle($submitted, $guidance, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-nosub-'.$submitted->id);
    $attendanceAction->handle($noSubmission, $guidance, ExamAttendanceRecord::OUTCOME_VOID, 'att-nosub-'.$noSubmission->id);
    $submissionAction->handle($submitted, $guidance, 'sub-nosub-'.$submitted->id);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.close', $session), ['expected_version' => 2])
        ->assertRedirect();

    $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.show', $grading))
        ->assertInertia(fn ($page) => $page
            ->has('applicants', 1)
            ->where('applicants.0.attempt_id', $submitted->admission_attempt_id)
            ->where('roster_stats.present_and_submitted', 1)
            ->where('roster_stats.no_submission', 1)
            ->where('progress_stats.total', 1)
        );

    $areas = activeAreas();
    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $submitted->admission_attempt_id]), [
        'scores' => array_map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 88], $areas),
        'idempotency_key' => 'key-nosub-eligible',
    ])->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
        ->assertSessionHasNoErrors();

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_FINALIZED);
});
test('scoring routes show a completed exam roster and save scores using exam identity', function (): void {
    $fixture = buildGradingFixture($this);
    $examSession = $fixture['session'];
    $attempt = $fixture['attempts'][0];
    $areas = activeAreas();

    $this->actingAs($fixture['guidance'])
        ->get('/scoring')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Assessment/Grading'));

    $this->actingAs($fixture['guidance'])
        ->get('/scoring/sessions/'.$examSession->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/GradingSession')
            ->has('applicants', 1)
            ->where('applicants.0.attempt_id', $attempt->id));

    $this->actingAs($fixture['guidance'])
        ->put('/scoring/sessions/'.$examSession->id.'/attempts/'.$attempt->id.'/scores', [
            'scores' => array_map(fn (int $areaId): array => [
                'aptitude_area_id' => $areaId,
                'score' => 84,
            ], $areas),
            'idempotency_key' => 'scoring-e-route-'.uniqid(),
        ])
        ->assertRedirect('/scoring/sessions/'.$examSession->id);
});
