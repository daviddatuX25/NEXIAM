<?php

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Printing\PrintJob;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\ConfirmPhysicalPrintAction;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use Database\Seeders\FoundationalScoringSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

/**
 * @return array{attempt: AdmissionAttempt, document: ResultDocument, grading_session: GradingSession}
 */
function resultSheetPrintAuthorizationFixture(TestCase $test): array
{
    seedE2Foundation();
    ResultSheetTemplate::query()->update(['is_active' => false]);
    ResultSheetTemplate::query()->create([
        'name' => 'Seed HTML',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{institution_name}}</h1><p>{{applicant_name}}</p>',
        'is_active' => true,
    ]);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $scoringFixture = individualReleaseReadinessFixture($test, $guidance, 'print-auth-'.uniqid());
    recordIndividualReleaseExternalFinal($scoringFixture, 'complete', $guidance);
    $attempt = $scoringFixture['attempts']['complete'];
    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    return ['attempt' => $attempt, 'document' => $document, 'grading_session' => $scoringFixture['grading_session']];
}

test('result prepare capability authorizes requesting prints and managing physical print receipts', function (): void {
    Queue::fake();
    $fixture = resultSheetPrintAuthorizationFixture($this);
    $clerk = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk->syncRoles([]);
    $clerk->revokePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
    $clerk->givePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE);
    $clerk = $clerk->fresh();

    $job = app(RequestResultSheetPrint::class)->handle($clerk, [$fixture['document']->id]);
    $markedCount = app(MarkResultSheetsPrinted::class)->handle($clerk, [$fixture['document']->id], true);
    $receipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint($fixture['document'], $clerk, 'test-key');

    expect($job->exists)->toBeTrue()
        ->and($markedCount)->toBe(1)
        ->and($receipt->exists)->toBeTrue()
        ->and($receipt->confirmed_by_user_id)->toBe($clerk->id)
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE))->toBeFalse();
});

test('result sheet print actions reject an actor without either results capability', function (): void {
    $fixture = resultSheetPrintAuthorizationFixture($this);
    $unprivileged = User::factory()->create();

    expect(fn () => app(RequestResultSheetPrint::class)->handle($unprivileged, [$fixture['document']->id]))
        ->toThrow(RuntimeException::class, 'The result release or result prepare capability is required to print result sheets.');

    expect(fn () => app(MarkResultSheetsPrinted::class)->handle($unprivileged, [$fixture['document']->id], true))
        ->toThrow(RuntimeException::class, 'The result release or result prepare capability is required to manage printed status.');

    expect(fn () => app(ConfirmPhysicalPrintAction::class)->confirmPrint($fixture['document'], $unprivileged, 'denied-confirmation'))
        ->toThrow(RuntimeException::class, 'The result release or result prepare capability is required to manage physical print receipts.');
});

test('guidance clerk and support staff require explicit TA delegation to access result printing routes', function (): void {
    $this->withoutVite();
    $fixture = resultSheetPrintAuthorizationFixture($this);
    $gradingSession = $fixture['grading_session'];
    $delegator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    foreach (['guidance.clerk@nexiam.test', 'guidance.support@nexiam.test'] as $email) {
        $actor = User::query()->where('email', $email)->firstOrFail();
        $staffSession = [
            'auth.surface' => 'staff',
            'auth.session_version' => (int) ($actor->staffProfile?->authenticationState?->session_version ?? 1),
        ];

        $this->actingAs($actor)->withSession($staffSession)->get(route('release.index'))->assertForbidden();
        $this->actingAs($actor)->withSession($staffSession)
            ->get(route('release.print.batches.index', $gradingSession))->assertForbidden();
        $actor = app(GrantTestAdministratorCapabilities::class)->handle($delegator, $actor);

        $this->actingAs($actor)
            ->withSession($staffSession)
            ->get(route('release.index'))
            ->assertOk();

        $this->actingAs($actor)
            ->withSession($staffSession)
            ->get(route('release.print.batches.index', $gradingSession))
            ->assertOk();
    }
});

