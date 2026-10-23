<?php

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{institution: Institution, user: User, academic_cycle_id: int}
 */
function makeTestKitPersistenceScope(): array
{
    $institution = Institution::query()->create([
        'code' => 'KIT-'.Str::upper(Str::random(10)),
        'name' => 'Test Kit Persistence Fixture',
    ]);
    $user = User::factory()->create();
    $now = now();
    $academicCycleId = DB::table('academic_cycles')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'KIT-CYCLE-'.Str::upper(Str::random(10)),
        'name' => 'Test Kit Fixture Cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'institution' => $institution,
        'user' => $user,
        'academic_cycle_id' => $academicCycleId,
    ];
}

/**
 * @param  array{institution: Institution, user: User, academic_cycle_id: int}  $scope
 * @return array{profile_content_sha256:string, test_instrument_id:int, test_instrument_version_id:int, template_id:int, template_version:OmrAnswerSheetTemplateVersion, booklet_id:int, booklet_version_id:int, booklet_set_id:int, booklet_set_order_hash:string, booklet_set_answer_key_hash:string}
 */
function makeTestKitPersistenceMaterials(array $scope): array
{
    $now = now();
    $institutionId = $scope['institution']->id;
    $userId = $scope['user']->id;
    $suffix = Str::upper(Str::random(10));
    $instrumentId = DB::table('test_instruments')->insertGetId([
        'institution_id' => $institutionId,
        'code' => 'SYNTHETIC-'.$suffix,
        'is_synthetic' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $testInstrumentVersionId = DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'status' => 'approved',
        'display_name' => 'Synthetic persistence profile '.$suffix,
        'publisher_name' => 'Protected synthetic fixture',
        'form_code' => 'SYN-'.$suffix,
        'edition' => 'Test fixture',
        'language' => 'en',
        'source_reference' => 'Protected synthetic fixture; not an official source.',
        'permitted_use_reference' => 'Persistence tests only.',
        'source_reviewed_by_user_id' => $userId,
        'source_reviewed_at' => $now,
        'approved_by_user_id' => $userId,
        'approved_at' => $now,
        'lock_version' => 1,
        'content_sha256' => hash('sha256', 'synthetic-profile-'.$suffix),
        'created_by_user_id' => $userId,
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'Synthetic paper-backed persistence test procedure.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('test_instruments')
        ->where('id', $instrumentId)
        ->update(['current_approved_version_id' => $testInstrumentVersionId, 'updated_at' => $now]);

    $template = OmrAnswerSheetTemplate::factory()->create([
        'institution_id' => $institutionId,
        'is_synthetic' => true,
    ]);
    $templateVersion = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $template->id,
        'test_instrument_version_id' => $testInstrumentVersionId,
    ]);

    $bookletId = DB::table('omr_question_booklets')->insertGetId([
        'institution_id' => $institutionId,
        'code' => 'SYN-BOOKLET-'.$suffix,
        'name' => 'Synthetic persistence booklet '.$suffix,
        'is_synthetic' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bookletVersionId = DB::table('omr_question_booklet_versions')->insertGetId([
        'booklet_id' => $bookletId,
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $testInstrumentVersionId,
        'version' => 1,
        'status' => 'draft',
        'authoring_mode' => 'answer_keys_only',
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => hash('sha256', 'booklet-'.$suffix),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $setOrderHash = hash('sha256', 'set-order-'.$suffix);
    $setAnswerKeyHash = hash('sha256', 'set-answer-key-'.$suffix);
    $bookletSetId = DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersionId,
        'set_code' => 'A',
        'status' => 'draft',
        'generation_version' => 'booklet-set-v1',
        'order_hash' => $setOrderHash,
        'answer_key_hash' => $setAnswerKeyHash,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'profile_content_sha256' => hash('sha256', 'synthetic-profile-'.$suffix),
        'test_instrument_id' => $instrumentId,
        'test_instrument_version_id' => $testInstrumentVersionId,
        'template_id' => $template->id,
        'template_version' => $templateVersion,
        'booklet_id' => $bookletId,
        'booklet_version_id' => $bookletVersionId,
        'booklet_set_id' => $bookletSetId,
        'booklet_set_order_hash' => $setOrderHash,
        'booklet_set_answer_key_hash' => $setAnswerKeyHash,
    ];
}

/**
 * @param  array{institution: Institution, user: User, academic_cycle_id: int}  $scope
 */
function makeTestKitPersistenceFamily(array $scope, ?string $code = null): TestKit
{
    return TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $scope['institution']->id,
        'code' => $code ?? 'KIT-'.Str::upper(Str::random(10)),
        'name' => 'Synthetic persistence kit',
        'is_synthetic' => true,
        'created_by_user_id' => $scope['user']->id,
    ]);
}

/**
 * @param  array{test_instrument_id:int, test_instrument_version_id:int, template_id:int, template_version:OmrAnswerSheetTemplateVersion, booklet_id:int, booklet_version_id:int, booklet_set_id:int, booklet_set_order_hash:string, booklet_set_answer_key_hash:string}  $materials
 * @param  array<string, mixed>  $overrides
 */
function makeTestKitPersistenceVersion(
    TestKit $kit,
    array $materials,
    User $creator,
    int $versionNumber = 1,
    ?TestKitVersion $predecessor = null,
    array $overrides = [],
): TestKitVersion {
    $manifest = [
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'test-kit-canonicalizer-v1',
        'provenance_class' => 'synthetic',
        'materials' => [
            'template_version_id' => $materials['template_version']->id,
            'booklet_version_id' => $materials['booklet_version_id'],
            'booklet_set' => [
                'id' => $materials['booklet_set_id'],
                'code' => 'A',
                'order_hash' => $materials['booklet_set_order_hash'],
                'answer_key_hash' => $materials['booklet_set_answer_key_hash'],
            ],
            'test_instrument_version_id' => $materials['test_instrument_version_id'],
        ],
    ];
    $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR);
    $attributes = [
        'test_kit_id' => $kit->id,
        'institution_id' => $kit->institution_id,
        'is_synthetic' => $kit->is_synthetic,
        'template_id' => $materials['template_id'],
        'booklet_id' => $materials['booklet_id'],
        'test_instrument_id' => $materials['test_instrument_id'],
        'version_number' => $versionNumber,
        'predecessor_version_id' => $predecessor?->id,
        'template_version_id' => $materials['template_version']->id,
        'booklet_version_id' => $materials['booklet_version_id'],
        'booklet_set_id' => $materials['booklet_set_id'],
        'test_instrument_version_id' => $materials['test_instrument_version_id'],
        'booklet_set_code' => 'A',
        'booklet_set_order_hash' => $materials['booklet_set_order_hash'],
        'booklet_set_answer_key_hash' => $materials['booklet_set_answer_key_hash'],
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'test-kit-canonicalizer-v1',
        'manifest_json' => $manifest,
        'manifest_sha256' => hash('sha256', $manifestJson),
        'status' => TestKitVersion::STATUS_CANDIDATE,
        'revision' => 1,
        'created_by_user_id' => $creator->id,
    ];

    return TestKitVersion::query()->create(array_replace($attributes, $overrides));
}

