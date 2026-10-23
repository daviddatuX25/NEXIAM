<?php

use App\Examination\Actions\CreateExamSession;
use App\Examination\Queries\ExamSessionListQuery;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;

function authenticatedExamPageStaff(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $user->authenticationState->session_version,
    ]);
}

test('exam sessions index uses management list without inline create and links to create page', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeCycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Index')
            ->has('sessions')
            ->has('statuses')
            ->has('cycles')
            ->where('suggestion_cycle_id', AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->value('id'))
        );

    ExamSession::factory()->for($activeCycle, 'academicCycle')->create([
        'code' => 'Batch-03',
        'status' => ExamSession::STATUS_CANCELLED,
    ]);

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Create')
            ->has('rooms')
            ->has('cycles')
            ->where('session_code_suggestions.'.$activeCycle->id, 'Batch-04')
        );
});

test('exam session index resolves suggestion cycle from explicit selection or unique active cycle', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeCycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $inactiveCycle = AcademicCycle::query()->create([
        'institution_id' => $activeCycle->institution_id,
        'code' => 'SYN-CYCLE-HISTORICAL',
        'name' => 'Historical Cycle',
        'school_year' => '2024-2025',
        'semester' => 'First Semester',
        'opens_on' => '2024-06-01',
        'closes_on' => '2025-05-31',
        'is_active' => false,
    ]);

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index', ['academic_cycle_id' => $inactiveCycle->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected_cycle_id', $inactiveCycle->id)
            ->where('suggestion_cycle_id', $inactiveCycle->id)
        );

    $inactiveCycle->update(['is_active' => true]);
    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected_cycle_id', null)
            ->where('suggestion_cycle_id', null)
        );

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index', ['status' => 'draft', 'search' => 'EXAM']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected_cycle_id', null)
            ->where('suggestion_cycle_id', null)
        );
});
test('scheduling defaults to draft sessions while published and historical sessions remain explicitly available', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $draft = ExamSession::factory()->for($cycle, 'academicCycle')->create([
        'practice_fixture_set_id' => null,
        'code' => 'QUEUE-DRAFT',
        'status' => ExamSession::STATUS_DRAFT,
    ]);
    $published = ExamSession::factory()->for($cycle, 'academicCycle')->create([
        'practice_fixture_set_id' => null,
        'code' => 'QUEUE-PUBLISHED',
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);
    $running = ExamSession::factory()->for($cycle, 'academicCycle')->create([
        'practice_fixture_set_id' => null,
        'code' => 'QUEUE-RUNNING',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);
    $completed = ExamSession::factory()->for($cycle, 'academicCycle')->create([
        'practice_fixture_set_id' => null,
        'code' => 'QUEUE-COMPLETED',
        'status' => ExamSession::STATUS_COMPLETED,
    ]);

    $general = app(ExamSessionListQuery::class)
        ->query(['status' => 'active'], (int) $cycle->institution_id);
    expect($general->sessions->pluck('id')->all())->toContain($draft->id, $published->id, $running->id);
    $scheduling = app(ExamSessionListQuery::class)
        ->query(['status' => 'active'], (int) $cycle->institution_id, schedulingOnly: true);
    expect($scheduling->summary['by_status'])->toHaveKey(ExamSession::STATUS_DRAFT)
        ->and($scheduling->summary['by_status'])->not->toHaveKeys([
            ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED,
        ]);

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'active')
            ->where('sessions', fn ($sessions): bool => collect($sessions)->contains('id', $draft->id)
                && ! collect($sessions)->contains('id', $published->id)
                && ! collect($sessions)->contains('id', $running->id)
                && ! collect($sessions)->contains('id', $completed->id))
        );

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index', ['status' => ['active']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sessions', fn ($sessions): bool => collect($sessions)->contains('id', $draft->id)
                && ! collect($sessions)->contains('id', $published->id)
                && ! collect($sessions)->contains('id', $running->id)
                && ! collect($sessions)->contains('id', $completed->id))
        );

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index', ['status' => ExamSession::STATUS_PUBLISHED]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sessions', fn ($sessions): bool => collect($sessions)->contains('id', $published->id))
        );

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index', ['status' => ExamSession::STATUS_COMPLETED]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sessions.0.id', $completed->id)
        );

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.index', ['status' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sessions', fn ($sessions): bool => collect($sessions)->contains('id', $draft->id)
                && collect($sessions)->contains('id', $published->id)
                && collect($sessions)->contains('id', $running->id)
                && collect($sessions)->contains('id', $completed->id))
        );
});

