<?php

use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\StartExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

function authenticatedSessionForSearch(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $user->authenticationState->session_version,
    ]);
}

test('search suggestions validate query and domain context', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $base = [
        'academic_cycle_id' => $cycle->id,
        'domain_context' => 'monitoring',
    ];

    authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', $base))
        ->assertUnprocessable();

    authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => 'A']))
        ->assertUnprocessable();

    authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => 'EX', 'domain_context' => 'invalid']))
        ->assertUnprocessable();

});

test('guidance counselor receives monitoring session and examinee suggestions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $session = createSearchMonitoringSession($guidance, 'EX-MON-SESSION');
    $base = [
        'academic_cycle_id' => $cycle->id,
        'domain_context' => 'monitoring',
    ];

    $sessionResponse = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => 'EX-MON-SESSION']))
        ->assertOk()
        ->assertJsonStructure([
            'q',
            'academic_cycle_id',
            'domain_context',
            'matches_truncated',
            'items' => [['kind', 'destination' => ['url']]],
        ]);

    $sessionItem = collect($sessionResponse->json('items'))->firstWhere('kind', 'session');
    expect($sessionItem)->not->toBeNull()
        ->and($sessionItem['session_code'])->toBe('EX-MON-SESSION')
        ->and($sessionItem['destination']['url'])->toBe(route('exams.sessions.monitoring', $session));

    $assignment = $session->assignments()->firstOrFail();
    $attempt = AdmissionAttempt::query()->findOrFail($assignment->admission_attempt_id);

    foreach (['Test EX-MON-SESSION', $attempt->reference_code] as $query) {
        $response = authenticatedSessionForSearch($guidance)
            ->getJson(route('exams.search-suggestions', [...$base, 'q' => $query]))
            ->assertOk();

        $examineeItem = collect($response->json('items'))->firstWhere('kind', 'examinee');
        expect($examineeItem)->not->toBeNull()
            ->and($examineeItem['assignment_id'])->toBe($assignment->id)
            ->and($examineeItem['destination']['url'])->toContain('focus_assignment_id='.$assignment->id);
    }
});

test('guidance counselor receives scheduling session and examinee suggestions including draft sessions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $session = createSearchMonitoringSession($guidance, 'EX-SCHED-SESSION');
    $session->update(['status' => ExamSession::STATUS_DRAFT]);
    $base = [
        'academic_cycle_id' => $cycle->id,
        'domain_context' => 'scheduling',
    ];

    $sessionResponse = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => $session->code]))
        ->assertOk();

    $sessionItem = collect($sessionResponse->json('items'))->firstWhere('kind', 'session');
    expect($sessionItem)->not->toBeNull()
        ->and($sessionItem['session_code'])->toBe($session->code)
        ->and($sessionItem['destination']['route_name'])->toBe('exams.sessions.show')
        ->and($sessionItem['destination']['url'])->toBe(route('exams.sessions.show', $session))
        ->and($sessionItem['destination'])->toHaveKey('focus_kind', null)
        ->and($sessionItem['destination'])->toHaveKey('focus_id', null);
    $assignment = $session->assignments()->firstOrFail();
    $attempt = AdmissionAttempt::query()->findOrFail($assignment->admission_attempt_id);
    $response = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => $attempt->reference_code]))
        ->assertOk();

    $examineeItem = collect($response->json('items'))->firstWhere('kind', 'examinee');
    expect($examineeItem)->not->toBeNull()
        ->and($examineeItem['assignment_id'])->toBe($assignment->id)
        ->and($examineeItem['destination']['route_name'])->toBe('exams.sessions.show')
        ->and($examineeItem['destination']['focus_kind'])->toBe('assignment')
        ->and($examineeItem['destination']['focus_id'])->toBe($assignment->id);

    $identity = ApplicantIdentity::query()->findOrFail($attempt->applicant_identity_id);
    $identity->update([
        'first_name' => 'Celia',
        'middle_name' => 'Marisol',
        'last_name' => 'Dela Cruz',
    ]);
    foreach (['Celia', 'Celia Marisol', 'Celia Dela'] as $query) {
        $nameResponse = authenticatedSessionForSearch($guidance)
            ->getJson(route('exams.search-suggestions', [...$base, 'q' => $query]))
            ->assertOk();
        $celiaSuggestion = collect($nameResponse->json('items'))->firstWhere('assignment_id', $assignment->id);
        expect($celiaSuggestion)->not->toBeNull()
            ->and($celiaSuggestion['name'])->toBe('Celia Marisol Dela Cruz');
    }

    $activeStatusResponse = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => $session->code, 'status' => 'active']))
        ->assertOk();
    expect(collect($activeStatusResponse->json('items'))->pluck('session_code'))
        ->toContain($session->code);

    $monitoringResponse = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [
            'academic_cycle_id' => $cycle->id,
            'domain_context' => 'monitoring',
            'q' => $session->code,
        ]))
        ->assertOk();

    $publishedOnly = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [...$base, 'q' => $session->code, 'status' => 'published']))
        ->assertOk();
    expect($publishedOnly->json('items'))->toBe([]);
});

test('explicit inactive scheduling cycle works without an active cycle code', function (): void {
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
    $session = createSearchMonitoringSession($guidance, 'EX-HISTORICAL-SESSION');
    $session->update([
        'academic_cycle_id' => $inactiveCycle->id,
        'status' => ExamSession::STATUS_DRAFT,
    ]);
    StaffProfile::query()->where('user_id', $guidance->id)->update(['active_cycle_code' => null]);

    $response = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [
            'q' => $session->code,
            'academic_cycle_id' => $inactiveCycle->id,
            'domain_context' => 'scheduling',
        ]))
        ->assertOk();

    expect($response->json('academic_cycle_id'))->toBe($inactiveCycle->id)
        ->and(collect($response->json('items'))->firstWhere('kind', 'session')['session_code'])->toBe($session->code);
});

