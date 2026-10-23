<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\ActivateTestKitVersion;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignDirectAssessmentSession;
use App\Ingestion\Actions\ApproveIngestionBatch;
use App\Ingestion\Actions\CommitAllInOneIngestionBatch;
use App\Ingestion\Actions\CommitScoreIngestionBatch;
use App\Ingestion\Actions\ResumeGuidanceIngestionBatch;
use App\Ingestion\Actions\StageImportBatch;
use App\Ingestion\Services\ErrorReportGenerator;
use App\Ingestion\Services\ImportTemplateCatalog;
use App\Ingestion\Services\SpreadsheetInspector;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\ScoreConversionRow;
use App\Models\Foundation\SpaceKind;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';
require_once __DIR__.'/../../Support/E2Helpers.php';
require_once __DIR__.'/../../Support/ExternalFinalFixtures.php';

uses(RefreshDatabase::class);

function createIngestionActor(array $capabilities = []): User
{
    $actor = User::factory()->create();
    foreach ($capabilities as $cap) {
        Permission::findOrCreate($cap, 'web');
        $actor->givePermissionTo($cap);
    }

    return $actor;
}

function setupIngestionEnvironment(): array
{
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'AY-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'BSCS',
        'name' => 'Bachelor of Science in Computer Science',
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-101',
        'name' => 'Main Testing Room',
        'space_kind' => SpaceKind::Room,
        'capacity' => 50,
        'is_active' => true,
    ]);

    $mathArea = AptitudeArea::query()->create([
        'code' => 'MATH',
        'name' => 'Mathematics',
        'max_items' => 50,
        'scoring_method' => AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
        'is_active' => true,
    ]);

    $sciArea = AptitudeArea::query()->create([
        'code' => 'SCIENCE',
        'name' => 'Science',
        'max_items' => 50,
        'scoring_method' => AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
        'is_active' => true,
    ]);

    foreach ([$mathArea, $sciArea] as $area) {
        ScoreConversionRow::query()->create([
            'aptitude_area_id' => $area->id,
            'min_score' => 0,
            'max_score' => 50,
            'output_value' => '100',
        ]);
    }

    return compact('institution', 'cycle', 'program', 'room', 'mathArea', 'sciArea');
}

test('all-in-one commit strictly enforces dual capability authorization', function (): void {
    $env = setupIngestionEnvironment();
    $actorAdmissionsOnly = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);

    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'test.xlsx',
        'file_hash' => 'hash123',
        'batch_fingerprint' => 'fp123',
        'status' => ImportBatch::STATUS_STAGED,
        'created_by_user_id' => $actorAdmissionsOnly->id,
    ]);

    $action = app(CommitAllInOneIngestionBatch::class);
    $authorizer = app(CapabilityAuthorizer::class);

    expect(fn () => $action->handle($batch, [], $actorAdmissionsOnly, $authorizer))
        ->toThrow(AuthorizationException::class);
});

test('commit all-in-one batch atomically executes single-pass ingestion with identity, attempt, exam assignment and score records', function (): void {
    $env = setupIngestionEnvironment();
    $actorDual = createIngestionActor([
        CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $kitVersion = createTestActiveKitVersionFor($this, $actorDual, $env['institution'], $env['cycle'], 'ALLINONE');

    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'test_examinees.csv',
        'file_hash' => 'hash456',
        'batch_fingerprint' => 'fp456',
        'status' => ImportBatch::STATUS_STAGED,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => $kitVersion->id,
                'norm_package_version_id' => null,
            ],
        ],
        'total_rows' => 2,
        'valid_rows' => 2,
        'created_by_user_id' => $actorDual->id,
    ]);

    $row1 = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 2,
        'idempotency_key' => 'key-row-1',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => ['first_name' => 'Maria', 'last_name' => 'Santos', 'RAW_A' => '2'],
        'normalized_payload' => [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'birthdate' => '2005-04-12',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '2',
        ],
    ]);

    $row2 = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 3,
        'idempotency_key' => 'key-row-2',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'RAW_A' => '1'],
        'normalized_payload' => [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthdate' => '2004-11-08',
            'sex' => 'male',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '1',
        ],
    ]);

    $action = app(CommitAllInOneIngestionBatch::class);
    $authorizer = app(CapabilityAuthorizer::class);

    $committed = $action->handle($batch, [], $actorDual, $authorizer);
    expect($committed->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and($committed->imported_rows)->toBe(2)
        ->and($committed->failed_rows)->toBe(0);

    // Assert identities created
    expect(ApplicantIdentity::query()->where('first_name', 'Maria')->exists())->toBeTrue()
        ->and(ApplicantIdentity::query()->where('first_name', 'Juan')->exists())->toBeTrue();

    // Assert admission attempts created in accepted status
    $attempts = AdmissionAttempt::query()->where('academic_cycle_id', $env['cycle']->id)->get();
    expect($attempts)->toHaveCount(2)
        ->and($attempts->every(fn ($a) => $a->status === AdmissionAttempt::STATUS_ACCEPTED))->toBeTrue();

    // Assert exam assignments created
    expect(ExamAssignment::query()->count())->toBe(2);

    $rawResults = AssessmentRawResultRevision::query()->get();
    expect($rawResults)->toHaveCount(2)
        ->and($rawResults->every(fn ($result) => $result->source === 'import'))->toBeTrue();

    // Assert row statuses updated to imported
    expect($row1->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and($row2->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED);
});