test('release print batch and overview list examinees scored via profile-bound AssessmentResultSet without ScoreRecord rows', function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $this->seed(FoundationalScoringSeeder::class);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $kit = TestKit::query()->where('code', 'TK-ICAT-2026')->firstOrFail();
    $preparation = $kit->preparations()->where('state', 'preparing')->firstOrFail();
    $normId = (int) $preparation->norm_package_version_id;
    $version = app(AssembleTestKitPreparation::class)->handle($guidance, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $guidance->id,
        'revision' => (int) $version->revision + 1,
    ])->save();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $attempt = AdmissionAttempt::query()
        ->where('academic_cycle_id', $cycle->id)
        ->where('reference_code', 'APP-ICAT-2026-MIKA')
        ->firstOrFail();

    $created = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: [$attempt->id],
        actor: $guidance,
        label: 'Release print batch profile-bound test',
        kitVersion: $version,
    );
    $examSession = $created['session'];
    $grading = $examSession->gradingSession;

    app(RecordScores::class)->handle(
        gradingSession: $grading,
        attempt: $attempt,
        scores: [
            ['metric_code' => 'VOCABULARY_RAW', 'score' => 34],
            ['metric_code' => 'COMPUTATION_RAW', 'score' => 28],
            ['metric_code' => 'SPATIAL_RAW', 'score' => 16],
            ['metric_code' => 'WORD_COMP_RAW', 'score' => 91],
        ],
        actor: $guidance,
        idempotencyKey: (string) Str::uuid(),
        scoringMode: 'manual_raw',
        testKitVersionId: (int) $version->id,
        normPackageVersionId: $normId,
    );

    app(FinalizeGradingSession::class)->handle(
        $grading->fresh(),
        $guidance,
        (int) $grading->fresh()->version,
    );

    expect($grading->scoreRecords()->count())->toBe(0)
        ->and($grading->assessmentResultSets()->count())->toBe(1);

    $staffSession = [
        'auth.surface' => 'staff',
        'auth.session_version' => (int) ($guidance->staffProfile?->authenticationState?->session_version ?? 1),
    ];

    $this->actingAs($guidance)
        ->withSession($staffSession)
        ->get(route('release.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Release/Index')
            ->where('sessions', fn ($sessions) => collect($sessions)->contains(
                fn ($s) => (int) $s['exam_session_id'] === (int) $examSession->id
                    && (int) $s['examinees_count'] === 1
            ))
        );

    $this->actingAs($guidance)
        ->withSession($staffSession)
        ->get(route('release.print.sessions.index', $examSession))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Release/PrintBatch')
            ->has('applicants', 1)
            ->where('applicants.0.attempt_id', $attempt->id)
            ->where('applicants.0.reference', 'APP-ICAT-2026-MIKA')
        );

    $this->actingAs($guidance)
        ->withSession($staffSession)
        ->get(route('release.print.sessions.applicant', ['examSession' => $examSession->id, 'attempt' => $attempt->id]))
        ->assertNotFound();
    app(PrepareResultDocument::class)->handle($attempt->fresh(), $guidance);

    $this->actingAs($guidance)
        ->withSession($staffSession)
        ->get(route('release.print.sessions.applicant', ['examSession' => $examSession->id, 'attempt' => $attempt->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Release/ResultSheet')
            ->where('applicant.reference', 'APP-ICAT-2026-MIKA')
        );
});

test('mixed current and foreign-institution document requests fail atomically before creating print work', function (): void {
    Queue::fake();
    $fixture = resultSheetPrintAuthorizationFixture($this);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->create([
        'code' => 'SYN-FOREIGN-PRINT', 'name' => 'Synthetic foreign print institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-FOREIGN-PRINT-CYCLE',
        'name' => 'Synthetic foreign print cycle',
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institution->id,
        'normalized_key' => 'synthetic-foreign-print',
        'first_name' => 'Synthetic', 'last_name' => 'Foreign print record',
        'birthdate' => '2001-01-01', 'sex' => 'unspecified',
        'email' => 'synthetic-foreign-print@example.test',
    ]);
    $attempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => 'SYN-FOREIGN-PRINT',
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'accepted_at' => now(),
        'payload' => [],
        'version' => 1,
    ]);
    $foreignDocument = $fixture['document']->replicate();
    $foreignDocument->admission_attempt_id = $attempt->id;
    $foreignDocument->save();
    $attempt->forceFill(['current_result_document_id' => $foreignDocument->id])->save();

    $this->actingAs($guidance)->postJson(route('release.print.request'), [
        'document_ids' => [$fixture['document']->id, $foreignDocument->id],
    ])->assertForbidden();
    expect(PrintJob::query()->count())->toBe(0)
        ->and($fixture['attempt']->fresh()->current_result_document_id)->toBe($fixture['document']->id)
        ->and($attempt->fresh()->current_result_document_id)->toBe($foreignDocument->id);
});