/**
 * @param  array{institution: Institution, user: User, academic_cycle_id: int}  $scope
 * @param  array{profile_content_sha256:string, test_instrument_id:int, test_instrument_version_id:int, template_id:int, template_version:OmrAnswerSheetTemplateVersion, booklet_id:int, booklet_version_id:int, booklet_set_id:int, booklet_set_order_hash:string, booklet_set_answer_key_hash:string}  $materials
 */
function makeTestKitPersistenceDiagnosticRun(
    array $scope,
    array $materials,
    TestKitVersion $version,
): int {
    $now = now();
    $protocolSchemaVersion = 'test-kit-protocol-v1';
    $protocolSha256 = hash('sha256', $protocolSchemaVersion);
    $planSchemaVersion = 'test-kit-plan-v1';
    $planSha256 = hash('sha256', $planSchemaVersion);
    $plan = TestKitAcceptancePlanRecord::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'source_key' => 'synthetic-persistence-run-plan',
        'authored_source_sha256' => hash('sha256', 'authored-source'),
        'protocol_schema_version' => $protocolSchemaVersion,
        'protocol_sha256' => $protocolSha256,
        'plan_schema_version' => $planSchemaVersion,
        'plan_sha256' => $planSha256,
        'plan_payload' => ['cases' => [['case_id' => 'persisted-run']]],
        'provenance_kind' => 'synthetic_software_fixture',
        'author_reference' => 'Synthetic persistence test author',
        'truth_source_references_json' => [[
            'reference' => 'test-only://independently-worked-truth',
            'sha256' => hash('sha256', 'independent-truth'),
        ]],
        'review_evidence_reference' => 'test-only://review-record',
        'review_evidence_sha256' => hash('sha256', 'review-evidence'),
        'created_by_user_id' => $scope['user']->id,
    ]);

    return DB::table('omr_diagnostic_runs')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => 'diagnostic',
        'institution_id' => $scope['institution']->id,
        'academic_cycle_id' => $scope['academic_cycle_id'],
        'template_version_id' => $materials['template_version']->id,
        'booklet_version_id' => $materials['booklet_version_id'],
        'declared_set_code' => 'A',
        'status' => 'open',
        'phase' => 'capture',
        'revision' => 0,
        'test_kit_id' => $version->test_kit_id,
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $plan->id,
        'booklet_set_id' => $materials['booklet_set_id'],
        'manifest_sha256' => $version->manifest_sha256,
        'test_instrument_version_id' => $materials['test_instrument_version_id'],
        'profile_content_sha256' => $materials['profile_content_sha256'],
        'acceptance_protocol_schema_version' => $protocolSchemaVersion,
        'acceptance_protocol_sha256' => $protocolSha256,
        'acceptance_plan_schema_version' => $planSchemaVersion,
        'acceptance_plan_sha256' => $planSha256,
        'scoring_kernel_identity' => 'assessment-raw-evaluator-v1',
        'expected_worker_identity_json' => json_encode(['worker' => 'test'], JSON_THROW_ON_ERROR),
        'acceptance_plan_json' => encrypt(['cases' => [['case_id' => 'persisted-run']]]),
        'created_by_user_id' => $scope['user']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/**
 * @param  array{institution: Institution, user: User, academic_cycle_id: int}  $scope
 * @param  array{profile_content_sha256:string, test_instrument_id:int, test_instrument_version_id:int, template_id:int, template_version:OmrAnswerSheetTemplateVersion, booklet_id:int, booklet_version_id:int, booklet_set_id:int, booklet_set_order_hash:string, booklet_set_answer_key_hash:string}  $materials
 */
