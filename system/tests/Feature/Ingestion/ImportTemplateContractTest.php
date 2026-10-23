<?php

use App\Admission\Queries\ListAdmissionAttemptsQuery;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\RevokeTestKitVersion;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Ingestion\Actions\StageImportBatch;
use App\Ingestion\Services\ExternalFinalImportAdapter;
use App\Ingestion\Services\ImportTemplateCatalog;
use App\Ingestion\Services\ResolvedImportContract;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldPolicy;
use App\Models\Ingestion\ImportBatch;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/E2Helpers.php';
require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/ExternalFinalFixtures.php';

uses(RefreshDatabase::class);
function templateContractStaffActor(Institution $institution, AcademicCycle $cycle, string $office, string $capability): User
{
    $actor = User::factory()->create();
    Permission::findOrCreate($capability, 'web');
    $actor->givePermissionTo($capability);
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => $office,
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'two_factor_generation' => (string) Str::uuid(),
    ]);

    return $actor;
}

test('applicant template schema uses default policy without seeding and exports canonical required fields', function (): void {
    $institution = Institution::query()->create([
        'code' => 'TPL-SYNTHETIC',
        'name' => 'Template Synthetic Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'TPL-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    $before = IntakeFieldPolicy::query()->count();
    $schema = app(ImportTemplateCatalog::class)->schema(
        ImportBatch::TYPE_APPLICANTS_ONLY,
        $institution,
        $cycle,
        null,
    );

    expect(IntakeFieldPolicy::query()->count())->toBe($before)
        ->and($schema['type'])->toBe(ImportBatch::TYPE_APPLICANTS_ONLY)
        ->and($schema['academic_cycle'])->toBe(['id' => $cycle->id, 'label' => $cycle->name])
        ->and($schema['revision'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($schema['default_headers'])->toContain('first_name', 'last_name', 'birthdate', 'sex', 'course_preference_1')
        ->and(collect($schema['fields'])->firstWhere('header', 'first_name')['required'])->toBeTrue()
        ->and($schema['default_headers'])->not->toContain('applicant_number')
        ->and(collect($schema['fields'])->pluck('aliases')->flatten()->map(fn (string $alias): string => strtolower($alias))->all())->not->toContain('lrn', 'learner reference number');
});

test('selected CSV headers preserve selected order and contain no data rows', function (): void {
    $csv = app(ImportTemplateCatalog::class)->csvHeaders(['last_name', 'first_name', 'birthdate']);

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and(substr($csv, 3))->toBe("last_name,first_name,birthdate\n");
});

test('exact kit contracts do not treat unrelated global aptitude areas as writable score fields', function (): void {
    $institution = Institution::query()->create([
        'code' => 'TPL-KIT-SYNTHETIC',
        'name' => 'Template Kit Synthetic Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'TPL-KIT-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    AptitudeArea::query()->create([
        'code' => 'UNRELATED',
        'name' => 'Unrelated global aptitude area',
        'max_items' => 40,
        'scoring_method' => AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
        'is_active' => true,
    ]);
    $kit = new TestKitVersion;
    $kit->forceFill([
        'institution_id' => $institution->id,
        'test_instrument_version_id' => 987654,
        'revoked_at' => null,
    ]);
    $before = IntakeFieldPolicy::query()->count();

    $contract = ResolvedImportContract::compile($institution, $cycle, $kit);

    expect($contract->aptitudeAreaCodeToId)->toBe([])
        ->and($contract->metricCodeToId)->toBe([])
        ->and($contract->unsupportedScoreCodes)->toContain('UNRELATED')
        ->and(IntakeFieldPolicy::query()->count())->toBe($before);
});

test('authorized HTTP schema and selected export enforce current contract without domain writes', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SYN-INST-001',
        'name' => 'Synthetic Template Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'TPL-HTTP-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    $registrar = templateContractStaffActor($institution, $cycle, 'registrar', CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
    $list = app(ListAdmissionAttemptsQuery::class)->handle(
        Request::create('/admissions', 'GET', ['cycle' => $cycle->code]),
        $registrar,
    );
    $cycleOption = collect($list['cycles'])->firstWhere('value', $cycle->code);
    expect($cycleOption['id'])->toBe((int) $cycle->id)
        ->and($list['filters']['cycle'])->toBe($cycle->code);
    $schemaUrl = route('imports.templates.schema', ['type' => ImportBatch::TYPE_APPLICANTS_ONLY, 'academic_cycle_id' => $cycleOption['id']]);
    $writesBefore = ImportBatch::query()->count();

    $schemaResponse = $this->actingAs($registrar)->getJson($schemaUrl)->assertOk()
        ->assertJsonPath('type', ImportBatch::TYPE_APPLICANTS_ONLY);
    $schema = $schemaResponse->json();
    $query = http_build_query([
        'academic_cycle_id' => $cycle->id,
        'revision' => $schema['revision'],
        'headers' => $schema['default_headers'],
    ]);
    $export = $this->get(route('imports.templates', ['type' => ImportBatch::TYPE_APPLICANTS_ONLY]).'?'.$query)->assertOk();
    $csv = substr($export->streamedContent(), 3);

    expect($csv)->toBe(implode(',', $schema['default_headers'])."\n")
        ->and(ImportBatch::query()->count())->toBe($writesBefore)
        ->and(IntakeFieldPolicy::query()->count())->toBe(0);

    $base = route('imports.templates', ['type' => ImportBatch::TYPE_APPLICANTS_ONLY]);
    $requiredHeaders = $schema['default_headers'];
    $this->getJson($base.'?'.http_build_query([
        'academic_cycle_id' => $cycle->id,
        'revision' => $schema['revision'],
        'headers' => ['first_name'],
    ]))->assertUnprocessable()->assertJsonValidationErrors('headers');
    $this->getJson($base.'?'.http_build_query([
        'academic_cycle_id' => $cycle->id,
        'revision' => $schema['revision'],
        'headers' => [...$requiredHeaders, 'unknown_field'],
    ]))->assertUnprocessable()->assertJsonValidationErrors('headers');
    $this->getJson($base.'?'.http_build_query([
        'academic_cycle_id' => $cycle->id,
        'revision' => $schema['revision'],
        'headers' => [...$requiredHeaders, $requiredHeaders[0]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('headers');
    $this->getJson($base.'?'.http_build_query([
        'academic_cycle_id' => $cycle->id,
        'revision' => 'stale-contract',
        'headers' => $requiredHeaders,
    ]))->assertStatus(409)->assertJsonValidationErrors('revision');
    $this->getJson($base.'?'.http_build_query([
        'academic_cycle_id' => 999999,
        'revision' => $schema['revision'],
        'headers' => $requiredHeaders,
    ]))->assertUnprocessable()->assertJsonValidationErrors('academic_cycle_id');
    foreach (['1.5', '1e3', str_repeat('9', 30), ['1']] as $invalidId) {
        $this->getJson(route('imports.templates.schema', [
            'type' => ImportBatch::TYPE_APPLICANTS_ONLY,
            'academic_cycle_id' => $invalidId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('academic_cycle_id');
    }
    foreach ([
        ['middle_name', 'institution_required'],
        ['suffix', 'disabled'],
    ] as [$field, $requirement]) {
        IntakeFieldPolicy::query()->create([
            'institution_id' => $institution->id,
            'academic_cycle_id' => $cycle->id,
            'version' => 2,
            'field_key' => $field,
            'field_group' => 'identity',
            'label' => $field,
            'requirement_level' => $requirement,
            'is_active' => $requirement !== 'disabled',
            'sort_order' => 10,
        ]);
    }
    $revised = $this->getJson($schemaUrl)->assertOk()->json();
    expect(collect($revised['fields'])->firstWhere('header', 'middle_name')['required'])->toBeTrue()
        ->and(array_column($revised['fields'], 'header'))->not->toContain('suffix');
    $this->getJson($base.'?'.$query)->assertStatus(409)->assertJsonValidationErrors('revision');
});

test('template HTTP endpoints deny capabilities outside their office and type', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SYN-INST-001',
        'name' => 'Synthetic Template Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'TPL-DENY-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    $registrar = templateContractStaffActor($institution, $cycle, 'registrar', CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
    $scoreSchema = route('imports.templates.schema', [
        'type' => ImportBatch::TYPE_SCORES_ONLY,
        'academic_cycle_id' => $cycle->id,
        'test_kit_version_id' => 1,
    ]);

    $this->actingAs($registrar)->getJson($scoreSchema)->assertForbidden();
});

test('score and combined HTTP schemas use only the selected kits direct importable raw metrics', function (): void {
    seedE2Foundation();
    $institution = Institution::current();
    $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidance->staffProfile()->firstOrFail()->forceFill(['active_cycle_code' => $cycle->code])->save();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_IMPORT, 'web');
    $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_IMPORT);

    $fixture = testKitAcceptanceFixture($guidance, $institution, assemble: false, scenario: 'optional-attachment');
    $validator = app(TestInstrumentProfileValidator::class);
    $payload = $validator->normalizeVersion($fixture['profile']);
    $secondMetric = $payload['metrics'][0];
    $secondMetric['code'] = 'RAW_SECOND';
    $secondMetric['construct_aptitude_area_id'] = null;
    $payload['metrics'][] = $secondMetric;
    $derivedMetric = $secondMetric;
    $derivedMetric['code'] = 'DERIVED_TOTAL';
    $derivedMetric['rule_kind'] = TestInstrumentMetric::RULE_SUM_RAW_METRICS;
    $derivedMetric['subtest_code'] = null;
    $derivedMetric['min_raw_value'] = null;
    $derivedMetric['max_raw_value'] = null;
    $derivedMetric['input_codes'] = ['RAW_COUNT', 'RAW_SECOND'];
    $payload['metrics'][] = $derivedMetric;
    $draft = new TestInstrumentVersion([
        'test_instrument_id' => $fixture['profile']->test_instrument_id,
        'version_number' => 2,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'lock_version' => 1,
        'created_by_user_id' => $guidance->id,
    ]);
    app(TestInstrumentDraftWriter::class)->save($draft, $validator->normalize($payload));
    $fixture['profile'] = app(ApproveTestInstrumentVersion::class)->handle(
        $guidance, (int) $draft->test_instrument_id, (int) $draft->id,
        (int) $draft->lock_version, (string) Str::uuid(), sourceReviewAttestation: true, isDemo: true,
    );
    $setupOnly = testKitSetupOnlyFixture($guidance, $fixture['profile']);
    $kit = $setupOnly['version'];
    $kit->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $guidance->id,
    ])->save();

    $scoresUrl = route('imports.templates.schema', [
        'type' => ImportBatch::TYPE_SCORES_ONLY,
        'academic_cycle_id' => $cycle->id,
        'test_kit_version_id' => $kit->id,
    ]);
    $combinedUrl = route('imports.templates.schema', [
        'type' => ImportBatch::TYPE_ALL_IN_ONE,
        'academic_cycle_id' => $cycle->id,
        'test_kit_version_id' => $kit->id,
    ]);
    $scores = $this->actingAs($guidance)->getJson($scoresUrl)->assertOk()->json();
    $combined = $this->getJson($combinedUrl)->assertOk()->json();

    expect(array_column($scores['fields'], 'header'))->toContain('applicant_number', 'RAW_COUNT')
        ->not->toContain('DERIVED_TOTAL')
        ->and($scores['default_headers'])->toContain('applicant_number', 'RAW_COUNT')
        ->and(array_column($combined['fields'], 'header'))->toContain('first_name', 'RAW_COUNT')
        ->not->toContain('DERIVED_TOTAL')
        ->and($combined['default_headers'])->toContain('first_name', 'RAW_COUNT')
        ->and($combined['default_headers'])->not->toContain('applicant_number');
    expect(collect($scores['fields'])->firstWhere('header', 'RAW_COUNT')['precision_scale'])->toBe(0);
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'test_kit_version_id' => $kit->id,
        'test_instrument_version_id' => $fixture['profile']->id,
        'code' => 'SYN-TEMPLATE-SESSION',
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'version' => 1,
    ]);
    $values = array_column($combined['fields'], 'example', 'header');
    $stream = fopen('php://temp', 'w+b');
    fputcsv($stream, array_keys($values));
    fputcsv($stream, array_values($values));
    rewind($stream);
    $combinedFile = UploadedFile::fake()->createWithContent('combined.csv', stream_get_contents($stream));
    fclose($stream);
    $this->postJson(route('imports.analyze'), [
        'file' => $combinedFile,
        'academic_cycle_id' => $cycle->id,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'test_kit_version_id' => $kit->id,
    ])->assertOk()->assertJsonPath('suggested_import_type', ImportBatch::TYPE_ALL_IN_ONE)
        ->assertJsonPath('sheets.0.has_scores', true);
    foreach ([
        ['applicant_number,RAW_COUNT,LOGIC', 'AY26-00001,0,1', false, 'LOGIC'],
        ['applicant_number,RAW_COUNT', 'AY26-00001,0.5', false, 'precision'],
        ['applicant_number,RAW_COUNT,external_sheet_row', 'AY26-00001,0,24', true, null],
    ] as $index => [$headers, $row, $valid, $error]) {
        $batch = app(StageImportBatch::class)->handle(
            UploadedFile::fake()->createWithContent("scores-{$index}.csv", $headers."\n".$row."\n"),
            $institution, $cycle, ImportBatch::TYPE_SCORES_ONLY, $guidance,
            targetExamSession: $session, targetKitVersion: $kit,
        );
        expect($batch->valid_rows)->toBe($valid ? 1 : 0);
        if ($error !== null) {
            expect(implode(' ', $batch->rows()->firstOrFail()->errors ?? []))->toContain($error);
        }
    }
    $kit = app(RevokeTestKitVersion::class)->handle(
        $guidance, (int) $kit->test_kit_id, (int) $kit->id,
        (int) $kit->revision, 'Synthetic history-read regression', (string) Str::uuid(),
    );
    expect(ResolvedImportContract::compile($institution, $cycle, $kit)->metricCodeToId)->toHaveKey('RAW_COUNT');
});

test('final-only templates and score staging require exact percentile and per-metric source columns', function (): void {
    seedE2Foundation();
    $institution = Institution::current();
    $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
    $guidance = templateContractStaffActor($institution, $cycle, 'guidance', CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
    $profile = createExternalFinalTestProfile($guidance, externalFinalConstructIds(), 'IMPORT_FINALS');
    $kit = testKitSetupOnlyFixture($guidance, $profile)['version'];
    $kit->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $guidance->id,
    ])->save();
    $schema = app(ImportTemplateCatalog::class)->schema(ImportBatch::TYPE_SCORES_ONLY, $institution, $cycle, $kit);
    $headers = array_column($schema['fields'], 'header');

    expect($schema['is_final_only'])->toBeTrue()
        ->and($schema['default_headers'])->toContain('FINAL_1', 'FINAL_1_source_reference')
        ->and($headers)->toContain('FINAL_6', 'FINAL_6_source_reference')
        ->and(collect($schema['fields'])->firstWhere('header', 'FINAL_1')['min'])->toBe(0)
        ->and(collect($schema['fields'])->firstWhere('header', 'FINAL_1')['max'])->toBe(100)
        ->and(collect($schema['fields'])->firstWhere('header', 'FINAL_1_source_reference'))->not->toHaveKey('min');

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'test_kit_version_id' => $kit->id,
        'test_instrument_version_id' => $profile->id,
        'code' => 'SYN-FINAL-IMPORT',
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'version' => 1,
    ]);
    $scoreHeaders = [];
    $row = ['AY26-00001'];
    foreach (range(1, 6) as $index) {
        $code = 'FINAL_'.$index;
        $scoreHeaders[] = $code;
        $scoreHeaders[] = $code.'_source_reference';
        $row[] = $index === 1 ? '0' : '85.25';
        $row[] = 'external-report-'.$index;
    }
    $analysisFile = UploadedFile::fake()->createWithContent(
        'final-analysis.csv',
        implode(',', ['applicant_number', ...$scoreHeaders])."\n".implode(',', $row)."\n",
    );
    $this->actingAs($guidance)->postJson(route('imports.analyze'), [
        'file' => $analysisFile,
        'academic_cycle_id' => $cycle->id,
        'test_kit_version_id' => $kit->id,
        'target_exam_session_id' => $session->id,
        'import_type' => ImportBatch::TYPE_SCORES_ONLY,
    ])->assertOk()
        ->assertJsonPath('assessment_contract.input_kind', 'external_final')
        ->assertJsonPath('assessment_contract.output_scale', 'percentile_rank')
        ->assertJsonPath('assessment_contract.source_header_map.FINAL_1', 'FINAL_1_source_reference');

    $validBatch = app(StageImportBatch::class)->handle(
        UploadedFile::fake()->createWithContent('final-score-only.csv', implode(',', ['applicant_number', ...$scoreHeaders])."\n".implode(',', $row)."\n"),
        $institution,
        $cycle,
        ImportBatch::TYPE_SCORES_ONLY,
        $guidance,
        targetExamSession: $session,
        targetKitVersion: $kit,
    );
    $selection = $validBatch->topology_meta['assessment_selection'];
    expect($validBatch->valid_rows)->toBe(1)
        ->and($selection['input_kind'])->toBe('external_final')
        ->and($selection['scoring_mode'])->toBe('manual_converted')
        ->and($selection['output_scale'])->toBe('percentile_rank')
        ->and((int) $selection['profile_version_id'])->toBe((int) $profile->id);
    $typedEntries = app(ExternalFinalImportAdapter::class)->entries(
        $validBatch->rows()->firstOrFail()->normalized_payload ?? [],
        $profile,
        $selection,
        (int) $kit->id,
    );
    expect($typedEntries)->toHaveCount(6)
        ->and($typedEntries[0]['typed_outputs'])->toBe(['percentile_rank' => '0'])
        ->and($typedEntries[0]['external_provenance'])->toBe('external-report-1');
    expect(fn () => app(StageImportBatch::class)->handle(
        UploadedFile::fake()->createWithContent('final-with-norm.csv', implode(',', ['applicant_number', ...$scoreHeaders])."\n".implode(',', $row)."\n"),
        $institution,
        $cycle,
        ImportBatch::TYPE_SCORES_ONLY,
        $guidance,
        targetExamSession: $session,
        targetKitVersion: $kit,
        normPackageVersion: new AssessmentNormPackageVersion,
    ))->toThrow(InvalidArgumentException::class);

    $missingSourceRow = $row;
    unset($missingSourceRow[2]);
    array_splice($missingSourceRow, 2, 0, ['']);
    $missingSource = app(StageImportBatch::class)->handle(
        UploadedFile::fake()->createWithContent('final-missing-source.csv', implode(',', ['applicant_number', ...$scoreHeaders])."\n".implode(',', $missingSourceRow)."\n"),
        $institution,
        $cycle,
        ImportBatch::TYPE_SCORES_ONLY,
        $guidance,
        targetExamSession: $session,
        targetKitVersion: $kit,
    );
    expect($missingSource->valid_rows)->toBe(0)
        ->and(implode(' ', $missingSource->rows()->firstOrFail()->errors ?? []))->toContain('source reference');

    foreach ([
        ['final-over-range.csv', 1, '100.01', 'from 0 to 100'],
        ['final-over-precision.csv', 3, '85.251', 'precision'],
    ] as [$fileName, $valueIndex, $value, $message]) {
        $invalidRow = $row;
        $invalidRow[$valueIndex] = $value;
        $invalidBatch = app(StageImportBatch::class)->handle(
            UploadedFile::fake()->createWithContent($fileName, implode(',', ['applicant_number', ...$scoreHeaders])."\n".implode(',', $invalidRow)."\n"),
            $institution,
            $cycle,
            ImportBatch::TYPE_SCORES_ONLY,
            $guidance,
            targetExamSession: $session,
            targetKitVersion: $kit,
        );
        expect($invalidBatch->valid_rows)->toBe(0)
            ->and(implode(' ', $invalidBatch->rows()->firstOrFail()->errors ?? []))->toContain($message);
    }
});
