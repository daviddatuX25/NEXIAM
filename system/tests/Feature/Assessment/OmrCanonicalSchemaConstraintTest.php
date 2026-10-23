<?php

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('deployed verification capture migration remains byte-for-byte unchanged', function (): void {
    expect(hash_file(
        'sha256',
        database_path('migrations/2026_08_26_090002_create_omr_template_verification_captures_table.php'),
    ))->toBe('fc4b73c125e19030593b6c3ef40ef3a595a22b488800c246c6262760ea2eb40b');
});

test('verification capture actual values allow false_accept and reject unknown states', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $values = [
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'expected' => OmrTemplateVerificationCapture::ACTUAL_TYPED_REJECTION,
        'actual' => OmrTemplateVerificationCapture::ACTUAL_FALSE_ACCEPT,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ];

    $captureId = DB::table('omr_template_verification_captures')->insertGetId($values);

    expect(DB::table('omr_template_verification_captures')->where('id', $captureId)->value('actual'))
        ->toBe(OmrTemplateVerificationCapture::ACTUAL_FALSE_ACCEPT);
    expect(fn () => DB::table('omr_template_verification_captures')->insert([
        ...$values,
        'actual' => 'not-a-capture-state',
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('omr_template_verification_captures')->where('id', $captureId)->update([
        'actual' => 'not-a-capture-state',
    ]))->toThrow(QueryException::class);
});

test('false-accept migration refuses rollback while incompatible evidence remains', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);

    DB::table('omr_template_verification_captures')->insert([
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'actual' => OmrTemplateVerificationCapture::ACTUAL_FALSE_ACCEPT,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_09_02_090000_allow_omr_false_accept_actual.php');

    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'Cannot roll back OMR false-accept compatibility');
});

test('canonical tables enforce draft stage and asset exactly-one-owner constraints', function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('PostgreSQL CHECK constraints are exercised in the Compose runtime.');
    }

    $actor = User::factory()->create();
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'CANONICAL-CONSTRAINT', 'name' => 'Canonical constraint']);

    expect(fn () => DB::table('omr_template_authoring_drafts')->insert([
        'template_id' => $template->id,
        'code' => 'invalid-stage',
        'name' => 'Invalid stage',
        'source_kind' => 'html',
        'registration_mode' => 'fiducial_anchored',
        'qr_mode' => 'qr_signed',
        'requested_page_count' => 1,
        'stage' => 'not-a-stage',
        'authoring_json' => '{}',
        'lock_version' => 0,
        'owner_user_id' => $actor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => OmrTemplateAsset::query()->create([
        'kind' => OmrTemplateAsset::KIND_SOURCE,
        'disk' => 'local',
        'path' => 'invalid/source.html',
        'mime' => 'text/html',
        'bytes' => 1,
        'sha256' => hash('sha256', 'invalid'),
    ]))->toThrow(QueryException::class);
});

test('paper policy columns reject unknown modes and page counts outside one through four', function (string $column, mixed $value): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('PostgreSQL CHECK constraints are exercised in the Compose runtime.');
    }

    $actor = User::factory()->create();
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'PAPER-POLICY-'.strtoupper(substr(hash('sha256', $column), 0, 8)),
        'name' => 'Paper policy constraint',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 2,
        'owner_user_id' => $actor->id,
    ]);

    expect(fn () => DB::table('omr_template_authoring_drafts')
        ->where('id', $draft->id)
        ->update([$column => $value]))
        ->toThrow(QueryException::class);
})->with([
    'registration mode' => ['registration_mode', 'unknown'],
    'QR mode' => ['qr_mode', 'unknown'],
    'zero pages' => ['requested_page_count', 0],
    'five pages' => ['requested_page_count', 5],
]);

test('marker profile code and version and blank display codes remain unique', function (): void {
    $actor = User::factory()->create();
    $values = [
        'code' => 'constraint.marker',
        'version' => 1,
        'family' => 'test',
        'asset_path' => 'marker.jpg',
        'asset_sha256' => hash('sha256', 'marker'),
        'matching_config' => [],
        'created_by_user_id' => $actor->id,
    ];
    OmrMarkerProfile::query()->create($values);

    expect(fn () => OmrMarkerProfile::query()->create($values))->toThrow(QueryException::class);
});

