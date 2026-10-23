<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAX_PAGE_COUNT = 20;

    public function up(): void
    {
        $runs = DB::table('omr_template_verification_runs')->get()->keyBy('id');
        $assetHashes = DB::table('omr_template_assets')->pluck('sha256', 'id');
        $challengeMethods = [];

        foreach (DB::table('omr_physical_challenges')->orderBy('id')->cursor() as $challenge) {
            $run = $runs->get($challenge->run_id);
            $target = $run === null ? null : $this->target($run);
            $method = $this->method($challenge->method);
            if ($run === null || $target === null || $method === null) {
                continue;
            }

            $manifest = $this->pageManifest($challenge, $run);
            $pageCount = $this->pageCount($manifest);
            if ($pageCount < 1) {
                continue;
            }
            $rendererVersion = (string) ($challenge->renderer_version ?? 'legacy-renderer-v1');
            $sheetAssetHash = $assetHashes->get($challenge->sheet_asset_id);
            $guideAssetHash = $assetHashes->get($challenge->guide_asset_id);
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            DB::table('omr_physical_challenges')->where('id', $challenge->id)->update([
                'method' => $method,
                'target_kind' => $target['kind'],
                'target_id' => $target['id'],
                'target_version' => $target['version'],
                'target_hash' => $target['hash'],
                'plan_version' => $challenge->plan_version ?: ($run->plan_version ?: 'v1'),
                'plan_seed' => $challenge->plan_seed,
                'renderer_version' => $rendererVersion,
                'renderer_hash' => $challenge->renderer_hash ?: hash('sha256', $rendererVersion),
                'page_manifest' => $manifestJson,
                'page_manifest_hash' => hash('sha256', $manifestJson),
                'sheet_asset_hash' => $challenge->sheet_asset_hash ?: $sheetAssetHash,
                'guide_asset_hash' => $challenge->guide_asset_hash ?: $guideAssetHash,
                'print_metadata' => $challenge->print_metadata ?: json_encode(['legacy_backfill' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ]);

            $methodId = $this->ensureMethod($run, $challenge, $method, $target, $pageCount);
            if ($methodId !== null) {
                $challengeMethods[(int) $challenge->id] = [
                    'method' => $method,
                    'method_id' => $methodId,
                ];
            }
        }

        foreach (DB::table('omr_template_verification_captures')->orderBy('id')->cursor() as $capture) {
            $run = $runs->get($capture->run_id);
            $target = $run === null ? null : $this->target($run);
            if ($run === null || $target === null) {
                continue;
            }

            $challenge = $capture->challenge_id === null
                ? null
                : DB::table('omr_physical_challenges')->where('id', $capture->challenge_id)->first();
            $challengeMethod = $challenge === null
                ? null
                : ($challengeMethods[(int) $challenge->id] ?? null);
            $method = $this->method($capture->method)
                ?? (is_array($challengeMethod) ? $challengeMethod['method'] : null);
            if ($method === null) {
                continue;
            }

            $methodId = is_array($challengeMethod) ? $challengeMethod['method_id'] : DB::table('omr_template_verification_methods')
                ->where('run_id', $run->id)
                ->where('method', $method)
                ->value('id');
            $summary = $this->jsonObject($capture->summary);
            $failureDetail = is_string($summary['detail'] ?? null) ? $summary['detail'] : null;
            $alignedHash = $capture->aligned_asset_id !== null ? $assetHashes->get($capture->aligned_asset_id) : null;
            $overlayHash = $capture->overlay_asset_id !== null ? $assetHashes->get($capture->overlay_asset_id) : null;
            $rendererVersion = $challenge?->renderer_version;
            $rendererHash = $challenge?->renderer_hash;
            $pageManifestHash = $challenge?->page_manifest_hash;
            $attemptNo = $this->positiveInt($capture->attempt_no) ?? $this->positiveInt($capture->attempt);
            $attemptState = in_array((string) $capture->attempt_state, ['active', 'passed', 'failed', 'superseded'], true)
                ? $capture->attempt_state
                : ((string) $capture->status === 'pass' ? 'passed' : 'failed');
            $values = [
                'method_id' => $methodId !== null ? (int) $methodId : null,
                'method' => $method,
                'attempt_no' => $attemptNo,
                'attempt_state' => $attemptState,
                'target_kind' => $target['kind'],
                'target_id' => $target['id'],
                'target_version' => $target['version'],
                'target_hash' => $target['hash'],
                'plan_version' => $capture->plan_version ?: ($run->plan_version ?: 'v1'),
                'plan_hash' => $capture->plan_hash ?: $run->plan_hash,
                'challenge_hash' => $capture->challenge_hash ?: ($challenge?->plan_hash),
                'renderer_version' => $capture->renderer_version ?: $rendererVersion,
                'renderer_hash' => $capture->renderer_hash ?: $rendererHash,
                'marker_profile_id' => $capture->marker_profile_id ?: $challenge?->marker_profile_id,
                'marker_profile_version' => $capture->marker_profile_version ?: $challenge?->marker_profile_version,
                'marker_profile_hash' => $capture->marker_profile_hash ?: $challenge?->marker_profile_hash,
                'page_manifest_hash' => $capture->page_manifest_hash ?: $pageManifestHash,
                'evidence_version' => $capture->evidence_version ?: 'v1',
                'input_asset_hash' => $capture->input_asset_hash ?: $capture->input_hash,
                'aligned_asset_hash' => $capture->aligned_asset_hash ?: $alignedHash,
                'overlay_asset_hash' => $capture->overlay_asset_hash ?: $overlayHash,
                'failure_code' => $capture->failure_code ?: $capture->error_code,
                'failure_detail' => $capture->failure_detail ?: $failureDetail,
                'lock_version' => $capture->lock_version ?? 0,
            ];
            DB::table('omr_template_verification_captures')->where('id', $capture->id)->update($values);

            $this->backfillItems($capture, $summary);
        }
    }

    /**
     * The new fields are derived history. Their removal in down() is handled
     * by the preceding schema migration; this data-only migration has nothing
     * safe to restore without risking a rewrite of old evidence.
     */
    public function down(): void {}

    /** @return array{kind:string,id:int,version:string,hash:string}|null */
    private function target(object $run): ?array
    {
        $draftId = $this->positiveInt($run->draft_id);
        $versionId = $this->positiveInt($run->template_version_id);
        if (($draftId === null) === ($versionId === null)) {
            return null;
        }

        $runHash = (string) ($run->artifact_hash ?? '');
        if (preg_match('/^[a-f0-9]{64}$/i', $runHash) !== 1) {
            return null;
        }

        if ($draftId !== null) {
            $draft = DB::table('omr_template_authoring_drafts')
                ->select(['id', 'authoring_json'])
                ->where('id', $draftId)
                ->first();
            if ($draft === null) {
                return null;
            }
            $authoring = $this->jsonObject($draft->authoring_json);
            $canonical = is_array($authoring['canonical'] ?? null) ? $authoring['canonical'] : [];
            $artifactSet = is_array($canonical['artifact_set'] ?? null) ? $canonical['artifact_set'] : [];
            $version = $artifactSet['candidate_version'] ?? null;
            $targetHash = is_scalar($artifactSet['artifact_hash'] ?? null)
                ? (string) $artifactSet['artifact_hash']
                : (is_scalar($canonical['artifact_hash'] ?? null)
                    ? (string) $canonical['artifact_hash']
                    : '');
            if (! is_numeric($version) || (int) $version < 1
                || preg_match('/^[a-f0-9]{64}$/i', $targetHash) !== 1
                || ! hash_equals($runHash, $targetHash)) {
                return null;
            }

            return [
                'kind' => 'candidate',
                'id' => $draftId,
                'version' => (string) $version,
                'hash' => $targetHash,
            ];
        }

        $version = DB::table('omr_answer_sheet_template_versions')
            ->select(['id', 'version', 'authoring_manifest'])
            ->where('id', $versionId)
            ->first();
        if ($version === null || (int) $version->version < 1) {
            return null;
        }
        $manifest = $this->jsonObject($version->authoring_manifest);
        $artifactSet = is_array($manifest['artifact_set'] ?? null) ? $manifest['artifact_set'] : [];
        $targetHash = is_scalar($artifactSet['artifact_hash'] ?? null) ? (string) $artifactSet['artifact_hash'] : '';
        if (preg_match('/^[a-f0-9]{64}$/i', $targetHash) !== 1 || ! hash_equals($runHash, $targetHash)) {
            return null;
        }

        return [
            'kind' => 'compiled_version',
            'id' => $versionId,
            'version' => (string) $version->version,
            'hash' => $targetHash,
        ];
    }

    private function method(mixed $value): ?string
    {
        $method = is_string($value) ? trim($value) : '';

        return in_array($method, ['pre_shaded', 'hand_shaded'], true) ? $method : null;
    }

    /** @return array<string, mixed> */
    private function pageManifest(object $challenge, object $run): array
    {
        $manifest = $this->jsonObject($challenge->page_manifest);
        if ($manifest === []) {
            $guide = $this->jsonObject($challenge->guide_json);
            $pageNumbers = [];
            foreach ((array) ($guide['sections'] ?? []) as $section) {
                $pageNo = is_array($section) ? $this->positiveInt($section['page_no'] ?? null) : null;
                if ($pageNo !== null && $pageNo <= self::MAX_PAGE_COUNT) {
                    $pageNumbers[] = $pageNo;
                }
            }
            $progressTotal = $this->positiveInt($run->progress_total);
            $pageCount = $pageNumbers === [] ? ($progressTotal ?? 1) : max($pageNumbers);

            return [
                'page_count' => min(self::MAX_PAGE_COUNT, max(1, $pageCount)),
                'legacy_backfill' => true,
            ];
        }

        $pages = $this->boundedPages($manifest['pages'] ?? []);
        $declared = $this->positiveInt($manifest['page_count'] ?? null) ?? 0;
        $highest = $pages === [] ? 0 : max(array_map(
            static fn (array $page): int => (int) $page['page_no'],
            $pages,
        ));
        $count = min(self::MAX_PAGE_COUNT, max(1, $declared, $highest));
        $manifest['page_count'] = $count;
        $manifest['pages'] = $pages;

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    private function pageCount(array $manifest): int
    {
        $pages = $this->boundedPages($manifest['pages'] ?? []);
        $declared = $this->positiveInt($manifest['page_count'] ?? null) ?? 0;
        $highest = $pages === [] ? 0 : max(array_map(
            static fn (array $page): int => (int) $page['page_no'],
            $pages,
        ));

        return min(self::MAX_PAGE_COUNT, max(1, $declared, $highest));
    }

    /** @param mixed $pages @return list<array<string, mixed>> */
    private function boundedPages(mixed $pages): array
    {
        $normalized = [];
        $seen = [];
        foreach (is_array($pages) ? $pages : [] as $page) {
            $pageNo = is_array($page)
                ? $this->positiveInt($page['page_no'] ?? null)
                : $this->positiveInt($page);
            if ($pageNo === null || $pageNo > self::MAX_PAGE_COUNT || isset($seen[$pageNo])) {
                continue;
            }
            $seen[$pageNo] = true;
            $normalized[] = is_array($page) ? [...$page, 'page_no' => $pageNo] : ['page_no' => $pageNo];
            if (count($normalized) >= self::MAX_PAGE_COUNT) {
                break;
            }
        }
        usort($normalized, static fn (array $left, array $right): int => $left['page_no'] <=> $right['page_no']);

        return array_values($normalized);
    }

    /** @param array{kind:string,id:int,version:string,hash:string} $target */
    private function ensureMethod(object $run, object $challenge, string $method, array $target, int $pageCount): ?int
    {
        $existing = DB::table('omr_template_verification_methods')
            ->where('run_id', $run->id)
            ->where('method', $method)
            ->first();
        $values = [
            'run_id' => $run->id,
            'method' => $method,
            'method_state' => 'challenge_ready',
            'target_kind' => $target['kind'],
            'target_id' => $target['id'],
            'target_version' => $target['version'],
            'target_hash' => $target['hash'],
            'plan_version' => $challenge->plan_version ?: ($run->plan_version ?: 'v1'),
            'plan_seed' => $challenge->plan_seed,
            'plan_hash' => $challenge->plan_hash ?: $run->plan_hash,
            'expected_vector_hash' => $challenge->expected_vector_hash ?: $run->expected_vector_hash,
            'challenge_id' => $challenge->id,
            'required_page_count' => min(self::MAX_PAGE_COUNT, max(1, $pageCount)),
            'challenge_ready_at' => $challenge->created_at,
            'last_attempt_at' => $challenge->updated_at ?: $challenge->created_at,
            'lock_version' => 0,
            'created_at' => $challenge->created_at ?: now(),
            'updated_at' => $challenge->updated_at ?: now(),
        ];

        if ($existing !== null) {
            if ((string) $existing->target_kind !== $target['kind']
                || (int) $existing->target_id !== $target['id']
                || (string) $existing->target_version !== $target['version']
                || ! hash_equals((string) $existing->target_hash, $target['hash'])) {
                return null;
            }
            $methodId = (int) $existing->id;
            DB::table('omr_template_verification_methods')->where('id', $methodId)->update([
                'challenge_id' => $values['challenge_id'],
                'required_page_count' => $values['required_page_count'],
                'updated_at' => $values['updated_at'],
            ]);
        } else {
            $methodId = (int) DB::table('omr_template_verification_methods')->insertGetId($values);
        }

        for ($pageNo = 1; $pageNo <= $values['required_page_count']; $pageNo++) {
            $exists = DB::table('omr_template_verification_method_pages')
                ->where('method_id', $methodId)
                ->where('page_no', $pageNo)
                ->exists();
            if (! $exists) {
                DB::table('omr_template_verification_method_pages')->insert([
                    'method_id' => $methodId,
                    'page_no' => $pageNo,
                    'required' => true,
                    'page_state' => 'not_started',
                    'page_version' => 0,
                    'created_at' => $challenge->created_at ?: now(),
                    'updated_at' => $challenge->updated_at ?: now(),
                ]);
            }
        }

        return $methodId > 0 ? $methodId : null;
    }

    /** @param array<string, mixed> $summary */
    private function backfillItems(object $capture, array $summary): void
    {
        $evidence = $this->jsonObject($summary['evidence'] ?? null);
        $items = is_array($evidence['items'] ?? null) ? $evidence['items'] : [];
        if ($items === []) {
            return;
        }

        $seen = [];
        foreach ($items as $position => $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemId = (string) ($item['item_id'] ?? $item['native_label'] ?? $item['label'] ?? ((int) $position + 1));
            $itemId = substr($itemId, 0, 128);
            if ($itemId === '' || isset($seen[$itemId])) {
                continue;
            }
            $seen[$itemId] = true;
            if (DB::table('omr_template_verification_item_evidence')->where('capture_id', $capture->id)->where('item_id', $itemId)->exists()) {
                continue;
            }

            $groupType = (string) ($item['group_type'] ?? 'aptitude_area');
            if (! in_array($groupType, ['aptitude_area', 'identity', 'calibration'], true)) {
                $groupType = 'aptitude_area';
            }
            $options = $item['options'] ?? $item['declared_options'] ?? null;
            $means = $item['means'] ?? $item['option_means'] ?? null;
            $rect = $item['crop_rect'] ?? $item['rect'] ?? null;
            DB::table('omr_template_verification_item_evidence')->insert([
                'capture_id' => $capture->id,
                'group_type' => $groupType,
                'area_key' => is_scalar($item['area_key'] ?? $item['area_code'] ?? null) ? (string) ($item['area_key'] ?? $item['area_code']) : null,
                'area_position' => is_numeric($item['area_position'] ?? null) && (int) $item['area_position'] > 0 ? (int) $item['area_position'] : null,
                'question_no' => is_numeric($item['question_no'] ?? null) && (int) $item['question_no'] > 0 ? (int) $item['question_no'] : null,
                'item_id' => $itemId,
                'options' => is_array($options) ? json_encode($options, JSON_UNESCAPED_SLASHES) : null,
                'expected' => is_scalar($item['expected'] ?? null) ? (string) $item['expected'] : null,
                'detected' => is_scalar($item['detected'] ?? null) ? (string) $item['detected'] : null,
                'state' => is_scalar($item['state'] ?? null) ? (string) $item['state'] : null,
                'means' => is_array($means) ? json_encode($means, JSON_UNESCAPED_SLASHES) : null,
                'delta' => is_numeric($item['delta'] ?? null) ? $item['delta'] : null,
                'status' => is_scalar($item['status'] ?? null) ? (string) $item['status'] : null,
                'crop_asset_id' => null,
                'crop_asset_hash' => null,
                'crop_rect' => is_array($rect) ? json_encode($rect, JSON_UNESCAPED_SLASHES) : null,
                'evidence_version' => 'v1',
                'evidence_hash' => $capture->evidence_hash,
                'row_version' => 0,
                'created_at' => $capture->created_at ?: now(),
                'updated_at' => $capture->updated_at ?: now(),
            ]);
        }
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