function makeTestKitPersistenceReceipt(
    TestKitVersion $version,
    array $scope,
    array $materials,
    int $diagnosticRunId,
): TestKitAcceptanceReceipt {

    $runRevision = (int) DB::table('omr_diagnostic_runs')->where('id', $diagnosticRunId)->value('revision');
    $acceptancePlanId = (int) DB::table('omr_diagnostic_runs')->where('id', $diagnosticRunId)->value('acceptance_plan_id');
    $revisionVector = ['run_revision' => $runRevision, 'pages' => []];

    return TestKitAcceptanceReceipt::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'diagnostic_run_id' => $diagnosticRunId,
        'template_version_id' => $materials['template_version']->id,
        'booklet_version_id' => $materials['booklet_version_id'],
        'booklet_set_code' => 'A',
        'run_revision' => $runRevision,
        'acceptance_plan_id' => $acceptancePlanId,
        'evidence_revision_vector_json' => $revisionVector,
        'evidence_revision_vector_sha256' => hash('sha256', json_encode($revisionVector, JSON_THROW_ON_ERROR)),
        'acceptance_protocol_schema_version' => 'test-kit-protocol-v1',
        'acceptance_protocol_sha256' => hash('sha256', 'test-kit-protocol-v1'),
        'acceptance_plan_schema_version' => 'test-kit-plan-v1',
        'acceptance_plan_sha256' => hash('sha256', 'test-kit-plan-v1'),
        'scoring_kernel_identity' => 'assessment-raw-evaluator-v1',
        'worker_commit' => str_repeat('a', 40),
        'manifest_sha256' => $version->manifest_sha256,
        'result' => TestKitAcceptanceReceipt::RESULT_FAILED,
        'check_report_json' => ['checks' => [['code' => 'synthetic_persistence_fixture', 'passed' => false]]],
        'confidential_evidence_references' => ['case' => 'private-evidence://synthetic-test-fixture'],
        'created_by_user_id' => $scope['user']->id,
        'created_at' => now(),
    ]);
}

