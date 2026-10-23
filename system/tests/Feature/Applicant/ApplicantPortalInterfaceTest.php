<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentSheetHandoffReceipt;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\Release\Actions\ReleaseResult;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('sensitive');
});

/**
 * Applicant status-spine hub + self-service records
 * (2026-08-15-applicant-portal-interface, design §3/§5/§8).
 */
function applicantPortalFixture(
    string $email,
    string $releaseMode = ReleaseResult::MODE_ONLINE,
    bool $isAdvised = false,
): array {
    seedE2Foundation();
    $result = submitSyntheticApplicant($email, 'Portal'.substr(md5($email), 0, 6));
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );
    $attempt = $result['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->whereKey($attempt->academic_cycle_id)->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-PORTAL-'.substr(md5($email), 0, 10),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);

    if ($isAdvised) {
        ConsultationDecision::query()->create([
            'admission_attempt_id' => $attempt->id,
            'counselor_user_id' => $guidance->id,
            'decision_type' => 'approve',
            'idempotency_key' => 'portal-decision-'.md5($email),
        ]);
    }

    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'applicant-portal-test-v1',
        'checksum' => hash('sha256', 'applicant-portal-'.$email),
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
    ]);
    $bytes = app(PrintRenderer::class)->render('<h1>Official portal fixture result</h1>');
    $path = 'results/'.$attempt->id.'/portal-fixture.pdf';
    app(SensitiveAssetStore::class)->store($bytes, $path);
    $document->update(['artifact_manifest' => ['pdf' => [
        'status' => 'ready',
        'path' => $path,
        'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
    ]]]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();

    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'mode' => $releaseMode,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'portal-release-'.md5($email),
    ]);

    return [$attempt, $applicant, $guidance];
}

test('hub renders empty state when the account has no attempt', function (): void {
    seedE2Foundation();

    $user = User::query()->create([
        'name' => 'No Attempt',
        'email' => 'hub-empty@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('page_state', 'empty'));
});

test('hub marks needs_correction on the submitted stage with fix action', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant('hub-correct@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $result['attempt']->update([
        'status' => AdmissionAttempt::STATUS_NEEDS_CORRECTION,
        'correction_notes' => 'Birthdate looks wrong.',
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('page_state', 'ready')
            ->where('notices.needs_correction', true)
            ->where('notices.correction_notes', 'Birthdate looks wrong.')
            ->where('stages.0.id', 'submitted')
            ->where('stages.0.status', 'action')
            ->where('stages.0.actions.0.key', 'fix_application')
            ->where('stages.1.status', 'current'));
});

test('hub renders released score artifact for MODE_ONLINE', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('hub-online@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) use ($attempt) {
            $page
                ->component('Applicant/Portal')
                ->where('page_state', 'ready')
                ->where('identity.applicant_no', $attempt->reference_code);

            $stages = $page->toArray()['props']['stages'] ?? [];
            $results = collect($stages)->firstWhere('id', 'results');

            expect($results['status'])->toBe('done')
                ->and($results['release']['mode'])->toBe('online')
                ->and($results['actions'][0]['key'])->toBe('view_results');
            expect($results)->not->toHaveKey('profile');
            expect($results)->not->toHaveKey('score_profile');
            expect(json_encode($results, JSON_THROW_ON_ERROR))
                ->not->toContain('"series"')
                ->not->toContain('"scores"');

            return $page;
        });
});

test('hub never serializes scores for MODE_F2F and shows claim state', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('hub-f2f@example.test', ReleaseResult::MODE_F2F);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page
                ->component('Applicant/Portal')
                ->where('page_state', 'ready')
                ->where('notices.results_blocked', true);

            $stages = $page->toArray()['props']['stages'] ?? [];
            $results = collect($stages)->firstWhere('id', 'results');

            expect($results['status'])->toBe('action')
                ->and($results['release']['mode'])->toBe('f2f')
                ->and($results['actions'][0]['key'])->toBe('book_consultation');
            expect($results)->not->toHaveKey('profile');
            expect($results)->not->toHaveKey('score_profile');
            expect(json_encode($results, JSON_THROW_ON_ERROR))
                ->not->toContain('"series"')
                ->not->toContain('"scores"');

            return $page;
        });
});

