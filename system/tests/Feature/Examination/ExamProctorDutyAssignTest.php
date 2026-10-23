<?php

use App\Authorization\CapabilityCatalog;
use App\Examination\Queries\ExamBookletDistributionQuery;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

test('historical duty remains stored but does not authorize exam operations', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    Role::findByName('guidance_clerk')->revokePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-PR-HISTORICAL',
        'starts_at' => now()->addDay(),
        'effective_end_at' => now()->addDay()->addHour(),
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);
    $historicalDuty = ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $clerk->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'historical-duty-inert',
        'assigned_at' => now(),
    ]);

    $this->actingAs($clerk)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertForbidden();

    $this->actingAs($clerk)
        ->get(route('exams.monitoring'))
        ->assertForbidden();
    expect(fn () => app(ExamBookletDistributionQuery::class)->handle($session, $clerk))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($clerk)->post("/exams/sessions/{$session->id}/proctor-duty", [])->assertNotFound();
    $this->actingAs($clerk)->post("/proctor-duty/{$historicalDuty->id}/revoke", [])->assertNotFound();
    expect($historicalDuty->fresh()->status)->toBe(ProctorDuty::STATUS_ACTIVE);
});
