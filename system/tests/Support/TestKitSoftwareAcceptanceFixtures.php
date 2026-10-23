<?php

declare(strict_types=1);

use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\RecordOmrDiagnosticItemReview;
use App\Assessment\Actions\Omr\Operational\StartOmrDiagnosticRun;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Actions\RecordReviewedTestKitAcceptancePlan;
use App\Assessment\Actions\RecordTestKitAcceptanceReceipt;
use App\Assessment\Actions\SelectTestKitAcceptanceReceipt;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/TestKitHelpers.php';

function testKitInstallSyntheticAcceptanceSourceOverride(string $sourceKey): void
{
    if ($sourceKey !== 'two-item-count-opaque-swapped-v1') {
        return;
    }

    $fixtureRoot = base_path('tests/Fixtures/TestKit');
    $sourcePath = $fixtureRoot.'/'.$sourceKey.'.json';
    $reviewPath = $fixtureRoot.'/'.$sourceKey.'.review.json';
    $reviewerPath = $fixtureRoot.'/'.$sourceKey.'.review.md';
    foreach ([$sourcePath, $reviewPath, $reviewerPath] as $path) {
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('The synthetic opaque acceptance source requires separately reviewed checked-in evidence.');
        }
    }
    $review = json_decode(file_get_contents($reviewPath), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($review) || ! is_string($review['reviewer_reference'] ?? null) || ! is_string($review['reviewer_name'] ?? null)) {
        throw new RuntimeException('The synthetic opaque acceptance source review evidence is malformed.');
    }

    $sourceSha256 = hash_file('sha256', $sourcePath);
    $registry = (array) config('test-kit.acceptance_sources', []);
    $registry[$sourceKey] = [
        'provenance_kind' => 'synthetic_software_fixture',
        'source_sha256' => $sourceSha256,
        'review_evidence_sha256' => hash_file('sha256', $reviewPath),
        'reviewer_evidence_sha256' => hash_file('sha256', $reviewerPath),
        'author_reference' => 'repository-artifact:tests/Fixtures/TestKit/'.$sourceKey.'.json; AI-authored software-only fixture',
        'truth_reference' => 'tests/Fixtures/TestKit/'.$sourceKey.'.json',
        'truth_sha256' => $sourceSha256,
        'truth_attributed_to' => 'AI-authored synthetic software response cases; independent AI software review in the bound review artifact; no institutional or physical qualification',
        'review_evidence_reference' => 'tests/Fixtures/TestKit/'.$sourceKey.'.review.json',
        'reviewer_reference' => 'tests/Fixtures/TestKit/'.$sourceKey.'.review.md',
    ];
    config(['test-kit.acceptance_sources' => $registry]);
}

/**
 * @param  array<string, mixed>  $fixture
 * @param  list<string>  $includedCaseIds
 * @param  array<string, array<string, string|null>>  $caseResponseOverrides
 * @return array{run: OmrDiagnosticRun, planRecord: TestKitAcceptancePlanRecord, capturesByCase: array<string, OmrDiagnosticCapture>, set: mixed}
 */