test('F2F results unlock only after a completed Day of Release encounter', function (): void {
    [$attempt, $applicant, $guidance] = applicantPortalFixture(
        'hub-f2f-completed@example.test',
        ReleaseResult::MODE_F2F,
    );

    ConsultationEncounter::query()->create([
        'institution_id' => $attempt->institution_id,
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $guidance->id,
        'medium' => ConsultationEncounter::MEDIUM_F2F,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
        'admitted_at' => now()->subMinutes(15),
        'closed_at' => now(),
        'idempotency_key' => 'portal-f2f-encounter-'.md5((string) $attempt->id),
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $guidance->id,
        'decision_type' => 'approve',
        'idempotency_key' => 'portal-f2f-decision-'.md5((string) $attempt->id),
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page
                ->component('Applicant/Portal')
                ->where('notices.results_blocked', false);

            $results = collect($page->toArray()['props']['stages'] ?? [])
                ->firstWhere('id', 'results');
            expect($results['status'])->toBe('done')
                ->and($results['actions'][0]['key'])->toBe('view_results');
            expect($results)->not->toHaveKey('profile');
            expect($results)->not->toHaveKey('score_profile');
            expect(json_encode($results, JSON_THROW_ON_ERROR))
                ->not->toContain('"series"')
                ->not->toContain('"scores"');

            return $page;
        });
});

test('hub renders terminal card for dismissed attempts', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('hub-dismissed@example.test');

    $attempt->update([
        'status' => AdmissionAttempt::STATUS_DISMISSED,
        'dismissal_reason' => 'Missing documents.',
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('notices.dismissed', true)
            ->where('notices.dismissal_reason', 'Missing documents.')
            ->where('stages', []));
});

test('hub shows guidance default as auto stage with optional ack', function (): void {
    [$attempt, $applicant, $guidance] = applicantPortalFixture('hub-default@example.test', ReleaseResult::MODE_ONLINE, true);

    app(SetGuidanceProgramPool::class)->handle(
        $attempt,
        $guidance,
        DEFAULT_PROGRAM,
        [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
            ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
        ],
        'Test default.',
        'hub-pool-default',
    );

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/Portal');

            $stages = $page->toArray()['props']['stages'] ?? [];
            $program = collect($stages)->firstWhere('id', 'program_choice');

            expect($program['status'])->toBe('auto')
                ->and($program['summary'])->toContain('projection active')
                ->and($program['actions'][0]['key'])->toBe('ack_default');

            return $page;
        });
});

test('existing applicant lifecycle facts keep their source-state order', function (): void {
    [, $applicant] = applicantPortalFixture('hub-order@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/Portal');

            $ids = collect($page->toArray()['props']['stages'] ?? [])->pluck('id')->all();

            expect($ids)->toBe([
                'submitted',
                'review',
                'exam',
                'results',
                'consultation',
                'program_choice',
                'admission_clearance',
            ]);

            return $page;
        });
});
test('applicant pipeline groups the seven operational steps into four phases', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('hub-pipeline@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) use ($attempt) {
            $phases = collect($page->toArray()['props']['pipeline'] ?? []);

            expect($phases->pluck('label')->all())->toBe([
                'Application',
                'Examination',
                'Release of Results',
                'Admission Clearance',
            ]);

            $steps = $phases->pluck('steps')->flatten(1);
            expect($steps->pluck('id')->all())->toBe([
                'application',
                'exam_scheduling',
                'exam_monitoring',
                'scoring',
                'release_scheduling',
                'release_encounter',
                'admission_clearance',
            ]);

            expect($steps->pluck('href')->all())->toBe([
                route('applicant.application', ['attempt' => $attempt->id]),
                route('applicant.schedule'),
                route('applicant.exam-day'),
                route('applicant.exam-scoring'),
                route('applicant.consultation'),
                route('applicant.results.show'),
                route('applicant.admission-clearance'),
            ]);

            expect($steps->every(fn (array $step): bool => ! array_key_exists('context', $step)))->toBeTrue();
            expect($page->toArray()['props']['admission_clearance'])
                ->toHaveKeys(['status', 'is_confirmed', 'historical_sheet_issue', 'historical_sheet_issued_at', 'historical_sheet_belongs_to_latest_clearance'])
                ->not->toHaveKey('sheet_issued');

            return $page;
        });
});
test('pipeline keeps scoring, release scheduling, and completed release day independently sourced', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('hub-independent-steps@example.test', ReleaseResult::MODE_ONLINE, true);
    $examSessionId = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->value('exam_session_id');
    GradingSession::query()
        ->where('exam_session_id', $examSessionId)
        ->update(['status' => GradingSession::STATUS_OPEN, 'finalized_at' => null]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $steps = collect($page->toArray()['props']['pipeline'])
                ->pluck('steps')
                ->flatten(1)
                ->keyBy('id');
            $results = collect($page->toArray()['props']['stages'])->firstWhere('id', 'results');
            $releaseSchedule = $steps->get('release_scheduling');
            $releaseDay = $steps->get('release_encounter');

            expect($results['status'])->toBe('done')
                ->and($steps->get('scoring')['status'])->toBe('upcoming')
                ->and($releaseSchedule['status'])->toBe('upcoming')
                ->and($releaseSchedule['at'])->toBeNull()
                ->and($releaseDay['status'])->toBe('done')
                ->and($releaseDay['at'])->not->toBeNull()
                ->and($releaseSchedule['summary'])->not->toBe($releaseDay['summary']);

            return $page;
        });
});
test('Home keeps a revoked latest clearance separate from its historical handoff', function (): void {
    [$attempt, $applicant, $registrar] = applicantPortalFixture('hub-revoked-handoff@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $confirmed = AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'CLR-HUB-HISTORICAL',
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['private' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now()->subDays(3),
        'idempotency_key' => 'hub-clearance-confirmed-'.$attempt->id,
    ]);
    $issuedAt = now()->subDays(2);
    EnrollmentSheetHandoffReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'admission_clearance_receipt_id' => $confirmed->id,
        'program_id' => $program->id,
        'receipt_number' => 'ESH-HUB-HISTORICAL',
        'issued_by_user_id' => $registrar->id,
        'issued_at' => $issuedAt,
        'paper_reference' => 'private-paper-reference',
        'idempotency_key' => 'hub-sheet-'.$attempt->id,
    ]);
    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'CLR-HUB-REVOKED',
        'status' => AdmissionClearanceReceipt::STATUS_REVOKED,
        'evidence_vector' => ['private' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now()->subDay(),
        'predecessor_receipt_id' => $confirmed->id,
        'revoked_by_user_id' => $registrar->id,
        'revoked_at' => now(),
        'revocation_reason' => 'private internal reason',
        'idempotency_key' => 'hub-clearance-revoked-'.$attempt->id,
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) use ($issuedAt) {
            $props = $page->toArray()['props'];
            $clearance = $props['admission_clearance'];
            $steps = collect($props['pipeline'])->pluck('steps')->flatten(1)->keyBy('id');

            expect($clearance['status'])->toBe('revoked')
                ->and($clearance['is_confirmed'])->toBeFalse()
                ->and($clearance['historical_sheet_issue']['receipt_number'])->toBe('ESH-HUB-HISTORICAL')
                ->and($clearance['historical_sheet_belongs_to_latest_clearance'])->toBeFalse()
                ->and($clearance['historical_sheet_issued_at'])->toBe($issuedAt->toIso8601String())
                ->and($steps->get('admission_clearance')['status'])->toBe('action')
                ->and($clearance)->not->toHaveKey('sheet_issued')
                ->and($clearance)->not->toHaveKey('revocation_reason');

            return $page;
        });
});

