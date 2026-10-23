<?php

declare(strict_types=1);

use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/TestKitPostgresSchemaFixtures.php';
require_once __DIR__.'/../../Support/TestKitHelpers.php';

function testKitPgRejects(Closure $write, string $message, ?string $triggerMessage = null, ?Closure $assertUnchanged = null): void
{
    static $savepoint = 0;
    $name = 'test_kit_pg_contract_'.(++$savepoint);
    DB::statement('SAVEPOINT '.$name);
    try {
        $write();
    } catch (Throwable $exception) {
        DB::statement('ROLLBACK TO SAVEPOINT '.$name);
        DB::statement('RELEASE SAVEPOINT '.$name);

        $errorInfo = match (true) {
            $exception instanceof QueryException => $exception->errorInfo,
            $exception instanceof PDOException => $exception->errorInfo,
            $exception->getPrevious() instanceof PDOException => $exception->getPrevious()->errorInfo,
            default => null,
        };

        $sqlState = is_array($errorInfo)
            ? (string) ($errorInfo[0] ?? '')
            : (is_string($exception->getCode()) ? $exception->getCode() : '');

        $databaseMessage = is_array($errorInfo)
            ? (string) ($errorInfo[2] ?? '')
            : $exception->getMessage();

        if (str_starts_with($sqlState, '23') || str_starts_with($sqlState, '55')) {
            if ($assertUnchanged !== null) {
                $assertUnchanged();
            }

            return;
        }

        if ($sqlState === 'P0001' || ($triggerMessage !== null && str_contains($databaseMessage, $triggerMessage))) {
            if ($triggerMessage !== null && ! str_contains($databaseMessage, $triggerMessage)) {
                throw $exception;
            }

            if ($assertUnchanged !== null) {
                $assertUnchanged();
            }

            return;
        }

        throw $exception;
    }

    DB::statement('ROLLBACK TO SAVEPOINT '.$name);
    DB::statement('RELEASE SAVEPOINT '.$name);

    throw new RuntimeException($message);
}

function testKitPgSessionFixture(Institution $institution): int
{
    $now = now();
    $cycleId = DB::table('academic_cycles')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'PG-CONTRACT-'.Str::upper(Str::random(8)),
        'name' => 'Synthetic contract cycle',
        'opens_on' => $now->toDateString(),
        'closes_on' => $now->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $roomId = DB::table('rooms')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'PG-'.Str::upper(Str::random(8)),
        'name' => 'Synthetic contract room',
        'capacity' => 20,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('exam_sessions')->insertGetId([
        'academic_cycle_id' => $cycleId,
        'room_id' => $roomId,
        'code' => 'PG-'.Str::upper(Str::random(8)),
        'status' => 'draft',
        'starts_at' => now()->addDay(),
        'effective_end_at' => now()->addDay()->addHours(2),
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** @return array{planId:int,runId:int,receiptId:int} */
function testKitPgFailedReceipt(array $fixture, User $actor, int $cycleId): array
{
    $version = $fixture['version']->fresh();
    $now = now();
    $protocolSchema = 'test-kit-acceptance-protocol-v1';
    $protocolHash = hash('sha256', $protocolSchema);
    $planSchema = 'test-kit-acceptance-plan-v1';
    $planHash = hash('sha256', $planSchema.'|'.$version->manifest_sha256);
    $truthReference = 'synthetic-test-only://literal-response-fixture';
    $reviewPath = base_path('tests/Fixtures/TestKit/two-item-count-v1.json');
    $reviewHash = hash_file('sha256', $reviewPath);
    if (! is_string($reviewHash)) {
        throw new RuntimeException('The local synthetic Test Kit plan fixture is missing.');
    }

    $planId = (int) DB::table('test_kit_acceptance_plans')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'protocol_schema_version' => $protocolSchema,
        'protocol_sha256' => $protocolHash,
        'plan_schema_version' => $planSchema,
        'plan_sha256' => $planHash,
        'plan_payload' => 'encrypted synthetic schema-only test plan',
        'source_key' => 'synthetic-pg-contract-'.Str::lower(Str::random(8)),
        'authored_source_sha256' => hash('sha256', 'synthetic-test-source'),
        'author_reference' => 'Local synthetic fixture; no institutional author or source asserted.',
        'truth_source_references_json' => json_encode([$truthReference], JSON_THROW_ON_ERROR),
        'review_evidence_reference' => 'repository:tests/Fixtures/TestKit/two-item-count-v1.json',
        'review_evidence_sha256' => $reviewHash,
        'provenance_kind' => 'synthetic_software_fixture',
        'created_by_user_id' => $actor->id,
        'created_at' => $now,
    ]);
    $runId = (int) DB::table('omr_diagnostic_runs')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => 'diagnostic',
        'institution_id' => $version->institution_id,
        'academic_cycle_id' => $cycleId,
        'template_version_id' => $version->template_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'declared_set_code' => 'A',
        'status' => 'failed',
        'phase' => 'complete',
        'revision' => 2,
        'created_by_user_id' => $actor->id,
        'completed_at' => $now,
        'test_kit_id' => $version->test_kit_id,
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $planId,
        'booklet_set_id' => $version->booklet_set_id,
        'manifest_sha256' => $version->manifest_sha256,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'profile_content_sha256' => $fixture['profile']->content_sha256,
        'acceptance_protocol_schema_version' => $protocolSchema,
        'acceptance_protocol_sha256' => $protocolHash,
        'acceptance_plan_schema_version' => $planSchema,
        'acceptance_plan_sha256' => $planHash,
        'scoring_kernel_identity' => 'synthetic-fixture-only',
        'expected_worker_identity_json' => json_encode(['fixture' => 'synthetic-only'], JSON_THROW_ON_ERROR),
        'acceptance_plan_json' => 'encrypted synthetic schema-only test plan',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $receiptId = (int) DB::table('test_kit_acceptance_receipts')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'diagnostic_run_id' => $runId,
        'acceptance_plan_id' => $planId,
        'template_version_id' => $version->template_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'booklet_set_code' => 'A',
        'run_revision' => 2,
        'evidence_revision_vector_json' => json_encode([], JSON_THROW_ON_ERROR),
        'evidence_revision_vector_sha256' => hash('sha256', '[]'),
        'acceptance_protocol_schema_version' => $protocolSchema,
        'acceptance_protocol_sha256' => $protocolHash,
        'acceptance_plan_schema_version' => $planSchema,
        'acceptance_plan_sha256' => $planHash,
        'scoring_kernel_identity' => 'synthetic-fixture-only',
        'worker_commit' => 'synthetic-test-only',
        'manifest_sha256' => $version->manifest_sha256,
        'result' => 'failed',
        'check_report_json' => json_encode(['checks' => [['name' => 'schema fixture', 'passed' => false]]], JSON_THROW_ON_ERROR),
        'confidential_evidence_references' => 'synthetic-test-only://no-institutional-evidence',
        'created_by_user_id' => $actor->id,
        'created_at' => $now,
    ]);

    return compact('planId', 'runId', 'receiptId');
}