function testKitDiagnosticEvidenceFixture(
    array $fixture,
    User $actor,
    Institution $institution,
    array $includedCaseIds = ['correct', 'incorrect', 'blank', 'mixed'],
    array $caseResponseOverrides = [],
): array {
    $version = TestKitVersion::query()
        ->with(['templateVersion', 'bookletVersion', 'bookletSet', 'testInstrumentVersion'])
        ->findOrFail((int) $fixture['version']->id);
    $plans = app(TestKitAcceptancePlan::class);
    $sourceKey = (string) ($fixture['acceptance_source_key'] ?? 'two-item-count-native-v1');
    testKitInstallSyntheticAcceptanceSourceOverride($sourceKey);
    $source = $plans->loadReviewedSource($sourceKey);
    app(TestKitManifestBuilder::class)->verify($version);
    $frozen = $plans->freeze($version, [
        ...$source['authored_plan'],
        'truth_source' => $source['truth_source'],
        'source_evidence' => $source['source_evidence'],
    ]);
    $planRecord = TestKitAcceptancePlanRecord::query()
        ->where('test_kit_version_id', $version->getKey())
        ->where('source_key', $source['source_key'])
        ->where('authored_source_sha256', $source['source_sha256'])
        ->where('manifest_sha256', $version->manifest_sha256)
        ->where('protocol_schema_version', $frozen['acceptance_protocol_schema_version'])
        ->where('protocol_sha256', $frozen['acceptance_protocol_sha256'])
        ->where('plan_schema_version', $frozen['acceptance_plan_schema_version'])
        ->where('plan_sha256', $frozen['acceptance_plan_sha256'])
        ->where('provenance_kind', $source['provenance_kind'])
        ->where('author_reference', $source['author_reference'])
        ->where('review_evidence_reference', $source['review_evidence_reference'])
        ->where('review_evidence_sha256', $source['review_evidence_sha256'])
        ->get()
        ->first(static fn (TestKitAcceptancePlanRecord $record): bool => $record->plan_payload === $frozen['acceptance_plan_json']
            && $record->truth_source_references_json === $source['truth_source_references']);
    if ($planRecord === null) {
        $planRecord = app(RecordReviewedTestKitAcceptancePlan::class)->handle(
            actor: $actor,
            testKitVersionId: (int) $version->id,
            sourceKey: $sourceKey,
            expectedRevision: (int) $version->revision,
            idempotencyKey: (string) Str::uuid(),
        );
    }
    $fixture['version'] = $version;
    $plan = (array) $planRecord->plan_payload;
    $itemBindings = $plan['item_bindings'] ?? null;
    if (! is_array($itemBindings) || ! array_is_list($itemBindings)) {
        throw new RuntimeException('The frozen diagnostic plan has no ordered exact item bindings.');
    }
    $bindingsByPageAndLabel = [];
    foreach ($itemBindings as $binding) {
        if (! is_array($binding)
            || ! is_int($binding['page_no'] ?? null)
            || ! is_string($binding['native_label'] ?? null)
            || $binding['native_label'] === ''
            || ! is_array($binding['option_tokens'] ?? null)
            || ! array_is_list($binding['option_tokens'])
            || $binding['option_tokens'] === []
            || array_filter($binding['option_tokens'], static fn (mixed $token): bool => ! is_string($token) || $token === '') !== []
            || count(array_unique($binding['option_tokens'], SORT_STRING)) !== count($binding['option_tokens'])) {
            throw new RuntimeException('The frozen diagnostic plan has an invalid exact item binding.');
        }
        $bindingKey = $binding['page_no'].':'.$binding['native_label'];
        if (isset($bindingsByPageAndLabel[$bindingKey])) {
            throw new RuntimeException('The frozen diagnostic plan contains an ambiguous page and native-label binding.');
        }
        $bindingsByPageAndLabel[$bindingKey] = $binding;
    }
    $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
    $actor->staffProfile()->update(['active_cycle_code' => $cycle->code]);
    $run = app(StartOmrDiagnosticRun::class)->handle(
        templateVersion: null,
        bookletVersion: null,
        declaredSetCode: null,
        actor: $actor,
        institutionId: (int) $institution->id,
        academicCycleId: (int) $cycle->id,
        testKitVersion: $fixture['version'],
        acceptancePlan: $planRecord,
    );
    $set = app(StartOmrPendingCaptureSet::class)->forDiagnostic(run: $run, actor: $actor, idempotencyKey: (string) Str::uuid());
    $capturesByCase = [];
    $reviewAction = app(RecordOmrDiagnosticItemReview::class);

    foreach ($plan['cases'] as $case) {
        $caseId = (string) $case['case_id'];
        if (! in_array($caseId, $includedCaseIds, true)) {
            continue;
        }
        $page = $case['pages'][0];
        $pageNo = (int) $page['page_no'];
        $inputHash = hash('sha256', 'synthetic_acceptance_evidence:asset:'.$caseId);
        $asset = OmrTemplateAsset::query()->create([
            'template_version_id' => (int) $fixture['sheet']->id,
            'kind' => OmrTemplateAsset::KIND_EVIDENCE,
            'page_no' => $pageNo,
            'disk' => 'sensitive',
            'path' => 'evidence/synthetic_acceptance_'.$run->id.'_'.$caseId.'.png',
            'mime' => 'image/png',
            'bytes' => 128,
            'sha256' => $inputHash,
            'metadata' => ['synthetic_namespace' => 'synthetic_acceptance_evidence'],
        ]);
        $evidenceHash = hash('sha256', 'synthetic_acceptance_evidence:evidence:'.$caseId);
        $capture = OmrDiagnosticCapture::query()->create([
            'diagnostic_run_id' => (int) $run->id,
            'client_capture_id' => (string) Str::uuid(),
            'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
            'version' => 1,
            'input_asset_id' => (int) $asset->id,
            'input_asset_hash' => $inputHash,
            'evidence_json' => ['evidence_hash' => $evidenceHash],
            'evidence_hash' => $evidenceHash,
            'detected_answers_json' => [],
            'effective_answers_json' => [],
            'created_by_user_id' => (int) $actor->id,
            'captured_at' => now(),
        ]);
        $mappedIdentities = [];
        foreach ($page['responses'] as $idx => $response) {
            $label = (string) $response['native_label'];
            $bindingKey = $pageNo.':'.$label;
            $binding = $bindingsByPageAndLabel[$bindingKey] ?? null;
            if (! is_array($binding)) {
                throw new RuntimeException('A frozen diagnostic response has no unique exact page/native-label binding.');
            }
            $optionTokens = $binding['option_tokens'];
            $token = array_key_exists($label, $caseResponseOverrides[$caseId] ?? [])
                ? $caseResponseOverrides[$caseId][$label]
                : $response['token'];
            if ($token !== null && (! is_string($token) || ! in_array($token, $optionTokens, true))) {
                throw new RuntimeException('A synthetic diagnostic response is outside its exact frozen binding token domain.');
            }
            $itemKey = $caseId.'-'.$label;
            $itemHash = hash('sha256', 'synthetic_acceptance_evidence:item:'.$caseId.':'.$idx);
            $item = OmrDiagnosticItemEvidence::query()->create([
                'diagnostic_capture_id' => (int) $capture->id,
                'item_key' => $itemKey,
                'page_no' => $pageNo,
                'evidence_hash' => $itemHash,
                'evidence_version' => 'v1',
                'option_evidence_json' => array_map(
                    static fn (string $option): array => ['code' => $option, 'option' => $option],
                    $optionTokens,
                ),
                'machine_proposal_json' => [
                    'state' => $token === null ? 'blank' : 'marked',
                    'selected_option' => $token,
                    'options' => $optionTokens,
                    'observed_options' => $token === null ? [] : [$token],
                ],
            ]);
            $freshRun = $run->fresh();
            $freshCapture = $capture->fresh();
            $reviewAction->handle(
                itemEvidence: $item,
                decision: $token === null ? OmrReviewDecision::Blank : OmrReviewDecision::SetOption,
                expectedRunRevision: (int) $freshRun->revision,
                expectedCaptureVersion: (int) $freshCapture->version,
                expectedItemRevision: 0,
                expectedEvidenceVersion: 1,
                reviewedEvidenceHash: $itemHash,
                idempotencyKey: 'review-'.$caseId.'-'.$idx.'-'.Str::random(8),
                actor: $actor,
                finalOption: $token,
                reason: 'synthetic_software_review_verification',
            );
            $mappedIdentities[] = array_replace($binding, ['item_key' => $itemKey]);
        }
        $attemptId = DB::table('test_kit_diagnostic_page_attempts')->insertGetId([
            'diagnostic_run_id' => (int) $run->id,
            'case_id' => $caseId,
            'page_no' => $pageNo,
            'diagnostic_capture_id' => (int) $capture->id,
            'input_sha256' => $inputHash,
            'item_bindings_json' => json_encode($mappedIdentities, JSON_THROW_ON_ERROR),
            'created_by_user_id' => (int) $actor->id,
            'created_at' => now(),
        ]);
        DB::table('test_kit_diagnostic_page_selections')->insert([
            'diagnostic_run_id' => (int) $run->id,
            'case_id' => $caseId,
            'page_no' => $pageNo,
            'selected_attempt_id' => $attemptId,
            'revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $capturesByCase[$caseId] = $capture->fresh();
    }

    return ['run' => $run->fresh(), 'planRecord' => $planRecord, 'capturesByCase' => $capturesByCase, 'set' => $set];
}

/** @param array<string, mixed> $fixture @return array{fixture: array<string, mixed>, receipt: TestKitAcceptanceReceipt, run: OmrDiagnosticRun} */
function testKitAcceptedSoftwareFixture(array $fixture, User $actor, Institution $institution): array
{
    $prepared = testKitDiagnosticEvidenceFixture($fixture, $actor, $institution);
    $run = $prepared['run'];
    app(CompareOmrDiagnosticRun::class)->handle(run: $run, actor: $actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(run: $run, actor: $actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    $result = app(RecordTestKitAcceptanceReceipt::class)->handle(actor: $actor, version: $fixture['version'], run: $run, expectedRunRevision: (int) $run->revision);
    if ($result['result'] !== TestKitAcceptanceReceipt::RESULT_PASSED) {
        throw new RuntimeException('Synthetic acceptance fixture did not produce a passing receipt.');
    }
    $version = $fixture['version']->fresh();
    $selected = app(SelectTestKitAcceptanceReceipt::class)->handle(
        actor: $actor,
        testKitId: (int) $fixture['kit']->id,
        versionId: (int) $version->id,
        receiptIdOrInput: (int) $result['id'],
        expectedRevision: (int) $version->revision,
        idempotencyKey: (string) Str::uuid(),
    );

    return ['fixture' => [...$fixture, 'version' => $selected], 'receipt' => TestKitAcceptanceReceipt::query()->findOrFail((int) $result['id']), 'run' => $run];
}
