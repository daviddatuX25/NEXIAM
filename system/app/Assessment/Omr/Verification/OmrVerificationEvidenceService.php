<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_filter;
use function array_flip;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function array_values;
use function hash;
use function hash_equals;
use function is_numeric;
use function json_encode;
use function preg_match;
use function strlen;
use function substr;

/**
 * Persists one normalized digital verification result without retaining image
 * bytes in the run row. Evidence images stay in the private asset store;
 * captures retain only hashes, safe summaries, and asset foreign keys.
 */
final class OmrVerificationEvidenceService
{
    private const MAX_ITEM_EVIDENCE_ITEMS = 256;

    private const MAX_ITEM_EVIDENCE_BYTES = 262144;

    /**
     * @param  list<OmrTemplateAsset>  $createdAssets
     * @return callable(string,string,string):array<string,mixed>
     */
    public function evidencePersister(
        OmrTemplateVerificationRun $run,
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateAssetStore $assetStore,
        string $proofHash,
        array &$createdAssets,
        int $pageNo = 1,
        ?string $captureVariant = null,
    ): callable {
        return function (string $variantName, string $fileName, string $bytes) use ($run, $draft, $assetStore, $proofHash, &$createdAssets, $pageNo, $captureVariant): array {
            $asset = $assetStore->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_EVIDENCE,
                $bytes,
                'image/png',
                $pageNo,
                ['run_id' => $run->id, 'attempt' => $run->attempt, 'proof_hash' => $proofHash, 'variant' => $captureVariant ?? $variantName, 'role' => $fileName],
            );
            if ($asset->wasRecentlyCreated) {
                $createdAssets[] = $asset;
            }

            return ['role' => $fileName, 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        };
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  list<array{page_no:int,asset_id:int,sha256:string}>  $proofAssets
     */
    public function storeCapture(
        OmrTemplateVerificationRun $run,
        string $variant,
        int $pageNo,
        array $entry,
        array $proofAssets,
        ?string $detectedVectorHash,
        ?bool $vectorMatch,
    ): OmrTemplateVerificationCapture {
        $registrationPolicy = $run->draft?->registration_mode;
        if (! $registrationPolicy instanceof OmrRegistrationMode) {
            throw new RuntimeException('verification_registration_mode_missing');
        }
        $variantPageNo = OmrNoGoMatrixRunner::parseVariantKey($variant, $registrationPolicy)['page_no'];
        if ($pageNo !== $variantPageNo) {
            throw new RuntimeException('verification_capture_page_mismatch');
        }
        $assetRefs = [];
        foreach ((array) ($entry['evidence_assets'] ?? []) as $evidence) {
            if (! is_array($evidence)) {
                continue;
            }
            $role = (string) ($evidence['role'] ?? '');
            $assetId = (int) ($evidence['asset_id'] ?? 0);
            if ($role !== '' && $assetId > 0) {
                $assetRefs[$role] = [
                    'asset_id' => $assetId,
                    'sha256' => (string) ($evidence['sha256'] ?? ''),
                ];
            }
        }

        $inputHash = (string) ($entry['input_hash'] ?? '');
        $inputRef = $assetRefs['input.png'] ?? null;
        if (! is_array($inputRef) || $inputHash === '' || preg_match('/^[a-f0-9]{64}$/i', $inputHash) !== 1) {
            throw new RuntimeException('verification_input_asset_missing');
        }
        $inputAsset = OmrTemplateAsset::query()->find($inputRef['asset_id']);
        if ($inputAsset === null
            || ($run->draft_id !== null && (int) $inputAsset->authoring_draft_id !== (int) $run->draft_id)
            || preg_match('/^[a-f0-9]{64}$/i', $inputRef['sha256']) !== 1
            || ! hash_equals($inputHash, (string) $inputAsset->sha256)
            || ! hash_equals($inputHash, $inputRef['sha256'])) {
            throw new RuntimeException('verification_input_asset_hash_mismatch');
        }
        $storedInput = Storage::disk($inputAsset->disk)->get($inputAsset->path);
        if (! is_string($storedInput) || ! hash_equals($inputHash, hash('sha256', $storedInput))) {
            throw new RuntimeException('verification_input_asset_integrity_failed');
        }

        $items = $this->boundedItems($entry['item_evidence'] ?? []);
        $itemSummary = OmrNoGoMatrixRunner::summarizeItems($items);
        $issues = $this->boundedIssues($entry['issues'] ?? []);
        $alignedHash = is_string($entry['aligned_hash'] ?? null) ? $entry['aligned_hash'] : null;
        $overlayHash = is_string($entry['overlay_hash'] ?? null) ? $entry['overlay_hash'] : null;
        $rectification = $this->boundedRectification($entry['rectification'] ?? null);
        $registrationMode = $entry['registration_mode'] ?? null;
        if ($registrationMode !== null && $registrationMode !== $run->draft?->registration_mode?->value) {
            throw new RuntimeException('verification_registration_mode_mismatch');
        }
        $workerCodes = [];
        foreach (['layout', 'detection'] as $worker) {
            $code = $entry['worker_codes'][$worker] ?? null;
            $workerCodes[$worker] = is_string($code) && preg_match('/^[a-z0-9_]{1,64}$/', $code) === 1 ? $code : null;
        }
        $evidence = [
            'outcome' => ($entry['detection_outcome'] ?? null) === 'succeeded' ? 'succeeded' : 'failed',
            'code' => $entry['error_code'] ?? null,
            'detail' => $entry['detail'] ?? null,
            'page_no' => $pageNo,
            'registration_mode' => $registrationMode,
            'rectification' => $rectification,
            'worker_codes' => $workerCodes,
            'target' => null,
            'assets' => [
                'captured' => ['mime' => 'image/png', 'sha256' => $inputHash],
                'aligned' => $alignedHash !== null ? ['mime' => 'image/png', 'sha256' => $alignedHash] : null,
                'overlay' => $overlayHash !== null ? ['mime' => 'image/png', 'sha256' => $overlayHash] : null,
            ],
            'hashes' => [
                'input' => $inputHash,
                'aligned' => $alignedHash,
                'overlay' => $overlayHash,
                'plan' => $run->plan_hash,
            ],
            'gates' => $entry['gates'] ?? null,
            'marker_matches' => $entry['marker_matches'] ?? null,
            'field_block_shifts' => $entry['field_block_shifts'] ?? [],
            'items' => $items,
            'identity' => [],
            'summary' => $itemSummary,
            'timings' => $entry['timings'] ?? [],
            'issues' => $issues,
            'calibration_status' => 'unqualified',
            'plan_hash' => $run->plan_hash,
            'plan_purpose' => 'digital',
            'provider_item_evidence' => null,
        ];
        $summary = [
            'execution' => $entry['execution'] ?? null,
            'detail' => $entry['detail'] ?? null,
            'generator' => $entry['generator'] ?? null,
            'source_hash' => $entry['source_hash'] ?? null,
            'template_hash' => $entry['template_hash'] ?? null,
            'config_hash' => $entry['config_hash'] ?? null,
            'manifest_hash' => $entry['manifest_hash'] ?? null,
            'renderer' => $entry['renderer'] ?? null,
            'detection_outcome' => $entry['detection_outcome'] ?? null,
            'registration_mode' => $registrationMode,
            'rectification' => $rectification,
            'worker_codes' => $workerCodes,
            'gates' => $entry['gates'] ?? null,
            'marker_matches' => $entry['marker_matches'] ?? null,
            'field_block_shifts' => $entry['field_block_shifts'] ?? null,
            'timings' => $entry['timings'] ?? null,
            'answer_vector_match' => $entry['answer_vector_match'] ?? null,
            'item_summary' => $itemSummary,
            'ambiguous' => $itemSummary['ambiguous'],
            'missing' => $itemSummary['missing'],
            'issues' => $issues,
            'evidence' => $evidence,
        ];
        $evidenceHash = hash('sha256', json_encode([
            'variant' => $variant,
            'actual' => $entry['actual'] ?? null,
            'status' => $entry['status'] ?? null,
            'error_code' => $entry['error_code'] ?? null,
            'input_hash' => $inputHash,
            'aligned_hash' => $alignedHash,
            'overlay_hash' => $overlayHash,
            'vector_match' => $vectorMatch,
            'summary' => $summary,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $evidence['evidence_hash'] = $evidenceHash;
        $summary['evidence'] = $evidence;

        return OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => $run->attempt,
            'page_no' => $pageNo,
            'mode' => $run->mode,
            'variant' => $variant,
            'expected' => $entry['expected'] ?? null,
            'actual' => $entry['actual'] ?? null,
            'status' => $entry['status'] ?? 'fail',
            'error_code' => $entry['error_code'] ?? null,
            'input_hash' => $inputHash,
            'input_asset_id' => $inputAsset->id,
            'aligned_asset_id' => $assetRefs['aligned.png']['asset_id'] ?? null,
            'overlay_asset_id' => $assetRefs['layout-overlay.png']['asset_id'] ?? null,
            'evidence_hash' => $evidenceHash,
            'expected_vector_hash' => $run->expected_vector_hash,
            'detected_vector_hash' => $detectedVectorHash,
            'vector_match' => $vectorMatch,
            'summary' => $summary,
            'captured_by_user_id' => $run->started_by_user_id,
            'captured_at' => now(),
        ]);
    }

    /**
     * Keep forensic item evidence useful while bounding stored JSON. Problems
     * are placed first so a cap never hides the first diagnostic rows.
     *
     * @return list<array<string, mixed>>
     */
    private function boundedItems(mixed $value): array
    {
        $problems = [];
        $exact = [];
        foreach ((array) $value as $item) {
            if (! is_array($item)) {
                continue;
            }
            // The provider must never smuggle applicant/score mutation data
            // into read-only evidence, even if a future response adds fields.
            foreach (['score', 'scores', 'correctness', 'correct_option', 'answer_key', 'per_area_scores'] as $forbidden) {
                unset($item[$forbidden]);
            }
            $item['native_label'] = is_string($item['native_label'] ?? null) ? substr($item['native_label'], 0, 96) : null;
            $item['area_code'] = is_string($item['area_code'] ?? null) ? substr($item['area_code'], 0, 64) : null;
            $item['mismatch'] = in_array($item['mismatch'] ?? null, ['wrong', 'ambiguous', 'missing'], true) ? $item['mismatch'] : null;
            if (($item['exact'] ?? null) === true) {
                $exact[] = $item;
            } else {
                $problems[] = $item;
            }
        }
        $items = array_slice(array_merge($problems, $exact), 0, self::MAX_ITEM_EVIDENCE_ITEMS);
        if (strlen(json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) <= self::MAX_ITEM_EVIDENCE_BYTES) {
            return $items;
        }

        // Preserve labels, states, expected/detected values, and raw means if
        // a provider ever returns unusually large geometry metadata.
        $compact = [];
        foreach ($items as $item) {
            $item['options'] = array_map(static function (mixed $option): array {
                if (! is_array($option)) {
                    return ['code' => null, 'mean' => null];
                }

                return [
                    'code' => is_string($option['code'] ?? null) ? substr($option['code'], 0, 16) : null,
                    'mean' => isset($option['mean']) && is_numeric($option['mean']) ? (float) $option['mean'] : null,
                ];
            }, (array) ($item['options'] ?? []));
            unset($item['region']);
            $compact[] = $item;
        }
        while ($compact !== [] && strlen(json_encode($compact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) > self::MAX_ITEM_EVIDENCE_BYTES) {
            array_pop($compact);
        }

        return $compact;
    }

    /** @return array<string, mixed>|null */
    private function boundedRectification(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        if (array_key_exists('layout', $value) || array_key_exists('detection', $value)) {
            return [
                'layout' => $this->boundedRectification($value['layout'] ?? null),
                'detection' => $this->boundedRectification($value['detection'] ?? null),
                'accepted' => ($value['accepted'] ?? null) === true,
                'same_selected_warp' => ($value['same_selected_warp'] ?? null) === true,
            ];
        }

        $keys = [
            'attempted', 'applied', 'mode', 'status', 'reason', 'candidate_count',
            'plausible_candidate_count', 'selected_candidate_index', 'quad_normalized',
            'quad_area_ratio', 'quad_edge_score', 'quad_aspect_ratio_score',
            'quad_contrast_score', 'composite_score', 'homography_hash',
            'homography_count', 'warp_count',
        ];
        $bounded = array_intersect_key($value, array_flip($keys));
        foreach (['mode', 'status', 'reason', 'homography_hash'] as $key) {
            if (isset($bounded[$key])) {
                $bounded[$key] = is_string($bounded[$key]) ? substr($bounded[$key], 0, 96) : null;
            }
        }
        if (isset($bounded['quad_normalized'])) {
            $points = $bounded['quad_normalized'];
            if (! is_array($points) || ! array_is_list($points) || count($points) !== 4) {
                unset($bounded['quad_normalized']);
            } else {
                $quad = [];
                foreach ($points as $point) {
                    if (! is_array($point) || ! array_is_list($point) || count($point) !== 2
                        || ! is_numeric($point[0]) || ! is_numeric($point[1])
                        || ! is_finite((float) $point[0]) || ! is_finite((float) $point[1])
                        || (float) $point[0] < 0 || (float) $point[0] > 1
                        || (float) $point[1] < 0 || (float) $point[1] > 1) {
                        $quad = [];
                        break;
                    }
                    $quad[] = [(float) $point[0], (float) $point[1]];
                }
                if ($quad === []) {
                    unset($bounded['quad_normalized']);
                } else {
                    $bounded['quad_normalized'] = $quad;
                }
            }
        }
        foreach (['document_gates', 'content_gate'] as $key) {
            if (is_array($value[$key] ?? null)) {
                $allowed = $key === 'document_gates'
                    ? ['accepted', 'unique_quad', 'aspect_ratio', 'homography', 'distributed_content']
                    : ['accepted', 'expected_region_count', 'supported_region_count', 'minimum_supported_regions', 'spatially_distributed'];
                $bounded[$key] = array_intersect_key($value[$key], array_flip($allowed));
            }
        }

        return $bounded;
    }

    /** @return list<array{code:string,severity:string,area_code:?string,question_no:?int}> */
    private function boundedIssues(mixed $value): array
    {
        $issues = [];
        foreach ((array) $value as $issue) {
            if (! is_array($issue) || ! is_string($issue['code'] ?? null)
                || preg_match('/^[a-z0-9_]{1,64}$/', $issue['code']) !== 1) {
                continue;
            }
            $severity = is_string($issue['severity'] ?? null) ? $issue['severity'] : 'warning';
            if (! in_array($severity, ['warning', 'required', 'terminal'], true)) {
                $severity = 'warning';
            }
            $issues[] = [
                'code' => $issue['code'],
                'severity' => $severity,
                'area_code' => is_string($issue['area_code'] ?? null) ? substr($issue['area_code'], 0, 64) : null,
                'question_no' => isset($issue['question_no']) ? (int) $issue['question_no'] : null,
            ];
            if (count($issues) >= 64) {
                break;
            }
        }

        return $issues;
    }

    /**
     * @param  array<string,mixed>  $proof
     * @return array<string,mixed>
     */
    public function resultSummary(
        OmrTemplateVerificationRun $run,
        array $proof,
        string $detectedVectorHash,
    ): array {
        $captures = $run->captures()->where('attempt', $run->attempt)->with('alignedAsset')->with('overlayAsset')->orderBy('variant')->get();
        $variants = [];
        foreach ($captures as $capture) {
            if ($capture->variant === null) {
                continue;
            }
            $variants[$capture->variant] = [
                'expected' => $capture->expected,
                'actual' => $capture->actual,
                'status' => $capture->status,
                'error_code' => $capture->error_code,
                'execution' => $capture->summary['execution'] ?? null,
                'detail' => $capture->summary['detail'] ?? null,
                'generator' => $capture->summary['generator'] ?? null,
                'renderer' => $capture->summary['renderer'] ?? null,
                'input_hash' => $capture->input_hash,
                'input_asset_id' => $capture->input_asset_id,
                'answer_vector_match' => $capture->vector_match,
                'item_summary' => $capture->summary['item_summary'] ?? null,
                'issues' => $capture->summary['issues'] ?? [],
                'registration_mode' => $capture->summary['registration_mode'] ?? null,
                'rectification' => $capture->summary['rectification'] ?? null,
                'worker_codes' => $capture->summary['worker_codes'] ?? null,
                'aligned_hash' => $capture->alignedAsset?->sha256,
                'overlay_hash' => $capture->overlayAsset?->sha256,
            ];
        }

        return [
            'matrix_hash' => hash('sha256', json_encode($variants, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'variants' => $variants,
            'proof_hash' => $proof['proof_hash'],
            'expected_vector_hash' => $run->expected_vector_hash,
            'clean_vector_hash' => $detectedVectorHash,
            'threshold_status' => 'Unqualified',
        ];
    }

    /**
     * @param  array<string,mixed>  $proof
     * @param  array<string,mixed>  $summary
     */
    public function promoteDraft(
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateVerificationRun $run,
        array $proof,
        array $summary,
        string $detectedVectorHash,
    ): bool {
        $verificationHash = hash('sha256', json_encode([
            'run_id' => $run->id,
            'attempt' => $run->attempt,
            'plan_hash' => $run->plan_hash,
            'artifact_hash' => $run->artifact_hash,
            'proof_hash' => $proof['proof_hash'],
            'matrix_hash' => $summary['matrix_hash'],
            'clean_vector_hash' => $detectedVectorHash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $proofAssets = [];
        foreach ($proof['pages'] as $page) {
            $asset = OmrTemplateAsset::query()
                ->where('authoring_draft_id', $draft->id)
                ->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)
                ->where('page_no', (int) $page['page_no'])
                ->where('sha256', (string) $page['sha256'])
                ->first();
            if ($asset !== null) {
                $proofAssets[] = ['page_no' => (int) $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
            }
        }
        $verification = [
            'status' => 'passed',
            'artifact_hash' => $run->artifact_hash,
            'proof_hash' => $proof['proof_hash'],
            'proof_assets' => $proofAssets,
            'answer_vector' => $proof['answer_vector'],
            'matrix' => $summary,
            'hashes' => ['digital_verification' => $verificationHash],
            'threshold_status' => 'Unqualified',
            'run_id' => $run->id,
        ];

        return DB::transaction(function () use ($draft, $run, $verification): bool {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->find($draft->id);
            if ($locked === null) {
                return false;
            }
            $authoring = (array) $locked->authoring_json;
            $currentArtifactHash = (string) (($authoring['canonical']['artifact_set']['artifact_hash'] ?? ''));
            if ($locked->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                || $currentArtifactHash === ''
                || ! hash_equals($currentArtifactHash, $run->artifact_hash)) {
                return false;
            }
            $authoring['verification'] = $verification;
            $locked->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
                'authoring_json' => $authoring,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();

            return true;
        });
    }

    /**
     * AD-10: on failure delete ONLY newly-created assets that no capture row
     * references; evidence rows remain the resume authority.
     *
     * @param  list<OmrTemplateAsset>  $createdAssets
     */
    public function cleanupUnreferenced(array $createdAssets): void
    {
        if ($createdAssets === []) {
            return;
        }
        $ids = array_map(static fn (OmrTemplateAsset $asset): int => $asset->id, $createdAssets);
        $referenced = array_unique(array_values(array_filter(array_merge(
            OmrTemplateVerificationCapture::query()->whereIn('input_asset_id', $ids)->pluck('input_asset_id')->all(),
            OmrTemplateVerificationCapture::query()->whereIn('aligned_asset_id', $ids)->pluck('aligned_asset_id')->all(),
            OmrTemplateVerificationCapture::query()->whereIn('overlay_asset_id', $ids)->pluck('overlay_asset_id')->all(),
        ))));
        $referenced = array_flip($referenced);
        foreach ($createdAssets as $asset) {
            if (isset($referenced[$asset->id])) {
                continue;
            }
            Storage::disk($asset->disk)->delete($asset->path);
            $asset->delete();
        }
    }
}