beforeEach(function (): void {
    $this->testKitPostgresTransactionStarted = false;

    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Test-kit PostgreSQL contracts require the isolated PostgreSQL test-kit configuration.');
    }

    expect((string) DB::scalar('SELECT current_database()'))
        ->toMatch('/^nexiam_test_kit_acceptance_[a-z0-9_]+$/');

    DB::beginTransaction();
    $this->testKitPostgresTransactionStarted = true;
    $this->institution = Institution::query()->create([
        'code' => 'SYN-INST-'.Str::upper(Str::random(8)),
        'name' => 'Synthetic PostgreSQL schema fixture',
    ]);
    $this->actor = User::factory()->create();
    $this->fixture = testKitPgSchemaFixture($this->actor, $this->institution);
});

afterEach(function (): void {
    if (! $this->testKitPostgresTransactionStarted) {
        return;
    }

    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

test('PostgreSQL schema-three versions reject undeclared profile requirements instead of accepting a no-paper shortcut', function (): void {
    $row = (array) DB::table('test_kit_versions')->where('id', $this->fixture['version']->id)->first();
    unset($row['id']);
    $row['version_number'] = 2;
    $row['predecessor_version_id'] = $this->fixture['version']->id;
    $row['template_id'] = null;
    $row['booklet_id'] = null;
    $row['template_version_id'] = null;
    $row['booklet_version_id'] = null;
    $row['booklet_set_id'] = null;
    $row['booklet_set_code'] = null;
    $row['booklet_set_order_hash'] = null;
    $row['booklet_set_answer_key_hash'] = null;
    $row['manifest_schema_version'] = 3;
    $row['manifest_json'] = json_encode([
        'requirements' => [
            'schema_version' => 1,
            'metrics' => [],
            'required_resources' => [],
            'omr_applicable' => false,
        ],
    ], JSON_THROW_ON_ERROR);
    $row['manifest_sha256'] = testKitPgSchemaHash(json_decode($row['manifest_json'], true, 512, JSON_THROW_ON_ERROR));

    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->insert($row),
        'Expected PostgreSQL to reject conditional composition whose profile metrics have no approved input declarations.',
        'schema-3 composition requires declared input contracts for every profile metric',
    );
});

test('PostgreSQL accepts a no-paper schema-three manual composition with the exact declared metric contract', function (): void {
    $suffix = Str::upper(Str::random(8));
    $timestamps = ['created_at' => now(), 'updated_at' => now()];
    $instrumentId = DB::table('test_instruments')->insertGetId([
        'institution_id' => $this->fixture['institution']->id,
        'code' => 'TI-SYN-'.$suffix,
        'is_synthetic' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $baseProfile = (array) DB::table('test_instrument_versions')->where('id', $this->fixture['version']->test_instrument_version_id)->first();
    unset($baseProfile['id']);
    $baseProfile['test_instrument_id'] = $instrumentId;
    $baseProfile['version_number'] = 2;
    $baseProfile['status'] = 'draft';
    $baseProfile['material_requirement'] = 'no_paper';
    $baseProfile['form_code'] = 'NO-PAPER-'.$suffix;
    $baseProfile['display_name'] = 'Synthetic no-paper schema test '.$suffix;
    $baseProfile['content_sha256'] = null;
    $baseProfile['source_reviewed_by_user_id'] = null;
    $baseProfile['source_reviewed_at'] = null;
    $baseProfile['approved_by_user_id'] = null;
    $baseProfile['approved_at'] = null;
    $profileId = (int) DB::table('test_instrument_versions')->insertGetId($baseProfile);

    $baseSubtest = (array) DB::table('test_instrument_subtests')
        ->where('test_instrument_version_id', $this->fixture['version']->test_instrument_version_id)->first();
    unset($baseSubtest['id']);
    $baseSubtest['test_instrument_version_id'] = $profileId;
    $baseSubtest['code'] = 'MANUAL-'.$suffix;
    $subtestId = (int) DB::table('test_instrument_subtests')->insertGetId($baseSubtest);

    $inputContract = [
        'allowed_sources' => ['manual'],
        'required_resources' => [],
        'source_requirements' => [
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
        ],
    ];
    $metric = (array) DB::table('test_instrument_metrics')
        ->where('test_instrument_version_id', $this->fixture['version']->test_instrument_version_id)->first();
    unset($metric['id']);
    $metric['test_instrument_version_id'] = $profileId;
    $metric['test_instrument_subtest_id'] = $subtestId;
    $metric['code'] = 'MANUAL-'.$suffix;
    $metric['input_contract_json'] = json_encode($inputContract, JSON_THROW_ON_ERROR);
    $metricId = (int) DB::table('test_instrument_metrics')->insertGetId($metric);
    $baseItems = DB::table('test_instrument_items')
        ->where('test_instrument_version_id', $this->fixture['version']->test_instrument_version_id)->get();
    foreach ($baseItems as $item) {
        $copy = (array) $item;
        unset($copy['id']);
        $copy['test_instrument_version_id'] = $profileId;
        $copy['test_instrument_subtest_id'] = $subtestId;
        $copy['item_code'] = 'MANUAL-'.$suffix.'-'.$item->item_code;
        DB::table('test_instrument_items')->insert($copy);
    }
    $profileModel = TestInstrumentVersion::query()->findOrFail($profileId);
    $profileValidator = new TestInstrumentProfileValidator;
    $normalizedProfile = $profileValidator->normalizeVersion($profileModel);
    $profileValidator->assertApprovable($normalizedProfile, $normalizedProfile['profile']);
    DB::table('test_instrument_versions')->where('id', $profileId)->update([
        'status' => 'approved',
        'source_reviewed_by_user_id' => $this->actor->id,
        'source_reviewed_at' => now(),
        'approved_by_user_id' => $this->actor->id,
        'approved_at' => now(),
        'content_sha256' => $profileValidator->digest($normalizedProfile),
        'updated_at' => now(),
    ]);

    $requirements = [
        'schema_version' => 1,
        'metrics' => [[
            'metric_id' => $metricId,
            'code' => 'MANUAL-'.$suffix,
            'input_contract_json' => $inputContract,
        ]],
        'required_resources' => [],
        'omr_applicable' => false,
    ];
    $manifest = [
        'institution_id' => (int) $this->institution->id,
        'kit' => ['provenance_class' => 'synthetic'],
        'requirements' => $requirements,
    ];
    $version = (array) DB::table('test_kit_versions')->where('id', $this->fixture['version']->id)->first();
    unset($version['id']);
    $version['version_number'] = 2;
    $version['predecessor_version_id'] = $this->fixture['version']->id;
    $version['test_instrument_version_id'] = $profileId;
    $version['test_instrument_id'] = $instrumentId;
    foreach (['template_id', 'booklet_id', 'template_version_id', 'booklet_version_id', 'booklet_set_id', 'booklet_set_code', 'booklet_set_order_hash', 'booklet_set_answer_key_hash'] as $field) {
        $version[$field] = null;
    }
    $version['manifest_schema_version'] = 3;
    $version['manifest_json'] = json_encode($manifest, JSON_THROW_ON_ERROR);
    $version['manifest_sha256'] = testKitPgSchemaHash($manifest);
    $id = (int) DB::table('test_kit_versions')->insertGetId($version);

    expect(DB::table('test_kit_versions')->where('id', $id)->value('template_version_id'))->toBeNull()
        ->and(DB::table('test_kit_versions')->where('id', $id)->value('booklet_set_id'))->toBeNull()
        ->and(DB::table('test_kit_versions')->where('id', $id)->value('test_instrument_version_id'))->toBe($profileId)
        ->and(DB::table('test_kit_version_composition_rows')->where('test_kit_version_id', $id)->count())->toBe(0)
        ->and(DB::table('test_kit_acceptance_receipts')->where('test_kit_version_id', $id)->count())->toBe(0);

    $sessionId = testKitPgSessionFixture($this->institution);
    DB::table('exam_sessions')->where('id', $sessionId)->update([
        'test_kit_version_id' => $id,
        'test_instrument_version_id' => $profileId,
        'booklet_version_id' => null,
    ]);
    $session = (array) DB::table('exam_sessions')->where('id', $sessionId)->first();
    expect($session['test_kit_version_id'])->toBe($id)
        ->and($session['test_instrument_version_id'])->toBe($profileId)
        ->and($session['booklet_version_id'])->toBeNull();

    unset($session['id']);
    $session['code'] = 'PG-NO-PAPER-'.Str::upper(Str::random(8));
    $insertedSessionId = DB::table('exam_sessions')->insertGetId($session);
    expect(DB::table('exam_sessions')->where('id', $insertedSessionId)->value('test_kit_version_id'))->toBe($id)
        ->and(DB::table('exam_sessions')->where('id', $insertedSessionId)->value('booklet_version_id'))->toBeNull();

    testKitPgRejects(
        fn () => DB::table('exam_sessions')->where('id', $sessionId)->update([
            'booklet_version_id' => $this->fixture['booklet']->id,
        ]),
        'A Setup-only Kit must not accept a Booklet from another configuration.',
        'exam session Test Kit pins must match the exact institution, Booklet, and profile',
    );
    testKitPgRejects(
        fn () => DB::table('exam_sessions')->where('id', $sessionId)->update([
            'test_instrument_version_id' => $this->fixture['profile']->id,
        ]),
        'A Setup-only Kit must retain its exact Test Setup version.',
        'exam session Test Kit pins must match the exact institution, Booklet, and profile',
    );
    $sheetInstrumentId = (int) DB::table('test_instruments')->insertGetId([
        'institution_id' => $this->fixture['institution']->id,
        'code' => 'TI-SHEET-'.$suffix,
        'is_synthetic' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $sheetProfile = $baseProfile;
    $sheetProfile['test_instrument_id'] = $sheetInstrumentId;
    $sheetProfile['version_number'] = 1;
    $sheetProfile['status'] = 'draft';
    $sheetProfile['content_sha256'] = null;
    $sheetProfile['source_reviewed_by_user_id'] = null;
    $sheetProfile['source_reviewed_at'] = null;
    $sheetProfile['approved_by_user_id'] = null;
    $sheetProfile['approved_at'] = null;
    $sheetProfile['material_requirement'] = 'paper_backed';
    $sheetProfile['form_code'] = 'SHEET-'.$suffix;
    $sheetProfile['display_name'] = 'Synthetic sheet-required schema test '.$suffix;
    $sheetProfileId = (int) DB::table('test_instrument_versions')->insertGetId($sheetProfile);
    $sheetSubtest = $baseSubtest;
    $sheetSubtest['test_instrument_version_id'] = $sheetProfileId;
    $sheetSubtest['code'] = 'SHEET-'.$suffix;
    $sheetSubtestId = (int) DB::table('test_instrument_subtests')->insertGetId($sheetSubtest);
    $sheetContract = [
        'allowed_sources' => ['manual'],
        'required_resources' => ['answer_sheet'],
        'source_requirements' => [
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'answer_sheet', 'metric']],
        ],
    ];
    $sheetMetric = $metric;
    unset($sheetMetric['id']);
    $sheetMetric['test_instrument_version_id'] = $sheetProfileId;
    $sheetMetric['test_instrument_subtest_id'] = $sheetSubtestId;
    $sheetMetric['code'] = 'SHEET-'.$suffix;
    $sheetMetric['input_contract_json'] = json_encode($sheetContract, JSON_THROW_ON_ERROR);
    $sheetMetricId = (int) DB::table('test_instrument_metrics')->insertGetId($sheetMetric);
    foreach ($baseItems as $item) {
        $copy = (array) $item;
        unset($copy['id']);
        $copy['test_instrument_version_id'] = $sheetProfileId;
        $copy['test_instrument_subtest_id'] = $sheetSubtestId;
        $copy['item_code'] = 'SHEET-'.$suffix.'-'.$item->item_code;
        DB::table('test_instrument_items')->insert($copy);
    }
    $sheetProfileModel = TestInstrumentVersion::query()->findOrFail($sheetProfileId);
    $normalizedSheetProfile = $profileValidator->normalizeVersion($sheetProfileModel);
    $profileValidator->assertApprovable($normalizedSheetProfile, $normalizedSheetProfile['profile']);
    DB::table('test_instrument_versions')->where('id', $sheetProfileId)->update([
        'status' => 'approved',
        'source_reviewed_by_user_id' => $this->actor->id,
        'source_reviewed_at' => now(),
        'approved_by_user_id' => $this->actor->id,
        'approved_at' => now(),
        'content_sha256' => $profileValidator->digest($normalizedSheetProfile),
        'updated_at' => now(),
    ]);
    $missingSheetManifest = ['requirements' => [
        'schema_version' => 1,
        'metrics' => [[
            'metric_id' => $sheetMetricId,
            'code' => 'SHEET-'.$suffix,
            'input_contract_json' => $sheetContract,
        ]],
        'required_resources' => ['answer_sheet'],
        'omr_applicable' => false,
    ]];
    $missingSheetVersion = $version;
    $missingSheetVersion['version_number'] = 3;
    $missingSheetVersion['predecessor_version_id'] = $id;
    $missingSheetVersion['test_instrument_version_id'] = $sheetProfileId;
    $missingSheetVersion['manifest_json'] = json_encode($missingSheetManifest, JSON_THROW_ON_ERROR);
    $missingSheetVersion['manifest_sha256'] = testKitPgSchemaHash($missingSheetManifest);
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->insert($missingSheetVersion),
        'Expected PostgreSQL to reject an approved profile\'s required Answer Sheet when its exact Sheet pin is absent.',
    );
    $foreignInstrument = testKitPgSchemaFixture($this->actor, $this->institution);
    $foreignOwnerVersion = (array) DB::table('test_kit_versions')->where('id', $id)->first();
    unset($foreignOwnerVersion['id']);
    $foreignOwnerVersion['version_number'] = 3;
    $foreignOwnerVersion['predecessor_version_id'] = $id;
    $foreignOwnerVersion['test_instrument_id'] = $foreignInstrument['version']->test_instrument_id;
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->insert($foreignOwnerVersion),
        'Expected PostgreSQL to reject a schema-three profile paired with a different instrument owner.',
    );
});

test('schema-only fixtures persist actual synthetic material and exact Set A tuples', function (): void {
    $version = $this->fixture['version']->fresh();
    $sheet = DB::table('omr_answer_sheet_template_versions')->where('id', $this->fixture['sheet']->id)->first();
    $booklet = DB::table('omr_question_booklet_versions')->where('id', $this->fixture['booklet']->id)->first();
    expect($version)->toBeInstanceOf(TestKitVersion::class)
        ->and($version->status)->toBe(TestKitVersion::STATUS_CANDIDATE)
        ->and($version->is_synthetic)->toBeTrue()
        ->and($version->selected_acceptance_receipt_id)->toBeNull()
        ->and($version->manifest_json)->toBe($this->fixture['manifest'])
        ->and($version->manifest_sha256)->toBe($this->fixture['manifest_sha256'])
        ->and($version->manifest_sha256)->toBe(testKitPgSchemaHash($version->manifest_json))
        ->and($version->booklet_set_code)->toBe('A')
        ->and($version->booklet_set_order_hash)->toBe($this->fixture['set']->order_hash)
        ->and($version->booklet_set_answer_key_hash)->toBe($this->fixture['set']->answer_key_hash)
        ->and($sheet->status)->toBe('compiled')
        ->and($sheet->test_instrument_version_id)->toBeNull()
        ->and($booklet->template_version_id)->toBeNull()
        ->and($booklet->test_instrument_version_id)->toBeNull()
        ->and($sheet->native_bundle_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($this->fixture['booklet']->status)->toBe('finalized')
        ->and($this->fixture['booklet']->activated_at)->toBeNull()
        ->and(DB::table('omr_answer_sheet_pages')->where('template_version_id', $sheet->id)->count())->toBe(1)
        ->and(DB::table('omr_template_item_mappings')->where('template_version_id', $sheet->id)->count())->toBe(1)
        ->and(DB::table('omr_template_assets')->where('template_version_id', $sheet->id)->count())->toBe(1)
        ->and(DB::table('omr_booklet_set_items')->where('booklet_set_id', $this->fixture['set']->id)->count())->toBe(1);
});

test('PostgreSQL seal triggers deny root descendant update delete reparent and mass writes while permitting proof append', function (): void {
    $sheetId = (int) $this->fixture['sheet']->id;
    $secondarySheetId = (int) $this->fixture['secondary_sheet_id'];
    $mapping = DB::table('omr_template_item_mappings')->where('id', $this->fixture['mapping_id'])->first();
    $secondaryMapping = DB::table('omr_template_item_mappings')->where('id', $this->fixture['secondary_mapping_id'])->first();
    expect($mapping)->not->toBeNull()->and($secondaryMapping)->not->toBeNull();
    $mappingBefore = (array) $mapping;
    $secondaryMappingBefore = (array) $secondaryMapping;

    testKitPgRejects(
        fn () => DB::table('omr_template_item_mappings')->where('id', $mapping->id)->update(['global_position' => 99]),
        'Expected PostgreSQL to reject an update below a compiled Sheet root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_item_mappings')->where('id', $mapping->id)->first())->toBe($mappingBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_item_mappings')->where('template_version_id', $sheetId)->update(['global_position' => DB::raw('global_position + 1')]),
        'Expected PostgreSQL to reject a mass update below a compiled Sheet root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_item_mappings')->where('id', $mapping->id)->first())->toBe($mappingBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_item_mappings')->where('template_version_id', $sheetId)->delete(),
        'Expected PostgreSQL to reject mass deletion below a compiled Sheet root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_item_mappings')->where('id', $mapping->id)->first())->toBe($mappingBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_item_mappings')->where('id', $mapping->id)->update(['template_version_id' => $secondarySheetId]),
        'Expected PostgreSQL to reject reparenting from a sealed old root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_item_mappings')->where('id', $mapping->id)->first())->toBe($mappingBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_item_mappings')->where('id', $secondaryMapping->id)->update(['template_version_id' => $sheetId]),
        'Expected PostgreSQL to reject reparenting into a sealed new root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_item_mappings')->where('id', $secondaryMapping->id)->first())->toBe($secondaryMappingBefore),
    );
    testKitPgRejects(
        function () use ($mapping): void {
            $insert = (array) $mapping;
            unset($insert['id']);
            DB::table('omr_template_item_mappings')->insert($insert);
        },
        'Expected PostgreSQL to reject insertion of a structural descendant into a compiled root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect(DB::table('omr_template_item_mappings')->where('template_version_id', $sheetId)->count())->toBe(1),
    );
    $sourceAssetBefore = (array) DB::table('omr_template_assets')->where('id', $this->fixture['source_asset_id'])->first();

    $sourceAssetId = (int) $this->fixture['source_asset_id'];
    $secondarySourceAssetBefore = (array) DB::table('omr_template_assets')->where('id', $this->fixture['secondary_source_asset_id'])->first();
    testKitPgRejects(
        fn () => DB::table('omr_template_assets')->where('id', $sourceAssetId)->update(['bytes' => 3]),
        'Expected PostgreSQL to reject source-asset mutation below a compiled root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_assets')->where('id', $sourceAssetId)->first())->toBe($sourceAssetBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_assets')->where('template_version_id', $sheetId)->update(['metadata' => json_encode(['changed' => true], JSON_THROW_ON_ERROR)]),
        'Expected PostgreSQL to reject a mass update of Sheet assets below a compiled root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_assets')->where('id', $sourceAssetId)->first())->toBe($sourceAssetBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_assets')->where('template_version_id', $sheetId)->delete(),
        'Expected PostgreSQL to reject mass deletion of Sheet assets below a compiled root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_assets')->where('id', $sourceAssetId)->first())->toBe($sourceAssetBefore),
    );
    testKitPgRejects(
        function () use ($sourceAssetId, $secondarySheetId): void {
            DB::table('omr_template_assets')->where('id', $sourceAssetId)->update(['template_version_id' => $secondarySheetId]);
        },
        'Expected PostgreSQL to reject asset reparenting from a sealed old root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_assets')->where('id', $sourceAssetId)->first())->toBe($sourceAssetBefore),
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_assets')->where('id', $this->fixture['secondary_source_asset_id'])->update(['template_version_id' => $sheetId]),
        'Expected PostgreSQL to reject asset reparenting into a sealed new root.',
        'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented',
        fn () => expect((array) DB::table('omr_template_assets')->where('id', $this->fixture['secondary_source_asset_id'])->first())->toBe($secondarySourceAssetBefore),
    );

    $proofId = (int) DB::table('omr_template_assets')->insertGetId([
        'template_version_id' => $sheetId,
        'kind' => 'evidence',
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => 'test-kit-pg-schema/'.Str::uuid().'.json',
        'mime' => 'application/json',
        'bytes' => 2,
        'sha256' => hash('sha256', '{}'),
        'metadata' => json_encode(['synthetic_schema_fixture' => true], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $proofBefore = (array) DB::table('omr_template_assets')->where('id', $proofId)->first();
    expect(DB::table('omr_template_assets')->where('id', $proofId)->exists())->toBeTrue();
    testKitPgRejects(
        fn () => DB::table('omr_template_assets')->where('id', $proofId)->update(['bytes' => 3]),
        'Expected PostgreSQL to reject mutation of append-only proof evidence.',
    );
    testKitPgRejects(
        fn () => DB::table('omr_template_assets')->where('id', $proofId)->delete(),
        'Expected PostgreSQL to reject deletion of append-only proof evidence.',
    );

    $setItemId = (int) $this->fixture['set_item_id'];
    testKitPgRejects(
        fn () => DB::table('omr_booklet_set_items')->where('id', $setItemId)->update(['global_position' => 99]),
        'Expected PostgreSQL to reject finalized Set A key mutation.',
    );
    testKitPgRejects(
        fn () => DB::table('omr_booklet_set_items')->where('booklet_set_id', $this->fixture['set']->id)->delete(),
        'Expected PostgreSQL to reject mass deletion of finalized Set A keys.',
    );
});

test('PostgreSQL owner and exact material constraints reject partial and cross-version tuples', function (): void {
    $version = $this->fixture['version'];
    $other = testKitPgSchemaFixture($this->actor, $this->institution);

    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update(['is_synthetic' => false]),
        'Expected PostgreSQL to reject a changed family provenance tuple.',
    );
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update(['template_id' => $other['template_id']]),
        'Expected PostgreSQL to reject an Answer Sheet version paired with a different component root.',
    );
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update(['booklet_id' => $other['booklet_id']]),
        'Expected PostgreSQL to reject a Booklet version paired with a different component root.',
    );
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update(['template_version_id' => $other['sheet']->id]),
        'Expected PostgreSQL to reject a cross-version Answer Sheet pin.',
    );
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update(['booklet_version_id' => $other['booklet']->id]),
        'Expected PostgreSQL to reject a cross-version Booklet pin.',
    );
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update(['booklet_set_id' => $other['set']->id]),
        'Expected PostgreSQL to reject a cross-receipt Set A identity.',
    );
    $insertVersion = (array) DB::table('test_kit_versions')->where('id', $version->id)->first();
    unset($insertVersion['id']);
    $insertVersion['version_number'] = 2;
    testKitPgRejects(
        function () use ($insertVersion, $other): void {
            $row = $insertVersion;
            $row['booklet_version_id'] = $other['booklet']->id;
            DB::table('test_kit_versions')->insert($row);
        },
        'Expected PostgreSQL to reject a candidate whose Set A belongs to another Booklet version.',
    );
    testKitPgRejects(
        function () use ($insertVersion, $other): void {
            $row = $insertVersion;
            $row['booklet_set_id'] = $other['set']->id;
            DB::table('test_kit_versions')->insert($row);
        },
        'Expected PostgreSQL to reject a cross-version Set A tuple.',
    );

    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->where('id', $version->id)->update([
            'manifest_sha256' => hash('sha256', 'forged-manifest'),
        ]),
        'Expected PostgreSQL to reject an altered material manifest digest.',
    );

    $partial = (array) DB::table('test_kit_versions')->where('id', $version->id)->first();
    unset($partial['id']);
    $partial['version_number'] = (int) DB::table('test_kit_versions')->where('test_kit_id', $version->test_kit_id)->max('version_number') + 1;
    $partial['institution_id'] = null;
    testKitPgRejects(
        fn () => DB::table('test_kit_versions')->insert($partial),
        'Expected PostgreSQL to reject a new version missing its owner provenance tuple.',
    );
});
test('PostgreSQL rejects partial and cross-pinned session snapshot and administration tuples', function (): void {
    $other = testKitPgSchemaFixture($this->actor, $this->institution);
    $version = $this->fixture['version'];
    $sessionId = testKitPgSessionFixture($this->institution);
    $session = DB::table('exam_sessions')->where('id', $sessionId)->first();

    testKitPgRejects(
        fn () => DB::table('exam_sessions')->where('id', $sessionId)->update([
            'test_kit_version_id' => $version->id,
        ]),
        'Expected PostgreSQL to reject a Test Kit session pin without matching Booklet and profile pins.',
    );
    testKitPgRejects(
        fn () => DB::table('exam_sessions')->where('id', $sessionId)->update([
            'test_kit_version_id' => $version->id,
            'booklet_version_id' => $this->fixture['booklet']->id,
            'test_instrument_version_id' => $other['profile']->id,
        ]),
        'Expected PostgreSQL to reject a cross-version session profile tuple.',
    );

    $crossSessionId = testKitPgSessionFixture($this->institution);
    $cycleId = (int) $session->academic_cycle_id;
    $primaryReceipt = testKitPgFailedReceipt($this->fixture, $this->actor, $cycleId);
    $otherReceipt = testKitPgFailedReceipt($other, $this->actor, $cycleId);

    testKitPgRejects(
        fn () => DB::table('exam_session_material_snapshots')->insert([
            'exam_session_id' => $sessionId,
            'template_version_id' => $this->fixture['sheet']->id,
            'booklet_version_id' => $this->fixture['booklet']->id,
            'test_instrument_version_id' => $this->fixture['profile']->id,
            'scoring_policy_identity' => 'synthetic-contract-only',
            'material_hash' => hash('sha256', 'snapshot-fixture'),
            'published_at' => now(),
            'test_kit_version_id' => $version->id,
            'manifest_sha256' => null,
            'acceptance_receipt_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'Expected PostgreSQL to reject an incomplete snapshot material/receipt tuple.',
    );
    $snapshotId = (int) DB::table('exam_session_material_snapshots')->insertGetId([
        'exam_session_id' => $sessionId,
        'template_version_id' => $this->fixture['sheet']->id,
        'booklet_version_id' => $this->fixture['booklet']->id,
        'test_instrument_version_id' => $this->fixture['profile']->id,
        'scoring_policy_identity' => 'synthetic-contract-only',
        'material_hash' => hash('sha256', 'synthetic-snapshot-fixture'),
        'published_by_user_id' => $this->actor->id,
        'published_at' => now(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $primaryReceipt['receiptId'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    testKitPgRejects(
        fn () => DB::table('exam_session_material_snapshots')->insert([
            'exam_session_id' => $crossSessionId,
            'template_version_id' => $this->fixture['sheet']->id,
            'booklet_version_id' => $this->fixture['booklet']->id,
            'test_instrument_version_id' => $this->fixture['profile']->id,
            'scoring_policy_identity' => 'synthetic-contract-only',
            'material_hash' => hash('sha256', 'cross-receipt-snapshot'),
            'published_at' => now(),
            'test_kit_version_id' => $version->id,
            'manifest_sha256' => $version->manifest_sha256,
            'acceptance_receipt_id' => $otherReceipt['receiptId'],
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'Expected PostgreSQL to reject a snapshot pointing to another version receipt.',
    );
    $identityId = DB::table('applicant_identities')->insertGetId([
        'institution_id' => $this->institution->id,
        'normalized_key' => 'PG-'.Str::upper(Str::random(12)),
        'first_name' => 'Synthetic',
        'last_name' => 'Applicant',
        'birthdate' => '2000-01-01',
        'sex' => 'unspecified',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $attemptId = DB::table('admission_attempts')->insertGetId([
        'applicant_identity_id' => $identityId,
        'institution_id' => $this->institution->id,
        'academic_cycle_id' => $cycleId,
        'reference_code' => 'PG-'.Str::upper(Str::random(12)),
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $assignmentId = DB::table('exam_assignments')->insertGetId([
        'exam_session_id' => $sessionId,
        'admission_attempt_id' => $attemptId,
        'status' => 'assigned',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    testKitPgRejects(
        fn () => DB::table('assessment_administrations')->insert([
            'exam_assignment_id' => $assignmentId,
            'admission_attempt_id' => $attemptId,
            'binding_kind' => 'synthetic_contract',
            'binding_revision' => 1,
            'test_instrument_version_id' => $this->fixture['profile']->id,
            'required_metric_set' => json_encode([], JSON_THROW_ON_ERROR),
            'binding_state' => 'ready',
            'bound_at' => now(),
            'test_kit_version_id' => $version->id,
            'manifest_sha256' => null,
            'acceptance_receipt_id' => null,
            'material_snapshot_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'Expected PostgreSQL to reject an incomplete assessment-administration Test Kit tuple.',
    );
    testKitPgRejects(
        fn () => DB::table('assessment_administrations')->insert([
            'exam_assignment_id' => $assignmentId,
            'admission_attempt_id' => $attemptId,
            'binding_kind' => 'synthetic_contract',
            'binding_revision' => 1,
            'test_instrument_version_id' => $this->fixture['profile']->id,
            'required_metric_set' => json_encode([], JSON_THROW_ON_ERROR),
            'binding_state' => 'ready',
            'bound_at' => now(),
            'test_kit_version_id' => $version->id,
            'manifest_sha256' => $version->manifest_sha256,
            'acceptance_receipt_id' => $otherReceipt['receiptId'],
            'material_snapshot_id' => $snapshotId,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'Expected PostgreSQL to reject an administration crossing the snapshot receipt identity.',
    );
});
test('PostgreSQL failed receipts remain append-only and reject cross-version material and run-plan tuples', function (): void {
    $other = testKitPgSchemaFixture($this->actor, $this->institution);
    $sessionId = testKitPgSessionFixture($this->institution);
    $cycleId = (int) DB::table('exam_sessions')->where('id', $sessionId)->value('academic_cycle_id');
    $receiptFixture = testKitPgFailedReceipt($this->fixture, $this->actor, $cycleId);
    $receipt = DB::table('test_kit_acceptance_receipts')->where('id', $receiptFixture['receiptId'])->first();

    expect($receipt->result)->toBe('failed')
        ->and($receipt->test_kit_version_id)->toBe($this->fixture['version']->id)
        ->and($receipt->manifest_sha256)->toBe($this->fixture['version']->manifest_sha256);

    testKitPgRejects(
        fn () => DB::table('test_kit_acceptance_receipts')->where('id', $receipt->id)->update(['result' => 'passed']),
        'Expected PostgreSQL to reject mutation of the failed acceptance receipt.',
    );
    testKitPgRejects(
        fn () => DB::table('test_kit_acceptance_receipts')->where('id', $receipt->id)->delete(),
        'Expected PostgreSQL to reject deletion of the failed acceptance receipt.',
    );
    testKitPgRejects(
        function () use ($receipt, $other): void {
            $crossVersion = (array) $receipt;
            unset($crossVersion['id']);
            $crossVersion['public_id'] = (string) Str::uuid();
            $crossVersion['test_kit_version_id'] = $other['version']->id;
            DB::table('test_kit_acceptance_receipts')->insert($crossVersion);
        },
        'Expected PostgreSQL to reject a receipt whose exact run/material version differs from its kit version.',
    );
    testKitPgRejects(
        function () use ($receipt, $other): void {
            $crossManifest = (array) $receipt;
            unset($crossManifest['id']);
            $crossManifest['public_id'] = (string) Str::uuid();
            $crossManifest['manifest_sha256'] = $other['version']->manifest_sha256;
            DB::table('test_kit_acceptance_receipts')->insert($crossManifest);
        },
        'Expected PostgreSQL to reject a receipt that crosses the immutable material manifest.',
    );
});
test('PostgreSQL operation pins are all-or-none, source-bound, and immutable', function (): void {
    $this->actor->staffProfile()->create([
        'institution_id' => $this->institution->id, 'office' => 'guidance', 'status' => 'active',
    ]);
    foreach ([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_SCORING_OMR,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
        $this->actor->givePermissionTo($capability);
    }
    $this->fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $other = testKitPgSchemaFixture($this->actor, $this->institution);
    $sessionId = testKitPgSessionFixture($this->institution);
    $cycleId = (int) DB::table('exam_sessions')->where('id', $sessionId)->value('academic_cycle_id');
    $receiptFixture = testKitPgFailedReceipt($this->fixture, $this->actor, $cycleId);
    $receipt = DB::table('test_kit_acceptance_receipts')->find($receiptFixture['receiptId']);
    $version = $this->fixture['version'];

    expect($receipt->result)->toBe('failed');

    $identityId = DB::table('applicant_identities')->insertGetId([
        'institution_id' => $this->institution->id,
        'normalized_key' => 'PG-'.Str::upper(Str::random(12)),
        'first_name' => 'Synthetic',
        'last_name' => 'Applicant',
        'birthdate' => '2000-01-01',
        'sex' => 'unspecified',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $attemptId = DB::table('admission_attempts')->insertGetId([
        'applicant_identity_id' => $identityId,
        'institution_id' => $this->institution->id,
        'academic_cycle_id' => $cycleId,
        'reference_code' => 'PG-'.Str::upper(Str::random(12)),
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $assignmentId = DB::table('exam_assignments')->insertGetId([
        'exam_session_id' => $sessionId,
        'admission_attempt_id' => $attemptId,
        'status' => 'assigned',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $snapshotId = (int) DB::table('exam_session_material_snapshots')->insertGetId([
        'exam_session_id' => $sessionId,
        'template_version_id' => $version->template_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'scoring_policy_identity' => 'synthetic-contract-only',
        'material_hash' => hash('sha256', 'operation-pin-snapshot'),
        'published_at' => now(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $receipt->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $administrationId = DB::table('assessment_administrations')->insertGetId([
        'exam_assignment_id' => $assignmentId,
        'admission_attempt_id' => $attemptId,
        'binding_kind' => 'synthetic_contract',
        'binding_revision' => 1,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'material_snapshot_id' => null,
        'booklet_set_id' => $version->booklet_set_id,
        'required_metric_set' => json_encode($this->fixture['profile']->metrics()->where('is_required', true)->orderBy('code')->get()
            ->map(static fn ($metric): array => [
                'code' => $metric->code, 'name' => $metric->name, 'rule_kind' => $metric->rule_kind,
                'unit' => $metric->unit, 'min_raw_value' => $metric->min_raw_value,
                'max_raw_value' => $metric->max_raw_value, 'precision_scale' => $metric->precision_scale,
                'required_output_scales' => $metric->required_output_scales, 'input_contract_json' => $metric->input_contract_json,
            ])->all(), JSON_THROW_ON_ERROR),
        'procedure_snapshot_json' => json_encode([
            'content_sha256' => $this->fixture['profile']->content_sha256,
            'material_requirement' => $this->fixture['profile']->material_requirement,
            'administration_procedure_reference' => $this->fixture['profile']->administration_procedure_reference,
            'manifest_schema_version' => (int) $version->manifest_schema_version,
            'kit_manifest_sha256' => $version->manifest_sha256,
            'operation' => 'manual',
        ], JSON_THROW_ON_ERROR),
        'binding_state' => 'ready',
        'bound_by_user_id' => $this->actor->id,
        'bound_at' => now(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $baseRun = [
        'administration_id' => $administrationId,
        'run_uuid' => (string) Str::uuid(),
        'actor_user_id' => $this->actor->id,
        'idempotency_key' => 'operation-pin-'.Str::lower(Str::random(12)),
        'created_at' => now(),
    ];
    $historicalId = DB::table('assessment_scoring_runs')->insertGetId(array_merge($baseRun, ['source' => 'manual']));

    expect(DB::table('assessment_scoring_runs')->where('id', $historicalId)->value('test_kit_version_id'))->toBeNull();

    $validPins = [
        'test_kit_version_id' => $version->id,
        'test_kit_version_revision' => $version->revision,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $receipt->id,
    ];
    $runId = DB::table('assessment_scoring_runs')->insertGetId(array_merge($baseRun, [
        'source' => 'omr',
        'run_uuid' => (string) Str::uuid(),
        'idempotency_key' => 'omr-pin-'.Str::lower(Str::random(12)),
    ], $validPins));

    expect(DB::table('assessment_scoring_runs')->where('id', $runId)->value('test_kit_version_id'))->toBe($version->id);
    testKitPgRejects(
        fn () => DB::table('assessment_scoring_runs')->insert(array_merge($baseRun, [
            'source' => 'omr',
            'run_uuid' => (string) Str::uuid(),
            'idempotency_key' => 'missing-pin-'.Str::lower(Str::random(12)),
        ])),
        'Expected PostgreSQL to reject creating an OMR run without operation acceptance proof.',
    );

    testKitPgRejects(
        fn () => DB::table('assessment_scoring_runs')->where('id', $runId)->update(['acceptance_receipt_id' => $other['version']->id]),
        'Expected PostgreSQL to reject changing an operation acceptance receipt pin.',
        'Assessment scoring run operation acceptance pins are immutable',
    );
    testKitPgRejects(
        fn () => DB::table('assessment_scoring_runs')->where('id', $runId)->update(['source' => 'manual']),
        'Expected PostgreSQL to reject changing a pinned OMR run to a manual source.',
    );
    testKitPgRejects(
        fn () => DB::table('assessment_scoring_runs')->where('id', $historicalId)->update(['source' => 'omr']),
        'Expected PostgreSQL to reject converting an unpinned historical manual run to OMR.',
    );
    testKitPgRejects(
        fn () => DB::table('assessment_scoring_runs')->insert(array_merge($baseRun, [
            'source' => 'omr',
            'run_uuid' => (string) Str::uuid(),
            'idempotency_key' => 'partial-pin-'.Str::lower(Str::random(12)),
            'test_kit_version_id' => $version->id,
        ])),
        'Expected PostgreSQL to reject a partially pinned OMR operation.',
    );
    $otherReceiptFixture = testKitPgFailedReceipt($other, $this->actor, $cycleId);
    $otherVersion = $other['version'];
    $rejectedPins = [
        'test_kit_version_id' => $otherVersion->id,
        'test_kit_version_revision' => $otherVersion->revision,
        'manifest_sha256' => $otherVersion->manifest_sha256,
        'acceptance_receipt_id' => $otherReceiptFixture['receiptId'],
    ];
    $beforeRejectedInsert = DB::table('assessment_scoring_runs')->count();
    testKitPgRejects(
        fn () => DB::table('assessment_scoring_runs')->insert(array_merge($baseRun, [
            'source' => 'omr',
            'run_uuid' => (string) Str::uuid(),
            'idempotency_key' => 'wrong-admin-pin-'.Str::lower(Str::random(12)),
        ], $rejectedPins)),
        'Expected PostgreSQL to reject a run whose administration owns another kit and receipt.',
        null,
        fn () => expect(DB::table('assessment_scoring_runs')->count())->toBe($beforeRejectedInsert),
    );

    DB::table('test_kit_versions')->where('id', $version->id)->update([
        'revision' => $version->revision + 1,
        'updated_at' => now(),
    ]);
    DB::table('test_kit_versions')->where('id', $version->id)->update([
        'status' => 'retired',
        'activated_at' => now()->subDay(),
        'activated_by_user_id' => $this->actor->id,
        'retired_at' => now(),
        'retired_by_user_id' => $this->actor->id,
        'updated_at' => now(),
    ]);
    expect(DB::table('assessment_scoring_runs')->where('id', $runId)->value('test_kit_version_revision'))->toBe($version->revision)
        ->and(DB::table('assessment_scoring_runs')->where('id', $runId)->value('manifest_sha256'))->toBe($version->manifest_sha256)
        ->and(DB::table('assessment_scoring_runs')->where('id', $runId)->value('acceptance_receipt_id'))->toBe($receipt->id);
});