test('scores-only commit updates an existing grading roster without creating an applicant', function (): void {
    $env = setupIngestionEnvironment();
    $actor = createIngestionActor([
        CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $kitVersion = createTestActiveKitVersionFor($this, $actor, $env['institution'], $env['cycle'], 'SCORES-ONLY');
    $authorizer = app(CapabilityAuthorizer::class);
    $applicationBatch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'applicant.csv',
        'file_hash' => 'score-applicant-hash',
        'batch_fingerprint' => 'score-applicant-fingerprint',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 1,
        'valid_rows' => 1,
        'created_by_user_id' => $actor->id,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => $kitVersion->id,
                'norm_package_version_id' => null,
            ],
        ],
    ]);
    ImportBatchRow::query()->create([
        'import_batch_id' => $applicationBatch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 2,
        'idempotency_key' => 'score-applicant-row',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => [],
        'normalized_payload' => [
            'first_name' => 'Score',
            'last_name' => 'Candidate',
            'birthdate' => '2005-01-01',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '1',
        ],
    ]);
    app(CommitAllInOneIngestionBatch::class)->handle($applicationBatch, [], $actor, $authorizer);
    $attempt = AdmissionAttempt::query()->sole();
    $identityCount = ApplicantIdentity::query()->count();
    $targetSessionId = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->value('exam_session_id');
    $targetAssignment = ExamAssignment::query()
        ->where('exam_session_id', $targetSessionId)
        ->where('admission_attempt_id', $attempt->id)
        ->active()
        ->firstOrFail();
    $administrationId = AssessmentAdministration::query()
        ->where('exam_assignment_id', $targetAssignment->id)
        ->active()
        ->value('id');

    $scoreBatch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_SCORES_ONLY,
        'file_name' => 'scores.csv',
        'file_hash' => 'score-only-hash',
        'batch_fingerprint' => 'score-only-fingerprint',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 1,
        'valid_rows' => 1,
        'created_by_user_id' => $actor->id,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => $kitVersion->id,
                'norm_package_version_id' => null,
            ],
        ],
    ]);
    $scoreRow = ImportBatchRow::query()->create([
        'import_batch_id' => $scoreBatch->id,
        'sheet_name' => 'Scores',
        'target_exam_session_id' => $targetSessionId,
        'row_index' => 2,
        'idempotency_key' => 'score-only-row',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => [],
        'normalized_payload' => [
            'applicant_number' => $attempt->reference_code,
            'RAW_A' => '2',
        ],
    ]);

    $result = app(CommitScoreIngestionBatch::class)->handle($scoreBatch, [], $actor, $authorizer);

    expect($result->imported_rows)->toBe(1)
        ->and($result->failed_rows)->toBe(0)
        ->and($scoreRow->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and(ApplicantIdentity::query()->count())->toBe($identityCount)
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $administrationId)
            ->where('is_current', true)
            ->value('raw_value'))->toBe('2.000000');
    $runsBeforeReplay = AssessmentScoringRun::query()->where('administration_id', $administrationId)->count();
    app(CommitScoreIngestionBatch::class)->handle($scoreBatch->fresh(), [], $actor, $authorizer);
    expect(AssessmentScoringRun::query()->where('administration_id', $administrationId)->count())->toBe($runsBeforeReplay);
    $otherKit = createTestActiveKitVersionFor($this, $actor, $env['institution'], $env['cycle'], 'WRONG-SCORE-KIT');
    $wrongSession = ExamSession::query()->create([
        'academic_cycle_id' => $env['cycle']->id,
        'room_id' => $env['room']->id,
        'code' => 'WRONG-SCORE-SITTING',
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'version' => 1,
    ]);
    foreach ([
        ['kit' => $otherKit, 'session' => $targetSessionId],
        ['kit' => $kitVersion, 'session' => $wrongSession->id],
    ] as $index => $wrongTarget) {
        $invalidBatch = ImportBatch::query()->create([
            'institution_id' => $env['institution']->id,
            'academic_cycle_id' => $env['cycle']->id,
            'policy_version' => 1,
            'import_type' => ImportBatch::TYPE_SCORES_ONLY,
            'file_name' => 'wrong-target.csv',
            'file_hash' => 'wrong-target-'.$index,
            'batch_fingerprint' => 'wrong-target-'.$index,
            'status' => ImportBatch::STATUS_STAGED,
            'total_rows' => 1,
            'valid_rows' => 1,
            'created_by_user_id' => $actor->id,
            'topology_meta' => ['assessment_selection' => ['test_kit_version_id' => $wrongTarget['kit']->id]],
        ]);
        $invalidRow = $invalidBatch->rows()->create([
            'sheet_name' => 'Scores', 'row_index' => 2,
            'target_exam_session_id' => $wrongTarget['session'],
            'idempotency_key' => 'wrong-target-row-'.$index,
            'status' => ImportBatchRow::STATUS_VALID,
            'raw_payload' => [],
            'normalized_payload' => ['applicant_number' => $attempt->reference_code, 'RAW_A' => '0'],
        ]);
        $rejected = app(CommitScoreIngestionBatch::class)->handle($invalidBatch, [], $actor, $authorizer);
        expect($rejected->imported_rows)->toBe(0)
            ->and($rejected->failed_rows)->toBe(1)
            ->and($invalidRow->fresh()->status)->toBe(ImportBatchRow::STATUS_FAILED)
            ->and(AssessmentRawResultRevision::query()->where('administration_id', $administrationId)->where('is_current', true)->value('raw_value'))->toBe('2.000000')
            ->and(AssessmentScoringRun::query()->where('administration_id', $administrationId)->count())->toBe($runsBeforeReplay);
    }
    $actor->syncPermissions([]);
    expect(fn () => app(CommitScoreIngestionBatch::class)->handle($scoreBatch->fresh(), [], $actor, $authorizer))
        ->toThrow(AuthorizationException::class);
});

