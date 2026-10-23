<?php

declare(strict_types=1);

use App\Assessment\Support\TestKitCurrentEvidence;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function currentEvidenceFixture(?array $customPlan = null): array
{
    $now = now();
    $suffix = Str::upper(Str::random(10));
    $institution = Institution::query()->create([
        'code' => 'EVIDENCE-'.$suffix,
        'name' => 'Current evidence fixture',
    ]);
    $actor = User::factory()->create();
    $cycleId = DB::table('academic_cycles')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'EVIDENCE-CYCLE-'.$suffix,
        'name' => 'Current evidence fixture cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $instrumentId = DB::table('test_instruments')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'EVIDENCE-'.$suffix,
        'is_synthetic' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $profileHash = hash('sha256', 'profile-'.$suffix);
    $profileVersionId = DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'status' => 'approved',
        'display_name' => 'Synthetic evidence profile',
        'publisher_name' => 'Private fixture',
        'form_code' => 'EVIDENCE-'.$suffix,
        'edition' => 'Test fixture',
        'language' => 'en',
        'source_reference' => 'private-test://current-evidence',
        'permitted_use_reference' => 'Test use only',
        'source_reviewed_by_user_id' => $actor->id,
        'source_reviewed_at' => $now,
        'approved_by_user_id' => $actor->id,
        'approved_at' => $now,
        'lock_version' => 1,
        'content_sha256' => $profileHash,
        'created_by_user_id' => $actor->id,
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'Synthetic test procedure',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $template = OmrAnswerSheetTemplate::factory()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
    ]);
    $templateVersion = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $template->id,
        'test_instrument_version_id' => $profileVersionId,
    ]);
    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $institution->id,
        'code' => 'EVIDENCE-'.$suffix,
        'name' => 'Current evidence booklet',
        'is_synthetic' => true,
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $profileVersionId,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => hash('sha256', 'booklet-'.$suffix),
    ]);
    $setOrderHash = hash('sha256', 'set-order-'.$suffix);
    $setKeyHash = hash('sha256', 'set-key-'.$suffix);
    $setId = DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersion->id,
        'set_code' => 'A',
        'status' => 'draft',
        'generation_version' => 'test-kit-evidence-v1',
        'order_hash' => $setOrderHash,
        'answer_key_hash' => $setKeyHash,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'EVIDENCE-'.$suffix,
        'name' => 'Current evidence fixture',
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);
    $manifest = ['fixture' => 'current-evidence', 'suffix' => $suffix];
    $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $version = TestKitVersion::query()->create([
        'test_kit_id' => $kit->id,
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'template_id' => $template->id,
        'booklet_id' => $booklet->id,
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'template_version_id' => $templateVersion->id,
        'booklet_version_id' => $bookletVersion->id,
        'booklet_set_id' => $setId,
        'test_instrument_version_id' => $profileVersionId,
        'booklet_set_code' => 'A',
        'booklet_set_order_hash' => $setOrderHash,
        'booklet_set_answer_key_hash' => $setKeyHash,
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'test-kit-canonicalizer-v1',
        'manifest_json' => $manifest,
        'manifest_sha256' => $manifestHash,
        'status' => TestKitVersion::STATUS_CANDIDATE,
        'revision' => 1,
        'created_by_user_id' => $actor->id,
    ]);

    $itemBindings = [
        ['page_no' => 1, 'native_label' => 'P1-Q1', 'global_position' => 1, 'sheet_item_id' => 101, 'item_id' => 201, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
        ['page_no' => 2, 'native_label' => 'P2-Q1', 'global_position' => 2, 'sheet_item_id' => 102, 'item_id' => 202, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
    ];
    $cases = [
        ['case_id' => 'case-a', 'coverage' => ['correct'], 'pages' => [
            ['page_no' => 1, 'responses' => [['native_label' => 'P1-Q1', 'token' => 'A']]],
            ['page_no' => 2, 'responses' => [['native_label' => 'P2-Q1', 'token' => null]]],
        ], 'expected_metrics' => [], 'recorded_measures' => []],
        ['case_id' => 'case-b', 'coverage' => ['incorrect', 'blank'], 'pages' => [
            ['page_no' => 1, 'responses' => [['native_label' => 'P1-Q1', 'token' => 'B']]],
        ], 'expected_metrics' => [], 'recorded_measures' => []],
    ];
    $plan = $customPlan ?? [
        'schema_version' => 'test-kit-acceptance-plan-v1',
        'item_bindings' => $itemBindings,
        'cases' => $cases,
    ];
    $itemBindings = $plan['item_bindings'] ?? $itemBindings;
    $planHash = hash('sha256', json_encode(currentEvidenceCanonicalize($plan), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $protocolHash = hash('sha256', 'protocol-'.$suffix);
    $planRecord = TestKitAcceptancePlanRecord::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $manifestHash,
        'protocol_schema_version' => 'test-kit-acceptance-protocol-v1',
        'protocol_sha256' => $protocolHash,
        'plan_schema_version' => 'test-kit-acceptance-plan-v1',
        'plan_sha256' => $planHash,
        'plan_payload' => $plan,
        'source_key' => 'current-evidence-'.$suffix,
        'authored_source_sha256' => hash('sha256', 'source-'.$suffix),
        'author_reference' => 'Synthetic software fixture; no institutional author or source asserted.',
        'truth_source_references_json' => [[
            'reference' => 'test-only://independent-truth',
            'sha256' => hash('sha256', 'truth-'.$suffix),
        ]],
        'review_evidence_reference' => 'test-only://reviewed-plan',
        'review_evidence_sha256' => hash('sha256', 'review-'.$suffix),
        'provenance_kind' => 'synthetic_software_fixture',
        'created_by_user_id' => $actor->id,
    ]);
    $planId = (int) $planRecord->getKey();
    $run = new OmrDiagnosticRun;
    $run->forceFill([
        'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycleId,
        'template_version_id' => $templateVersion->id,
        'booklet_version_id' => $bookletVersion->id,
        'declared_set_code' => 'A',
        'status' => OmrDiagnosticRun::STATUS_COMPLETED,
        'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
        'revision' => 8,
        'created_by_user_id' => $actor->id,
        'test_kit_id' => $kit->id,
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $planId,
        'booklet_set_id' => $setId,
        'manifest_sha256' => $manifestHash,
        'test_instrument_version_id' => $profileVersionId,
        'profile_content_sha256' => $profileHash,
        'acceptance_protocol_schema_version' => 'test-kit-acceptance-protocol-v1',
        'acceptance_protocol_sha256' => $protocolHash,
        'acceptance_plan_schema_version' => 'test-kit-acceptance-plan-v1',
        'acceptance_plan_sha256' => $planHash,
        'scoring_kernel_identity' => 'assessment-raw-evaluator-test-v1',
        'expected_worker_identity_json' => ['commit' => 'fixture-worker'],
        'acceptance_plan_json' => $plan,
    ]);
    $run->saveOrFail();

    return compact('actor', 'institution', 'version', 'templateVersion', 'bookletVersion', 'setId', 'profileVersionId', 'manifestHash', 'plan', 'planHash', 'run', 'itemBindings');
}

function currentEvidenceCanonicalize(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('currentEvidenceCanonicalize', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = currentEvidenceCanonicalize($item);
    }

    return $value;
}

/** @return array{capture:OmrDiagnosticCapture, attempt_id:int, items:list<OmrDiagnosticItemEvidence>} */
function currentEvidencePage(
    array $fixture,
    string $caseId,
    int $pageNo,
    ?int $predecessorId = null,
    ?int $pendingSetId = null,
    ?int $pendingEvidenceId = null,
    ?array $pendingPageLink = null,
    ?string $forcedInputHash = null,
    ?string $mappedItemKey = null,
): array {
    $suffix = $caseId.'-'.$pageNo.'-'.Str::lower(Str::random(8));
    $inputHash = $forcedInputHash ?? hash('sha256', 'input-'.$suffix);
    $asset = OmrTemplateAsset::query()->create([
        'template_version_id' => $fixture['templateVersion']->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => $pageNo,
        'disk' => 'sensitive',
        'path' => 'test-kit-current-evidence/'.$suffix.'.png',
        'mime' => 'image/png',
        'bytes' => 1,
        'sha256' => $inputHash,
        'metadata' => [],
    ]);

    $baseEvidence = ['fixture' => $suffix];
    $preLinkEvidenceHash = hash('sha256', json_encode($baseEvidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $baseEvidence['evidence_hash'] = $preLinkEvidenceHash;
    if ($pendingPageLink !== null) {
        $evidenceJson = $baseEvidence;
        $evidenceJson['pending_page_link'] = array_replace($pendingPageLink, [
            'diagnostic_evidence_hash' => $preLinkEvidenceHash,
        ]);
        $evidenceHash = hash('sha256', json_encode($evidenceJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    } else {
        $evidenceJson = $baseEvidence;
        $evidenceHash = $preLinkEvidenceHash;
    }

    $capture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $fixture['run']->id,
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrDiagnosticCapture::STATUS_COMPLETED,
        'version' => 1,
        'input_asset_id' => $asset->id,
        'input_asset_hash' => $inputHash,
        'evidence_json' => $evidenceJson,
        'evidence_hash' => $evidenceHash,
        'detected_answers_json' => [],
        'effective_answers_json' => [],
        'created_by_user_id' => $fixture['actor']->id,
        'captured_at' => now(),
        'completed_at' => now(),
    ]);

    $bindings = collect($fixture['itemBindings'])->filter(static fn (array $item): bool => $item['page_no'] === $pageNo)->values()->all();
    $items = [];
    $mappedIdentities = [];
    foreach ($bindings as $index => $binding) {
        $itemKey = $caseId.'-'.$binding['native_label'];
        $itemHash = hash('sha256', 'item-'.$suffix.'-'.$index);
        $item = OmrDiagnosticItemEvidence::query()->create([
            'diagnostic_capture_id' => $capture->id,
            'item_key' => $itemKey,
            'page_no' => $pageNo,
            'evidence_hash' => $itemHash,
            'evidence_version' => 'diagnostic-item-v1',
        ]);
        OmrDiagnosticReviewRevision::query()->create([
            'diagnostic_item_evidence_id' => $item->id,
            'revision' => 1,
            'parent_revision' => 1,
            'decision' => 'confirm_machine',
            'final_option' => 'A',
            'reviewed_evidence_hash' => $itemHash,
            'actor_id' => $fixture['actor']->id,
            'idempotency_key' => 'review-'.$suffix.'-'.$index,
        ]);
        $items[] = $item;
        $mappedIdentities[] = array_replace($binding, ['item_key' => $mappedItemKey ?? $itemKey]);
    }

    $attemptId = DB::table('test_kit_diagnostic_page_attempts')->insertGetId([
        'diagnostic_run_id' => $fixture['run']->id,
        'case_id' => $caseId,
        'page_no' => $pageNo,
        'diagnostic_capture_id' => $capture->id,
        'pending_set_id' => $pendingSetId,
        'pending_page_evidence_id' => $pendingEvidenceId,
        'input_sha256' => $inputHash,
        'predecessor_attempt_id' => $predecessorId,
        'item_bindings_json' => json_encode($mappedIdentities, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'created_by_user_id' => $fixture['actor']->id,
        'created_at' => now(),
    ]);

    return ['capture' => $capture, 'attempt_id' => $attemptId, 'items' => $items];
}

function currentEvidenceSelect(array $fixture, string $caseId, int $pageNo, int $attemptId, int $revision = 1): void
{
    DB::table('test_kit_diagnostic_page_selections')->insert([
        'diagnostic_run_id' => $fixture['run']->id,
        'case_id' => $caseId,
        'page_no' => $pageNo,
        'selected_attempt_id' => $attemptId,
        'revision' => $revision,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('selects only the current attempt for every frozen case and multipage page and hashes persisted evidence', function (): void {
    $fixture = currentEvidenceFixture();
    $old = currentEvidencePage($fixture, 'case-a', 1);
    $current = currentEvidencePage($fixture, 'case-a', 1, $old['attempt_id']);
    $pageTwo = currentEvidencePage($fixture, 'case-a', 2);
    $caseB = currentEvidencePage($fixture, 'case-b', 1);
    currentEvidenceSelect($fixture, 'case-a', 1, $current['attempt_id']);
    currentEvidenceSelect($fixture, 'case-a', 2, $pageTwo['attempt_id']);
    currentEvidenceSelect($fixture, 'case-b', 1, $caseB['attempt_id']);

    $selected = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);

    expect($selected['complete'])->toBeTrue()
        ->and($selected['errors'])->toBe([])
        ->and($selected['attempts'])->toHaveCount(3)
        ->and(array_column($selected['attempts'], 'id'))->not->toContain($old['attempt_id'])
        ->and($selected['evidence_vector']['entries'])->toHaveCount(3)
        ->and($selected['evidence_vector_sha256'])->toBe(hash('sha256', json_encode($selected['evidence_vector'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
});

test('reports missing planned pages and extra selected case pages without selecting unrelated captures', function (): void {
    $fixture = currentEvidenceFixture();
    $pageOne = currentEvidencePage($fixture, 'case-a', 1);
    $pageTwo = currentEvidencePage($fixture, 'case-a', 2);
    $extra = currentEvidencePage($fixture, 'case-extra', 1);
    currentEvidenceSelect($fixture, 'case-a', 1, $pageOne['attempt_id']);
    currentEvidenceSelect($fixture, 'case-extra', 1, $extra['attempt_id']);

    $selected = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);

    expect($selected['complete'])->toBeFalse()
        ->and($selected['errors'])->toContain(['code' => 'selected_page_missing', 'case_id' => 'case-a', 'page_no' => 2])
        ->and($selected['errors'])->toContain(['code' => 'selected_page_extra', 'case_id' => 'case-extra', 'page_no' => 1])
        ->and($selected['captures']->modelKeys())->toContain($pageOne['capture']->id)
        ->and($selected['captures']->modelKeys())->not->toContain($extra['capture']->id, $pageTwo['capture']->id);
});

test('rejects cross-run captures and case-mismatched attempts at persisted lineage boundary', function (): void {
    $fixture = currentEvidenceFixture();
    $other = currentEvidenceFixture();
    $capture = currentEvidencePage($other, 'case-a', 1);
    $binding = collect($fixture['itemBindings'])->first(static fn (array $item): bool => $item['page_no'] === 1);

    expect(fn () => DB::table('test_kit_diagnostic_page_attempts')->insert([
        'diagnostic_run_id' => $fixture['run']->id,
        'case_id' => 'case-a',
        'page_no' => 1,
        'diagnostic_capture_id' => $capture['capture']->id,
        'input_sha256' => (string) $capture['capture']->input_asset_hash,
        'item_bindings_json' => json_encode([$binding], JSON_THROW_ON_ERROR),
        'created_by_user_id' => $fixture['actor']->id,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);

    $valid = currentEvidencePage($fixture, 'case-a', 1);
    expect(fn () => DB::table('test_kit_diagnostic_page_selections')->insert([
        'diagnostic_run_id' => $fixture['run']->id,
        'case_id' => 'case-b',
        'page_no' => 1,
        'selected_attempt_id' => $valid['attempt_id'],
        'revision' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('latest attributable review must match current evidence and review changes alter the exact vector', function (): void {
    $fixture = currentEvidenceFixture();
    $pageOne = currentEvidencePage($fixture, 'case-a', 1);
    currentEvidenceSelect($fixture, 'case-a', 1, $pageOne['attempt_id']);
    currentEvidenceSelect($fixture, 'case-a', 2, currentEvidencePage($fixture, 'case-a', 2)['attempt_id']);
    currentEvidenceSelect($fixture, 'case-b', 1, currentEvidencePage($fixture, 'case-b', 1)['attempt_id']);
    $before = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);
    $item = $pageOne['items'][0];
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $item->id,
        'revision' => 2,
        'parent_revision' => 1,
        'decision' => 'confirm_machine',
        'final_option' => 'B',
        'reviewed_evidence_hash' => hash('sha256', 'superseded-evidence-hash'),
        'actor_id' => $fixture['actor']->id,
        'idempotency_key' => 'stale-review-'.$item->id,
    ]);

    $after = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);

    expect($before['complete'])->toBeTrue()
        ->and($after['complete'])->toBeFalse()
        ->and($after['errors'])->toContain(['code' => 'item_review_stale', 'case_id' => 'case-a', 'page_no' => 1])
        ->and($after['evidence_vector_sha256'])->not->toBe($before['evidence_vector_sha256']);
});

test('resolves multi-item pages with authoritative native-label mapping to captured item_key without single-item fallback', function (): void {
    $twoItemBindings = [
        ['page_no' => 1, 'native_label' => 'P1-Q1', 'global_position' => 1, 'sheet_item_id' => 101, 'item_id' => 201, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
        ['page_no' => 1, 'native_label' => 'P1-Q2', 'global_position' => 2, 'sheet_item_id' => 102, 'item_id' => 202, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
    ];
    $cases = [
        ['case_id' => 'case-multi', 'coverage' => ['correct'], 'pages' => [
            ['page_no' => 1, 'responses' => [
                ['native_label' => 'P1-Q1', 'token' => 'A'],
                ['native_label' => 'P1-Q2', 'token' => 'B'],
            ]],
        ], 'expected_metrics' => [], 'recorded_measures' => []],
    ];
    $multiPlan = [
        'schema_version' => 'test-kit-acceptance-plan-v1',
        'item_bindings' => $twoItemBindings,
        'cases' => $cases,
    ];

    $fixture = currentEvidenceFixture($multiPlan);
    $pageOne = currentEvidencePage($fixture, 'case-multi', 1);
    currentEvidenceSelect($fixture, 'case-multi', 1, $pageOne['attempt_id']);

    $selected = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);
    expect($selected['complete'])->toBeTrue()
        ->and($selected['errors'])->toBe([])
        ->and($selected['evidence_vector']['entries'][0]['items'])->toHaveCount(2)
        ->and($selected['evidence_vector']['entries'][0]['items'][0]['item_key'])->toBe('case-multi-P1-Q1')
        ->and($selected['evidence_vector']['entries'][0]['items'][1]['item_key'])->toBe('case-multi-P1-Q2');
});

test('rejects one-item evidence when its persisted item_key has no planned identity mapping', function (): void {
    $fixture = currentEvidenceFixture();
    $pageOne = currentEvidencePage($fixture, 'case-a', 1, mappedItemKey: 'wrong-item-key');
    currentEvidenceSelect($fixture, 'case-a', 1, $pageOne['attempt_id']);

    $selected = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);

    expect($selected['complete'])->toBeFalse()
        ->and($selected['errors'])->toContain(['code' => 'mapped_item_evidence_mismatch', 'case_id' => 'case-a', 'page_no' => 1]);
});

test('supports QR-disabled pending page lineage and non-self-referential evidence hash comparison', function (): void {
    $fixture = currentEvidenceFixture();
    $now = now();
    $setId = DB::table('omr_pending_capture_sets')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'diagnostic_run_id' => $fixture['run']->id,
        'institution_id' => $fixture['institution']->id,
        'academic_cycle_id' => $fixture['run']->academic_cycle_id,
        'template_version_id' => $fixture['templateVersion']->id,
        'created_by_user_id' => $fixture['actor']->id,
        'registration_mode' => 'document_bound',
        'copy_policy' => 'diagnostic_only',
        'identity_mode' => 'sheet_bound',
        'expected_pages_json' => json_encode([['page_no' => 1], ['page_no' => 2]], JSON_THROW_ON_ERROR),
        'page_count' => 2,
        'active_page_no' => 1,
        'pages_hash' => hash('sha256', 'pages'),
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'artifact_hash' => hash('sha256', 'artifact'),
        'idempotency_key' => (string) Str::uuid(),
        'payload_fingerprint' => hash('sha256', 'pending-context-'.$fixture['run']->id),
        'revision' => 1,
        'status' => 'pending',
        'expires_at' => now()->addHour(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $suffix = Str::lower(Str::random(8));
    $inputHash = hash('sha256', 'input-case-a-1-'.$suffix);
    $evidenceId = DB::table('omr_pending_capture_page_evidence')->insertGetId([
        'set_id' => $setId,
        'client_capture_id' => (string) Str::uuid(),
        'page_no' => 1,
        'asset_path' => 'test-kit-current-evidence/pending-page-1.png',
        'input_hash' => $inputHash,
        'mime_type' => 'image/png',
        'byte_count' => 1,
        'captured_by_user_id' => $fixture['actor']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $pendingLink = [
        'capture_set_id' => $setId,
        'pending_page_evidence_id' => $evidenceId,
        'page_no' => 1,
        'input_hash' => $inputHash,
        'supersedes_pending_page_evidence_id' => null,
    ];

    $pageOne = currentEvidencePage($fixture, 'case-a', 1, null, $setId, $evidenceId, $pendingLink, $inputHash);
    currentEvidenceSelect($fixture, 'case-a', 1, $pageOne['attempt_id']);
    currentEvidenceSelect($fixture, 'case-a', 2, currentEvidencePage($fixture, 'case-a', 2)['attempt_id']);
    currentEvidenceSelect($fixture, 'case-b', 1, currentEvidencePage($fixture, 'case-b', 1)['attempt_id']);

    $selected = app(TestKitCurrentEvidence::class)->forRun($fixture['run']);
    expect($selected['complete'])->toBeTrue()
        ->and($selected['errors'])->toBe([])
        ->and($selected['evidence_vector']['entries'][0]['pending_page'])->not->toBeNull()
        ->and($selected['evidence_vector']['entries'][0]['pending_page']['page_evidence_id'])->toBe($evidenceId);
});