test('my application is read-only and own-scope with document checklist', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('myapp-owner@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.application', ['attempt' => $attempt->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/MyApplication')
            ->where('application.reference_code', $attempt->reference_code)
            ->where(
                'application.personal.name',
                trim((string) $attempt->identity->first_name.' '.(string) $attempt->identity->last_name),
            )
            ->where('application.preferences.0', 'BSIT')
            ->where('application.grades', [])
            ->has('document_checklist'));

    // Cross-applicant access is denied.
    $other = submitSyntheticApplicant('myapp-other@example.test', 'PortalOther');
    $otherUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $other['access_token'],
        '2001-01-01',
    );

    $this->actingAs($otherUser)
        ->get(route('applicant.application', ['attempt' => $attempt->id]))
        ->assertForbidden();
});

test('applicant shell share exposes server-authored navigation', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('nav@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/Portal');

            $nav = $page->toArray()['props']['applicant_navigation'] ?? [];

            expect(collect($nav)->pluck('key')->all())->toBe([
                'portal',
                'application',
                'schedule',
                'exam_day',
                'exam_scoring',
                'release_schedule',
                'release_day',
                'results',
                'program_choice',
                'clearance',
                'admission_support',
                'access_history',
                'notifications',
                'qr',
            ]);

            return $page;
        });
});
test('Home preserves scheduling completion and advances Exam Day when monitoring starts', function (): void {
    [$attempt, $applicant] = applicantPortalFixture('home-exam-progress@example.test');
    $assignment = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->firstOrFail();
    $session = ExamSession::query()->findOrFail($assignment->exam_session_id);
    $session->update([
        'status' => ExamSession::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
        'started_at' => null,
        'closed_at' => null,
    ]);

    $publishedSteps = [];
    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$publishedSteps): void {
            $publishedSteps = collect($page->toArray()['props']['pipeline'])
                ->pluck('steps')
                ->flatten(1)
                ->keyBy('id')
                ->all();
        });

    expect($publishedSteps['exam_scheduling']['status'])->toBe('done')
        ->and($publishedSteps['exam_monitoring']['status'])->toBe('upcoming');

    $session->update([
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'started_at' => now(),
    ]);

    $startedSteps = [];
    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$startedSteps): void {
            $startedSteps = collect($page->toArray()['props']['pipeline'])
                ->pluck('steps')
                ->flatten(1)
                ->keyBy('id')
                ->all();
        });

    expect($startedSteps['exam_scheduling']['status'])->toBe('done')
        ->and($startedSteps['exam_monitoring']['status'])->toBe('current');
});