test('kit versions expose same-family predecessors and selected receipts while rejecting cross-kit pointers', function (): void {
    $scope = makeTestKitPersistenceScope();
    $materials = makeTestKitPersistenceMaterials($scope);
    $firstKit = makeTestKitPersistenceFamily($scope);
    $secondKit = makeTestKitPersistenceFamily($scope);
    $firstVersion = makeTestKitPersistenceVersion($firstKit, $materials, $scope['user']);
    $otherKitVersion = makeTestKitPersistenceVersion($secondKit, $materials, $scope['user']);
    $successor = makeTestKitPersistenceVersion($firstKit, $materials, $scope['user'], 2, $firstVersion);
    $successorRunId = makeTestKitPersistenceDiagnosticRun($scope, $materials, $successor);

    expect(fn () => makeTestKitPersistenceReceipt($otherKitVersion, $scope, $materials, $successorRunId))
        ->toThrow(QueryException::class);

    $receipt = makeTestKitPersistenceReceipt($successor, $scope, $materials, $successorRunId);

    $successor->forceFill([
        'selected_acceptance_receipt_id' => $receipt->id,
        'revision' => 2,
    ])->save();
    $successor->refresh();

    expect($successor->testKit->is($firstKit))->toBeTrue()
        ->and($successor->predecessor->is($firstVersion))->toBeTrue()
        ->and($successor->selectedAcceptanceReceipt->is($receipt))->toBeTrue()
        ->and($receipt->testKitVersion->is($successor))->toBeTrue()
        ->and($receipt->booklet_set_code)->toBe('A')
        ->and($successor->bookletSet->id)->toBe($materials['booklet_set_id']);

    expect(fn () => makeTestKitPersistenceVersion(
        $firstKit,
        $materials,
        $scope['user'],
        3,
        $otherKitVersion,
    ))->toThrow(QueryException::class);

    $otherKitVersion->forceFill([
        'selected_acceptance_receipt_id' => $receipt->id,
        'revision' => 2,
    ]);

    expect(fn () => $otherKitVersion->save())->toThrow(QueryException::class);
});

