<?php

use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;

function eligibilityReadyAttempt(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function eligibilityFinishFlow(array $result, string $decisionType = 'approve'): void
{
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-ELIG-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $result['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);

    $document = app(PrepareResultDocument::class)->handle($result['attempt'], $guidance);

    app(ReleaseResult::class)->handle(
        $result['attempt'],
        $document,
        $guidance,
        'eligibility-release-'.$result['attempt']->id,
        ReleaseResult::MODE_F2F,
    );

    if ($decisionType !== null) {
        app(RecordConsultationDecision::class)->handle(
            $result['attempt'],
            $guidance,
            $decisionType,
            null,
            'eligibility-decision-'.$result['attempt']->id,
        );
    }
}

test('eligibility card shows all facts and eligible after release + confirmed decision', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-ok@example.test', 'Ok');
    eligibilityFinishFlow($result, 'approve');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registrar/EnrollmentEligibility')
            ->where('page_state', 'card')
            ->where('card.eligible', true)
            ->where('card.missing', [])
            ->where('card.facts.accepted', true)
            ->where('card.facts.grading_finalized', true)
            ->where('card.facts.result_released', true)
            ->where('card.facts.advised', true));
});

test('eligibility card names the missing requirement when not released or advised', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-missing@example.test', 'Missing');
    // accepted only — nothing else

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registrar/EnrollmentEligibility')
            ->where('card.eligible', false)
            ->where('card.facts.accepted', true)
            ->where('card.facts.grading_finalized', false)
            ->where('card.facts.result_released', false)
            ->where('card.facts.advised', false)
            ->where('card.missing', ['grading_finalized', 'result_released', 'advised']));
});

test('deferred decision does not mark advised', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-deferred@example.test', 'Deferred');
    eligibilityFinishFlow($result, 'deferred');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('card.facts.advised', false)
            ->where('card.eligible', false));
});

test('every registrar eligibility lookup is audited', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-audit@example.test', 'Audit');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('registrar.enrollment-eligibility.show', $result['attempt']))
        ->assertOk();

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'registrar.eligibility_lookup',
        'actor' => (string) $registrar->id,
    ]);
});

test('staff without the registrar capability cannot use the eligibility surface', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('registrar.enrollment-eligibility.index'))
        ->assertForbidden();
});

test('registrar search returns matching applicants by reference and name', function (): void {
    seedE2Foundation();
    $result = eligibilityReadyAttempt('elig-search@example.test', 'Searchable');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('registrar.enrollment-eligibility.index', ['q' => $result['attempt']->reference_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registrar/EnrollmentEligibility')
            ->where('page_state', 'search')
            ->where('results', fn ($rows) => $rows->contains(fn ($row) => $row['id'] === $result['attempt']->id)));

    $this->actingAs($registrar)
        ->get(route('registrar.enrollment-eligibility.index', ['q' => 'Searchable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('results', fn ($rows) => $rows->contains(fn ($row) => $row['id'] === $result['attempt']->id)));
});
