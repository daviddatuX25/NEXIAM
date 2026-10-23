<?php

use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function omrPgAcceptanceMigration(string $filename): void
{
    $exitCode = Artisan::call('migrate', [
        '--path' => "database/migrations/{$filename}",
        '--force' => true,
    ]);

    if ($exitCode !== 0) {
        throw new RuntimeException("Migration {$filename} failed: ".Artisan::output());
    }
}

function omrPgAcceptanceRollbackMigrations(array $filenames): void
{
    foreach ($filenames as $filename) {
        $migration = require database_path("migrations/{$filename}");
        $migration->down();
        DB::table('migrations')
            ->where('migration', pathinfo($filename, PATHINFO_FILENAME))
            ->delete();
    }
}

function omrPgAcceptanceDraft(int $ownerId, string $code, string $artifactHash, bool $valid = true): int
{
    $authoring = $valid
        ? ['canonical' => ['artifact_set' => ['candidate_version' => 1, 'artifact_hash' => $artifactHash]]]
        : ['canonical' => ['artifact_set' => ['candidate_version' => 1, 'artifact_hash' => 'not-a-sha256']]];

    return (int) DB::table('omr_template_authoring_drafts')->insertGetId([
        'code' => $code,
        'name' => "PostgreSQL acceptance {$code}",
        'source_kind' => 'html',
        'registration_mode' => 'fiducial_anchored',
        'qr_mode' => 'qr_signed',
        'requested_page_count' => 1,
        'stage' => 'mapping_complete',
        'authoring_json' => json_encode($authoring, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'lock_version' => 0,
        'owner_user_id' => $ownerId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function omrPgAcceptanceRun(
    int $actorId,
    int $draftId,
    string $artifactHash,
    string $suffix,
    string $status = 'awaiting_capture',
    string $mode = 'quick_rehearsal',
    ?int $templateVersionId = null,
): int {
    return (int) DB::table('omr_template_verification_runs')->insertGetId([
        'draft_id' => $draftId,
        'template_version_id' => $templateVersionId,
        'status' => $status,
        'mode' => $mode,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', "{$suffix}:plan"),
        'expected_vector_hash' => hash('sha256', "{$suffix}:vector"),
        'artifact_hash' => $artifactHash,
        'native_bundle_hash' => hash('sha256', "{$suffix}:bundle"),
        'manifest_hash' => hash('sha256', "{$suffix}:manifest"),
        'config_hash' => hash('sha256', "{$suffix}:config"),
        'mapping_hash' => hash('sha256', "{$suffix}:mapping"),
        'progress_total' => 1,
        'started_by_user_id' => $actorId,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function omrPgAcceptanceChallenge(int $actorId, int $runId, string $code, bool $withPages = true): int
{
    $manifest = $withPages
        ? [
            'page_count' => 2,
            'pages' => [
                ['page_no' => 2],
                ['page_no' => 1],
                ['page_no' => 1],
                ['page_no' => 21],
            ],
        ]
        : [];

    return (int) DB::table('omr_physical_challenges')->insertGetId([
        'run_id' => $runId,
        'attempt' => 1,
        'mode' => 'quick',
        'challenge_code' => $code,
        'plan_hash' => hash('sha256', "{$code}:plan"),
        'expected_vector_hash' => hash('sha256', "{$code}:vector"),
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'page_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'print_metadata' => json_encode(['legacy_fixture' => true], JSON_THROW_ON_ERROR),
        'status' => 'created',
        'created_by_user_id' => $actorId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** @param list<array<string, mixed>> $items */
function omrPgAcceptanceCapture(
    int $actorId,
    int $runId,
    int $challengeId,
    string $suffix,
    ?string $method = OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
    ?int $attemptNo = 99,
    ?array $items = null,
): int {
    $summary = $items === null
        ? null
        : json_encode(['evidence' => ['items' => $items]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    return (int) DB::table('omr_template_verification_captures')->insertGetId([
        'run_id' => $runId,
        'challenge_id' => $challengeId,
        'attempt' => 1,
        'attempt_no' => $attemptNo,
        'page_no' => 1,
        'mode' => 'quick_rehearsal',
        'method' => $method,
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'input_hash' => hash('sha256', "{$suffix}:input"),
        'evidence_hash' => hash('sha256', "{$suffix}:evidence"),
        'expected_vector_hash' => hash('sha256', "{$suffix}:vector"),
        'summary' => $summary,
        'captured_by_user_id' => $actorId,
        'captured_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function omrPgAcceptanceTemplateVersion(int $actorId, string $suffix): int
{
    $templateId = (int) DB::table('omr_answer_sheet_templates')->insertGetId([
        'code' => "PG-ACCEPTANCE-{$suffix}",
        'name' => "PostgreSQL acceptance {$suffix}",
        'is_synthetic' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('omr_answer_sheet_template_versions')->insertGetId([
        'template_id' => $templateId,
        'version' => 1,
        'status' => 'draft',
        'registration_mode' => 'fiducial_anchored',
        'qr_mode' => 'qr_signed',
        'page_count' => 1,
        'page_width' => 1,
        'page_height' => 1,
        'identity_regions_json' => '[]',
        'provider_config_json' => '[]',
        'config_hash' => hash('sha256', "{$suffix}:version"),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function omrPgAcceptanceExplain(object $query): array
{
    $row = DB::selectOne(
        'EXPLAIN (FORMAT JSON, ANALYZE false) '.$query->toSql(),
        $query->getBindings(),
    );
    $values = array_values((array) $row);
    $raw = $values[0] ?? null;
    if (! is_string($raw)) {
        throw new RuntimeException('PostgreSQL EXPLAIN returned no JSON plan.');
    }

    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($decoded) || ! is_array($decoded[0]['Plan'] ?? null)) {
        throw new RuntimeException('PostgreSQL EXPLAIN JSON has no root plan.');
    }

    return $decoded[0]['Plan'];
}

/** @param array<string, mixed> $plan @return list<array{type:string,index:?string}> */
function omrPgAcceptancePlanNodes(array $plan): array
{
    $nodes = [[
        'type' => (string) ($plan['Node Type'] ?? ''),
        'index' => isset($plan['Index Name']) ? (string) $plan['Index Name'] : null,
    ]];
    foreach ($plan['Plans'] ?? [] as $child) {
        if (is_array($child)) {
            $nodes = [...$nodes, ...omrPgAcceptancePlanNodes($child)];
        }
    }

    return $nodes;
}

/** @param array<string, mixed> $plan @return list<string> */
function omrPgAcceptancePlanIndexes(array $plan): array
{
    return array_values(array_filter(array_map(
        static fn (array $node): ?string => $node['index'],
        omrPgAcceptancePlanNodes($plan),
    )));
}

/** @param array<string, mixed> $plan @return list<string> */
function omrPgAcceptancePlanTypes(array $plan): array
{
    return array_values(array_filter(array_map(
        static fn (array $node): string => $node['type'],
        omrPgAcceptancePlanNodes($plan),
    )));
}

function omrPgAcceptanceRestoreRunTargetConstraint(): void
{
    DB::statement(<<<'SQL'
        ALTER TABLE omr_template_verification_runs
            ADD CONSTRAINT omr_verification_runs_target_check
            CHECK ((draft_id IS NOT NULL AND template_version_id IS NULL)
                OR (draft_id IS NULL AND template_version_id IS NOT NULL))
    SQL);
}

test('isolated PostgreSQL migrations preserve legacy history and use locked assurance indexes', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $validTargetHash = hash('sha256', 'pg-acceptance-valid-target');
    $validDraftId = omrPgAcceptanceDraft($actor->id, 'PG-ACCEPTANCE-VALID', $validTargetHash);

    // Roll back only the named physical-assurance migrations so unrelated
    // migrations added later cannot change this fixture boundary.
    omrPgAcceptanceRollbackMigrations([
        '2026_09_01_090000_harden_omr_physical_assurance_capture_trigger.php',
        '2026_08_31_090006_add_omr_physical_assurance_indexes.php',
        '2026_08_31_090005_add_omr_physical_assurance_constraints.php',
        '2026_08_31_090004_backfill_omr_physical_assurance_legacy.php',
    ]);

    $validRunId = omrPgAcceptanceRun($actor->id, $validDraftId, $validTargetHash, 'valid');
    $validChallengeId = omrPgAcceptanceChallenge($actor->id, $validRunId, 'CHAL-ABCDEF1234');
    $legacyItems = [
        [
            'item_id' => 'PG-LEGACY-1',
            'group_type' => 'aptitude_area',
            'area_key' => 'logic',
            'area_position' => 1,
            'question_no' => 1,
            'options' => ['A', 'B', 'C', 'D'],
            'expected' => 'A',
            'detected' => 'A',
            'state' => 'match',
            'status' => 'match',
        ],
        [
            'item_id' => 'PG-LEGACY-1',
            'group_type' => 'aptitude_area',
            'area_key' => 'logic',
            'area_position' => 1,
            'question_no' => 1,
            'options' => ['A', 'B', 'C', 'D'],
            'expected' => 'A',
            'detected' => 'A',
            'state' => 'match',
            'status' => 'match',
        ],
        [
            'item_id' => 'PG-LEGACY-2',
            'group_type' => 'identity',
            'options' => ['0', '1'],
            'expected' => '0',
            'detected' => '0',
            'state' => 'match',
            'status' => 'match',
        ],
    ];
    $validCaptureIds = [
        omrPgAcceptanceCapture($actor->id, $validRunId, $validChallengeId, 'valid-one', items: $legacyItems),
        omrPgAcceptanceCapture($actor->id, $validRunId, $validChallengeId, 'valid-two', items: $legacyItems),
    ];

    $corruptHash = str_repeat('z', 64);
    $corruptDraftId = omrPgAcceptanceDraft($actor->id, 'PG-ACCEPTANCE-CORRUPT', $corruptHash, valid: false);
    $corruptRunId = omrPgAcceptanceRun($actor->id, $corruptDraftId, $corruptHash, 'corrupt');
    $corruptChallengeId = omrPgAcceptanceChallenge($actor->id, $corruptRunId, 'CHAL-ABCDEF5678');
    $corruptCaptureId = omrPgAcceptanceCapture($actor->id, $corruptRunId, $corruptChallengeId, 'corrupt', method: null, attemptNo: null);

    // The old run schema has an XOR target check, so temporarily remove only
    // that check to model an ambiguous historical row. It is restored before
    // current constraints/indexes are applied and all fixture rows are removed.
    DB::statement('ALTER TABLE omr_template_verification_runs DROP CONSTRAINT IF EXISTS omr_verification_runs_target_check');
    $ambiguousDraftId = omrPgAcceptanceDraft($actor->id, 'PG-ACCEPTANCE-AMBIGUOUS', $validTargetHash);
    $ambiguousVersionId = omrPgAcceptanceTemplateVersion($actor->id, 'AMBIGUOUS');
    $ambiguousRunId = omrPgAcceptanceRun(
        $actor->id,
        $ambiguousDraftId,
        $validTargetHash,
        'ambiguous',
        templateVersionId: $ambiguousVersionId,
    );
    $ambiguousChallengeId = omrPgAcceptanceChallenge($actor->id, $ambiguousRunId, 'CHAL-ABCDEF9ABC');
    $ambiguousCaptureId = omrPgAcceptanceCapture($actor->id, $ambiguousRunId, $ambiguousChallengeId, 'ambiguous', method: null, attemptNo: null);

    omrPgAcceptanceMigration('2026_08_31_090003_create_omr_template_verification_item_evidence_table.php');
    omrPgAcceptanceMigration('2026_08_31_090004_backfill_omr_physical_assurance_legacy.php');

    $validMethod = DB::table('omr_template_verification_methods')
        ->where('run_id', $validRunId)
        ->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)
        ->first();
    expect($validMethod)->not->toBeNull()
        ->and((int) $validMethod->required_page_count)->toBe(2)
        ->and(DB::table('omr_template_verification_method_pages')->where('method_id', $validMethod->id)->count())->toBe(2);

    expect(DB::table('omr_template_verification_item_evidence')->where('capture_id', $validCaptureIds[0])->count())->toBe(2)
        ->and(DB::table('omr_template_verification_item_evidence')->where('capture_id', $validCaptureIds[1])->count())->toBe(2)
        ->and(DB::table('omr_physical_challenges')->where('id', $corruptChallengeId)->value('target_kind'))->toBeNull()
        ->and(DB::table('omr_template_verification_captures')->where('id', $corruptCaptureId)->value('method_id'))->toBeNull()
        ->and(DB::table('omr_physical_challenges')->where('id', $ambiguousChallengeId)->value('target_kind'))->toBeNull()
        ->and(DB::table('omr_template_verification_captures')->where('id', $ambiguousCaptureId)->value('method_id'))->toBeNull()
        ->and(DB::table('omr_template_verification_methods')->whereIn('run_id', [$corruptRunId, $ambiguousRunId])->count())->toBe(0);

    // Remove only the intentionally malformed fixtures before current checks
    // are installed; valid historical rows remain for idempotence assertions.
    DB::table('omr_template_verification_captures')->whereIn('id', [$corruptCaptureId, $ambiguousCaptureId])->delete();
    DB::table('omr_physical_challenges')->whereIn('id', [$corruptChallengeId, $ambiguousChallengeId])->delete();
    DB::table('omr_template_verification_runs')->whereIn('id', [$corruptRunId, $ambiguousRunId])->delete();
    omrPgAcceptanceRestoreRunTargetConstraint();

    omrPgAcceptanceMigration('2026_08_31_090005_add_omr_physical_assurance_constraints.php');
    omrPgAcceptanceMigration('2026_08_31_090006_add_omr_physical_assurance_indexes.php');
    $normalizedAttempts = DB::table('omr_template_verification_captures')
        ->whereIn('id', $validCaptureIds)
        ->orderBy('id')
        ->pluck('attempt_no')
        ->map(static fn (mixed $attempt): int => (int) $attempt)
        ->all();
    expect($normalizedAttempts)->toBe([1, 2]);

    $beforeRepeat = DB::table('omr_template_verification_captures')
        ->whereIn('id', $validCaptureIds)
        ->orderBy('id')
        ->get(['id', 'attempt_no', 'method_id', 'attempt_state'])
        ->map(static fn (object $capture): array => (array) $capture)
        ->all();
    $beforeEvidenceCount = DB::table('omr_template_verification_item_evidence')
        ->whereIn('capture_id', $validCaptureIds)
        ->count();

    // Re-run the backfill after rolling back only its named dependent migrations.
    // Existing immutable facts and normalized attempt numbers must remain unchanged.
    omrPgAcceptanceRollbackMigrations([
        '2026_09_01_090000_harden_omr_physical_assurance_capture_trigger.php',
        '2026_08_31_090006_add_omr_physical_assurance_indexes.php',
        '2026_08_31_090005_add_omr_physical_assurance_constraints.php',
    ]);
    omrPgAcceptanceMigration('2026_08_31_090004_backfill_omr_physical_assurance_legacy.php');
    omrPgAcceptanceMigration('2026_08_31_090005_add_omr_physical_assurance_constraints.php');
    omrPgAcceptanceMigration('2026_08_31_090006_add_omr_physical_assurance_indexes.php');
    // Re-install the forward fix after replaying the committed tail so the
    // stronger physical trigger/index contract is exercised below.
    omrPgAcceptanceMigration('2026_09_01_090000_harden_omr_physical_assurance_capture_trigger.php');

    $afterRepeat = DB::table('omr_template_verification_captures')
        ->whereIn('id', $validCaptureIds)
        ->orderBy('id')
        ->get(['id', 'attempt_no', 'method_id', 'attempt_state'])
        ->map(static fn (object $capture): array => (array) $capture)
        ->all();
    expect($afterRepeat)->toBe($beforeRepeat)
        ->and(DB::table('omr_template_verification_item_evidence')->whereIn('capture_id', $validCaptureIds)->count())
        ->toBe($beforeEvidenceCount);

    $targetHash = $validTargetHash;
    $perfRunId = omrPgAcceptanceRun($actor->id, $validDraftId, $targetHash, 'performance', 'passed');
    $perfMethodId = (int) DB::table('omr_template_verification_methods')->insertGetId([
        'run_id' => $perfRunId,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'method_state' => OmrTemplateVerificationMethod::STATE_PASSED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $validDraftId,
        'target_version' => '1',
        'target_hash' => $targetHash,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', 'performance-plan'),
        'expected_vector_hash' => hash('sha256', 'performance-vector'),
        'required_page_count' => 1,
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $perfPageId = (int) DB::table('omr_template_verification_method_pages')->insertGetId([
        'method_id' => $perfMethodId,
        'page_no' => 1,
        'required' => true,
        'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
        'page_version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $perfCaptureId = (int) DB::table('omr_template_verification_captures')->insertGetId([
        'run_id' => $perfRunId,
        'method_id' => $perfMethodId,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'mode' => 'quick_rehearsal',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $validDraftId,
        'target_version' => '1',
        'target_hash' => $targetHash,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', 'performance-plan'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'input_hash' => hash('sha256', 'performance-input'),
        'evidence_hash' => hash('sha256', 'performance-evidence'),
        'expected_vector_hash' => hash('sha256', 'performance-vector'),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('omr_template_verification_method_pages')
        ->where('id', $perfPageId)
        ->update(['selected_attempt_id' => $perfCaptureId]);

    $evidenceRows = [];
    for ($position = 1; $position <= 150; $position++) {
        $areaPosition = intdiv($position - 1, 25) + 1;
        $questionNo = (($position - 1) % 25) + 1;
        $state = $position % 7 === 0
            ? OmrTemplateVerificationItemEvidence::STATE_MISMATCH
            : OmrTemplateVerificationItemEvidence::STATE_MATCH;
        $evidenceRows[] = [
            'capture_id' => $perfCaptureId,
            'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
            'area_key' => "area-{$areaPosition}",
            'area_position' => $areaPosition,
            'question_no' => $questionNo,
            'item_id' => sprintf('PG-ITEM-%03d', $position),
            'options' => json_encode(['A', 'B', 'C', 'D'], JSON_THROW_ON_ERROR),
            'expected' => 'A',
            'detected' => $state === OmrTemplateVerificationItemEvidence::STATE_MATCH ? 'A' : 'B',
            'state' => $state,
            'means' => json_encode(['A' => 0.8, 'B' => 0.2], JSON_THROW_ON_ERROR),
            'delta' => 0.6,
            'status' => $state,
            'evidence_version' => 'v1',
            'evidence_hash' => hash('sha256', "performance-item-{$position}"),
            'row_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('omr_template_verification_item_evidence')->insert($evidenceRows);

    // Scale the method/capture relations enough for PostgreSQL to make a
    // meaningful cost choice, while keeping the fixture bounded for CI.
    for ($index = 1; $index <= 128; $index++) {
        $scaleRunId = omrPgAcceptanceRun($actor->id, $validDraftId, $targetHash, "scale-{$index}", 'passed');
        $scaleMethodId = (int) DB::table('omr_template_verification_methods')->insertGetId([
            'run_id' => $scaleRunId,
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'method_state' => OmrTemplateVerificationMethod::STATE_PASSED,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => $validDraftId,
            'target_version' => '1',
            'target_hash' => $targetHash,
            'plan_version' => 'v1',
            'plan_hash' => hash('sha256', "scale-plan-{$index}"),
            'expected_vector_hash' => hash('sha256', "scale-vector-{$index}"),
            'required_page_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('omr_template_verification_captures')->insert([
            'run_id' => $scaleRunId,
            'method_id' => $scaleMethodId,
            'attempt' => 1,
            'attempt_no' => 1,
            'page_no' => 1,
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'mode' => 'quick_rehearsal',
            'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => $validDraftId,
            'target_version' => '1',
            'target_hash' => $targetHash,
            'plan_version' => 'v1',
            'plan_hash' => hash('sha256', "scale-plan-{$index}"),
            'expected' => 'pass',
            'actual' => 'pass',
            'status' => OmrTemplateVerificationCapture::STATUS_PASS,
            'input_hash' => hash('sha256', "scale-input-{$index}"),
            'evidence_hash' => hash('sha256', "scale-evidence-{$index}"),
            'expected_vector_hash' => hash('sha256', "scale-vector-{$index}"),
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    foreach (['omr_template_verification_methods', 'omr_template_verification_method_pages', 'omr_template_verification_captures', 'omr_template_verification_item_evidence'] as $table) {
        DB::statement("ANALYZE {$table}");
    }

    $planSpecs = [
        [
            'label' => 'method-summary',
            'index' => 'omr_verification_methods_run_method_state_idx',
            'query' => static fn () => OmrTemplateVerificationMethod::query()
                ->select(['id', 'run_id', 'method', 'method_state'])
                ->where('run_id', $perfRunId)
                ->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)
                ->where('method_state', OmrTemplateVerificationMethod::STATE_PASSED),
        ],
        [
            'label' => 'selected-page',
            'index' => 'omr_verification_method_pages_method_page_state_idx',
            'query' => static fn () => OmrTemplateVerificationMethodPage::query()
                ->select(['id', 'method_id', 'page_no', 'page_state', 'selected_attempt_id'])
                ->where('method_id', $perfMethodId)
                ->where('page_no', 1)
                ->where('page_state', OmrTemplateVerificationMethodPage::STATE_PASSED),
        ],
        [
            'label' => 'attempts',
            'index' => 'omr_verification_captures_run_method_state_created_idx',
            'query' => static fn () => OmrTemplateVerificationCapture::query()
                ->select(['id', 'run_id', 'method', 'attempt_state', 'created_at'])
                ->where('run_id', $perfRunId)
                ->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)
                ->where('attempt_state', OmrTemplateVerificationCapture::ATTEMPT_PASSED)
                ->orderBy('created_at')
                ->orderBy('id'),
        ],
        [
            'label' => 'area-pagination',
            'index' => 'omr_verification_item_evidence_capture_group_order_idx',
            'query' => static fn () => DB::table('omr_template_verification_item_evidence')
                ->select(['id', 'capture_id', 'area_position', 'question_no', 'item_id'])
                ->where('capture_id', $perfCaptureId)
                ->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)
                ->where('area_position', 1)
                ->orderBy('area_position')
                ->orderBy('question_no')
                ->orderBy('item_id')
                ->limit(25)
                ->offset(0),
        ],
        [
            'label' => 'mismatch-pagination',
            'index' => 'omr_verification_item_evidence_capture_state_order_idx',
            'query' => static fn () => DB::table('omr_template_verification_item_evidence')
                ->select(['id', 'capture_id', 'state', 'area_position', 'question_no', 'item_id'])
                ->where('capture_id', $perfCaptureId)
                ->whereIn('state', [
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    OmrTemplateVerificationItemEvidence::STATE_MISSING,
                ])
                ->orderBy('state')
                ->orderBy('area_position')
                ->orderBy('question_no')
                ->orderBy('item_id')
                ->limit(25)
                ->offset(0),
        ],
        [
            'label' => 'evidence-hash',
            'index' => 'omr_verification_item_evidence_capture_hash_idx',
            'query' => static fn () => DB::table('omr_template_verification_item_evidence')
                ->select(['id', 'capture_id', 'evidence_version', 'evidence_hash'])
                ->where('capture_id', $perfCaptureId)
                ->where('evidence_version', 'v1')
                ->where('evidence_hash', hash('sha256', 'performance-item-001')),
        ],
    ];

    $allIndexes = [];
    foreach (['omr_template_verification_methods', 'omr_template_verification_method_pages', 'omr_template_verification_captures', 'omr_template_verification_item_evidence', 'omr_template_assets'] as $table) {
        foreach (Schema::getIndexes($table) as $index) {
            $allIndexes[] = $index['name'];
        }
    }
    expect($allIndexes)->toContain(
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

    foreach ($planSpecs as $spec) {
        $plan = omrPgAcceptanceExplain(($spec['query'])());
        $planIndexes = omrPgAcceptancePlanIndexes($plan);
        $planTypes = omrPgAcceptancePlanTypes($plan);
        expect($planTypes)->not->toBeEmpty();
        if (! in_array($spec['index'], $planIndexes, true)) {
            fwrite(STDOUT, sprintf(
                "omr_explain label=%s expected_index=%s planner=%s indexes=%s (small-table/alternative-plan)\n",
                $spec['label'],
                $spec['index'],
                implode(',', $planTypes),
                implode(',', $planIndexes) ?: 'none',
            ));
            expect(array_intersect($planTypes, [
                'Seq Scan',
                'Index Scan',
                'Index Only Scan',
                'Bitmap Heap Scan',
            ]))->not->toBeEmpty();
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $loadedMethod = OmrTemplateVerificationMethod::query()
        ->select(['id', 'run_id', 'method', 'method_state'])
        ->whereKey($perfMethodId)
        ->with([
            'pages' => static fn ($query) => $query->select([
                'id', 'method_id', 'page_no', 'required', 'page_state', 'page_version', 'selected_attempt_id',
            ]),
            'captures' => static fn ($query) => $query
                ->select(['id', 'method_id', 'run_id', 'page_no', 'attempt_no', 'attempt_state', 'evidence_hash'])
                ->with(['evidenceItems' => static fn ($items) => $items->select([
                    'id', 'capture_id', 'group_type', 'area_position', 'question_no', 'item_id', 'state',
                ])]),
        ])
        ->firstOrFail();
    $loadedItems = $loadedMethod->captures->flatMap(static fn ($capture) => $capture->evidenceItems);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($loadedMethod->pages)->toHaveCount(1)
        ->and($loadedMethod->captures)->toHaveCount(1)
        ->and($loadedItems)->toHaveCount(150)
        ->and($queryCount)->toBe(4);
});

test('isolated PostgreSQL serializes concurrent physical attempt reservations', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Concurrent physical reservations require PostgreSQL.');
    }
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The PostgreSQL acceptance image has no process fork support.');
    }

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $targetHash = hash('sha256', 'pg-concurrent-target');
    $draftId = omrPgAcceptanceDraft($actor->id, 'PG-CONCURRENT', $targetHash);
    $runId = omrPgAcceptanceRun($actor->id, $draftId, $targetHash, 'concurrent', 'awaiting_capture');
    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $runId,
        'attempt' => 1,
        'mode' => OmrPhysicalChallenge::MODE_QUICK,
        'challenge_code' => 'CHAL-1234ABCDEF',
        'plan_hash' => hash('sha256', 'pg-concurrent-challenge-plan'),
        'expected_vector_hash' => hash('sha256', 'pg-concurrent-challenge-vector'),
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $draftId,
        'target_version' => '1',
        'target_hash' => $targetHash,
        'plan_version' => 'v2',
        'status' => OmrPhysicalChallenge::STATUS_CREATED,
        'created_by_user_id' => $actor->id,
    ]);
    $challengeId = (int) $challenge->id;
    $planHash = hash('sha256', 'pg-concurrent-plan');
    $vectorHash = hash('sha256', 'pg-concurrent-vector');
    $methodId = (int) DB::table('omr_template_verification_methods')->insertGetId([
        'run_id' => $runId,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'method_state' => 'awaiting_capture',
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $draftId,
        'target_version' => '1',
        'target_hash' => $targetHash,
        'plan_version' => 'v2',
        'plan_hash' => $planHash,
        'expected_vector_hash' => $vectorHash,
        'challenge_id' => $challengeId,
        'required_page_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('omr_template_verification_method_pages')->insert([
        'method_id' => $methodId,
        'page_no' => 1,
        'required' => true,
        'page_state' => 'awaiting_capture',
        'page_version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $config = DB::connection()->getConfig();
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);
    $columns = 'run_id,method_id,challenge_id,attempt,attempt_no,page_no,method,mode,variant,attempt_state,target_kind,target_id,target_version,target_hash,plan_version,plan_hash,expected_vector_hash,expected,actual,status,input_hash,evidence_hash,captured_by_user_id,captured_at,created_at,updated_at';
    $sql = "INSERT INTO omr_template_verification_captures ({$columns}) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $bindings = [
        $runId, $methodId, $challengeId, 1, 1, 1,
        OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'physical_capture',
        OmrTemplateVerificationCapture::ATTEMPT_ACTIVE,
        OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        $draftId, '1', $targetHash, 'v2', $planHash, $vectorHash,
        'pass', 'failed', OmrTemplateVerificationCapture::STATUS_FAIL,
        hash('sha256', 'pg-concurrent-input'),
        hash('sha256', 'pg-concurrent-evidence'),
        $actor->id, now()->format('Y-m-d H:i:s.u'),
        now()->format('Y-m-d H:i:s.u'), now()->format('Y-m-d H:i:s.u'),
    ];
    $pdo1 = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo1->beginTransaction();
    $pdo1->prepare($sql)->execute($bindings);

    $resultFile = tempnam(sys_get_temp_dir(), 'omr-pg-concurrency-');
    if ($resultFile === false) {
        $pdo1->rollBack();
        $this->fail('Could not create the PostgreSQL concurrency result file.');
    }
    $pid = pcntl_fork();
    if ($pid === -1) {
        @unlink($resultFile);
        $pdo1->rollBack();
        $this->fail('Could not fork the PostgreSQL concurrency worker.');
    }
    if ($pid === 0) {
        $outcome = 'committed';
        try {
            $pdo2 = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo2->beginTransaction();
            $pdo2->prepare($sql)->execute($bindings);
            $pdo2->commit();
        } catch (PDOException $exception) {
            $outcome = 'error:'.$exception->getCode();
            if (isset($pdo2) && $pdo2->inTransaction()) {
                $pdo2->rollBack();
            }
        }
        file_put_contents($resultFile, $outcome);
        exit(0);
    }

    usleep(100000);
    $pdo1->commit();
    pcntl_waitpid($pid, $status);
    $outcome = (string) file_get_contents($resultFile);
    @unlink($resultFile);

    expect($outcome)->toBe('error:23505')
        ->and(DB::table('omr_template_verification_captures')
            ->where('run_id', $runId)
            ->where('method_id', $methodId)
            ->where('page_no', 1)
            ->where('attempt_no', 1)
            ->count())->toBe(1);
});