function omrSchemaRun(User $actor): OmrTemplateVerificationRun
{
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'SCHEMA-DRAFT-'.uniqid(),
        'name' => 'Schema draft',
        'source_kind' => 'html',
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [],
        'owner_user_id' => $actor->id,
    ]);

    return OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('c', 64),
        'mapping_hash' => str_repeat('g', 64),
        'progress_total' => 1,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);
}

test('physical assurance tables expose the locked additive columns and no sensitive payload columns', function (): void {
    expect(Schema::hasColumns('omr_template_verification_methods', [
        'run_id', 'method', 'method_state', 'target_kind', 'target_id',
        'target_version', 'target_hash', 'plan_version', 'plan_seed', 'plan_hash',
        'expected_vector_hash', 'challenge_id', 'required_page_count', 'lock_version',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('omr_template_verification_method_pages', [
            'method_id', 'page_no', 'required', 'page_state', 'page_version', 'selected_attempt_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('omr_physical_challenges', [
            'method', 'target_kind', 'target_id', 'target_version', 'target_hash',
            'plan_version', 'plan_seed', 'renderer_version', 'renderer_hash',
            'marker_profile_id', 'marker_profile_version', 'marker_profile_hash',
            'page_manifest', 'page_manifest_hash', 'sheet_asset_hash', 'guide_asset_hash',
            'print_metadata',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('omr_template_verification_captures', [
            'method_id', 'method', 'page_no', 'attempt_no', 'attempt_state', 'accepted_orientation',
            'plan_hash', 'evidence_version', 'evidence_hash', 'failure_code',
            'failure_detail', 'superseded_by_attempt_id', 'supersede_reason',
            'selection_reason', 'printer', 'device', 'paper', 'measurements', 'lock_version',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('omr_template_verification_item_evidence', [
            'capture_id', 'group_type', 'area_key', 'area_position', 'question_no',
            'item_id', 'options', 'expected', 'detected', 'state', 'means', 'delta',
            'status', 'crop_asset_id', 'crop_asset_hash', 'crop_rect',
            'evidence_version', 'evidence_hash', 'row_version',
        ]))->toBeTrue();

    $itemColumns = Schema::getColumnListing('omr_template_verification_item_evidence');
    expect($itemColumns)->not->toContain('bytes', 'base64', 'applicant_id', 'score');
});

test('physical assurance models expose typed relationships, casts, and stable scopes', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'method' => OmrPhysicalChallenge::METHOD_PRE_SHADED,
        'mode' => OmrPhysicalChallenge::MODE_FULL_QUALIFICATION,
        'challenge_code' => 'CHAL-123456789A',
        'target_kind' => 'candidate',
        'target_id' => 1,
        'target_version' => '1',
        'target_hash' => str_repeat('a', 64),
        'plan_version' => 'v1',
        'plan_seed' => 'seed',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'page_manifest' => ['page_count' => 1],
        'print_metadata' => ['actual_size' => true],
        'created_by_user_id' => $actor->id,
    ]);
    $method = OmrTemplateVerificationMethod::query()->create([
        'run_id' => $run->id,
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => 1,
        'target_version' => '1',
        'target_hash' => str_repeat('a', 64),
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'challenge_id' => $challenge->id,
        'required_page_count' => 1,
    ]);
    $page = OmrTemplateVerificationMethodPage::query()->create([
        'method_id' => $method->id,
        'page_no' => 1,
    ]);
    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $method->id,
        'challenge_id' => $challenge->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => 'candidate',
        'target_id' => 1,
        'target_version' => '1',
        'target_hash' => str_repeat('a', 64),
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'accepted_orientation' => 0,
        'orientation_tried' => [0, 90, 180, 270],
        'measurements' => ['bubble_mm' => 4.2],
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $item = OmrTemplateVerificationItemEvidence::query()->create([
        'capture_id' => $capture->id,
        'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
        'area_key' => 'verbal',
        'area_position' => 1,
        'question_no' => 1,
        'item_id' => 'VERBAL-1',
        'options' => ['A', 'B', 'C', 'D'],
        'expected' => 'A',
        'detected' => 'A',
        'state' => OmrTemplateVerificationItemEvidence::STATE_MATCH,
        'means' => ['A' => 0.8],
        'delta' => 0.25,
        'status' => 'match',
        'evidence_hash' => str_repeat('h', 64),
    ]);

    expect($run->methods)->toHaveCount(1)
        ->and($method->run->is($run))->toBeTrue()
        ->and($method->challenge->is($challenge))->toBeTrue()
        ->and($method->pages->first()->is($page))->toBeTrue()
        ->and($method->captures->first()->is($capture))->toBeTrue()
        ->and($page->method->is($method))->toBeTrue()
        ->and($capture->challenge->is($challenge))->toBeTrue()
        ->and($capture->evidenceItems->first()->is($item))->toBeTrue()
        ->and($item->capture->is($capture))->toBeTrue()
        ->and($method->required_page_count)->toBeInt()
        ->and($page->required)->toBeTrue()
        ->and($capture->orientation_tried)->toBe([0, 90, 180, 270])
        ->and($capture->measurements['bubble_mm'])->toBe(4.2)
        ->and($item->options)->toBe(['A', 'B', 'C', 'D'])
        ->and($item->delta)->toBe(0.25)
        ->and(OmrTemplateVerificationCapture::query()->forMethod('pre_shaded')->passed()->count())->toBe(1)
        ->and(OmrTemplateVerificationItemEvidence::query()->forGroup('aptitude_area')->count())->toBe(1);
});

test('method captures eager-load by explicit method foreign key', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $hashes = [
        'target_hash' => str_repeat('a', 64),
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
    ];

    foreach ([
        OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        OmrTemplateVerificationMethod::METHOD_HAND_SHADED,
    ] as $methodName) {
        $method = OmrTemplateVerificationMethod::query()->create([
            'run_id' => $run->id,
            'method' => $methodName,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => 1,
            'target_version' => '1',
            'required_page_count' => 1,
            ...$hashes,
        ]);
        OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'method_id' => $method->id,
            'attempt' => 1,
            'attempt_no' => 1,
            'page_no' => 1,
            'method' => $methodName,
            'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
            'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            'status' => OmrTemplateVerificationCapture::STATUS_PASS,
            'expected' => 'pass',
            'actual' => 'pass',
            'medium' => $methodName === OmrTemplateVerificationMethod::METHOD_HAND_SHADED
                ? OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL
                : null,
            ...$hashes,
            'input_hash' => str_repeat('i', 64),
            'evidence_hash' => str_repeat('h', 64),
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
    }

    $methods = OmrTemplateVerificationMethod::query()
        ->where('run_id', $run->id)
        ->with('captures')
        ->orderBy('method')
        ->get();

    expect($methods)->toHaveCount(2)
        ->and($methods->firstWhere('method', OmrTemplateVerificationMethod::METHOD_PRE_SHADED)->captures)->toHaveCount(1)
        ->and($methods->firstWhere('method', OmrTemplateVerificationMethod::METHOD_HAND_SHADED)->captures)->toHaveCount(1)
        ->and($methods->firstWhere('method', OmrTemplateVerificationMethod::METHOD_PRE_SHADED)->captures->first()->method)
        ->toBe(OmrTemplateVerificationMethod::METHOD_PRE_SHADED)
        ->and($methods->firstWhere('method', OmrTemplateVerificationMethod::METHOD_HAND_SHADED)->captures->first()->method)
        ->toBe(OmrTemplateVerificationMethod::METHOD_HAND_SHADED);
});

test('physical assurance uniqueness, foreign keys, and legacy defaults remain enforced', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $methodValues = [
        'run_id' => $run->id,
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'target_kind' => 'candidate',
        'target_id' => 1,
        'target_version' => '1',
        'target_hash' => str_repeat('a', 64),
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'required_page_count' => 1,
    ];
    OmrTemplateVerificationMethod::query()->create($methodValues);
    expect(fn () => DB::transaction(fn () => OmrTemplateVerificationMethod::query()->create($methodValues)))->toThrow(QueryException::class);

    $method = OmrTemplateVerificationMethod::query()->firstOrFail();
    $pageValues = ['method_id' => $method->id, 'page_no' => 1];
    OmrTemplateVerificationMethodPage::query()->create($pageValues);
    expect(fn () => DB::transaction(fn () => OmrTemplateVerificationMethodPage::query()->create($pageValues)))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => OmrTemplateVerificationMethod::query()->create([...$methodValues, 'run_id' => 999999])))->toThrow(QueryException::class);

    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'mode' => OmrPhysicalChallenge::MODE_QUICK,
        'challenge_code' => 'CHAL-ABCDEF1234',
        'plan_hash' => $run->plan_hash,
        'expected_vector_hash' => $run->expected_vector_hash,
        'created_by_user_id' => $actor->id,
    ]);
    expect($challenge->method)->toBe(OmrPhysicalChallenge::METHOD_PRE_SHADED)
        ->and($challenge->plan_version)->toBe('v1');

    if (DB::connection()->getDriverName() !== 'sqlite') {
        expect(fn () => DB::transaction(fn () => DB::table('omr_template_verification_methods')->insert([
            ...$methodValues,
            'method' => 'invalid',
        ])))->toThrow(QueryException::class);
        expect(fn () => DB::transaction(fn () => DB::table('omr_template_verification_captures')->insert([
            'run_id' => $run->id,
            'attempt' => 1,
            'attempt_no' => 1,
            'page_no' => 1,
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'attempt_state' => 'invalid',
            'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
            'input_hash' => str_repeat('i', 64),
            'evidence_hash' => str_repeat('h', 64),
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ])))->toThrow(QueryException::class);
    }
});

