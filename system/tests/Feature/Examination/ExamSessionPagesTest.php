<?php

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

test('exam sessions index uses management list without inline create and links to create page', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('exams.sessions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Index')
            ->has('sessions')
            ->has('statuses')
            ->has('cycles')
            ->missing('create_form')
        );

    $this->actingAs($guidance)
        ->get(route('exams.sessions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Create')
            ->has('rooms')
            ->has('cycles')
        );
});

test('create edit and show follow legacy list then separate pages journey', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
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

    $this->actingAs($guidance)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Show')
            ->where('session.code', 'EX-B2-1')
            ->where('session.status', 'draft')
            ->has('assigned_applicants')
            ->has('available_applicants')
        );

    $this->actingAs($guidance)
        ->get(route('exams.sessions.edit', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Edit')
            ->where('session.code', 'EX-B2-1')
        );

    $this->actingAs($guidance)
        ->put(route('exams.sessions.update', $session), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-B2-1A',
            'date' => now()->addDays(6)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'expected_version' => $session->version,
        ])
        ->assertRedirect(route('exams.sessions.show', $session));

    expect($session->fresh()->code)->toBe('EX-B2-1A');

    $result = submitSyntheticApplicant('b2-show@example.test');
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1]);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session->fresh()), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    $assignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->active()
        ->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.remove', [$session, $assignment]), [
            'reason' => 'B2 remove path',
        ])
        ->assertRedirect();

    expect($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_REMOVED);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session->fresh()), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session->fresh()), [
            'expected_version' => $session->fresh()->version,
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED);
});

test('publish without applicants is blocked from show gates', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-B2-EMPTY',
        'starts_at' => now()->addDays(3)->toDateTimeString(),
        'effective_end_at' => now()->addDays(3)->addHours(2)->toDateTimeString(),
    ]);

    $session = ExamSession::query()->where('code', 'EX-B2-EMPTY')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->version,
        ])
        ->assertSessionHasErrors('publish');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT);
});
