<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrLayoutVerificationResult;

use function array_diff_key;
use function count;
use function hash;
use function is_array;
use function json_encode;
use function max;

/**
 * Immutable, read-only normalized diagnostic evidence (OpenSpec 35.14, AD-5).
 *
 * One shape for candidate rehearsals, compiled Test capture, and grading
 * scanner review. Carries captured/aligned/overlay assets, gates, marker
 * matches, field-block shifts, declared-option item/ID evidence, expected and
 * detected values, the exact/ambiguous/missing summary, timings, hashes,
 * safe issues, and calibration status.
 *
 * PROHIBITED FIELDS: no applicant binding (roster/applicant ids), no score,
 * correctness, or any mutation command. Detection identity values are
 * advisory observations only; staff binding stays outside this object.
 *
 * `normalize()` is the canonical projection shared by every caller; asset
 * BYTES are excluded from it (only hashes remain), mirroring how the no-go
 * matrix hashes evidence without embedding rasters.
 */
final readonly class OmrDiagnosticEvidence
{
    public const OUTCOME_SUCCEEDED = 'succeeded';

    public const OUTCOME_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $target  normalized target identity block
     * @param  array<string, string|null>  $hashes  input|aligned|overlay|plan
     * @param  array<string, mixed>  $assets  captured|aligned|overlay with bytes
     * @param  list<array<string, mixed>>  $items  declared-option item evidence
     * @param  list<array<string, mixed>>  $identity  declared-option ID evidence
     * @param  array<string, float|null>  $timings
     * @param  list<array<string, mixed>>  $issues  safe typed issues
     */
    public function __construct(
        public string $outcome,
        public ?string $code,
        public ?string $detail,
        public int $pageNo,
        public array $target,
        public array $hashes,
        public array $assets,
        public ?array $gates,
        public ?array $markerMatches,
        public ?array $fieldBlockShifts,
        public array $items,
        public array $identity,
        public array $timings,
        public array $issues,
        public ?string $calibrationStatus,
        public ?string $planHash,
        public ?string $planPurpose,
        public ?array $providerItemEvidence,
        public ?OmrDetectionResult $providerResult = null,
    ) {
        $this->summary = self::summarize($this->items);
    }

    /** @var array{total: int, expected: int, exact: int, ambiguous: int, missing: int, vector_match: ?bool} */
    public array $summary;

    public function succeeded(): bool
    {
        return $this->outcome === self::OUTCOME_SUCCEEDED;
    }

    /** Canonical evidence hash over the normalized projection (no asset bytes). */
    public function evidenceHash(): string
    {
        return hash('sha256', json_encode($this->projection(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * The canonical projection: every evidence field, asset bytes replaced by
     * their hashes, plus the self-referential evidence hash. Identical
     * inputs produce an identical array for both target kinds (contract 35.16).
     *
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [...$this->projection(), 'evidence_hash' => $this->evidenceHash()];
    }

    /**
     * @return array<string, mixed>
     */
    private function projection(): array
    {
        $assets = [];
        foreach (['captured', 'aligned', 'overlay'] as $name) {
            $asset = $this->assets[$name] ?? null;
            $assets[$name] = is_array($asset) ? array_diff_key($asset, ['bytes' => true]) : null;
        }

        return [
            'outcome' => $this->outcome,
            'code' => $this->code,
            'detail' => $this->detail,
            'page_no' => $this->pageNo,
            'target' => $this->target,
            'hashes' => $this->hashes,
            'assets' => $assets,
            'gates' => $this->gates,
            'marker_matches' => $this->markerMatches,
            'field_block_shifts' => $this->fieldBlockShifts,
            'items' => $this->items,
            'identity' => $this->identity,
            'summary' => $this->summary,
            'timings' => $this->timings,
            'issues' => $this->issues,
            'calibration_status' => $this->calibrationStatus,
            'plan_hash' => $this->planHash,
            'plan_purpose' => $this->planPurpose,
            'provider_item_evidence' => $this->providerItemEvidence,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{total: int, expected: int, exact: int, ambiguous: int, missing: int, vector_match: ?bool}
     */
    private static function summarize(array $items): array
    {
        $expected = 0;
        $exact = 0;
        $ambiguous = 0;
        $expectedAmbiguous = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $isAmbiguous = (string) ($item['detected']['state'] ?? '') === OmrDetectedItem::STATE_AMBIGUOUS;
            if ($isAmbiguous) {
                $ambiguous++;
            }
            if (($item['expected'] ?? null) === null) {
                continue;
            }
            $expected++;
            if (($item['exact'] ?? null) === true) {
                $exact++;
            } elseif ($isAmbiguous) {
                $expectedAmbiguous++;
            }
        }

        return [
            'total' => count($items),
            'expected' => $expected,
            'exact' => $exact,
            'ambiguous' => $ambiguous,
            'missing' => max(0, $expected - $exact - $expectedAmbiguous),
            'vector_match' => $expected > 0 ? $exact === $expected : null,
        ];
    }

    /**
     * Build a failed-attempt evidence with the SAME normalized shape as a
     * success (contract 35.16): target/hash identity, timings, and any
     * layout assets gathered before the failure are retained.
     */
    public static function failed(
        OmrDiagnosticTarget $target,
        int $pageNo,
        string $code,
        ?string $detail,
        string $inputHash,
        ?string $capturedMime = null,
        ?string $capturedBytes = null,
        array $timings = [],
        ?OmrProofPlan $plan = null,
        ?OmrLayoutVerificationResult $layout = null,
        ?array $gates = null,
        array $issues = [],
        ?OmrDetectionResult $providerResult = null,
    ): self {
        $timings = self::normalizeTimings($timings);
        $hashes = ['input' => $inputHash, 'aligned' => null, 'overlay' => null, 'plan' => $plan?->planHash()];
        $assets = ['captured' => null, 'aligned' => null, 'overlay' => null];
        if (is_string($capturedMime) && is_string($capturedBytes)) {
            $assets['captured'] = ['mime' => $capturedMime, 'bytes' => $capturedBytes, 'sha256' => $inputHash];
        }
        if ($layout !== null) {
            $hashes['aligned'] = $layout->alignedBytes !== null ? hash('sha256', $layout->alignedBytes) : null;
            $hashes['overlay'] = $layout->overlayBytes !== null ? hash('sha256', $layout->overlayBytes) : null;
            $assets['aligned'] = self::asset($layout->alignedBytes, $layout->alignedImage);
            $assets['overlay'] = self::asset($layout->overlayBytes, $layout->overlayImage);
        }

        return new self(
            outcome: self::OUTCOME_FAILED,
            code: $code,
            detail: $detail,
            pageNo: $pageNo,
            target: self::targetIdentity($target, $pageNo),
            hashes: $hashes,
            assets: $assets,
            gates: $gates,
            markerMatches: $layout?->markerMatches,
            fieldBlockShifts: $layout?->fieldBlockShifts,
            items: [],
            identity: [],
            timings: $timings,
            issues: $issues,
            calibrationStatus: null,
            planHash: $plan?->planHash(),
            planPurpose: $plan?->purpose->value,
            providerItemEvidence: $layout?->itemEvidence,
            providerResult: $providerResult,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $identity
     * @param  list<array<string, mixed>>  $issues
     * @param  array<string, float|null>  $timings
     */
    public static function successful(
        OmrDiagnosticTarget $target,
        int $pageNo,
        string $inputHash,
        string $capturedMime,
        string $capturedBytes,
        OmrLayoutVerificationResult $layout,
        array $gates,
        array $items,
        array $identity,
        array $timings,
        array $issues,
        ?OmrProofPlan $plan = null,
        ?string $calibrationStatus = null,
        ?OmrDetectionResult $providerResult = null,
    ): self {
        $timings = self::normalizeTimings($timings);
        $hashes = ['input' => $inputHash, 'aligned' => null, 'overlay' => null, 'plan' => $plan?->planHash()];
        $assets = ['captured' => null, 'aligned' => null, 'overlay' => null];
        $assets['captured'] = ['mime' => $capturedMime, 'bytes' => $capturedBytes, 'sha256' => $inputHash];
        $hashes['aligned'] = $layout->alignedBytes !== null ? hash('sha256', $layout->alignedBytes) : null;
        $hashes['overlay'] = $layout->overlayBytes !== null ? hash('sha256', $layout->overlayBytes) : null;
        $assets['aligned'] = self::asset($layout->alignedBytes, $layout->alignedImage);
        $assets['overlay'] = self::asset($layout->overlayBytes, $layout->overlayImage);

        return new self(
            outcome: self::OUTCOME_SUCCEEDED,
            code: null,
            detail: null,
            pageNo: $pageNo,
            target: self::targetIdentity($target, $pageNo),
            hashes: $hashes,
            assets: $assets,
            gates: $gates,
            markerMatches: $layout->markerMatches,
            fieldBlockShifts: $layout->fieldBlockShifts,
            items: $items,
            identity: $identity,
            timings: $timings,
            issues: $issues,
            calibrationStatus: $calibrationStatus,
            planHash: $plan?->planHash(),
            planPurpose: $plan?->purpose->value,
            providerItemEvidence: $layout->itemEvidence,
            providerResult: $providerResult,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function targetIdentity(OmrDiagnosticTarget $target, int $pageNo): array
    {
        return [
            'kind' => $target->kind(),
            'id' => $target->targetId(),
            'page_count' => $target->pageCount(),
            'page_no' => $pageNo,
            'registration_mode' => $target->registrationMode()->value,
            'qr_mode' => $target->qrMode()->value,
            'compiled_pages' => $target->compiledPages(),
            'qualification_capable' => $target->qualificationCapable(),
            'marker_profile' => [
                'code' => $target->markerProfileCode(),
                'version' => $target->markerProfileVersion(),
                'qualified' => $target->markerProfileQualified(),
                'asset_name' => $target->markerAssetName(),
                'asset_hash' => $target->markerAssetHash(),
            ],
            'hashes' => [
                'artifact' => $target->artifactHash(),
                'native_bundle' => $target->nativeBundleHash(),
                'manifest' => $target->manifestHash(),
                'config' => $target->configHash(),
                'mapping' => $target->mappingHash(),
            ],
            'engine' => [
                'name' => $target->engineName(),
                'commit' => $target->engineCommit(),
                'patch_identity' => $target->patchIdentity(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $image
     * @return array<string, mixed>|null
     */
    private static function asset(?string $bytes, ?array $image): ?array
    {
        if ($bytes === null || ! is_array($image)) {
            return null;
        }

        return [
            'mime' => (string) ($image['mime'] ?? ''),
            'width' => isset($image['width']) ? (int) $image['width'] : null,
            'height' => isset($image['height']) ? (int) $image['height'] : null,
            'sha256' => hash('sha256', $bytes),
            'bytes' => $bytes,
        ];
    }

    /**
     * Canonical timings keys so every evidence (success or any typed failure)
     * carries the same shape (contract 35.16).
     *
     * @param  array<string, float|null>  $timings
     * @return array{preprocess_ms: ?float, layout_verification_ms: ?float, detection_ms: ?float}
     */
    private static function normalizeTimings(array $timings): array
    {
        return [
            'preprocess_ms' => isset($timings['preprocess_ms']) ? (float) $timings['preprocess_ms'] : null,
            'layout_verification_ms' => isset($timings['layout_verification_ms']) ? (float) $timings['layout_verification_ms'] : null,
            'detection_ms' => isset($timings['detection_ms']) ? (float) $timings['detection_ms'] : null,
        ];
    }
}