test('multi-session consent creates applicants before guidance assigns mapped sessions and scores', function (): void {
    $env = setupIngestionEnvironment();
    $registrar = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $guidance = createIngestionActor([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $kitVersion = createTestActiveKitVersionFor($this, $guidance, $env['institution'], $env['cycle'], 'MULTI-SESSION');
    $targetSession = ExamSession::query()->create([
        'academic_cycle_id' => $env['cycle']->id,
        'room_id' => $env['room']->id,
        'code' => 'EX-MAPPED',
        'test_kit_version_id' => $kitVersion->id,
        'test_instrument_version_id' => $kitVersion->test_instrument_version_id,
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'version' => 1,
    ]);
    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_MULTI_SESSION,
        'file_name' => 'mapped.xlsx',
        'file_hash' => 'mapped-hash',
        'batch_fingerprint' => 'mapped-fingerprint',
        'status' => ImportBatch::STATUS_PENDING_APPROVAL,
        'total_rows' => 1,
        'valid_rows' => 1,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => null,
                'norm_package_version_id' => null,
                'session_kit_versions' => [$targetSession->id => $kitVersion->id],
            ],
        ],
        'created_by_user_id' => $guidance->id,
    ]);
    $row = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'target_exam_session_id' => $targetSession->id,
        'sheet_name' => 'Morning',
        'row_index' => 2,
        'idempotency_key' => 'mapped-row',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => [],
        'normalized_payload' => [
            'first_name' => 'Mapped',
            'last_name' => 'Candidate',
            'birthdate' => '2005-01-01',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '2',
        ],
    ]);
    $authorizer = app(CapabilityAuthorizer::class);

    $consented = app(ApproveIngestionBatch::class)->handle($batch, [$row->id], $registrar, $authorizer);

    expect($consented->status)->toBe(ImportBatch::STATUS_AWAITING_GUIDANCE)
        ->and($row->fresh()->status)->toBe(ImportBatchRow::STATUS_APPLICANT_IMPORTED)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $result = app(ResumeGuidanceIngestionBatch::class)->handle($batch, $guidance, $authorizer);

    expect($result->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and($result->imported_rows)->toBe(1)
        ->and($result->failed_rows)->toBe(0)
        ->and($row->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and(ExamAssignment::query()->sole()->exam_session_id)->toBe($targetSession->id)
        ->and(AssessmentRawResultRevision::query()->where('source', AssessmentRawResultRevision::SOURCE_IMPORT)->exists())->toBeTrue();
});

test('row-atomic fault isolation rolls back failing row while committing valid sibling rows', function (): void {
    $env = setupIngestionEnvironment();
    $actorDual = createIngestionActor([
        CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $kitVersion = createTestActiveKitVersionFor($this, $actorDual, $env['institution'], $env['cycle'], 'ROW-ATOMIC');

    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'test_fault.csv',
        'file_hash' => 'hashFault',
        'batch_fingerprint' => 'fpFault',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 2,
        'valid_rows' => 2,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => $kitVersion->id,
                'norm_package_version_id' => null,
            ],
        ],
        'created_by_user_id' => $actorDual->id,
    ]);

    // Row 1: completely valid
    $row1 = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 2,
        'idempotency_key' => 'key-fault-1',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => ['first_name' => 'Valid', 'last_name' => 'Candidate'],
        'normalized_payload' => [
            'first_name' => 'Valid',
            'last_name' => 'Candidate',
            'birthdate' => '2005-01-01',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '1',
        ],
    ]);

    // Row 2: invalid birthdate format that triggers exception inside Carbon parsing
    $row2 = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 3,
        'idempotency_key' => 'key-fault-2',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => ['first_name' => 'Faulty', 'last_name' => 'Candidate'],
        'normalized_payload' => [
            'first_name' => 'Faulty',
            'last_name' => 'Candidate',
            'birthdate' => 'not-a-valid-date-string-and-will-throw',
            'sex' => 'male',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '1',
        ],
    ]);

    $action = app(CommitAllInOneIngestionBatch::class);
    $authorizer = app(CapabilityAuthorizer::class);

    $result = $action->handle($batch, [], $actorDual, $authorizer);

    expect($result->imported_rows)->toBe(1)
        ->and($result->failed_rows)->toBe(1);

    // Row 1 succeeded and committed
    expect($row1->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and(ApplicantIdentity::query()->where('first_name', 'Valid')->exists())->toBeTrue();

    // Row 2 failed and rolled back: NO identity created for 'Faulty'
    expect($row2->fresh()->status)->toBe(ImportBatchRow::STATUS_FAILED)
        ->and($row2->fresh()->errors)->not->toBeEmpty()
        ->and(ApplicantIdentity::query()->where('first_name', 'Faulty')->exists())->toBeFalse();
});