test('duplicate session codes return a field error and allow correction without changing existing sessions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $existing = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'Batch-01',
        'status' => ExamSession::STATUS_CANCELLED,
    ]);
    $before = $existing->fresh()->getRawOriginal();
    $count = ExamSession::query()->count();
    $payload = [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'Batch-01',
        'date' => now()->addDays(30)->toDateString(),
        'start_time' => '08:00',
        'end_time' => '12:30',
    ];

    authenticatedExamPageStaff($guidance)
        ->from(route('exams.sessions.create'))
        ->post(route('exams.sessions.store'), $payload)
        ->assertRedirect(route('exams.sessions.create'))
        ->assertSessionHasErrors([
            'code' => 'This session code is already used in the selected academic year. Enter a different code.',
        ]);

    expect(ExamSession::query()->count())->toBe($count)
        ->and($existing->fresh()->getRawOriginal())->toBe($before);

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.store'), array_replace($payload, ['code' => 'Batch-02']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ExamSession::query()->where('academic_cycle_id', $cycle->id)->where('code', 'Batch-02')->firstOrFail()->status)
        ->toBe(ExamSession::STATUS_DRAFT);
});

test('session code uniqueness is enforced by the action and scoped to the academic year', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'Batch-01',
        'status' => ExamSession::STATUS_CANCELLED,
    ]);
    $action = app(CreateExamSession::class);
    $startsAt = now()->addDays(30)->startOfDay()->addHours(8)->toDateTimeString();
    $endsAt = now()->addDays(30)->startOfDay()->addHours(12)->toDateTimeString();

    expect(fn () => $action->handle($cycle, $room, 'Batch-01', $startsAt, $endsAt, $guidance, ExamSession::SEATING_MODE_SIMPLE_CAPACITY))
        ->toThrow(ValidationException::class);

    $otherCycle = $cycle->replicate();
    $otherCycle->code = 'SYN-CYCLE-OTHER';
    $otherCycle->school_year = '2030-2031';
    $otherCycle->save();
    $created = $action->handle($otherCycle, $room, 'Batch-01', $startsAt, $endsAt, $guidance, ExamSession::SEATING_MODE_SIMPLE_CAPACITY);

    expect($created->academic_cycle_id)->toBe($otherCycle->id)
        ->and($created->code)->toBe('Batch-01')
        ->and($created->status)->toBe(ExamSession::STATUS_DRAFT);
});

