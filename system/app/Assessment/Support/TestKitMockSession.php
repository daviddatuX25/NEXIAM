<?php

namespace App\Assessment\Support;

use App\Assessment\Omr\Canonical\OmrCanonicalSheetPageResolver;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\TestKitVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class TestKitMockSession
{
    public const MANIFEST_SCHEMA_VERSION = 1;

    public const GENERATOR_VERSION = 'nexiam.test-kit-mock-session.v1';

    private const PATTERN_MODES = ['guided_mix', 'perfect_set_a', 'blank_baseline'];

    public function __construct(
        private readonly KitCompositionContext $composition,
        private readonly AssessmentRawEvaluator $evaluator = new AssessmentRawEvaluator,
        private readonly ScoreConverter $converter = new ScoreConverter,
    ) {}

    /**
     * Freeze deterministic rehearsal instructions for one exact Kit edition.
     *
     * @return array{manifest:array<string,mixed>,mock_guide:array<string,mixed>,sha256:string}
     */
    public function prepare(TestKitVersion $version, int $instanceCount, string $patternMode): array
    {
        if (! in_array($instanceCount, [1, 2, 3], true)) {
            throw new InvalidArgumentException('mock_instance_count_invalid');
        }
        if (! in_array($patternMode, self::PATTERN_MODES, true)) {
            throw new InvalidArgumentException('mock_pattern_mode_invalid');
        }

        $resolved = $this->composition->resolveVersion($version);
        $template = $resolved['template'] ?? null;
        $map = $resolved['item_map'] ?? null;
        if (! is_array($map) || ! is_array($map['bindings'] ?? null)
            || ! is_array($map['items'] ?? null) || ! $template instanceof OmrAnswerSheetTemplateVersion) {
            throw new RuntimeException('mock_exact_omr_composition_required');
        }

        $bindings = $map['bindings'];
        usort($bindings, static fn (array $left, array $right): int => [
            (int) $left['global_position'], (int) $left['page_no'], (int) $left['item_id'],
        ] <=> [
            (int) $right['global_position'], (int) $right['page_no'], (int) $right['item_id'],
        ]);
        $keyDigest = (string) ($resolved['set']?->answer_key_hash ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $keyDigest) !== 1) {
            throw new RuntimeException('mock_set_a_key_digest_missing');
        }

        $itemsById = [];
        foreach ($map['items'] as $mapItem) {
            $itemsById[(int) $mapItem['item_id']] = $mapItem;
        }
        $scored = array_values(array_filter($bindings, static fn (array $binding): bool => ($itemsById[(int) $binding['item_id']]['is_scored'] ?? false) === true
            && is_string($binding['correct_token'] ?? null)
        ));
        if ($patternMode === 'guided_mix') {
            $eligible = array_values(array_filter($scored, static fn (array $binding): bool => count((array) ($binding['option_tokens'] ?? [])) >= 2));
            if (count($eligible) < 3) {
                throw new InvalidArgumentException('mock_guided_mix_unavailable');
            }
        }

        $normSelection = $this->normSelection($resolved);
        $instances = [];
        for ($instanceOrdinal = 1; $instanceOrdinal <= $instanceCount; $instanceOrdinal++) {
            $instanceId = sprintf('instance-%03d', $instanceOrdinal);
            $instructions = [];
            $scoredOrdinal = 0;
            foreach ($bindings as $binding) {
                $item = $itemsById[(int) $binding['item_id']];
                $tokens = array_values((array) $binding['option_tokens']);
                $isScored = (bool) ($item['is_scored'] ?? false);
                if ($isScored) {
                    $scoredOrdinal++;
                }
                $correct = $binding['correct_token'] ?? null;
                $intent = 'blank';
                $targets = [];
                if ($isScored && is_string($correct) && $patternMode !== 'blank_baseline') {
                    if ($patternMode === 'perfect_set_a') {
                        $intent = 'correct';
                        $targets = [$correct];
                    } else {
                        $slot = ($scoredOrdinal + $instanceOrdinal - 1) % 10;
                        $intent = match ($slot) {
                            0 => 'wrong',
                            1 => 'double',
                            default => 'correct',
                        };
                        $next = $this->nextToken($tokens, $correct);
                        $targets = match ($intent) {
                            'wrong' => [$next],
                            'double' => [$correct, $next],
                            default => [$correct],
                        };
                    }
                }
                $instructions[] = [
                    'page_no' => (int) $binding['page_no'],
                    'native_label' => (string) $binding['native_label'],
                    'item_id' => (int) $binding['item_id'],
                    'subtest_id' => (int) $binding['subtest_id'],
                    'global_position' => (int) $binding['global_position'],
                    'option_tokens' => $tokens,
                    'target_tokens' => $targets,
                    'intent' => $intent,
                    'expected_effective_state' => $intent === 'double' ? 'ambiguous' : ($intent === 'blank' ? 'blank' : 'single_mark'),
                    'is_scored' => $isScored,
                ];
            }
            if ($patternMode === 'guided_mix') {
                $scorableInstructions = array_values(array_filter($instructions, static fn (array $instruction): bool => $instruction['is_scored']));
                $intents = array_unique(array_column($scorableInstructions, 'intent'));
                if (count($intents) < 3) {
                    $reserved = ['correct', 'wrong', 'double'];
                    foreach ($instructions as &$instruction) {
                        if (! $instruction['is_scored'] || count($instruction['option_tokens']) < 2 || count($reserved) === 0) {
                            continue;
                        }
                        $desired = array_shift($reserved);
                        $instruction['intent'] = $desired;
                        $instruction['target_tokens'] = match ($desired) {
                            'wrong' => [$this->nextToken($instruction['option_tokens'], $this->keyForBinding($bindings, $instruction['item_id']))],
                            'double' => [
                                $this->keyForBinding($bindings, $instruction['item_id']),
                                $this->nextToken($instruction['option_tokens'], $this->keyForBinding($bindings, $instruction['item_id'])),
                            ],
                            default => [$this->keyForBinding($bindings, $instruction['item_id'])],
                        };
                        $instruction['expected_effective_state'] = $desired === 'double' ? 'ambiguous' : 'single_mark';
                    }
                    unset($instruction);
                    if ($reserved !== []) {
                        throw new InvalidArgumentException('mock_guided_mix_unavailable');
                    }
                }
            }
            $instances[] = [
                'instance_id' => $instanceId,
                'ordinal' => $instanceOrdinal,
                'label' => 'Mock Instance '.$instanceOrdinal,
                'pages' => array_map(static fn (int $pageNo): array => [
                    'page_no' => $pageNo,
                    'instructions' => array_values(array_filter($instructions, static fn (array $instruction): bool => $instruction['page_no'] === $pageNo)),
                ], range(1, (int) $template->page_count)),
            ];
        }

        $manifest = [
            'schema_version' => self::MANIFEST_SCHEMA_VERSION,
            'generator_version' => self::GENERATOR_VERSION,
            'diagnostic_purpose' => 'mock_rehearsal',
            'test_kit_id' => (int) $version->test_kit_id,
            'test_kit_version_id' => (int) $version->id,
            'test_kit_manifest_sha256' => (string) $version->manifest_sha256,
            'composition_sha256' => (string) $resolved['composition_sha256'],
            'template_version_id' => (int) $template->id,
            'booklet_version_id' => (int) $resolved['booklet']->id,
            'booklet_set_id' => (int) $resolved['set']->id,
            'profile_content_sha256' => (string) $resolved['profile']->content_sha256,
            'test_setup_form_code' => $resolved['profile']->form_code,
            'set_a_key_sha256' => $keyDigest,
            'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
            'expected_worker_identity' => $this->workerIdentity($template),
            'registration_mode' => $this->enumValue($template->registration_mode),
            'qr_mode' => $this->enumValue($template->qr_mode),
            'source_policy' => data_get($version->manifest_json, 'sheet.source_kind'),
            'page_count' => (int) $template->page_count,
            'page_inventory' => $this->pageInventory($template),
            'config' => ['instance_count' => $instanceCount, 'pattern_mode' => $patternMode],
            'norm' => $normSelection,
            'evaluation_contract' => [
                'profile_version_id' => (int) $map['profile_version_id'],
                'content_sha256' => (string) $map['content_sha256'],
                'items' => $map['items'],
                'bindings' => $map['bindings'],
                'metrics' => $map['metrics'],
            ],
            'instances' => $instances,
        ];
        $manifest = $this->canonicalize($manifest);
        $sha256 = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'manifest' => $manifest,
            'sha256' => $sha256,
            'config' => $manifest['config'],
            'mock_guide' => $this->guideProjection($manifest, $map['geometry'] ?? []),
        ];
    }

    /** Return only the immutable guide and exact pinned asset metadata. */
    public function guideForRun(OmrDiagnosticRun $run): array
    {
        $manifest = $this->mockManifest($run);
        $version = TestKitVersion::query()->findOrFail((int) $manifest['test_kit_version_id']);
        $resolved = $this->composition->resolveVersion($version);

        return $this->guideProjection($manifest, $resolved['item_map']['geometry'] ?? []);
    }

    /**
     * Resolve the next unselected instance/page without leaking expected answers.
     *
     * @return array<string, mixed>|null
     */
    public function captureTarget(OmrDiagnosticRun $run): ?array
    {
        $manifest = $this->mockManifest($run);
        $selectedPages = [];
        foreach (DB::table('test_kit_diagnostic_page_selections as selection')
            ->join('test_kit_diagnostic_page_attempts as attempt', 'attempt.id', '=', 'selection.selected_attempt_id')
            ->join('omr_diagnostic_captures as capture', 'capture.id', '=', 'attempt.diagnostic_capture_id')
            ->where('selection.diagnostic_run_id', (int) $run->id)
            ->where(function ($query): void {
                $query->whereNull('capture.failure_code')
                    ->orWhere('capture.failure_code', '!=', 'discarded_by_operator');
            })
            ->get(['selection.case_id', 'selection.page_no']) as $selection) {
            $selectedPages[(string) $selection->case_id][(int) $selection->page_no] = true;
        }
        foreach ($manifest['instances'] as $instance) {
            foreach ($instance['pages'] as $page) {
                $pageNo = (int) $page['page_no'];
                if (isset($selectedPages[$instance['instance_id']][$pageNo])) {
                    continue;
                }
                $target = [
                    'instance_id' => (string) $instance['instance_id'],
                    'sheet_id' => null,
                    'instance_no' => (int) $instance['ordinal'],
                    'instance_count' => count($manifest['instances']),
                    'page_no' => $pageNo,
                    'page_count' => (int) $manifest['page_count'],
                    'target_revision' => (int) $run->revision,
                    'pending_capture_id' => null,
                    'pending_capture_revision' => null,
                ];
                $pending = OmrPendingCaptureSet::query()
                    ->where('diagnostic_run_id', $run->id)
                    ->where('mock_instance_key', $instance['instance_id'])
                    ->whereIn('status', [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY])
                    ->latest('id')
                    ->first();
                if ($pending !== null) {
                    $target['pending_capture_id'] = (string) $pending->public_id;
                    $target['pending_capture_revision'] = (int) $pending->revision;
                }
                $clientHex = substr(hash('sha256', implode('|', [
                    (int) $run->id,
                    (string) $instance['instance_id'],
                    $pageNo,
                    (int) $run->revision,
                    (int) ($pending?->id ?? 0),
                    (int) ($pending?->revision ?? 0),
                ])), 0, 32);
                $clientHex[12] = '5';
                $clientHex[16] = dechex((hexdec($clientHex[16]) & 3) | 8);
                $target['client_capture_id'] = substr($clientHex, 0, 8).'-'.substr($clientHex, 8, 4).'-'
                    .substr($clientHex, 12, 4).'-'.substr($clientHex, 16, 4).'-'.substr($clientHex, 20, 12);

                return $target;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function currentCaptureIds(OmrDiagnosticRun $run): array
    {
        return DB::table('test_kit_diagnostic_page_selections as selection')
            ->join('test_kit_diagnostic_page_attempts as attempt', function ($join): void {
                $join->on('attempt.id', '=', 'selection.selected_attempt_id')
                    ->on('attempt.diagnostic_run_id', '=', 'selection.diagnostic_run_id')
                    ->on('attempt.case_id', '=', 'selection.case_id')
                    ->on('attempt.page_no', '=', 'selection.page_no');
            })
            ->join('omr_diagnostic_captures as capture', 'capture.id', '=', 'attempt.diagnostic_capture_id')
            ->where('selection.diagnostic_run_id', (int) $run->id)
            ->where(function ($query): void {
                $query->whereNull('capture.failure_code')
                    ->orWhere('capture.failure_code', '!=', 'discarded_by_operator');
            })
            ->distinct()
            ->orderBy('attempt.diagnostic_capture_id')
            ->pluck('attempt.diagnostic_capture_id')
            ->map(static fn (mixed $captureId): int => (int) $captureId)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function itemBindingsForPage(OmrDiagnosticRun $run, int $pageNo): array
    {
        $manifest = $this->mockManifest($run);
        $bindings = array_values(array_filter(
            $manifest['evaluation_contract']['bindings'],
            static fn (array $binding): bool => (int) $binding['page_no'] === $pageNo,
        ));
        if ($bindings === []) {
            throw new RuntimeException('mock_page_item_bindings_missing');
        }

        return array_map(static fn (array $binding): array => [
            'page_no' => (int) $binding['page_no'],
            'native_label' => (string) $binding['native_label'],
            'item_id' => (int) $binding['item_id'],
            'subtest_id' => (int) $binding['subtest_id'],
            'global_position' => (int) $binding['global_position'],
            'option_tokens' => array_values((array) $binding['option_tokens']),
            'item_key' => (string) $binding['native_label'],
        ], $bindings);
    }

    /** Project current selected pages into per-instance raw and typed summaries. */
    public function summarize(OmrDiagnosticRun $run): array
    {
        $manifest = $this->mockManifest($run);
        $selected = DB::table('test_kit_diagnostic_page_selections as selection')
            ->join('test_kit_diagnostic_page_attempts as attempt', function ($join): void {
                $join->on('attempt.id', '=', 'selection.selected_attempt_id')
                    ->on('attempt.diagnostic_run_id', '=', 'selection.diagnostic_run_id')
                    ->on('attempt.case_id', '=', 'selection.case_id')
                    ->on('attempt.page_no', '=', 'selection.page_no');
            })
            ->where('selection.diagnostic_run_id', (int) $run->id)
            ->get([
                'selection.case_id',
                'selection.page_no',
                'selection.revision as selection_revision',
                'attempt.id as attempt_id',
                'attempt.diagnostic_capture_id',
                'attempt.item_bindings_json',
                'attempt.input_sha256',
            ]);
        $selectedByInstance = [];
        foreach ($selected as $selection) {
            $selectedByInstance[(string) $selection->case_id][(int) $selection->page_no] = $selection;
        }
        $instances = [];
        foreach ($manifest['instances'] as $instance) {
            $instanceId = (string) $instance['instance_id'];
            $observations = [];
            $dependencyRows = [];
            $unresolvedItemCount = 0;
            $missingPageCount = 0;
            $adherence = ['expected_marks' => 0, 'matching_expected_marks' => 0, 'unexpected_marks' => 0, 'double_intents' => 0];
            $allPages = true;
            foreach ($instance['pages'] as $page) {
                $pageNo = (int) $page['page_no'];
                $selection = $selectedByInstance[$instanceId][$pageNo] ?? null;
                $capture = $selection === null ? null : OmrDiagnosticCapture::query()
                    ->whereKey((int) $selection->diagnostic_capture_id)
                    ->where('diagnostic_run_id', (int) $run->id)
                    ->first();
                if ($selection === null
                    || $capture === null
                    || $capture->failure_code === 'discarded_by_operator'
                    || ! in_array($capture->status, ['completed', 'reviewed', 'needs_review'], true)) {
                    $allPages = false;
                    $missingPageCount++;

                    continue;
                }
                $dependencyRows[] = [
                    'page_no' => $pageNo,
                    'attempt_id' => (int) $selection->attempt_id,
                    'selection_revision' => (int) $selection->selection_revision,
                    'input_sha256' => (string) $selection->input_sha256,
                    'capture_version' => (int) $capture->version,
                    'evidence_hash' => (string) $capture->evidence_hash,
                    'effective_answers_sha256' => hash('sha256', json_encode(
                        $this->canonicalize((array) ($capture->effective_answers_json ?? [])),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    )),
                ];
                $effective = (array) ($capture->effective_answers_json ?? []);
                if (($effective['has_stale'] ?? false) || ($effective['has_unresolved'] ?? false)) {
                    $allPages = false;
                }
                $answers = (array) ($effective['answers'] ?? []);
                $itemBindings = json_decode((string) $selection->item_bindings_json, true);
                $itemKeys = [];
                foreach (is_array($itemBindings) ? $itemBindings : [] as $binding) {
                    if (is_array($binding) && is_string($binding['native_label'] ?? null) && is_string($binding['item_key'] ?? null)) {
                        $itemKeys[$binding['native_label']] = $binding['item_key'];
                    }
                }
                foreach ($page['instructions'] as $instruction) {
                    $itemKey = $itemKeys[$instruction['native_label']] ?? $instruction['native_label'];
                    $answer = $answers[$itemKey] ?? null;
                    $state = is_array($answer) ? (string) ($answer['state'] ?? 'unresolved') : 'unresolved';
                    $option = is_array($answer) && is_string($answer['option'] ?? null) ? $answer['option'] : null;
                    $tokens = $state === 'blank' ? [] : (in_array($state, ['marked', 'selected'], true) && $option !== null ? [$option] : []);
                    $target = $instruction['target_tokens'];
                    $adherence['expected_marks'] += count($target);
                    $adherence['matching_expected_marks'] += count(array_intersect($target, $tokens));
                    $adherence['unexpected_marks'] += count(array_diff($tokens, $target));
                    if ($instruction['intent'] === 'double') {
                        $adherence['double_intents']++;
                    }
                    if (in_array($state, ['unresolved', 'ambiguous', 'unreadable'], true)) {
                        $allPages = false;
                        $unresolvedItemCount++;

                        continue;
                    }
                    $observations[] = [
                        'page_no' => (int) $instruction['page_no'],
                        'native_label' => (string) $instruction['native_label'],
                        'token' => $tokens[0] ?? null,
                    ];
                }
            }
            $completeEvidence = $allPages && count($observations) === count($manifest['evaluation_contract']['bindings']);
            $raw = [];
            $unavailableMetrics = [];
            if ($completeEvidence) {
                $rawContract = $this->rawEvaluationContract($manifest['evaluation_contract']);
                try {
                    $raw = $this->evaluator->evaluate($rawContract, $observations, [])['metric_values'];
                } catch (InvalidArgumentException) {
                    $unavailableMetrics = array_column($manifest['evaluation_contract']['metrics'], 'code');
                }
                $computed = array_fill_keys(array_column($raw, 'metric_id'), true);
                foreach ($manifest['evaluation_contract']['metrics'] as $metric) {
                    if (! isset($computed[(int) $metric['metric_id']])) {
                        $unavailableMetrics[] = (string) $metric['code'];
                    }
                }
            }
            $typedOutputs = $completeEvidence ? $this->typedOutputs($manifest, $raw) : [];
            $typedOutputIds = array_fill_keys(array_column($typedOutputs, 'metric_id'), true);
            $metricCodesById = array_column($manifest['evaluation_contract']['metrics'], 'code', 'metric_id');
            $unavailableOutputCodes = [];
            foreach ($raw as $rawMetric) {
                $metricId = (int) $rawMetric['metric_id'];
                if (! isset($typedOutputIds[$metricId])) {
                    $unavailableOutputCodes[] = (string) ($metricCodesById[$metricId] ?? '');
                }
            }
            $instances[] = [
                'instance_id' => $instanceId,
                'instance_no' => (int) $instance['ordinal'],
                'complete' => $completeEvidence,
                'result_state' => $completeEvidence
                    ? ($unavailableMetrics === [] ? 'complete' : 'complete_with_unavailable_metrics')
                    : ($missingPageCount > 0 ? 'missing_pages' : 'unresolved_items'),
                'captured_pages' => count($selectedByInstance[$instanceId] ?? []),
                'missing_page_count' => $missingPageCount,
                'unresolved_item_count' => $unresolvedItemCount,
                'page_count' => (int) $manifest['page_count'],
                'selected_evidence_sha256' => hash('sha256', json_encode(
                    $this->canonicalize($dependencyRows),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                )),
                'raw_metrics' => $completeEvidence ? $raw : null,
                'typed_outputs' => $typedOutputs,
                'typed_output_state' => ($manifest['norm']['state'] ?? 'norm_unavailable') !== 'pinned'
                    ? (string) ($manifest['norm']['state'] ?? 'norm_unavailable')
                    : ($unavailableOutputCodes === [] ? 'available' : 'table_or_interval_unavailable'),
                'unavailable_output_metric_codes' => array_values(array_unique($unavailableOutputCodes)),
                'unavailable_metrics' => array_values(array_unique($unavailableMetrics)),
                'norm' => $manifest['norm'],
                'guide_adherence' => $adherence,
            ];
        }

        return [
            'diagnostic_purpose' => 'mock_rehearsal',
            'official' => false,
            'expected_instance_count' => count($instances),
            'completed_instance_count' => count(array_filter($instances, static fn (array $instance): bool => $instance['complete'])),
            'expected_page_count' => count($instances) * (int) $manifest['page_count'],
            'captured_page_count' => array_sum(array_column($instances, 'captured_pages')),
            'instances' => $instances,
        ];
    }

    /** Find the exact page-pinned reference asset from the frozen manifest. */
    public function referenceAsset(TestKitVersion|OmrDiagnosticRun $owner, int $pageNo): OmrTemplateAsset
    {
        if ($pageNo < 1) {
            throw new InvalidArgumentException('mock_reference_page_invalid');
        }
        if ($owner instanceof OmrDiagnosticRun) {
            $manifest = $this->mockManifest($owner);
            $page = $manifest['page_inventory'][$pageNo - 1] ?? null;
            $templateVersionId = (int) ($manifest['template_version_id'] ?? 0);
        } else {
            $owner->loadMissing(['templateVersion.canonicalAssets', 'templateVersion.pages']);
            $template = $owner->templateVersion;
            $page = $template instanceof OmrAnswerSheetTemplateVersion
                ? ($this->pageInventory($template)[$pageNo - 1] ?? null)
                : null;
            $templateVersionId = (int) ($template?->id ?? 0);
        }
        $assetId = (int) ($page['asset_id'] ?? 0);
        $asset = $assetId > 0 ? OmrTemplateAsset::query()->find($assetId) : null;
        if (($page['page_no'] ?? null) !== $pageNo
            || ! $asset instanceof OmrTemplateAsset
            || (int) $asset->template_version_id !== $templateVersionId
            || (int) $asset->page_no !== $pageNo
            || ! in_array($asset->kind, [OmrTemplateAsset::KIND_REFERENCE_PAGE, OmrTemplateAsset::KIND_RASTER_PAGE], true)
            || ! is_string($page['asset_sha256'] ?? null)
            || ! hash_equals((string) $page['asset_sha256'], (string) $asset->sha256)) {
            throw new RuntimeException('mock_reference_page_unavailable');
        }

        return $asset;
    }

    private function guideProjection(array $manifest, array $geometry): array
    {
        $nativeFields = [];
        foreach ((array) ($geometry['native_fields'] ?? []) as $field) {
            $nativeFields[(int) $field['page_no']][(string) $field['label']][(string) $field['token']] = $field['pixel'];
        }
        $correctTokensByPage = [];
        foreach ($manifest['evaluation_contract']['bindings'] as $binding) {
            $correctTokensByPage[(int) $binding['page_no']][(string) $binding['native_label']] = $binding['correct_token'];
        }
        $pages = [];
        foreach ($manifest['page_inventory'] as $page) {
            $pageNo = (int) $page['page_no'];
            $instanceGuides = [];
            foreach ($manifest['instances'] as $instance) {
                $instructions = $instance['pages'][$pageNo - 1]['instructions'] ?? [];
                $bubbles = [];
                foreach ($instructions as $instruction) {
                    foreach ($instruction['option_tokens'] as $token) {
                        $rect = $nativeFields[$pageNo][$instruction['native_label']][$token] ?? null;
                        if (! is_array($rect)) {
                            continue;
                        }
                        $targetToken = in_array($token, $instruction['target_tokens'], true);
                        $style = $instruction['intent'] === 'double' && $targetToken
                            ? 'double'
                            : ($targetToken && $instruction['intent'] === 'wrong' ? 'wrong' : ($targetToken ? 'correct' : 'outline'));
                        $bubbles[] = [
                            'native_label' => (string) $instruction['native_label'],
                            'token' => (string) $token,
                            'x' => (float) ($rect['x'] ?? 0),
                            'y' => (float) ($rect['y'] ?? 0),
                            'w' => (float) ($rect['w'] ?? 0),
                            'h' => (float) ($rect['h'] ?? 0),
                            'style' => $style,
                        ];
                    }
                }
                $guideInstructions = array_map(
                    static fn (array $instruction): array => [
                        ...$instruction,
                        'key_token' => $correctTokensByPage[$pageNo][$instruction['native_label']] ?? null,
                    ],
                    $instructions,
                );
                $instanceGuides[] = [
                    'instance_id' => (string) $instance['instance_id'],
                    'instance_no' => (int) $instance['ordinal'],
                    'instructions' => $guideInstructions,
                    'bubbles' => $bubbles,
                ];
            }
            $pages[] = [
                'page_no' => $pageNo,
                'width_px' => (int) $page['width'],
                'height_px' => (int) $page['height'],
                'reference_asset_id' => $page['asset_id'] ?? null,
                'raster_sha256' => $page['asset_sha256'] ?? null,
                'available' => (int) ($page['asset_id'] ?? 0) > 0,
                'instances' => $instanceGuides,
            ];
        }

        return [
            'test_kit_version_id' => (int) $manifest['test_kit_version_id'],
            'pattern_mode' => (string) $manifest['config']['pattern_mode'],
            'instance_count' => (int) $manifest['config']['instance_count'],
            'legend' => [
                'correct' => 'Shade the correct Set A option.',
                'wrong' => 'Shade the intentionally wrong option.',
                'double' => 'Shade both instructed options; expect Review.',
                'blank' => 'Leave this item blank.',
            ],
            'pages' => $pages,
        ];
    }

    /** @return array<string, mixed> */
    private function mockManifest(OmrDiagnosticRun $run): array
    {
        $manifest = $run->getAttribute('mock_manifest_json');
        if ($run->getAttribute('diagnostic_purpose') !== 'mock_rehearsal'
            || ! is_array($manifest)
            || ($manifest['diagnostic_purpose'] ?? null) !== 'mock_rehearsal') {
            throw new RuntimeException('mock_manifest_unavailable');
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    private function normSelection(array $resolved): array
    {
        $preparation = $resolved['preparation'] ?? null;
        $normVersionId = (int) ($preparation?->norm_package_version_id ?? 0);
        if ($normVersionId < 1) {
            return ['state' => 'missing_explicit_norm'];
        }

        $testKitVersion = $resolved['version'] ?? null;
        $profile = $resolved['profile'] ?? null;
        $norm = AssessmentNormPackageVersion::query()->with(['package', 'tables'])->find($normVersionId);
        $normPackage = $norm?->package;
        $profileForm = $profile?->form_code;
        $normFormMatches = $norm?->form_code === null
            || $profileForm === null
            || $norm?->form_code === $profileForm;
        $syntheticCompatible = $normPackage?->is_synthetic !== true
            || $profile?->instrument?->is_synthetic === true;
        if (! $norm instanceof AssessmentNormPackageVersion
            || $norm->status !== AssessmentNormPackageVersion::STATUS_APPROVED
            || $norm->approved_at === null
            || $norm->source_reviewed_at === null
            || preg_match('/^[a-f0-9]{64}$/D', (string) $norm->content_sha256) !== 1
            || $normPackage === null
            || $testKitVersion === null
            || (int) $normPackage->institution_id !== (int) $testKitVersion->institution_id
            || (bool) $normPackage->is_synthetic !== (bool) $testKitVersion->is_synthetic
            || ! $normFormMatches
            || ! $syntheticCompatible) {
            return ['state' => 'explicit_norm_unavailable', 'norm_package_version_id' => $normVersionId];
        }

        return [
            'state' => 'pinned',
            'norm_package_version_id' => (int) $norm->id,
            'sha256' => (string) $norm->content_sha256,
            'form_code' => (string) $norm->form_code,
            'institution_id' => (int) $normPackage->institution_id,
            'provenance' => (bool) $normPackage->is_synthetic ? 'synthetic' : 'institutional',
            'source_reference' => (string) ($norm->source_reference ?? ''),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pageInventory(OmrAnswerSheetTemplateVersion $template): array
    {
        return app(OmrCanonicalSheetPageResolver::class)
            ->pageInventoryForVersion($template);
    }

    /** @return array<string, mixed> */
    private function workerIdentity($template): array
    {
        return [
            'engine_name' => $template->engine_name,
            'engine_commit' => $template->engine_commit,
            'patch_identity' => $template->patch_identity,
            'native_bundle_version' => $template->native_bundle_version,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function typedOutputs(array $manifest, array $rawMetrics): array
    {
        if (($manifest['norm']['state'] ?? null) !== 'pinned') {
            return [];
        }
        $norm = AssessmentNormPackageVersion::query()
            ->with(['package', 'tables'])
            ->find((int) $manifest['norm']['norm_package_version_id']);
        if (! $norm instanceof AssessmentNormPackageVersion
            || $norm->status !== AssessmentNormPackageVersion::STATUS_APPROVED
            || $norm->approved_at === null
            || $norm->source_reviewed_at === null
            || ! hash_equals((string) $manifest['norm']['sha256'], (string) $norm->content_sha256)
            || (int) ($norm->package?->institution_id ?? 0) !== (int) ($manifest['norm']['institution_id'] ?? 0)
            || ((bool) ($norm->package?->is_synthetic ?? false) ? 'synthetic' : 'institutional') !== ($manifest['norm']['provenance'] ?? null)
            || ($norm->form_code !== null && $manifest['test_setup_form_code'] !== null && $norm->form_code !== $manifest['test_setup_form_code'])) {
            return [];
        }

        $metricsById = [];
        foreach ($manifest['evaluation_contract']['metrics'] as $metric) {
            $metricsById[(int) $metric['metric_id']] = $metric;
        }
        $outputs = [];
        foreach ($rawMetrics as $raw) {
            $metric = $metricsById[(int) $raw['metric_id']] ?? null;
            $table = $norm->tables->first(static fn ($table): bool => (string) $table->metric_code === (string) ($metric['code'] ?? '')
                && (string) $table->output_scale === 'percentile_rank'
            );
            if ($metric === null || $table === null) {
                continue;
            }
            $converted = $this->converter->convertViaNormTable($table, $raw['value']);
            if ($converted !== null) {
                $outputs[] = [
                    'metric_id' => (int) $raw['metric_id'],
                    'metric_code' => (string) $metric['code'],
                    'output_type' => 'percentile_rank',
                    'value' => $converted,
                    'norm_package_version_id' => (int) $norm->id,
                    'norm_sha256' => (string) $norm->content_sha256,
                ];
            }
        }

        return $outputs;
    }

    /** @return array<string, mixed> */
    private function rawEvaluationContract(array $contract): array
    {
        $included = [];
        foreach ($contract['metrics'] as $metric) {
            if ($metric['rule_kind'] === 'correct_item_count') {
                $included[(int) $metric['metric_id']] = true;
            }
        }
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($contract['metrics'] as $metric) {
                $metricId = (int) $metric['metric_id'];
                if (! in_array($metric['rule_kind'], ['sum_raw_metrics', 'formula_raw_metrics'], true) || isset($included[$metricId])) {
                    continue;
                }
                $inputs = array_map('intval', (array) $metric['input_metric_ids']);
                if ($inputs !== [] && count(array_filter($inputs, static fn (int $id): bool => isset($included[$id]))) === count($inputs)) {
                    $included[$metricId] = true;
                    $changed = true;
                }
            }
        }
        $contract['metrics'] = array_values(array_filter(
            $contract['metrics'],
            static fn (array $metric): bool => isset($included[(int) $metric['metric_id']]),
        ));

        return $contract;
    }

    private function keyForBinding(array $bindings, int $itemId): string
    {
        foreach ($bindings as $binding) {
            if ((int) $binding['item_id'] === $itemId && is_string($binding['correct_token'] ?? null)) {
                return $binding['correct_token'];
            }
        }
        throw new RuntimeException('mock_key_missing');
    }

    private function nextToken(array $tokens, string $key): string
    {
        $index = array_search($key, $tokens, true);
        if ($index === false || count($tokens) < 2) {
            throw new RuntimeException('mock_next_option_unavailable');
        }

        return (string) $tokens[($index + 1) % count($tokens)];
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    private function canonicalize(array $value): array
    {
        foreach ($value as &$nested) {
            if (is_array($nested)) {
                $nested = $this->canonicalize($nested);
            }
        }
        unset($nested);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
