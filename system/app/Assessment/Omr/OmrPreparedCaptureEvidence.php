<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticEvidence;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use RuntimeException;

use function array_diff_key;
use function hash;
use function is_array;
use function json_encode;

/**
 * Immutable result crossing the shared capture-analysis boundary.
 *
 * It records the original input identity, every ordered orientation attempt,
 * the accepted layout, and the existing normalized diagnostic evidence. It has
 * no workflow, persistence, score, qualification, or audit operations.
 */
final readonly class OmrPreparedCaptureEvidence
{
    public const OUTCOME_SUCCEEDED = 'succeeded';

    public const OUTCOME_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, string|null>  $hashes
     * @param  array<string, mixed>  $assets
     * @param  array<string, mixed>|null  $gates
     * @param  array<string, mixed>|null  $markerMatches
     * @param  list<array<string, mixed>>|null  $fieldBlockShifts
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $identity
     * @param  array<string, float|null>  $timings
     * @param  list<array<string, mixed>>  $issues
     */
    public function __construct(
        public string $outcome,
        public ?string $code,
        public ?string $detail,
        public int $pageNo,
        public string $inputHash,
        public ?int $acceptedOrientation,
        public OmrQuarterTurnDiagnostics $orientationDiagnostics,
        public ?OmrLayoutVerificationResult $layout,
        public ?OmrDiagnosticEvidence $diagnosticEvidence,
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
        public ?OmrDetectionResult $providerResult = null,
        public ?OmrDetectionRequest $detectionRequest = null,
        public ?string $expectedVectorHash = null,
        public ?OmrCaptureInput $input = null,
    ) {
        $this->diagnostic = $diagnosticEvidence;
    }

    public readonly ?OmrDiagnosticEvidence $diagnostic;

    public function succeeded(): bool
    {
        return $this->outcome === self::OUTCOME_SUCCEEDED;
    }

    public function acceptedOrientation(): ?int
    {
        return $this->acceptedOrientation;
    }

    /**
     * Existing callers retain the old normalized evidence DTO while callers
     * that need orientation provenance consume this wrapper directly.
     *
     * @throws RuntimeException when a failure was built without a diagnostic target
     */
    public function asDiagnosticEvidence(): OmrDiagnosticEvidence
    {
        if ($this->diagnosticEvidence !== null) {
            return $this->diagnosticEvidence;
        }
        if ($this->input === null || $this->target === []) {
            throw new RuntimeException('Prepared capture evidence has no diagnostic target.');
        }

        throw new RuntimeException('Prepared capture evidence is missing its diagnostic evidence.');
    }

    public function evidenceHash(): string
    {
        return hash('sha256', json_encode($this->projection(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function normalize(): array
    {
        return [...$this->projection(), 'evidence_hash' => $this->evidenceHash()];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->normalize();
    }

    /** @return array<string, mixed> */
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
            'input_hash' => $this->inputHash,
            'accepted_orientation' => $this->acceptedOrientation,
            'orientation_diagnostics' => $this->orientationDiagnostics->toArray(),
            'layout' => $this->layoutProjection(),
            'target' => $this->target,
            'hashes' => $this->hashes,
            'assets' => $assets,
            'gates' => $this->gates,
            'marker_matches' => $this->markerMatches,
            'field_block_shifts' => $this->fieldBlockShifts,
            'items' => $this->items,
            'identity' => $this->identity,
            'timings' => $this->timings,
            'issues' => $this->issues,
            'expected_vector_hash' => $this->expectedVectorHash,
            'rectification' => $this->layout?->rectification ?? $this->providerResult?->success?->rectification ?? null,
            'diagnostic' => $this->diagnosticEvidence?->normalize(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function layoutProjection(): ?array
    {
        if ($this->layout === null) {
            return null;
        }

        return [
            'outcome' => $this->layout->outcome,
            'code' => $this->layout->code,
            'detail' => $this->layout->detail,
            'aligned_image' => $this->layout->alignedImage,
            'overlay_image' => $this->layout->overlayImage,
            'marker_matches' => $this->layout->markerMatches,
            'field_block_shifts' => $this->layout->fieldBlockShifts,
            'gates' => $this->layout->gates,
            'preprocess_ms' => $this->layout->preprocessMs,
            'verification_ms' => $this->layout->verificationMs,
            'rectification' => $this->layout->rectification,
        ];
    }

    public static function failed(
        ?OmrDiagnosticTarget $target,
        int $pageNo,
        string $inputHash,
        string $code,
        ?string $detail,
        ?OmrCaptureInput $input = null,
        ?OmrQuarterTurnDiagnostics $orientationDiagnostics = null,
        ?OmrLayoutVerificationResult $layout = null,
        ?array $gates = null,
        ?array $markerMatches = null,
        ?array $fieldBlockShifts = null,
        array $timings = [],
        array $issues = [],
        ?string $expectedVectorHash = null,
        ?OmrDetectionResult $providerResult = null,
        ?OmrDetectionRequest $detectionRequest = null,
    ): self {
        $diagnostic = null;
        $assets = ['captured' => null, 'aligned' => null, 'overlay' => null];
        $hashes = ['input' => $inputHash, 'aligned' => null, 'overlay' => null, 'plan' => null];
        if ($target !== null && $input !== null) {
            $diagnostic = OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: $code,
                detail: $detail,
                inputHash: $inputHash,
                capturedMime: $input->mime,
                capturedBytes: $input->bytes,
                timings: $timings,
                layout: $layout,
                gates: $gates,
                issues: $issues,
            );
            $assets = $diagnostic->assets;
            $hashes = $diagnostic->hashes;
        }

        return new self(
            outcome: self::OUTCOME_FAILED,
            code: $code,
            detail: $detail,
            pageNo: $pageNo,
            inputHash: $inputHash,
            acceptedOrientation: null,
            orientationDiagnostics: $orientationDiagnostics ?? new OmrQuarterTurnDiagnostics([]),
            layout: $layout,
            diagnosticEvidence: $diagnostic,
            target: $diagnostic?->target ?? ($target !== null ? ['kind' => $target->kind(), 'id' => $target->targetId()] : []),
            hashes: $hashes,
            assets: $assets,
            gates: $gates,
            markerMatches: $markerMatches ?? $layout?->markerMatches,
            fieldBlockShifts: $fieldBlockShifts ?? $layout?->fieldBlockShifts,
            items: [],
            identity: [],
            timings: self::timings($timings),
            issues: $issues,
            providerResult: $providerResult,
            detectionRequest: $detectionRequest,
            expectedVectorHash: $expectedVectorHash,
            input: $input,
        );
    }

    public static function fromDiagnostic(
        OmrDiagnosticTarget $target,
        OmrCaptureInput $input,
        int $pageNo,
        OmrQuarterTurnDiagnostics $orientationDiagnostics,
        OmrDiagnosticEvidence $diagnostic,
        ?int $acceptedOrientation,
        ?OmrLayoutVerificationResult $layout = null,
        ?OmrDetectionResult $providerResult = null,
        ?OmrDetectionRequest $detectionRequest = null,
        ?string $expectedVectorHash = null,
    ): self {
        return new self(
            outcome: $diagnostic->outcome,
            code: $diagnostic->code,
            detail: $diagnostic->detail,
            pageNo: $pageNo,
            inputHash: $input->sourceHash(),
            acceptedOrientation: $acceptedOrientation,
            orientationDiagnostics: $orientationDiagnostics,
            layout: $layout ?? ($diagnostic->markerMatches !== null || $diagnostic->gates !== null
                ? new OmrLayoutVerificationResult(
                    outcome: $diagnostic->succeeded() ? OmrLayoutVerificationResult::OUTCOME_SUCCEEDED : OmrLayoutVerificationResult::OUTCOME_FAILED,
                    code: $diagnostic->succeeded() ? null : $diagnostic->code,
                    detail: $diagnostic->succeeded() ? null : $diagnostic->detail,
                    markerMatches: $diagnostic->markerMatches,
                    fieldBlockShifts: $diagnostic->fieldBlockShifts,
                    gates: $diagnostic->gates,
                )
                : null),
            diagnosticEvidence: $diagnostic,
            target: $diagnostic->target,
            hashes: [
                ...$diagnostic->hashes,
                'original_input' => $input->sourceHash(),
                'normalized_input' => $diagnostic->hashes['input'] ?? null,
            ],
            assets: $diagnostic->assets,
            gates: $diagnostic->gates,
            markerMatches: $diagnostic->markerMatches,
            fieldBlockShifts: $diagnostic->fieldBlockShifts,
            items: $diagnostic->items,
            identity: $diagnostic->identity,
            timings: self::timings($diagnostic->timings),
            issues: $diagnostic->issues,
            providerResult: $providerResult ?? $diagnostic->providerResult,
            detectionRequest: $detectionRequest,
            expectedVectorHash: $expectedVectorHash,
            input: $input,
        );
    }

    /** @param array<string, float|null> $timings @return array<string, float|null> */
    private static function timings(array $timings): array
    {
        return [
            'preprocess_ms' => isset($timings['preprocess_ms']) ? (float) $timings['preprocess_ms'] : null,
            'layout_verification_ms' => isset($timings['layout_verification_ms']) ? (float) $timings['layout_verification_ms'] : null,
            'detection_ms' => isset($timings['detection_ms']) ? (float) $timings['detection_ms'] : null,
        ];
    }
}