test('create edit and show follow legacy list then separate pages journey', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-B2-1',
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-B2-1')->firstOrFail();

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Show')
            ->where('session.code', 'EX-B2-1')
            ->where('session.status', 'draft')
            ->has('assigned_applicants')
            ->has('available_applicants')
        );

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.edit', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Edit')
            ->where('session.code', 'EX-B2-1')
        );

    authenticatedExamPageStaff($guidance)
        ->put(route('exams.sessions.update', $session), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-B2-1A',
            'date' => now()->addDays(6)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
            'seating_arrangement_id' => null,
            'expected_version' => $session->version,
        ])
        ->assertRedirect(route('exams.sessions.show', $session));

    expect($session->fresh()->code)->toBe('EX-B2-1A');

    $result = submitSyntheticApplicant('b2-show@example.test');
    authenticatedExamPageStaff($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1]);

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.assign', $session->fresh()), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    $assignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->active()
        ->firstOrFail();

    authenticatedExamPageStaff($guidance)
        ->from(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $assignment->id,
        ]))
        ->post(route('exams.sessions.remove', [$session, $assignment]), [
            'reason' => 'B2 remove path',
        ])
        ->assertRedirect(route('exams.sessions.show', $session));

    expect($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_REMOVED);

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.assign', $session->fresh()), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $activeAssignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->active()
        ->firstOrFail();
    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $activeAssignment->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Show')
            ->where('focus_assignment_id', $activeAssignment->id)
            ->where('assigned_applicants.0.id', $activeAssignment->id)
        );

    authenticatedExamPageStaff($guidance)
        ->getJson(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $assignment->id,
        ]))
        ->assertUnprocessable();
    authenticatedExamPageStaff($guidance)
        ->getJson(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $activeAssignment->id.'.5',
        ]))
        ->assertUnprocessable();

    $foreignResult = submitSyntheticApplicant('b2-foreign-focus@example.test', 'ForeignFocusApplicant');
    authenticatedExamPageStaff($registrar)
        ->post(route('admissions.accept', $foreignResult['attempt']), ['expected_version' => 1])
        ->assertRedirect();
    $otherSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-B2-FOREIGN-FOCUS',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(5),
        'effective_end_at' => now()->addDays(5)->addHours(2),
        'version' => 1,
        'lock_version' => 1,
    ]);
    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.assign', $otherSession), [
            'admission_attempt_id' => $foreignResult['attempt']->id,
        ])
        ->assertRedirect();
    $foreignAssignment = ExamAssignment::query()
        ->where('exam_session_id', $otherSession->id)
        ->active()
        ->firstOrFail();
    authenticatedExamPageStaff($guidance)
        ->getJson(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $foreignAssignment->id,
        ]))
        ->assertUnprocessable();
    $reassignedResult = submitSyntheticApplicant('b2-reassign-focus@example.test');
    authenticatedExamPageStaff($registrar)
        ->post(route('admissions.accept', $reassignedResult['attempt']), ['expected_version' => 1])
        ->assertRedirect();
    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $reassignedResult['attempt']->id,
        ])
        ->assertRedirect();
    $reassignedAssignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->where('admission_attempt_id', $reassignedResult['attempt']->id)
        ->active()
        ->firstOrFail();

    authenticatedExamPageStaff($guidance)
        ->from(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $reassignedAssignment->id,
        ]))
        ->post(route('exams.sessions.reassign', $session), [
            'assignment_ids' => [$reassignedAssignment->id],
            'target_session_id' => $otherSession->id,
            'reason' => 'B2 focused reassignment',
        ])
        ->assertRedirect(route('exams.sessions.show', $session));

    $session = $session->fresh();
    expect($session->test_kit_version_id)->toBeNull()
        ->and($session->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY);
    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->version,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($session->fresh()->test_kit_version_id)->toBeNull();
    $this->assertDatabaseMissing('exam_session_material_snapshots', [
        'exam_session_id' => $session->id,
    ]);

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Show')
            ->where('session.seating_mode', ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
            ->where('is_physical_seating', false)
            ->has('assigned_applicants', 1)
            ->where('assigned_applicants.0.seat_label', null)
            ->missing('assigned_applicants.0.set_code')
            ->missing('session.material')
            ->missing('has_multiple_sets')
        );

    $focusBeforeCancellation = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->active()
        ->firstOrFail();
    authenticatedExamPageStaff($guidance)
        ->from(route('exams.sessions.show', [
            'session' => $session,
            'focus_assignment_id' => $focusBeforeCancellation->id,
        ]))
        ->post(route('exams.sessions.cancel', $session), [
            'expected_version' => $session->fresh()->version,
            'reason' => 'B2 focused cancellation',
        ])
        ->assertRedirect(route('exams.sessions.show', $session));
});

test('publish without applicants is blocked from show gates', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    authenticatedExamPageStaff($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-B2-EMPTY',
        'starts_at' => now()->addDays(3)->toDateTimeString(),
        'effective_end_at' => now()->addDays(3)->addHours(2)->toDateTimeString(),
    ]);

    $session = ExamSession::query()->where('code', 'EX-B2-EMPTY')->firstOrFail();

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->version,
        ])
        ->assertSessionHasErrors('publish');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT);
});

test('creates and publishes a no-kit simple-capacity session without physical seating', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Create')
            ->where('enable_seating_arrangements', false)
        );

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-SIMPLE-CAP',
            'date' => now()->addDays(4)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();
    $session = ExamSession::query()->where('code', 'EX-SIMPLE-CAP')->firstOrFail();

    expect($session->test_kit_version_id)->toBeNull()
        ->and($session->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($session->seating_arrangement_id)->toBeNull();

    $result = submitSyntheticApplicant('simple-cap@example.test', 'SimpleCap');
    authenticatedExamPageStaff($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1]);

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    authenticatedExamPageStaff($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->fresh()->version,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $published = $session->fresh();
    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($published->test_kit_version_id)->toBeNull()
        ->and($published->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($published->seating_arrangement_id)->toBeNull();
    $this->assertDatabaseMissing('exam_session_material_snapshots', [
        'exam_session_id' => $published->id,
    ]);

    authenticatedExamPageStaff($guidance)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Show')
            ->where('session.seating_mode', ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
            ->where('is_physical_seating', false)
            ->has('assigned_applicants', 1)
            ->where('assigned_applicants.0.seat_label', null)
        );
});