test('selected attempts require a same-method/page passed capture on PostgreSQL', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $hashes = [
        'target_hash' => str_repeat('a', 64),
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
    ];
    $method = OmrTemplateVerificationMethod::query()->create([
        'run_id' => $run->id,
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'target_kind' => 'candidate',
        'target_id' => 1,
        'target_version' => '1',
        'required_page_count' => 1,
        ...$hashes,
    ]);
    $page = OmrTemplateVerificationMethodPage::query()->create([
        'method_id' => $method->id,
        'page_no' => 1,
    ]);
    $failed = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $method->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'target_kind' => 'candidate',
        'target_id' => 1,
        'target_version' => '1',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        ...$hashes,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    if (DB::connection()->getDriverName() !== 'sqlite') {
        expect(fn () => DB::transaction(function () use ($page, $failed): void {
            DB::table('omr_template_verification_method_pages')
                ->where('id', $page->id)
                ->update(['selected_attempt_id' => $failed->id]);
            DB::statement('SET CONSTRAINTS omr_method_pages_selected_attempt_guard IMMEDIATE');
        }))->toThrow(QueryException::class);
    }

    $passed = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $method->id,
        'attempt' => 2,
        'attempt_no' => 2,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'target_kind' => 'candidate',
        'target_id' => 1,
        'target_version' => '1',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        ...$hashes,
        'input_hash' => str_repeat('j', 64),
        'evidence_hash' => str_repeat('k', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $page->update(['selected_attempt_id' => $passed->id]);

    expect($page->fresh()->selectedAttempt->is($passed))->toBeTrue();
});