test('scheduling search denies unauthorized users', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    authenticatedSessionForSearch($registrar)
        ->getJson(route('exams.search-suggestions', [
            'q' => 'EX-SCHED',
            'academic_cycle_id' => $cycle->id,
            'domain_context' => 'scheduling',
        ]))
        ->assertForbidden();
});

test('scheduling search disallows tab parameter', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [
            'q' => 'EX-SCHED',
            'academic_cycle_id' => $cycle->id,
            'domain_context' => 'scheduling',
            'tab' => 'active',
        ]))
        ->assertUnprocessable();
});

test('search suggestions deny unauthenticated and unauthorized actors', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $url = route('exams.search-suggestions', [
        'q' => 'EX-MON',
        'academic_cycle_id' => $cycle->id,
        'domain_context' => 'monitoring',
    ]);

    $this->get($url)->assertRedirect(route('login'));

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    authenticatedSessionForSearch($registrar)->getJson($url)->assertForbidden();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidance->syncRoles([]);
    $guidance->revokePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL);

    authenticatedSessionForSearch($guidance->fresh())
        ->getJson(route('exams.search-suggestions', [
            'q' => 'EX-MON',
            'academic_cycle_id' => $cycle->id,
            'domain_context' => 'grading',
        ]))
        ->assertForbidden();
});

test('scoring queue suggestions retain repair sessions and explicit history', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $repair = createSearchMonitoringSession($guidance, 'QUEUE-REPAIR');
    $repair->update(['status' => ExamSession::STATUS_COMPLETED]);
    $finalized = createSearchMonitoringSession($guidance, 'QUEUE-FINALIZED');
    $finalized->update(['status' => ExamSession::STATUS_COMPLETED]);
    GradingSession::query()->create([
        'exam_session_id' => $finalized->id,
        'status' => 'finalized',
        'opened_at' => now(),
        'opened_by_user_id' => $guidance->id,
        'version' => 1,
    ]);
    $base = ['academic_cycle_id' => $cycle->id, 'domain_context' => 'grading', 'q' => 'QUEUE'];

    foreach (['active', 'repair_required', 'all', 'finalized'] as $status) {
        $response = authenticatedSessionForSearch($guidance)
            ->getJson(route('exams.search-suggestions', [...$base, 'status' => $status]))
            ->assertOk();
        $codes = collect($response->json('items'))->where('kind', 'session')->pluck('session_code')->all();
        expect(in_array($repair->code, $codes, true))->toBe($status !== 'finalized')
            ->and(in_array($finalized->code, $codes, true))->toBe(in_array($status, ['all', 'finalized'], true));
    }

    $detail = authenticatedSessionForSearch($guidance)
        ->getJson(route('exams.search-suggestions', [
            ...$base, 'exam_session_id' => $finalized->id, 'lookup_scope' => 'current_session',
        ]))
        ->assertOk();
    expect(collect($detail->json('items'))->where('kind', 'session')->pluck('session_code')->all())
        ->toBe([$finalized->code]);
});

test('scheduling suggestions follow draft queue default and explicit historical scope', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $publishedSession = createSearchMonitoringSession($guidance, 'QUEUE-PUB');
    $publishedBase = ['academic_cycle_id' => $cycle->id, 'domain_context' => 'scheduling', 'q' => $publishedSession->code];

    foreach (['active', 'all', 'published'] as $status) {
        $response = authenticatedSessionForSearch($guidance)
            ->getJson(route('exams.search-suggestions', [...$publishedBase, 'status' => $status]))
            ->assertOk();
        $items = collect($response->json('items'));
        expect($items->contains('kind', 'session'))->toBe($status !== 'active')
            ->and($items->contains('kind', 'examinee'))->toBe($status !== 'active');
    }

    $session = createSearchMonitoringSession($guidance, 'QUEUE-START');
    app(StartExamSession::class)->handle($session, $guidance, $session->version);
    $base = ['academic_cycle_id' => $cycle->id, 'domain_context' => 'scheduling', 'q' => $session->code];

    foreach (['active', 'all', 'in_progress'] as $status) {
        $response = authenticatedSessionForSearch($guidance)
            ->getJson(route('exams.search-suggestions', [...$base, 'status' => $status]))
            ->assertOk();
        $items = collect($response->json('items'));
        expect($items->contains('kind', 'session'))->toBe($status !== 'active')
            ->and($items->contains('kind', 'examinee'))->toBe($status !== 'active');
    }
});

function createSearchMonitoringSession(User $guidance, string $code, string $roomCode = 'RM-101'): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', $roomCode)->firstOrFail();

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->addMinutes(30),
        'version' => 1,
        'lock_version' => 1,
    ]);

    $institutionId = $cycle->institution_id;
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institutionId,
        'normalized_key' => strtolower($code),
        'first_name' => 'Test',
        'last_name' => $code,
        'birthdate' => '2008-01-01',
        'sex' => 'unspecified',
        'email' => "{$code}@example.test",
    ]);
    $attempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institutionId,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => "REF-{$code}",
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'accepted_at' => now(),
        'payload' => ['given_name' => 'Test', 'family_name' => $code],
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'assigned_at' => now(),
        'assigned_by_user_id' => $guidance->id,
    ]);

    return $session->fresh();
}
