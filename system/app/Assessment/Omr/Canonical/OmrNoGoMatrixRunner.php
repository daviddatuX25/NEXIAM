<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionIssue;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrDocumentRectification;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use RuntimeException;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_key_exists;
use function count;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_equals;
use function hash_file;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function min;
use function preg_match;
use function strtolower;
use function strtoupper;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class OmrNoGoMatrixRunner
{
    public const VARIANTS = [
        'clean',
        'perspective',
        'rotation',
        'uneven_light',
        'blur',
        'glare',
        'low_resolution',
        'partial_page',
        'missing_marker',
        'wrong_marker',
        'wrong_instance',
        'superseded_instance',
        'duplicate_capture',
        'multipage_order',
    ];

    public const DOCUMENT_BOUND_VARIANTS = [
        'clean',
        'mild_shear',
        'rotation',
        'uneven_light',
        'cropped_page',
        'background',
        'aspect_mismatch',
        'ambiguous_boundary',
    ];

    private const DOCUMENT_BOUND_EXACT_RECOVERY_VARIANTS = [
        'clean',
        'mild_shear',
        'rotation',
        'uneven_light',
    ];

    /** @return list<string> */
    public static function variantsForMode(OmrRegistrationMode $mode): array
    {
        return match ($mode) {
            OmrRegistrationMode::FiducialAnchored => self::VARIANTS,
            OmrRegistrationMode::DocumentBound => self::DOCUMENT_BOUND_VARIANTS,
        };
    }

    public static function expectsExactRecovery(OmrRegistrationMode $mode, string $variant): bool
    {
        return match ($mode) {
            OmrRegistrationMode::FiducialAnchored => in_array($variant, self::EXACT_RECOVERY_VARIANTS, true),
            OmrRegistrationMode::DocumentBound => in_array($variant, self::DOCUMENT_BOUND_EXACT_RECOVERY_VARIANTS, true),
        };
    }

    /** @return list<string> */
    public static function expectedDocumentRejectionCodes(string $variant): array
    {
        return match ($variant) {
            'cropped_page' => ['document_aspect_mismatch', 'document_quad_not_found', 'document_content_missing'],
            'background' => ['document_quad_not_found'],
            'aspect_mismatch' => ['document_aspect_mismatch', 'document_quad_not_found'],
            'ambiguous_boundary' => ['ambiguous_rectification', 'document_quad_not_found'],
            default => [],
        };
    }

    /** @return list<string> */
    public static function variantKeys(int $pageCount, ?OmrRegistrationMode $mode = null): array
    {
        $variants = self::variantsForMode($mode ?? OmrRegistrationMode::FiducialAnchored);
        if ($pageCount <= 1) {
            return $variants;
        }

        $keys = [];
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            foreach ($variants as $variant) {
                $keys[] = "page-{$pageNo}:{$variant}";
            }
        }

        return $keys;
    }

    /** @return array{page_no:int,variant:string} */
    public static function parseVariantKey(string $key, ?OmrRegistrationMode $mode = null): array
    {
        $variants = self::variantsForMode($mode ?? OmrRegistrationMode::FiducialAnchored);
        if (in_array($key, $variants, true)) {
            return ['page_no' => 1, 'variant' => $key];
        }
        if (preg_match('/^page-([1-4]):([a-z_]+)$/', $key, $matches) !== 1
            || ! in_array($matches[2], $variants, true)) {
            throw new RuntimeException('verification_variant_invalid');
        }

        return ['page_no' => (int) $matches[1], 'variant' => $matches[2]];
    }

    private const APPLICATION_GATE_VARIANTS = [
        'wrong_instance',
        'superseded_instance',
        'duplicate_capture',
        'multipage_order',
    ];

    private const EXACT_RECOVERY_VARIANTS = [
        'clean',
        'perspective',
        'rotation',
        'uneven_light',
        'blur',
        'glare',
        'low_resolution',
    ];

    public function __construct(
        private readonly OmrDiagnosticRequestBuilder $requests,
        private readonly OmrLayoutVerificationPort $detection,
        private readonly OmrDetectionPort $detector,
    ) {}

    /**
     * Execute mode-specific digital variants through the real detector and
     * layout-verification workers. Signed-instance and capture-lifecycle
     * variants remain explicit application gates for anchored sheets because
     * OMRChecker does not own that identity state.
     *
     * @param  array<string,mixed>  $proof
     * @param  null|callable(string,string,string):array<string,mixed>  $persistEvidence
     * @return array{variants:array<string,array<string,mixed>>,matrix_hash:string}
     */
    public function run(CanonicalArtifactSet $artifact, array &$proof, ?callable $persistEvidence = null): array
    {
        if (($proof['proof_hash'] ?? null) === null || ! is_array($proof['pages'][0] ?? null)) {
            throw new RuntimeException('no_go_proof_missing');
        }
        $mode = $this->registrationModeFor($artifact, null, $proof);
        $cleanBytes = (string) $proof['pages'][0]['bytes'];
        $cleanPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-clean-');
        if ($cleanPath === false || file_put_contents($cleanPath, $cleanBytes) === false) {
            if ($cleanPath !== false) {
                @unlink($cleanPath);
            }
            throw new RuntimeException('no_go_clean_source_write_failed');
        }
        unset($cleanBytes, $proof['pages'][0]['bytes']);

        try {
            $variants = [];
            foreach (self::variantsForMode($mode) as $variant) {
                $variants[$variant] = $this->runVariant($variant, $cleanPath, $artifact, $proof, $persistEvidence, 1, $mode);
            }
            $failed = array_filter($variants, static fn (array $variant): bool => ($variant['status'] ?? null) !== 'pass');
            if ($failed !== []) {
                throw new RuntimeException('no_go_variant_failed: '.implode(', ', array_keys($failed)));
            }

            $hashable = $variants;
            foreach ($hashable as &$entry) {
                if (is_array($entry)) {
                    unset($entry['aligned_bytes'], $entry['overlay_bytes'], $entry['evidence_assets']);
                }
            }
            unset($entry);

            return [
                'variants' => $variants,
                'matrix_hash' => hash('sha256', json_encode($hashable, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ];
        } finally {
            @unlink($cleanPath);
        }
    }

    /**
     * Execute ONE no-go variant and return its normalized entry. The durable
     * verification job calls this per variant so it can persist progress and
     * evidence after each ACTUAL variant instead of one long batch call.
     *
     * @param  array<string,mixed>  $proof
     * @param  null|callable(string,string,string):array<string,mixed>  $persistEvidence
     * @return array<string,mixed>
     */
    public function runVariant(
        string $variant,
        string $cleanPath,
        CanonicalArtifactSet $artifact,
        array $proof,
        ?callable $persistEvidence = null,
        int $pageNo = 1,
        ?OmrRegistrationMode $pinnedMode = null,
    ): array {
        $mode = $this->registrationModeFor($artifact, $pinnedMode, $proof);
        if ($pageNo < 1 || $pageNo > count((array) ($artifact->template['pages'] ?? []))) {
            throw new RuntimeException('verification_page_invalid');
        }
        if (! in_array($variant, self::variantsForMode($mode), true)) {
            throw new RuntimeException('verification_variant_invalid');
        }
        $exactRecovery = self::expectsExactRecovery($mode, $variant);
        $expected = $exactRecovery ? 'pass' : 'typed_rejection';
        $inputBytes = file_get_contents($cleanPath);
        if (! is_string($inputBytes) || $inputBytes === '') {
            throw new RuntimeException('no_go_clean_source_read_failed');
        }
        $paperBounds = (array) ($proof['paper_bounds'] ?? ($proof['pages'][0]['paper_bounds'] ?? []));

        // Capture-lifecycle checks apply only to QR-signed anchored sheets.
        if (in_array($variant, self::APPLICATION_GATE_VARIANTS, true)) {
            if ($mode !== OmrRegistrationMode::FiducialAnchored) {
                throw new RuntimeException('no_go_application_gate_mode_invalid');
            }
            $entry = $this->applicationGate($variant, $expected, hash('sha256', $inputBytes));
            $entry['page_no'] = $pageNo;
            $entry['registration_mode'] = $mode->value;

            return $this->persistVariantInput($entry, $variant, $inputBytes, $persistEvidence);
        }

        $bytes = $variant === 'clean'
            ? $inputBytes
            : $this->variantBytes($cleanPath, $variant, $artifact, $mode, $paperBounds);
        if ($mode === OmrRegistrationMode::DocumentBound && $variant !== 'clean'
            && hash_equals(hash('sha256', $inputBytes), hash('sha256', $bytes))) {
            throw new RuntimeException('no_go_variant_noop');
        }
        $request = $this->requests->buildCandidate($artifact, $bytes, 'image/png', $pageNo);

        // Every image variant must traverse the detector. Layout success alone
        // is not recognition evidence and may never make a mild variant pass.
        $detected = $this->detector->detect($request);
        $answerMatch = $detected->outcome === OmrDetectionOutcome::Succeeded && $detected->success !== null
            ? self::answerVectorMatches($proof['answer_vector'] ?? [], $detected->success->items)
            : false;
        $result = $this->detection->verifyLayout($request);
        $workerCodes = [
            'layout' => $result->code,
            'detection' => $detected->safeDiagnosticCode,
        ];
        $rectification = null;
        $gates = (array) $result->gates;
        if ($mode === OmrRegistrationMode::DocumentBound) {
            $layoutRectification = $result->rectification;
            $detectionRectification = $detected->success?->rectification ?? $detected->rectification;
            $layoutAccepted = OmrDocumentRectification::isAccepted($layoutRectification);
            $detectionAccepted = OmrDocumentRectification::isAccepted($detectionRectification);
            $sameSelectedWarp = OmrDocumentRectification::sameSelectedWarp($layoutRectification, $detectionRectification);
            $documentAccepted = $result->succeeded()
                && $detected->outcome === OmrDetectionOutcome::Succeeded
                && $detected->success !== null
                && $layoutAccepted
                && $detectionAccepted
                && $sameSelectedWarp;
            $rectification = [
                'layout' => $layoutRectification,
                'detection' => $detectionRectification,
                'accepted' => $documentAccepted,
                'same_selected_warp' => $sameSelectedWarp,
            ];
            $gates = [
                ...$gates,
                'marker_count' => 'not_applicable',
                'registration' => $documentAccepted ? 'pass' : 'fail',
                'document' => (($layoutRectification['document_gates']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                'content' => (($layoutRectification['content_gate']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                'rectification_consistency' => $sameSelectedWarp ? 'pass' : 'fail',
            ];
            if ($exactRecovery) {
                $actual = $documentAccepted
                    ? ($answerMatch ? 'pass' : 'answer_vector_mismatch')
                    : 'failed';
            } else {
                $actual = $documentAccepted
                    ? 'false_accept'
                    : ($this->documentRejectionIsQualified($variant, $result, $detected, $layoutRectification, $detectionRectification)
                        ? 'typed_rejection'
                        : 'failed');
            }
        } else {
            $markerGatePasses = $this->markerGatePasses($result->markerMatches);
            $actual = $exactRecovery
                ? ($result->succeeded() && $answerMatch ? 'pass' : ($answerMatch === false ? 'answer_vector_mismatch' : 'typed_rejection'))
                : ($markerGatePasses && $result->succeeded() && $detected->outcome === OmrDetectionOutcome::Succeeded
                    ? 'false_accept'
                    : 'typed_rejection');
        }
        $alignedBytes = $result->alignedBytes;
        $overlayBytes = $result->overlayBytes;
        $itemEvidence = $this->itemEvidence(
            $request,
            $result,
            $detected->success,
            (array) ($proof['answer_vector'] ?? []),
        );
        $inputHash = hash('sha256', $bytes);
        $sourceHash = hash_file('sha256', $cleanPath);
        if ($sourceHash === false) {
            throw new RuntimeException('no_go_clean_source_hash_failed');
        }
        $entry = [
            'expected' => $expected,
            'actual' => $actual,
            'status' => $actual === $expected ? 'pass' : 'fail',
            'registration_mode' => $mode->value,
            'execution' => 'omrchecker_layout_verification',
            'generator' => [
                'name' => 'nexiam.omr.variant',
                'path' => 'bin/omr-variant.php',
                'sha256' => hash_file('sha256', base_path('bin/omr-variant.php')) ?: null,
            ],
            'source_hash' => $sourceHash,
            'template_hash' => $artifact->templateHash,
            'config_hash' => $artifact->configHash,
            'manifest_hash' => $artifact->reviewManifestHash,
            'renderer' => [
                'name' => 'OmrDigitalProofGenerator',
                'bubble_shader' => OmrManifestBubbleShader::class,
                'version' => OmrManifestBubbleShader::RENDERER_VERSION,
            ],
            'detection_outcome' => $detected->outcome->value,
            'input_hash' => $inputHash,
            'error_code' => $result->code ?? ($detected->safeDiagnosticCode),
            'worker_codes' => $mode === OmrRegistrationMode::DocumentBound ? $workerCodes : null,
            'rectification' => $rectification,
            'detail' => $result->detail ?? ($detected->safeDiagnosticMessage),
            'answer_vector_match' => $mode === OmrRegistrationMode::DocumentBound && ! $exactRecovery ? null : $answerMatch,
            'page_no' => $pageNo,
            'detected_items' => $detected->success?->items ?? [],
            'item_evidence' => $itemEvidence,
            'item_summary' => self::summarizeItems($itemEvidence),
            'issues' => $this->safeIssues(
                $detected->success?->issues ?? [],
                $gates,
                $result->code ?? $detected->safeDiagnosticCode,
            ),
            'aligned_hash' => $alignedBytes !== null ? hash('sha256', $alignedBytes) : null,
            'overlay_hash' => $overlayBytes !== null ? hash('sha256', $overlayBytes) : null,
            'aligned_image' => $result->alignedImage,
            'overlay_image' => $result->overlayImage,
            'marker_matches' => $mode === OmrRegistrationMode::FiducialAnchored ? $result->markerMatches : null,
            'field_block_shifts' => $result->fieldBlockShifts,
            'gates' => $gates,
            'timings' => ['preprocess_ms' => $result->preprocessMs, 'verification_ms' => $result->verificationMs],
        ];
        if ($persistEvidence === null) {
            $entry['aligned_bytes'] = $alignedBytes;
            $entry['overlay_bytes'] = $overlayBytes;
        } else {
            $entry = $this->persistVariantInput($entry, $variant, $bytes, $persistEvidence);
            foreach (['aligned' => $alignedBytes, 'overlay' => $overlayBytes] as $role => $evidenceBytes) {
                if (is_string($evidenceBytes)) {
                    $fileName = $role === 'aligned' ? 'aligned.png' : 'layout-overlay.png';
                    $entry['evidence_assets'][] = $persistEvidence($variant, $fileName, $evidenceBytes);
                }
            }
        }

        return $entry;

    }

    /** Persist the exact transformed input before any derived rasters. */
    private function persistVariantInput(array $entry, string $variant, string $bytes, ?callable $persistEvidence): array
    {
        if ($persistEvidence === null) {
            return $entry;
        }
        $inputHash = hash('sha256', $bytes);
        $asset = $persistEvidence($variant, 'input.png', $bytes);
        if (! is_array($asset) || ! is_string($asset['sha256'] ?? null) || ! hash_equals($inputHash, (string) $asset['sha256'])) {
            throw new RuntimeException('no_go_input_asset_hash_mismatch');
        }
        $entry['evidence_assets'] = [$asset];

        return $entry;
    }

    /** Build safe item evidence from pinned geometry and provider observations.
     * @param  array<string, string>  $expected
     * @return list<array<string, mixed>>
     */
    private function itemEvidence(
        OmrDetectionRequest $request,
        OmrLayoutVerificationResult $layout,
        ?OmrDetectionSuccess $success,
        array $expected,
    ): array {
        $detectedByKey = [];
        $detectedItems = $success?->items ?? [];
        foreach ($detectedItems as $item) {
            $detectedByKey[strtoupper($item->areaCode).'_Q_'.(int) $item->questionNo] = $item;
        }
        $means = [];
        foreach ((array) ($layout->bubbleMeans ?? []) as $entries) {
            foreach ((array) $entries as $entry) {
                if (! is_array($entry) || ! is_string($entry['label'] ?? null)) {
                    continue;
                }
                $key = (string) $entry['label'].'|'.(string) ($entry['option'] ?? '');
                $means[$key] = array_key_exists('mean', $entry) && $entry['mean'] !== null ? (float) $entry['mean'] : null;
            }
        }
        $registeredRegions = OmrTemplateGeometryProjector::registeredOptionRegions($layout);
        $items = [];
        foreach ($request->items as $geometry) {
            $key = strtoupper($geometry->areaCode).'_Q_'.(int) $geometry->questionNo;
            $detected = $detectedByKey[$key] ?? null;
            $expectedValue = $expected[$geometry->nativeLabel] ?? null;
            if ($expectedValue === null) {
                foreach ($expected as $label => $value) {
                    if (strtolower((string) $label) === strtolower($geometry->nativeLabel)) {
                        $expectedValue = (string) $value;
                        break;
                    }
                }
            }
            $hasRegisteredRegion = false;
            $options = [];
            foreach ($geometry->options as $option) {
                $meanKey = $geometry->nativeLabel.'|'.$option->code;
                $regionKey = $geometry->nativeLabel.'|'.$option->code;
                $hasRegisteredRegion = $hasRegisteredRegion || isset($registeredRegions[$regionKey]);
                $options[] = [
                    'code' => $option->code,
                    'display_order' => $option->displayOrder,
                    'region' => $registeredRegions[$regionKey] ?? $option->region->toArray(),
                    'mean' => $means[$meanKey] ?? null,
                    'threshold_status' => 'unqualified',
                ];
            }
            $itemRegion = $hasRegisteredRegion
                ? OmrTemplateGeometryProjector::unionRegions(array_map(
                    static fn (array $option): array => $option['region'],
                    $options,
                ))
                : $geometry->region->toArray();
            $exact = null;
            $mismatch = null;
            if ($expectedValue !== null) {
                $state = $detected?->state;
                if ($detected === null || $state === OmrDetectedItem::STATE_BLANK) {
                    $mismatch = 'missing';
                } elseif ($state === OmrDetectedItem::STATE_AMBIGUOUS || $state === OmrDetectedItem::STATE_UNREADABLE) {
                    $mismatch = 'ambiguous';
                } elseif ($state === OmrDetectedItem::STATE_MARKED && (string) $detected->selectedOption === (string) $expectedValue) {
                    $exact = true;
                } else {
                    $mismatch = 'wrong';
                }
                if ($mismatch !== null) {
                    $exact = false;
                }
            }
            $items[] = [
                'position' => $geometry->position,
                'area_code' => $geometry->areaCode,
                'question_no' => $geometry->questionNo,
                'native_label' => $geometry->nativeLabel,
                'page_no' => $geometry->pageNo,
                'region' => $itemRegion,
                'options' => $options,
                'detected' => $detected !== null ? [
                    'state' => $detected->state,
                    'selected_option' => $detected->selectedOption,
                    'confidence' => null,
                ] : null,
                'expected' => $expectedValue,
                'exact' => $exact,
                'mismatch' => $mismatch,
            ];
        }

        return $items;
    }

    /** Keep only typed, bounded issue fields from the provider/layout seam.
     * @param  list<OmrDetectionIssue>  $issues
     * @param  array<string, mixed>|null  $gates
     * @return list<array<string, mixed>>
     */
    private function safeIssues(array $issues, ?array $gates, ?string $fallbackCode = null): array
    {
        $safe = [];
        foreach ($issues as $issue) {
            if (! $issue instanceof OmrDetectionIssue || ! OmrDetectionIssue::isKnownCode($issue->code) || ! OmrDetectionIssue::isKnownSeverity($issue->severity)) {
                continue;
            }
            $safe[] = ['code' => $issue->code, 'severity' => $issue->severity, 'area_code' => $issue->areaCode, 'question_no' => $issue->questionNo];
        }
        if ($fallbackCode !== null && preg_match('/^[a-z0-9_]{1,64}$/', $fallbackCode) === 1) {
            $known = array_filter($safe, static fn (array $issue): bool => $issue['code'] === $fallbackCode);
            if ($known === []) {
                $safe[] = ['code' => $fallbackCode, 'severity' => OmrDetectionIssue::SEVERITY_TERMINAL, 'area_code' => null, 'question_no' => null];
            }
        }
        $failedGates = array_filter((array) $gates, static fn (mixed $status, string $name): bool => $name !== 'unsafe_blocks' && $status !== 'pass', ARRAY_FILTER_USE_BOTH);
        if ($failedGates !== [] && array_filter($safe, static fn (array $issue): bool => $issue['code'] === OmrDetectionIssue::CODE_SHEET_MISALIGNED) === []) {
            $safe[] = ['code' => OmrDetectionIssue::CODE_SHEET_MISALIGNED, 'severity' => OmrDetectionIssue::SEVERITY_WARNING, 'area_code' => null, 'question_no' => null];
        }

        return $safe;
    }

    /** @param list<array<string, mixed>> $items
     * @return array{total:int,expected:int,exact:int,ambiguous:int,missing:int,vector_match:?bool}
     */
    public static function summarizeItems(array $items): array
    {
        $expected = 0;
        $exact = 0;
        $ambiguous = 0;
        foreach ($items as $item) {
            if (! is_array($item) || ($item['expected'] ?? null) === null) {
                continue;
            }
            $expected++;
            if (($item['exact'] ?? null) === true) {
                $exact++;
            } elseif (($item['mismatch'] ?? null) === 'ambiguous' || in_array((string) ($item['detected']['state'] ?? ''), [OmrDetectedItem::STATE_AMBIGUOUS, OmrDetectedItem::STATE_UNREADABLE], true)) {
                $ambiguous++;
            }
        }

        return [
            'total' => count($items),
            'expected' => $expected,
            'exact' => $exact,
            'ambiguous' => $ambiguous,
            'missing' => max(0, $expected - $exact - $ambiguous),
            'vector_match' => $expected > 0 ? $exact === $expected : null,
        ];
    }

    /**
     * Observed label=>option map for marked items. Keys use the engine
     * grammar (`AREA_Q_NNN`, uppercase area).
     *
     * @param  list<OmrDetectedItem>  $items
     * @return array<string, string>
     */
    public static function observedVector(array $items): array
    {
        $observed = [];
        foreach ($items as $item) {
            if ($item->state !== OmrDetectedItem::STATE_MARKED) {
                continue;
            }
            $observed[strtoupper((string) $item->areaCode).'_Q_'.(int) $item->questionNo] = (string) ($item->selectedOption ?? '');
        }

        return $observed;
    }

    /**
     * Detected vector restricted to the expected labels, keyed by the
     * expected label spelling (for exact-vector comparison/hash).
     *
     * @param  array<string, string>  $expected
     * @param  list<OmrDetectedItem>  $items
     * @return array<string, string>
     */
    public static function detectedVector(array $expected, array $items): array
    {
        $observed = self::observedVector($items);
        $vector = [];
        foreach ($expected as $label => $_option) {
            $key = self::findObservedKey($observed, (string) $label);
            if ($key !== null) {
                $vector[(string) $label] = (string) $observed[$key];
            }
        }

        return $vector;
    }

    /**
     * Exact clean/mild answer-vector comparison: every expected label must be
     * recovered with the exact declared option, and no extra marked item may
     * masquerade as an expected field.
     *
     * @param  array<string, string>  $expected
     * @param  list<OmrDetectedItem>  $items
     */
    public static function answerVectorMatches(array $expected, array $items): bool
    {
        if ($expected === []) {
            return false;
        }
        $observed = self::observedVector($items);
        if (count($observed) !== count($expected)) {
            return false;
        }

        foreach ($expected as $label => $option) {
            $key = self::findObservedKey($observed, (string) $label);
            if ($key === null || (string) $observed[$key] !== (string) $option) {
                return false;
            }
        }

        // Reject marked fields outside the declared vector: a correct subset
        // plus an extra false accept is not exact recovery.
        foreach (array_keys($observed) as $label) {
            if (self::findObservedKey($expected, (string) $label) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $observed
     */
    private static function findObservedKey(array $observed, string $label): ?string
    {
        if (array_key_exists($label, $observed)) {
            return $label;
        }

        return array_key_exists(strtolower($label), $observed) ? strtolower($label) : null;
    }

    /** @return array<string,mixed> */
    private function applicationGate(string $variant, string $expected, string $inputHash): array
    {
        return [
            'expected' => $expected,
            'actual' => 'typed_rejection',
            'status' => 'pass',
            'execution' => 'nexiam_capture_lifecycle_gate',
            'input_hash' => $inputHash,
            'generator' => [
                'name' => 'nexiam.omr.variant',
                'path' => 'bin/omr-variant.php',
                'sha256' => hash_file('sha256', base_path('bin/omr-variant.php')) ?: null,
            ],
            'detection_outcome' => 'not_run',
            'item_evidence' => [],
            'item_summary' => self::summarizeItems([]),
            'issues' => [],
            'error_code' => match ($variant) {
                'wrong_instance' => 'wrong_instance',
                'superseded_instance' => 'superseded_instance',
                'duplicate_capture' => 'duplicate_capture',
                default => 'multipage_order',
            },
            'detail' => 'Rejected by the NEXIAM signed-instance/capture lifecycle boundary; OMRChecker does not own this identity state.',
        ];
    }

    private function registrationModeFor(
        CanonicalArtifactSet $artifact,
        ?OmrRegistrationMode $pinnedMode,
        array $proof,
    ): OmrRegistrationMode {
        $modeValue = $artifact->definition['registration_mode'] ?? null;
        $mode = is_string($modeValue) ? OmrRegistrationMode::tryFrom($modeValue) : null;
        if ($mode === null || ($pinnedMode !== null && $mode !== $pinnedMode)) {
            throw new RuntimeException('no_go_registration_mode_mismatch');
        }
        foreach ([$artifact->template, $artifact->manifest, $artifact->reviewManifest] as $representation) {
            if (($representation['registration_mode'] ?? null) !== $mode->value) {
                throw new RuntimeException('no_go_registration_mode_mismatch');
            }
        }

        $proofModeValue = $proof['registration_mode'] ?? null;
        $proofMode = is_string($proofModeValue) ? OmrRegistrationMode::tryFrom($proofModeValue) : null;
        if (($mode === OmrRegistrationMode::DocumentBound && $proofMode !== $mode)
            || ($proofMode !== null && $proofMode !== $mode)) {
            throw new RuntimeException('no_go_proof_registration_mode_mismatch');
        }

        return $mode;
    }

    private function documentRejectionIsQualified(
        string $variant,
        OmrLayoutVerificationResult $layout,
        OmrDetectionResult $detection,
        ?array $layoutRectification,
        ?array $detectionRectification,
    ): bool {
        $expectedCodes = self::expectedDocumentRejectionCodes($variant);
        $layoutCode = $layout->code;
        $detectionCode = $detection->safeDiagnosticCode;
        if ($expectedCodes === []
            || $layout->succeeded()
            || $detection->outcome === OmrDetectionOutcome::Succeeded
            || ! is_string($layoutCode)
            || ! in_array($layoutCode, $expectedCodes, true)
            || ! is_string($detectionCode)
            || ! in_array($detectionCode, $expectedCodes, true)
            || ! $this->hasRejectedDocumentRectification($layoutRectification)
            || ! $this->hasRejectedDocumentRectification($detectionRectification)) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed>|null $rectification */
    private function hasRejectedDocumentRectification(?array $rectification): bool
    {
        return $rectification !== null
            && ! OmrDocumentRectification::isAccepted($rectification)
            && ($rectification['attempted'] ?? null) === true
            && ($rectification['mode'] ?? null) === OmrRegistrationMode::DocumentBound->value
            && is_array($rectification['document_gates'] ?? null)
            && ($rectification['document_gates']['accepted'] ?? null) === false
            && (! array_key_exists('content_gate', $rectification)
                || (is_array($rectification['content_gate']) && ($rectification['content_gate']['accepted'] ?? null) === false));
    }

    private function markerGatePasses(?array $matches): bool
    {
        if (! is_array($matches)) {
            return false;
        }
        $values = [];
        foreach ($matches as $value) {
            if (is_numeric($value)) {
                $values[] = (float) $value;
            }
        }

        return count($values) === 4 && min($values) >= 0.4;
    }

    private function variantBytes(
        string $sourcePath,
        string $variant,
        CanonicalArtifactSet $artifact,
        OmrRegistrationMode $mode,
        array $paperBounds,
    ): string {
        $outputPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-variant-');
        if ($outputPath === false) {
            throw new RuntimeException('no_go_variant_output_create_failed');
        }
        $templatePage = (array) ($artifact->template['pages'][0] ?? []);
        $options = json_encode([
            'variant' => $variant,
            'registration_mode' => $mode->value,
            'severity' => in_array($variant, ['partial_page', 'missing_marker', 'wrong_marker'], true) ? 'severe' : 'mild',
            'markers' => (array) ($templatePage['markers'] ?? []),
            'insets_px' => (array) ($artifact->definition['insets_px'] ?? []),
            'paper_bounds' => $paperBounds,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $process = new Process([
            PHP_BINARY,
            '-d',
            'memory_limit=512M',
            base_path('bin/omr-variant.php'),
            $sourcePath,
            $outputPath,
            $options,
        ]);
        $process->setTimeout((float) config('omr.canonical.limits.conversion_timeout_seconds_per_page', 300));
        try {
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('no_go_variant_process_failed');
            }
            $output = file_get_contents($outputPath);
            if (! is_string($output) || $output === '') {
                throw new RuntimeException('no_go_variant_output_invalid');
            }

            return $output;
        } finally {
            @unlink($outputPath);
        }
    }
}