test('stage import batch generates collision-safe batch fingerprint and row idempotency keys', function (): void {
    $env = setupIngestionEnvironment();
    $actor = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);

    // Create a temporary CSV file
    $csvContent = "first_name,last_name,birthdate,sex,course_preference_1,strand,contact_email,last_school_enrolled\n";
    $csvContent .= "Rhea,Reviewer,2005-02-15,female,BSCS,STEM,rhea@example.com,Manila High School\n";
    $csvContent .= "Robin,Reviewer,2005-06-20,male,BSCS,ABM,robin@example.com,Quezon High School\n";

    $tmpPath = tempnam(sys_get_temp_dir(), 'nex_test_').'.csv';
    file_put_contents($tmpPath, $csvContent);

    $uploadedFile = new UploadedFile($tmpPath, 'cohort_batch.csv', 'text/csv', null, true);
    $action = app(StageImportBatch::class);
    $batch = $action->handle(
        file: $uploadedFile,
        institution: $env['institution'],
        cycle: $env['cycle'],
        importType: ImportBatch::TYPE_APPLICANTS_ONLY,
        actor: $actor,
    );

    expect($batch)->not->toBeNull()
        ->and($batch->batch_fingerprint)->toHaveLength(64)
        ->and($batch->total_rows)->toBe(2)
        ->and($batch->valid_rows)->toBe(2);

    $rows = $batch->rows;
    expect($rows)->toHaveCount(2)
        ->and($rows->first()->idempotency_key)->toHaveLength(64)
        ->and($rows->first()->idempotency_key)->not->toBe($rows->last()->idempotency_key);

    // Re-uploading same file returns existing batch without duplicate insertion
    $batchReplay = $action->handle(
        file: $uploadedFile,
        institution: $env['institution'],
        cycle: $env['cycle'],
        importType: ImportBatch::TYPE_APPLICANTS_ONLY,
        actor: $actor,
    );

    expect($batchReplay->id)->toBe($batch->id)
        ->and(ImportBatch::query()->count())->toBe(1);

    @unlink($tmpPath);
});
test('selective confirmation imports chosen rows while leaving unselected rows untouched', function (): void {
    $env = setupIngestionEnvironment();
    $actorDual = createIngestionActor([
        CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $kitVersion = createTestActiveKitVersionFor($this, $actorDual, $env['institution'], $env['cycle'], 'SELECTIVE');

    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'selective.csv',
        'file_hash' => 'hashSel',
        'batch_fingerprint' => 'fpSel',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 2,
        'valid_rows' => 2,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => $kitVersion->id,
                'norm_package_version_id' => null,
            ],
        ],
        'created_by_user_id' => $actorDual->id,
    ]);

    $row1 = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 2,
        'idempotency_key' => 'key-sel-1',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => ['first_name' => 'Selected', 'last_name' => 'Applicant'],
        'normalized_payload' => [
            'first_name' => 'Selected',
            'last_name' => 'Applicant',
            'birthdate' => '2005-01-01',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
        ],
    ]);

    $row2 = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 3,
        'idempotency_key' => 'key-sel-2',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => ['first_name' => 'Unselected', 'last_name' => 'Applicant'],
        'normalized_payload' => [
            'first_name' => 'Unselected',
            'last_name' => 'Applicant',
            'birthdate' => '2005-01-01',
            'sex' => 'male',
            'course_preference_1' => 'BSCS',
        ],
    ]);

    $action = app(CommitAllInOneIngestionBatch::class);
    $authorizer = app(CapabilityAuthorizer::class);

    // Select ONLY row 1
    $result = $action->handle($batch, [$row1->id], $actorDual, $authorizer);

    expect($result->imported_rows)->toBe(1)
        ->and($result->skipped_rows)->toBe(1);

    // Row 1 is imported, Row 2 is NOT imported
    expect($row1->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and($row2->fresh()->status)->toBe(ImportBatchRow::STATUS_SKIPPED)
        ->and(ApplicantIdentity::query()->where('first_name', 'Selected')->exists())->toBeTrue()
        ->and(ApplicantIdentity::query()->where('first_name', 'Unselected')->exists())->toBeFalse();
});

test('error report generator streams csv with failing row details', function (): void {
    $env = setupIngestionEnvironment();
    $actor = createIngestionActor();

    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'report_test.csv',
        'file_hash' => 'hashRep',
        'batch_fingerprint' => 'fpRep',
        'status' => ImportBatch::STATUS_COMPLETED,
        'created_by_user_id' => $actor->id,
    ]);

    ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Batch 1',
        'row_index' => 4,
        'idempotency_key' => 'key-rep-1',
        'status' => ImportBatchRow::STATUS_FAILED,
        'reference_code' => 'APP-2026-999',
        'raw_payload' => ['name' => 'Faulty Row'],
        'errors' => ['Score must be numeric'],
    ]);

    $generator = app(ErrorReportGenerator::class);
    $response = $generator->generate($batch);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});
