<?php

namespace Tests\Feature\Examination;

use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Str;

function staffSession(User $user): array
{
    $state = $user->staffProfile?->authenticationState;

    return [
        'auth.surface' => 'staff',
        'auth.session_version' => (int) ($state?->session_version ?? 1),
    ];
}
test('program head and dean cannot access examination monitoring', function (): void {
    seedE2Foundation();

    foreach (['program.head.bsit@nexiam.test', 'dean.cas@nexiam.test'] as $email) {
        $actor = User::query()->where('email', $email)->firstOrFail();

        $this->actingAs($actor)
            ->withSession(staffSession($actor))
            ->get('/exams/monitoring')
            ->assertForbidden();
    }
});

test('actor without examination management capability is forbidden even with historical duty', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-MON-HISTORICAL-DUTY',
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->addMinutes(30),
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);
    ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $actor->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'mon-access-historical-duty',
        'assigned_at' => now(),
    ]);

    $this->actingAs($actor)
        ->withSession(staffSession($actor))
        ->get('/exams/monitoring')
        ->assertForbidden();

    expect(ProctorDuty::query()->where('exam_session_id', $session->id)->where('user_id', $actor->id)->exists())
        ->toBeTrue();
});

test('unassigned test administrator can access examination monitoring', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-MON-ACCESS',
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->addMinutes(30),
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);

    $this->actingAs($guidance)
        ->withSession(staffSession($guidance))
        ->get('/exams/monitoring')
        ->assertOk();
});

test('monitoring endpoints hide sessions from another institution', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->create([
        'code' => 'FOREIGN-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Test Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'FOREIGN-CYCLE-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Test Cycle',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => now()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $session = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'code' => 'EX-MON-FOREIGN-'.strtoupper(Str::random(4)),
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);

    $this->actingAs($guidance)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertNotFound();
    $this->actingAs($guidance)
        ->getJson(route('exams.sessions.monitoring.distribution', $session))
        ->assertNotFound();
    $this->actingAs($guidance)
        ->get(route('exams.sessions.monitoring.lookup-applicant', $session).'?reference_code=FOREIGN-APPLICANT')
        ->assertNotFound();
});
