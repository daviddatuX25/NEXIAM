<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Scheduling\Queries\SchedulingWaitlistQuery;
use Illuminate\Foundation\Testing\TestCase;

require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
});

function authenticatedConsultationSchedulingStaff(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) ($user->authenticationState?->session_version ?? 1),
    ]);
}

function createConsultationCandidateAttempt(
    AcademicCycle $cycle,
    string $referenceCode,
    string $status = AdmissionAttempt::STATUS_ACCEPTED,
    array $attributes = [],
): AdmissionAttempt {
    $institutionId = $cycle->institution_id;
    $firstName = $attributes['first_name'] ?? 'Candidate';
    $lastName = $attributes['last_name'] ?? 'Consultation';

    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institutionId,
        'normalized_key' => strtolower($referenceCode).'-'.uniqid(),
        'first_name' => $firstName,
        'middle_name' => $attributes['middle_name'] ?? null,
        'last_name' => $lastName,
        'birthdate' => '2005-01-01',
        'sex' => 'unspecified',
        'email' => "{$referenceCode}@example.test",
    ]);

    return AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institutionId,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => $referenceCode,
        'status' => $status,
        'accepted_at' => $attributes['accepted_at'] ?? ($status === AdmissionAttempt::STATUS_ACCEPTED ? now() : null),
        'payload' => ['first_name' => $firstName, 'last_name' => $lastName],
        'version' => 1,
    ]);
}

function createConsultationTestSession(
    AcademicCycle $cycle,
    Room $room,
    string $code,
    string $status = ConsultationSession::STATUS_DRAFT,
): ConsultationSession {
    return ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => $status,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(4)->setTime(10, 0),
        'version' => 1,
    ]);
}

test('consultation waitlist counts exact individual scoring readiness without document preparation', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'waitlist');
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    $attemptReady = $fixture['attempts']['complete'];
    $attemptAwaitingSlot = createConsultationCandidateAttempt($this->cycle, 'REF-CS-SLOT-'.uniqid());
    $attemptExamScheduled = createConsultationCandidateAttempt($this->cycle, 'REF-CS-SCHED-'.uniqid());
    $examSessionPub = ExamSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'EX-CS-PUB-'.uniqid(),
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
        'version' => 1,
        'lock_version' => 1,
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $examSessionPub->id,
        'admission_attempt_id' => $attemptExamScheduled->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'assigned_at' => now(),
        'assigned_by_user_id' => $this->guidance->id,
    ]);

    $query = app(SchedulingWaitlistQuery::class);
    $readyResult = $query->forDomain('consultation', $this->institution->id, $this->cycle->id, ['readiness' => 'ready']);
    $readyItem = collect($readyResult['applicants']['data'])->firstWhere('attempt_id', $attemptReady->id);

    expect($attemptReady->current_result_document_id)->toBeNull()
        ->and($readyResult['counts']['ready'])->toBe(1)
        ->and($readyResult['counts']['not_ready'])->toBe(3)
        ->and($readyItem)->not->toBeNull()
        ->and($readyItem['stage_code'])->toBe('ready_individual_scoring')
        ->and($readyItem['can_select_for_assignment'])->toBeTrue();

    $notReadyResult = $query->forDomain('consultation', $this->institution->id, $this->cycle->id, ['readiness' => 'not_ready']);
    $notReadyRows = collect($notReadyResult['applicants']['data'])->keyBy('attempt_id');

    expect($notReadyResult['counts']['blockers']['awaiting_exam_slot'])->toBe(1)
        ->and($notReadyResult['counts']['blockers']['exam_scheduled_or_in_progress'])->toBe(1)
        ->and($notReadyResult['counts']['blockers']['awaiting_exam_scoring'])->toBe(1)
        ->and(array_key_exists('awaiting_result_prep', $notReadyResult['counts']['blockers']))->toBeFalse()
        ->and($notReadyRows[$attemptAwaitingSlot->id]['stage_code'])->toBe('awaiting_exam_slot')
        ->and($notReadyRows[$attemptExamScheduled->id]['stage_code'])->toBe('exam_scheduled_or_in_progress')
        ->and($notReadyRows[$fixture['attempts']['incomplete']->id]['stage_code'])->toBe('awaiting_exam_scoring');
});

test('consultation bulk assignment accepts individually scored candidates without prepared documents', function (): void {
    $session = createConsultationTestSession($this->cycle, $this->room, 'CS-BULK-ASSIGN-'.uniqid());
    $firstFixture = individualReleaseReadinessFixture($this, $this->guidance, 'bulk-first');
    $secondFixture = individualReleaseReadinessFixture($this, $this->guidance, 'bulk-second');
    recordIndividualReleaseExternalFinal($firstFixture, 'complete', $this->guidance);
    recordIndividualReleaseExternalFinal($secondFixture, 'complete', $this->guidance);
    $cand1 = $firstFixture['attempts']['complete'];
    $cand2 = $secondFixture['attempts']['complete'];

    $response = authenticatedConsultationSchedulingStaff($this->guidance)
        ->from(route('consultation.sessions.show', $session))
        ->post(route('consultation.sessions.bulk-assign', $session), [
            'admission_attempt_ids' => [$cand1->id, $cand2->id],
        ]);

    $response->assertRedirect(route('consultation.sessions.show', $session));
    $response->assertSessionHas('status');

    expect($cand1->current_result_document_id)->toBeNull()
        ->and($cand2->current_result_document_id)->toBeNull()
        ->and(ConsultationAssignment::query()->where('consultation_session_id', $session->id)->count())->toBe(2)
        ->and(ConsultationAssignment::query()->where('consultation_session_id', $session->id)->where('admission_attempt_id', $cand1->id)->where('status', ConsultationAssignment::STATUS_ASSIGNED)->exists())->toBeTrue()
        ->and(ConsultationAssignment::query()->where('consultation_session_id', $session->id)->where('admission_attempt_id', $cand2->id)->where('status', ConsultationAssignment::STATUS_ASSIGNED)->exists())->toBeTrue();
});