test('direct import commit rejects missing empty and foreign row selections', function (): void {
    $env = setupIngestionEnvironment();
    $actor = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_APPLICANTS_ONLY,
        'file_name' => 'selection.csv',
        'file_hash' => 'selection-hash',
        'batch_fingerprint' => 'selection-fingerprint',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 1,
        'valid_rows' => 1,
        'created_by_user_id' => $actor->id,
    ]);
    $row = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Applicants',
        'row_index' => 2,
        'idempotency_key' => 'selection-row',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => [],
        'normalized_payload' => [
            'first_name' => 'Selection',
            'last_name' => 'Candidate',
            'birthdate' => '2005-01-01',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
        ],
    ]);

    $this->actingAs($actor)
        ->from(route('imports.review', $batch))
        ->post(route('imports.commit', $batch))
        ->assertSessionHasErrors('selected_row_ids');

    $this->actingAs($actor)
        ->from(route('imports.review', $batch))
        ->post(route('imports.commit', $batch), ['selected_row_ids' => []])
        ->assertSessionHasErrors('selected_row_ids');

    $this->actingAs($actor)
        ->from(route('imports.review', $batch))
        ->post(route('imports.commit', $batch), ['selected_row_ids' => [$row->id + 1000]])
        ->assertSessionHasErrors('selected_row_ids');

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_STAGED)
        ->and($row->fresh()->status)->toBe(ImportBatchRow::STATUS_VALID)
        ->and(ApplicantIdentity::query()->count())->toBe(0);
});
test('analyze recognizes applicant number as a score identifier and treats zero as populated', function (): void {
    $env = setupIngestionEnvironment();
    $guidance = createIngestionActor([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);
    $scores = UploadedFile::fake()->createWithContent(
        'scores.csv',
        "applicant_number,MATH,SCIENCE\nREF-001,0,41\n",
    );

    $this->actingAs($guidance)
        ->postJson(route('imports.analyze'), [
            'file' => $scores,
            'academic_cycle_id' => $env['cycle']->id,
        ])
        ->assertOk()
        ->assertJsonPath('suggested_import_type', 'scores_only')
        ->assertJsonPath('suggestion_authorized', true);
});

test('analyze recommends applicant intake when score columns are blank and omits raw cell values', function (): void {
    $env = setupIngestionEnvironment();
    $registrar = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $applicants = UploadedFile::fake()->createWithContent(
        'applicants.csv',
        "first_name,last_name,birthdate,sex,course,applicant_number,email,strand,school,math,science,notes\n".
        "Maria,Santos,2005-01-01,female,BSCS,REF-002,maria@example.test,STEM,North High,,,SENSITIVE-CELL-VALUE\n",
    );

    $this->actingAs($registrar)
        ->postJson(route('imports.analyze'), [
            'file' => $applicants,
            'academic_cycle_id' => $env['cycle']->id,
        ])
        ->assertOk()
        ->assertJsonPath('suggested_import_type', 'applicants_only')
        ->assertDontSee('SENSITIVE-CELL-VALUE');
});
test('multi-session stage rejects malformed nonblank scores and validates valid siblings', function (): void {
    $env = setupIngestionEnvironment();
    $actor = createIngestionActor([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $kitVersion = createTestActiveKitVersionFor($this, $actor, $env['institution'], $env['cycle'], 'MULTI-STAGE');
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $env['cycle']->id,
        'room_id' => $env['room']->id,
        'code' => 'EX-MULTI-COMPLETED',
        'test_kit_version_id' => $kitVersion->id,
        'test_instrument_version_id' => $kitVersion->test_instrument_version_id,
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'version' => 1,
    ]);
    $file = UploadedFile::fake()->createWithContent('multi.csv', implode("\n", [
        'first_name,last_name,birthdate,sex,course,email,strand,school,RAW_A',
        'Malformed,Score,2005-01-01,female,BSCS,bad-score@example.test,STEM,North High,not-a-number',
        'Valid,Score,2005-01-02,male,BSCS,valid-score@example.test,STEM,North High,2',
    ]));
    $sheetName = app(SpreadsheetInspector::class)->inspect($file)['sheets'][0]['name'];

    $batch = app(StageImportBatch::class)->handle(
        file: $file,
        institution: $env['institution'],
        cycle: $env['cycle'],
        importType: ImportBatch::TYPE_MULTI_SESSION,
        actor: $actor,
        sheetToSessionMap: [$sheetName => $session->id],
    );
    $rows = $batch->rows()->orderBy('row_index')->get();

    expect($batch->status)->toBe(ImportBatch::STATUS_PENDING_APPROVAL)
        ->and($batch->valid_rows)->toBe(1)
        ->and($batch->invalid_rows)->toBe(1)
        ->and($rows[0]->status)->toBe(ImportBatchRow::STATUS_INVALID)
        ->and($rows[1]->status)->toBe(ImportBatchRow::STATUS_VALID)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});

test('multi-session stage rejects score rows mapped to a session without a grading context', function (): void {
    $env = setupIngestionEnvironment();
    $actor = createIngestionActor([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
    ]);
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $env['cycle']->id,
        'room_id' => $env['room']->id,
        'code' => 'EX-MULTI-PUBLISHED',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addHour(),
        'effective_end_at' => now()->addHours(2),
        'published_at' => now(),
        'version' => 1,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'multi-published.csv',
        "first_name,last_name,birthdate,sex,course,email,strand,school,math,science\n".
        "Pending,Score,2005-01-01,female,BSCS,pending-score@example.test,STEM,North High,40,41\n",
    );
    $sheetName = app(SpreadsheetInspector::class)->inspect($file)['sheets'][0]['name'];

    $batch = app(StageImportBatch::class)->handle(
        file: $file,
        institution: $env['institution'],
        cycle: $env['cycle'],
        importType: ImportBatch::TYPE_MULTI_SESSION,
        actor: $actor,
        sheetToSessionMap: [$sheetName => $session->id],
    );

    expect($batch->valid_rows)->toBe(0)
        ->and($batch->invalid_rows)->toBe(1)
        ->and($batch->rows->first()->errors)->toContain('Rows with scores must be mapped to a completed exam session with a grading context.')
        ->and(ApplicantIdentity::query()->count())->toBe(0);
});

test('score-only import records exact external finals and replays without duplicate rows', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $fixture = externalFinalAdministrationFixture($this, $actor);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_IMPORT, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
    $session = $fixture['gradingSession']->examSession;
    $session->forceFill(['status' => ExamSession::STATUS_COMPLETED])->save();

    $headers = ['applicant_number'];
    $values = [$fixture['attempt']->reference_code];
    foreach ($fixture['profile']->metrics()->orderBy('id')->get() as $index => $metric) {
        $headers[] = $metric->code;
        $headers[] = $metric->code.'_source_reference';
        $values[] = $index === 0 ? '0.00' : '81.25';
        $values[] = 'Synthetic provider report '.($index + 1);
    }
    $file = UploadedFile::fake()->createWithContent(
        'external-final-score-only.csv',
        implode(',', $headers)."\n".implode(',', $values)."\n",
    );
    $foreignKit = testKitSetupOnlyFixture($actor, $fixture['profile'])['version'];
    expect(fn () => app(StageImportBatch::class)->handle(
        file: $file,
        institution: Institution::current(),
        cycle: AcademicCycle::query()->findOrFail($fixture['attempt']->academic_cycle_id),
        importType: ImportBatch::TYPE_SCORES_ONLY,
        actor: $actor,
        targetExamSession: $session,
        targetKitVersion: $foreignKit,
    ))->toThrow(InvalidArgumentException::class);

    $batch = app(StageImportBatch::class)->handle(
        file: $file,
        institution: Institution::current(),
        cycle: AcademicCycle::query()->findOrFail($fixture['attempt']->academic_cycle_id),
        importType: ImportBatch::TYPE_SCORES_ONLY,
        actor: $actor,
        targetExamSession: $session,
        targetKitVersion: $fixture['kitVersion'],
    );
    expect($batch->valid_rows)->toBe(1);

    $action = app(CommitScoreIngestionBatch::class);
    $authorizer = app(CapabilityAuthorizer::class);
    $committed = $action->handle($batch, [], $actor, $authorizer);
    $revision = AssessmentConversionRevision::query()
        ->where('administration_id', $fixture['administration']->id)
        ->where('is_current', true)
        ->firstOrFail();
    $revisionCount = AssessmentConversionRevision::query()
        ->where('administration_id', $fixture['administration']->id)
        ->count();

    expect($committed->imported_rows)->toBe(1)
        ->and($committed->failed_rows)->toBe(0)
        ->and($revision->source)->toBe('manual_converted')
        ->and($revision->norm_package_version_id)->toBeNull()
        ->and(BigDecimal::of($revision->typed_outputs_json['FINAL_1']['percentile_rank'])->compareTo('0'))->toBe(0)
        ->and($revision->provenance_json['acquisition_channel'])->toBe('import')
        ->and($revision->provenance_json['external_provenance']['FINAL_1'])->toBe('Synthetic provider report 1')
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(0);

    $action->handle($committed->fresh(), [], $actor, $authorizer);
    expect(AssessmentConversionRevision::query()
        ->where('administration_id', $fixture['administration']->id)->count())->toBe($revisionCount)
        ->and(ScoreRecord::query()->where('admission_attempt_id', $fixture['attempt']->id)->count())->toBe(0);
});