test('physical assurance identity and evidence facts are immutable while transitions remain narrow', function (): void {
    fwrite(STDOUT, 'physical_assurance_schema_driver='.DB::connection()->getDriverName().PHP_EOL);
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $hashes = [
        'target_hash' => str_repeat('a', 64),
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
    ];
    $method = OmrTemplateVerificationMethod::query()->create([
        'run_id' => $run->id,
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => 1,
        'target_version' => '1',
        'required_page_count' => 1,
        ...$hashes,
    ]);
    $page = OmrTemplateVerificationMethodPage::query()->create([
        'method_id' => $method->id,
        'page_no' => 1,
    ]);
    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $method->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => 1,
        'target_version' => '1',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        ...$hashes,
        'expected' => 'pass',
        'actual' => 'pass',
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $item = OmrTemplateVerificationItemEvidence::query()->create([
        'capture_id' => $capture->id,
        'item_id' => 'IMMUTABLE-1',
        'area_position' => 1,
        'question_no' => 1,
        'detected' => 'A',
        'evidence_hash' => str_repeat('h', 64),
    ]);

    expect(fn () => $method->update(['target_hash' => str_repeat('b', 64)]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $page->update(['required' => false]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $capture->update(['input_hash' => str_repeat('j', 64)]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $capture->delete())
        ->toThrow(RuntimeException::class)
        ->and(fn () => $item->update(['detected' => 'B']))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $item->delete())
        ->toThrow(RuntimeException::class);

    $page->refresh();
    $capture->refresh();
    expect(fn () => $capture->update(['attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED]))
        ->toThrow(RuntimeException::class)
        ->and($capture->fresh()->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED);

    $selected = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $method->id,
        'attempt' => 2,
        'attempt_no' => 2,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => 1,
        'target_version' => '1',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        ...$hashes,
        'expected' => 'pass',
        'actual' => 'pass',
        'input_hash' => str_repeat('k', 64),
        'evidence_hash' => str_repeat('l', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $page->update(['selected_attempt_id' => $selected->id]);

    expect(fn () => $selected->update([
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED,
    ]))->toThrow(RuntimeException::class);
    expect($page->fresh()->selected_attempt_id)->toBe($selected->id);

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::transaction(fn () => DB::table('omr_template_verification_item_evidence')
            ->where('id', $item->id)
            ->update(['detected' => 'C'])))->toThrow(QueryException::class)
            ->and(fn () => DB::transaction(fn () => DB::table('omr_template_verification_captures')
                ->where('id', $capture->id)
                ->update(['input_hash' => str_repeat('z', 64)])))->toThrow(QueryException::class)
            ->and(fn () => DB::transaction(fn () => DB::table('omr_template_verification_captures')
                ->where('id', $selected->id)
                ->update(['attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED])))->toThrow(QueryException::class);
    }
});

test('capture medium rules reject missing hand medium and pre-shaded medium', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $base = [
        'run_id' => $run->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ];

    expect(fn () => OmrTemplateVerificationCapture::query()->create([
        ...$base,
        'method' => OmrTemplateVerificationCapture::METHOD_HAND_SHADED,
        'medium' => null,
    ]))->toThrow(RuntimeException::class);
    expect(fn () => OmrTemplateVerificationCapture::query()->create([
        ...$base,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'medium' => OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL,
    ]))->toThrow(RuntimeException::class);

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::transaction(fn () => DB::table('omr_template_verification_captures')->insert([
            ...$base,
            'method' => OmrTemplateVerificationCapture::METHOD_HAND_SHADED,
            'medium' => null,
        ])))->toThrow(QueryException::class);
        expect(fn () => DB::transaction(fn () => DB::table('omr_template_verification_captures')->insert([
            ...$base,
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'medium' => OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL,
        ])))->toThrow(QueryException::class);
    }
});

test('stable evidence order puts null area and question values last on every driver', function (): void {
    $actor = User::factory()->create();
    $run = omrSchemaRun($actor);
    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    foreach ([
        ['item_id' => 'null-area', 'area_position' => null, 'question_no' => 1],
        ['item_id' => 'area-question', 'area_position' => 1, 'question_no' => 1],
        ['item_id' => 'null-question', 'area_position' => 1, 'question_no' => null],
        ['item_id' => 'area-two', 'area_position' => 2, 'question_no' => 1],
    ] as $row) {
        OmrTemplateVerificationItemEvidence::query()->create([
            'capture_id' => $capture->id,
            ...$row,
        ]);
    }

    expect(OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->stableOrder()
        ->pluck('item_id')
        ->all())->toBe(['area-question', 'null-question', 'area-two', 'null-area']);
});
test('physical assurance indexes expose every locked access path', function (): void {
    $names = [];
    foreach ([
        'omr_template_verification_methods',
        'omr_template_verification_method_pages',
        'omr_template_verification_captures',
        'omr_template_verification_item_evidence',
        'omr_template_assets',
    ] as $table) {
        foreach (Schema::getIndexes($table) as $index) {
            $names[] = $index['name'];
        }
    }

    expect($names)->toContain(
        'omr_verification_methods_run_method_unique',
        'omr_verification_methods_run_method_state_idx',
        'omr_verification_method_pages_method_page_unique',
        'omr_verification_method_pages_method_page_state_idx',
        'omr_verification_method_pages_selected_attempt_idx',
        'omr_verification_captures_run_method_page_attempt_unique',
        'omr_verification_captures_run_method_state_created_idx',
        'omr_verification_item_evidence_capture_item_unique',
        'omr_verification_item_evidence_capture_group_order_idx',
        'omr_verification_item_evidence_capture_state_order_idx',
        'omr_verification_item_evidence_capture_hash_idx',
        'omr_template_assets_sha256_idx',
    );
});