test('test_consultation_search_suggestions_returns_candidate_states: consultation search suggestions returns candidate states', function (): void {
    $uniqueCode = 'CS-SUGG-'.strtoupper(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6));
    $session = createConsultationTestSession($this->cycle, $this->room, $uniqueCode);

    $scheduledCandidate = createConsultationCandidateAttempt(
        $this->cycle,
        'REF-CS-SCHED-'.uniqid(),
        AdmissionAttempt::STATUS_ACCEPTED
    );
    ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $scheduledCandidate->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
        'assigned_at' => now(),
        'assigned_by_user_id' => $this->guidance->id,
    ]);

    // 1. Suggestions returns consultation sessions
    $sessionResponse = authenticatedConsultationSchedulingStaff($this->guidance)
        ->getJson(route('exams.search-suggestions', [
            'q' => $uniqueCode,
            'academic_cycle_id' => $this->cycle->id,
            'domain_context' => 'consultation_scheduling',
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'q',
            'academic_cycle_id',
            'domain_context',
            'items',
        ]);

    $sessionItem = collect($sessionResponse->json('items'))->firstWhere('kind', 'session');
    expect($sessionItem)->not->toBeNull()
        ->and($sessionItem['session_code'])->toBe($uniqueCode)
        ->and($sessionItem['destination']['url'])->toBe(route('consultation.sessions.show', $session));

    // 2. Suggestions returns scheduled examinees
    $examineeResponse = authenticatedConsultationSchedulingStaff($this->guidance)
        ->getJson(route('exams.search-suggestions', [
            'q' => $scheduledCandidate->reference_code,
            'academic_cycle_id' => $this->cycle->id,
            'domain_context' => 'consultation_scheduling',
        ]))
        ->assertOk();

    $examineeItem = collect($examineeResponse->json('items'))->firstWhere('kind', 'examinee');
    expect($examineeItem)->not->toBeNull()
        ->and($examineeItem['attempt_id'])->toBe($scheduledCandidate->id)
        ->and($examineeItem['pipeline_state'])->toBe('scheduled')
        ->and($examineeItem['destination']['url'])->toContain(route('consultation.sessions.show', $session));
});

test('test_dedicated_consultation_waitlist_page_renders_waitlist_and_index_returns_counts: dedicated consultation waitlist page renders waitlist and index returns counts', function (): void {
    $draftSession = createConsultationTestSession($this->cycle, $this->room, 'CS-DEF-DRAFT-'.uniqid(), ConsultationSession::STATUS_DRAFT);
    $publishedSession = createConsultationTestSession($this->cycle, $this->room, 'CS-DEF-PUB-'.uniqid(), ConsultationSession::STATUS_PUBLISHED);

    $indexResponse = authenticatedConsultationSchedulingStaff($this->guidance)
        ->get(route('consultation.sessions.index', ['academic_cycle_id' => $this->cycle->id]))
        ->assertOk();

    $indexResponse->assertInertia(fn ($page) => $page
        ->component('Consultation/ConsultationSessions')
        ->where('filters.status', 'active')
        ->where('sessions', fn ($sessions): bool => collect($sessions)->contains('id', $draftSession->id)
            && ! collect($sessions)->contains('id', $publishedSession->id))
        ->has('waitlist_counts')
        ->where('waitlist_counts.ready', fn ($val) => is_int($val))
        ->where('waitlist_counts.not_ready', fn ($val) => is_int($val))
        ->missing('waitlist')
        ->missing('tab')
    );

    authenticatedConsultationSchedulingStaff($this->guidance)
        ->get(route('consultation.sessions.index', ['academic_cycle_id' => $this->cycle->id, 'status' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sessions', fn ($sessions): bool => collect($sessions)->contains('id', $draftSession->id)
                && collect($sessions)->contains('id', $publishedSession->id))
        );

    $waitlistResponse = authenticatedConsultationSchedulingStaff($this->guidance)
        ->get(route('consultation.sessions.waitlist', ['academic_cycle_id' => $this->cycle->id]))
        ->assertOk();

    $waitlistResponse->assertInertia(fn ($page) => $page
        ->component('Consultation/Waitlist')
        ->has('waitlist')
        ->has('programs')
        ->has('sessions')
        ->where('active_cycle_id', $this->cycle->id)
    );
});