test('family provenance and version material remain immutable while lifecycle fields can advance', function (): void {
    $scope = makeTestKitPersistenceScope();
    $otherInstitution = Institution::query()->create([
        'code' => 'KIT-'.Str::upper(Str::random(10)),
        'name' => 'Other Test Kit Institution',
    ]);
    $materials = makeTestKitPersistenceMaterials($scope);
    $kit = makeTestKitPersistenceFamily($scope);
    $version = makeTestKitPersistenceVersion($kit, $materials, $scope['user']);

    $changedFamily = $kit->fresh();
    $changedFamily->forceFill(['is_synthetic' => false]);
    expect(fn () => $changedFamily->save())->toThrow(RuntimeException::class);

    expect(fn () => DB::table('test_kits')->where('id', $kit->id)->update(['institution_id' => $otherInstitution->id]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('test_kits')->where('id', $kit->id)->update(['is_synthetic' => false]))
        ->toThrow(QueryException::class);

    $changedVersion = $version->fresh();
    $changedVersion->forceFill(['institution_id' => $otherInstitution->id]);
    expect(fn () => $changedVersion->save())->toThrow(RuntimeException::class);

    $changedVersion = $version->fresh();
    $changedVersion->forceFill(['manifest_json' => ['changed' => true]]);
    expect(fn () => $changedVersion->save())->toThrow(RuntimeException::class);

    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'revision' => 2,
        'activated_at' => now(),
        'activated_by_user_id' => $scope['user']->id,
    ])->save();

    expect($version->fresh()->status)->toBe(TestKitVersion::STATUS_ACTIVE);

    expect(fn () => DB::table('test_kit_versions')->where('id', $version->id)->update([
        'manifest_sha256' => hash('sha256', 'tampered-manifest'),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('test_kit_versions')->where('id', $version->id)->update([
        'manifest_json' => json_encode(['tampered' => true], JSON_THROW_ON_ERROR),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('test_kit_versions')->where('id', $version->id)->update([
        'institution_id' => $otherInstitution->id,
    ]))->toThrow(QueryException::class);
});

test('only one version can be active in a family without limiting other families', function (): void {
    $scope = makeTestKitPersistenceScope();
    $materials = makeTestKitPersistenceMaterials($scope);
    $firstKit = makeTestKitPersistenceFamily($scope);
    $secondKit = makeTestKitPersistenceFamily($scope);

    if (DB::getDriverName() === 'sqlite') {
        $indexes = collect(DB::select("PRAGMA index_list('test_kit_versions')"))
            ->map(static function (object $index): array {
                $quotedIndex = '"'.str_replace('"', '""', $index->name).'"';

                return [
                    'name' => $index->name,
                    'unique' => (int) $index->unique,
                    'partial' => (int) $index->partial,
                    'columns' => array_map(
                        static fn (object $column): string => $column->name,
                        DB::select("PRAGMA index_info({$quotedIndex})"),
                    ),
                ];
            })
            ->filter(static fn (array $index): bool => $index['unique'] === 1 && $index['columns'] === ['test_kit_id'])
            ->values()
            ->all();

        expect($indexes)->toBe([[
            'name' => 'test_kit_versions_one_active_per_family',
            'unique' => 1,
            'partial' => 1,
            'columns' => ['test_kit_id'],
        ]]);
    }

    $first = makeTestKitPersistenceVersion($firstKit, $materials, $scope['user']);
    $second = makeTestKitPersistenceVersion($firstKit, $materials, $scope['user'], 2, $first);
    expect(DB::table('test_kit_versions')->where('test_kit_id', $firstKit->id)->where('status', TestKitVersion::STATUS_CANDIDATE)->count())
        ->toBe(2);
    $otherFamilyVersion = makeTestKitPersistenceVersion($secondKit, $materials, $scope['user']);
    $activatedAt = now();

    $first->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'revision' => 2,
        'activated_at' => $activatedAt,
        'activated_by_user_id' => $scope['user']->id,
    ])->save();

    $second->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'revision' => 2,
        'activated_at' => $activatedAt,
        'activated_by_user_id' => $scope['user']->id,
    ]);
    expect(fn () => $second->save())->toThrow(QueryException::class);

    $otherFamilyVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'revision' => 2,
        'activated_at' => $activatedAt,
        'activated_by_user_id' => $scope['user']->id,
    ])->save();

    expect($first->fresh()->status)->toBe(TestKitVersion::STATUS_ACTIVE)
        ->and($otherFamilyVersion->fresh()->status)->toBe(TestKitVersion::STATUS_ACTIVE)
        ->and(DB::table('test_kit_versions')->where('test_kit_id', $firstKit->id)->where('status', TestKitVersion::STATUS_ACTIVE)->count())->toBe(1)
        ->and(DB::table('test_kit_versions')->where('test_kit_id', $firstKit->id)->where('status', TestKitVersion::STATUS_CANDIDATE)->count())->toBe(1);
});

test('material foreign keys reject mismatched Set booklet sheet profile and owner tuples', function (): void {
    $scope = makeTestKitPersistenceScope();
    $otherScope = makeTestKitPersistenceScope();
    $firstMaterials = makeTestKitPersistenceMaterials($scope);
    $secondMaterials = makeTestKitPersistenceMaterials($scope);
    $kit = makeTestKitPersistenceFamily($scope);

    $validVersion = makeTestKitPersistenceVersion($kit, $firstMaterials, $scope['user']);
    expect($validVersion->fresh()->institution_id)->toBe($scope['institution']->id)
        ->and($validVersion->is_synthetic)->toBeTrue();

    $otherInstitutionKit = makeTestKitPersistenceFamily($otherScope);
    expect(fn () => makeTestKitPersistenceVersion($otherInstitutionKit, $firstMaterials, $otherScope['user']))
        ->toThrow(QueryException::class);
    expect(fn () => makeTestKitPersistenceVersion(
        $kit,
        $firstMaterials,
        $scope['user'],
        overrides: ['is_synthetic' => false],
    ))->toThrow(QueryException::class);

    $wrongSet = [
        'booklet_set_id' => $secondMaterials['booklet_set_id'],
        'booklet_set_order_hash' => $secondMaterials['booklet_set_order_hash'],
        'booklet_set_answer_key_hash' => $secondMaterials['booklet_set_answer_key_hash'],
    ];
    expect(fn () => makeTestKitPersistenceVersion(
        $kit,
        $firstMaterials,
        $scope['user'],
        versionNumber: 2,
        overrides: $wrongSet,
    ))->toThrow(QueryException::class);

    $wrongBooklet = [
        'booklet_version_id' => $secondMaterials['booklet_version_id'],
        'booklet_set_id' => $secondMaterials['booklet_set_id'],
        'booklet_set_order_hash' => $secondMaterials['booklet_set_order_hash'],
        'booklet_set_answer_key_hash' => $secondMaterials['booklet_set_answer_key_hash'],
    ];
    expect(fn () => makeTestKitPersistenceVersion(
        $kit,
        $firstMaterials,
        $scope['user'],
        versionNumber: 3,
        overrides: $wrongBooklet,
    ))->toThrow(QueryException::class);

    expect(fn () => makeTestKitPersistenceVersion(
        $kit,
        $firstMaterials,
        $scope['user'],
        versionNumber: 4,
        overrides: ['test_instrument_version_id' => $secondMaterials['test_instrument_version_id']],
    ))->toThrow(QueryException::class);

    expect(fn () => makeTestKitPersistenceVersion(
        $kit,
        $firstMaterials,
        $scope['user'],
        versionNumber: 5,
        overrides: ['institution_id' => null],
    ))->toThrow(QueryException::class);
});