test('score-only final import resolves normalized hyphen metric headers through the exact profile map', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $submitted = submitSyntheticApplicant('hyphen-final-'.Str::random(8).'@example.test', 'Hyphen');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submitted['attempt'], $registrar, 1);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_IMPORT, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_IMPORT);

    $constructIds = externalFinalConstructIds();
    $profilePayload = externalFinalProfilePayload($constructIds, 'HYPHEN-IMPORT');
    $profilePayload['metrics'][0]['code'] = 'FINAL-1';
    $instrument = new TestInstrument([
        'institution_id' => $actor->staffProfile()->firstOrFail()->institution_id,
        'code' => 'HYPHEN-IMPORT',
    ]);
    $instrument->forceFill(['is_synthetic' => false])->save();
    $draft = new TestInstrumentVersion([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'lock_version' => 1,
        'created_by_user_id' => $actor->id,
    ]);
    app(TestInstrumentDraftWriter::class)->save(
        $draft,
        app(TestInstrumentProfileValidator::class)->normalize($profilePayload),
    );
    $profile = app(ApproveTestInstrumentVersion::class)->handle(
        $actor,
        (int) $instrument->id,
        (int) $draft->id,
        (int) $draft->lock_version,
        (string) Str::uuid(),
        sourceReviewAttestation: true,
        isDemo: false,
    );
    $kit = testKitSetupOnlyFixture($actor, $profile)['version'];
    $kit = app(ActivateTestKitVersion::class)->handle(
        $actor,
        (int) $kit->test_kit_id,
        (int) $kit->id,
        [
            'expected_revision' => (int) $kit->revision,
            'idempotency_key' => (string) Str::uuid(),
            'academic_cycle_id' => $attempt->academic_cycle_id,
        ],
    );
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('institution_id', Institution::current()->id)->where('is_active', true)->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'test_kit_version_id' => $kit->id,
        'test_instrument_version_id' => $profile->id,
        'code' => 'HYPHEN-'.Str::upper(Str::random(8)),
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'version' => 1,
    ]);
    app(AssignDirectAssessmentSession::class)->handle(
        $cycle,
        $room,
        $attempt,
        $actor,
        $session,
        $kit,
    );

    $headers = ['applicant_number'];
    $values = [$attempt->reference_code];
    foreach ($profile->metrics()->orderBy('id')->get() as $index => $metric) {
        $headers[] = $metric->code;
        $headers[] = $metric->code.'_source_reference';
        $values[] = $index === 0 ? '0.00' : '81.25';
        $values[] = 'Hyphen report '.($index + 1);
    }
    $batch = app(StageImportBatch::class)->handle(
        file: UploadedFile::fake()->createWithContent(
            'hyphen-score-only.csv',
            implode(',', $headers)."\n".implode(',', $values)."\n",
        ),
        institution: Institution::current(),
        cycle: $cycle,
        importType: ImportBatch::TYPE_SCORES_ONLY,
        actor: $actor,
        targetExamSession: $session,
        targetKitVersion: $kit,
    );
    $committed = app(CommitScoreIngestionBatch::class)->handle($batch, [], $actor, app(CapabilityAuthorizer::class));
    $assignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->where('admission_attempt_id', $attempt->id)
        ->active()
        ->firstOrFail();
    $admin = AssessmentAdministration::query()
        ->where('exam_assignment_id', $assignment->id)
        ->active()
        ->firstOrFail();
    $revision = AssessmentConversionRevision::query()
        ->where('administration_id', $admin->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($committed->failed_rows)->toBe(0)
        ->and(BigDecimal::of($revision->typed_outputs_json['FINAL-1']['percentile_rank'])->compareTo('0'))->toBe(0)
        ->and($revision->provenance_json['external_provenance']['FINAL-1'])->toBe('Hyphen report 1');
});

test('combined final import binds external-final operation before its first typed score write', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $fixture = externalFinalAdministrationFixture($this, $actor);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_IMPORT, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
    $cycle = AcademicCycle::query()->findOrFail($fixture['attempt']->academic_cycle_id);
    $cycle->forceFill(['allow_guidance_all_in_one_intake' => true])->save();

    $schema = app(ImportTemplateCatalog::class)->schema(
        ImportBatch::TYPE_ALL_IN_ONE,
        Institution::current(),
        $cycle,
        $fixture['kitVersion'],
    );
    $applicant = [];
    foreach ($schema['fields'] as $field) {
        if (in_array($field['group'], ['scores', 'sources'], true) || ! $field['required']) {
            continue;
        }
        $applicant[$field['header']] = $field['example'] ?: match ($field['header']) {
            'birthdate' => '2001-01-01',
            'sex' => 'Female',
            'applicant_type' => 'New',
            'course_preference_1' => 'BSIT',
            'contact_email' => 'combined-final@example.test',
            default => 'Synthetic',
        };
    }
    $headers = array_keys($applicant);
    $values = array_values($applicant);
    foreach ($fixture['profile']->metrics()->orderBy('id')->get() as $index => $metric) {
        $headers[] = $metric->code;
        $headers[] = $metric->code.'_source_reference';
        $values[] = $index === 0 ? '0.00' : '81.25';
        $values[] = 'Combined report '.($index + 1);
    }
    $file = UploadedFile::fake()->createWithContent(
        'external-final-combined.csv',
        implode(',', $headers)."\n".implode(',', $values)."\n",
    );
    $batch = app(StageImportBatch::class)->handle(
        file: $file,
        institution: Institution::current(),
        cycle: $cycle,
        importType: ImportBatch::TYPE_ALL_IN_ONE,
        actor: $actor,
        targetKitVersion: $fixture['kitVersion'],
    );
    expect($batch->status)->toBe(ImportBatch::STATUS_STAGED)
        ->and($batch->valid_rows)->toBe(1);

    $action = app(CommitAllInOneIngestionBatch::class);
    $authorizer = app(CapabilityAuthorizer::class);
    $committed = $action->handle($batch, [], $actor, $authorizer);
    $row = $committed->rows()->firstOrFail();
    $attemptCount = AdmissionAttempt::query()->count();
    $assignment = ExamAssignment::query()->where('admission_attempt_id', $row->admission_attempt_id)->active()->firstOrFail();
    $administration = AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail();
    $revision = AssessmentConversionRevision::query()->where('administration_id', $administration->id)->where('is_current', true)->firstOrFail();

    expect($committed->imported_rows)->toBe(1)
        ->and($revision->source)->toBe('manual_converted')
        ->and($revision->provenance_json['acquisition_channel'])->toBe('import')
        ->and(BigDecimal::of($revision->typed_outputs_json['FINAL_1']['percentile_rank'])->compareTo('0'))->toBe(0)
        ->and($administration->procedure_snapshot_json['operation'])->toBe('external_final')
        ->and($revision->norm_package_version_id)->toBeNull()
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $administration->id)->count())->toBe(0);

    $action->handle($committed->fresh(), [], $actor, $authorizer);
    expect(AdmissionAttempt::query()->count())->toBe($attemptCount)
        ->and(ExamAssignment::query()->where('admission_attempt_id', $row->admission_attempt_id)->count())->toBe(1);
});

test('multi-session registrar consent hides final references and guidance resume records typed finals once', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $fixture = externalFinalAdministrationFixture($this, $guidance);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_IMPORT, 'web');
    $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
    $registrar = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $institution = Institution::current();
    $cycle = AcademicCycle::query()->findOrFail($fixture['attempt']->academic_cycle_id);
    $session = $fixture['gradingSession']->examSession;
    $session->forceFill(['status' => ExamSession::STATUS_COMPLETED])->save();
    $schema = app(ImportTemplateCatalog::class)->schema(
        ImportBatch::TYPE_MULTI_SESSION,
        $institution,
        $cycle,
        $fixture['kitVersion'],
        $session,
    );
    $headers = [];
    $values = [];
    foreach ($schema['fields'] as $field) {
        if (! $field['required'] && ! in_array($field['group'], ['scores', 'sources'], true)) {
            continue;
        }
        $example = $field['example'] ?? null;
        if ($example === null || $example === '') {
            $example = match ($field['header']) {
                'birthdate' => '2001-01-01',
                'sex' => 'Female',
                'applicant_type' => 'New',
                'course_preference_1' => 'BSIT',
                'contact_email' => 'multi-final@example.test',
                default => 'Synthetic',
            };
        }
        $headers[] = $field['header'];
        $values[] = $example;
    }
    $file = UploadedFile::fake()->createWithContent(
        'external-final-multisession.csv',
        implode(',', $headers)."\n".implode(',', $values)."\n",
    );
    $sheet = app(SpreadsheetInspector::class)->inspect($file)['sheets'][0]['name'];
    $batch = app(StageImportBatch::class)->handle(
        file: $file,
        institution: $institution,
        cycle: $cycle,
        importType: ImportBatch::TYPE_MULTI_SESSION,
        actor: $guidance,
        sheetToSessionMap: [$sheet => $session->id],
    );
    $row = $batch->rows()->firstOrFail();
    expect($batch->status)->toBe(ImportBatch::STATUS_PENDING_APPROVAL)
        ->and($batch->valid_rows)->toBe(1);

    $this->actingAs($registrar)->get(route('imports.review', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Import/Review')
            ->where('review_mode', 'registrar_consent')
            ->where('rows.data.0.score_values', [])
            ->missing('rows.data.0.applicant_fields.final_1')
            ->missing('rows.data.0.applicant_fields.final_1_source_reference'));
    $this->actingAs($guidance)->get(route('imports.review', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('review_mode', 'standard')
            ->where('rows.data.0.score_values.final_1', '0')
            ->where('rows.data.0.score_values.final_1_source_reference', 'Record reference'));

    $assignmentCountBeforeConsent = ExamAssignment::query()->count();

    $authorizer = app(CapabilityAuthorizer::class);
    $consented = app(ApproveIngestionBatch::class)->handle($batch, [$row->id], $registrar, $authorizer);
    expect($consented->status)->toBe(ImportBatch::STATUS_AWAITING_GUIDANCE)
        ->and($row->fresh()->status)->toBe(ImportBatchRow::STATUS_APPLICANT_IMPORTED)
        ->and(ExamAssignment::query()->count())->toBe($assignmentCountBeforeConsent)
        ->and(ScoreRecord::query()->where('admission_attempt_id', $row->fresh()->admission_attempt_id)->count())->toBe(0);

    $pinnedTopology = $consented->topology_meta;
    $partialTopology = $pinnedTopology;
    unset($partialTopology['assessment_selection']['session_contracts'][(string) $session->id]['test_kit_version_id']);
    $consented->update(['topology_meta' => $partialTopology]);
    $partialResume = app(ResumeGuidanceIngestionBatch::class)->handle($consented->fresh(), $guidance, $authorizer);
    expect($partialResume->status)->toBe(ImportBatch::STATUS_AWAITING_GUIDANCE)
        ->and($row->fresh()->status)->toBe(ImportBatchRow::STATUS_APPLICANT_IMPORTED)
        ->and($row->fresh()->guidance_status)->toBe('failed')
        ->and(ExamAssignment::query()->count())->toBe($assignmentCountBeforeConsent);

    $consented->update(['topology_meta' => $pinnedTopology]);
    $consented = $consented->fresh();
    $resumed = app(ResumeGuidanceIngestionBatch::class)->handle($consented, $guidance, $authorizer);
    $importedRow = $row->fresh();
    $assignment = ExamAssignment::query()->where('admission_attempt_id', $importedRow->admission_attempt_id)->active()->firstOrFail();
    $administration = AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail();
    $revision = AssessmentConversionRevision::query()->where('administration_id', $administration->id)->where('is_current', true)->firstOrFail();
    $revisionCount = AssessmentConversionRevision::query()->where('administration_id', $administration->id)->count();
    $assignmentCount = ExamAssignment::query()->count();

    expect($resumed->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and($importedRow->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and($revision->source)->toBe('manual_converted')
        ->and($revision->provenance_json['acquisition_channel'])->toBe('import')
        ->and(BigDecimal::of($revision->typed_outputs_json['FINAL_1']['percentile_rank'])->compareTo('0'))->toBe(0)
        ->and($revision->provenance_json['external_provenance']['FINAL_1'])->toBe('Record reference')
        ->and($administration->procedure_snapshot_json['operation'])->toBe('external_final')
        ->and($revision->norm_package_version_id)->toBeNull()
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $administration->id)->count())->toBe(0);

    app(ResumeGuidanceIngestionBatch::class)->handle($resumed->fresh(), $guidance, $authorizer);
    expect(AssessmentConversionRevision::query()->where('administration_id', $administration->id)->count())->toBe($revisionCount)
        ->and(ExamAssignment::query()->count())->toBe($assignmentCount);
});