test('acceptance plans persist reviewed truth sources without an updated timestamp column', function (): void {
    $scope = makeTestKitPersistenceScope();
    $materials = makeTestKitPersistenceMaterials($scope);
    $kit = makeTestKitPersistenceFamily($scope);
    $version = makeTestKitPersistenceVersion($kit, $materials, $scope['user']);
    $truthReferences = [[
        'reference' => 'test-only://independently-worked-truth',
        'sha256' => hash('sha256', 'independent-truth'),
    ]];

    $plan = TestKitAcceptancePlanRecord::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'source_key' => 'synthetic-persistence-plan',
        'authored_source_sha256' => hash('sha256', 'authored-source'),
        'protocol_schema_version' => 'test-kit-protocol-v1',
        'protocol_sha256' => hash('sha256', 'protocol'),
        'plan_schema_version' => 'test-kit-plan-v1',
        'plan_sha256' => hash('sha256', 'plan'),
        'plan_payload' => ['cases' => [['case_id' => 'persisted-only']]],
        'provenance_kind' => 'synthetic_software_fixture',
        'author_reference' => 'Synthetic persistence test author',
        'truth_source_references_json' => $truthReferences,
        'review_evidence_reference' => 'test-only://review-record',
        'review_evidence_sha256' => hash('sha256', 'review-evidence'),
        'created_by_user_id' => $scope['user']->id,
    ]);

    $persistedPlan = $plan->fresh();
    expect($persistedPlan->truth_source_references_json)->toBe($truthReferences)
        ->and($persistedPlan->plan_payload)->toBe(['cases' => [['case_id' => 'persisted-only']]])
        ->and($persistedPlan->created_at)->not->toBeNull();
    $persistedPlan->forceFill(['author_reference' => 'Changed after creation']);
    expect(fn () => $persistedPlan->save())->toThrow(RuntimeException::class);
});

test('acceptance receipts are append-only and private evidence references are not serialized', function (): void {
    $scope = makeTestKitPersistenceScope();
    $materials = makeTestKitPersistenceMaterials($scope);
    $kit = makeTestKitPersistenceFamily($scope);
    $version = makeTestKitPersistenceVersion($kit, $materials, $scope['user']);
    $runId = makeTestKitPersistenceDiagnosticRun($scope, $materials, $version);
    $receipt = makeTestKitPersistenceReceipt($version, $scope, $materials, $runId);

    $changedReceipt = $receipt->fresh();
    $changedReceipt->forceFill(['result' => TestKitAcceptanceReceipt::RESULT_PASSED]);
    expect(fn () => $changedReceipt->save())->toThrow(RuntimeException::class)
        ->and(fn () => $changedReceipt->delete())->toThrow(RuntimeException::class)
        ->and($receipt->toArray())->not->toHaveKey('confidential_evidence_references');

    expect(fn () => DB::table('test_kit_acceptance_receipts')->where('id', $receipt->id)->update(['result' => 'passed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('test_kit_acceptance_receipts')->where('id', $receipt->id)->delete())
        ->toThrow(QueryException::class);
});