test('demographic-only multi-session final-kit row resumes with scheduling authority and no final recording', function (): void {
    seedE2Foundation();
    $catalogActor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $fixture = externalFinalAdministrationFixture($this, $catalogActor);
    $registrar = createIngestionActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $scheduler = createIngestionActor([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE]);
    $cycle = AcademicCycle::query()->findOrFail($fixture['attempt']->academic_cycle_id);
    $institution = Institution::current();
    $room = Room::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'test_kit_version_id' => $fixture['kitVersion']->id,
        'test_instrument_version_id' => $fixture['profile']->id,
        'code' => 'DEMO-ONLY-'.Str::upper(Str::random(8)),
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDay(),
        'effective_end_at' => now()->addDays(2),
        'version' => 1,
    ]);
    $schema = app(ImportTemplateCatalog::class)->schema(
        ImportBatch::TYPE_MULTI_SESSION,
        $institution,
        $cycle,
        $fixture['kitVersion'],
        $session,
    );
    $scoreFields = collect($schema['fields'])->filter(fn (array $field): bool => in_array($field['group'], ['scores', 'sources'], true));
    expect($scoreFields->every(fn (array $field): bool => ! $field['required']))->toBeTrue()
        ->and($schema['default_headers'])->not->toContain('FINAL_1', 'FINAL_1_source_reference');

    $headers = [];
    $values = [];
    foreach ($schema['fields'] as $field) {
        if (in_array($field['group'], ['scores', 'sources'], true) || ! $field['required']) {
            continue;
        }
        $headers[] = $field['header'];
        $example = $field['example'] ?? null;
        $values[] = $example === null || $example === '' ? 'Synthetic' : $example;
    }
    $file = UploadedFile::fake()->createWithContent(
        'final-kit-demographic-only.csv',
        implode(',', $headers)."\n".implode(',', $values)."\n",
    );
    $sheet = app(SpreadsheetInspector::class)->inspect($file)['sheets'][0]['name'];
    $batch = app(StageImportBatch::class)->handle(
        file: $file,
        institution: $institution,
        cycle: $cycle,
        importType: ImportBatch::TYPE_MULTI_SESSION,
        actor: $scheduler,
        sheetToSessionMap: [$sheet => $session->id],
    );
    expect($batch->valid_rows)->toBe(1)
        ->and($batch->rows()->firstOrFail()->target_exam_session_id)->toBe($session->id);

    $row = $batch->rows()->firstOrFail();
    $authorizer = app(CapabilityAuthorizer::class);
    $consented = app(ApproveIngestionBatch::class)->handle($batch, [$row->id], $registrar, $authorizer);
    $result = app(ResumeGuidanceIngestionBatch::class)->handle($consented, $scheduler, $authorizer);
    $importedRow = $row->fresh();
    $assignment = ExamAssignment::query()->where('admission_attempt_id', $importedRow->admission_attempt_id)->active()->firstOrFail();

    expect($result->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and($result->imported_rows)->toBe(1)
        ->and($assignment->exam_session_id)->toBe($session->id)
        ->and($scheduler->can(CapabilityCatalog::GUIDANCE_SCORING_IMPORT))->toBeFalse()
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeFalse()
        ->and(ScoreRecord::query()->where('admission_attempt_id', $importedRow->admission_attempt_id)->exists())->toBeFalse();
});
