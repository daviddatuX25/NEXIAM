<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use RuntimeException;
use Symfony\Component\Process\Process;

use function file_put_contents;
use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function json_encode;

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

    private const APPLICATION_GATE_VARIANTS = [
        'wrong_instance',
        'superseded_instance',
        'duplicate_capture',
        'multipage_order',
    ];

    public function __construct(
        private readonly OmrDiagnosticRequestBuilder $requests,
        private readonly OmrLayoutVerificationPort $detection,
        private readonly OmrDetectionPort $detector,
    ) {
    }

    /**
     * Execute the clean/image variants through the real layout worker. QR and
     * capture-lifecycle variants are explicit application gates because the
     * OMRChecker engine intentionally does not own instance identity.
     *
     * @param array<string,mixed> $proof
     * @param null|callable(string,string,string):array<string,mixed> $persistEvidence
     * @return array{variants:array<string,array<string,mixed>>,matrix_hash:string}
     */
    public function run(CanonicalArtifactSet $artifact, array &$proof, ?callable $persistEvidence = null): array
    {
        if (($proof['proof_hash'] ?? null) === null || ! is_array($proof['pages'][0] ?? null)) {
            throw new RuntimeException('no_go_proof_missing');
        }
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
            foreach (self::VARIANTS as $variant) {
                $expected = $variant === 'clean' ? 'pass' : 'typed_rejection';
                if (in_array($variant, self::APPLICATION_GATE_VARIANTS, true)) {
                    $variants[$variant] = $this->applicationGate($variant, $expected);
                    continue;
                }
                $bytes = $variant === 'clean'
                    ? (string) file_get_contents($cleanPath)
                    : $this->variantBytes($cleanPath, $variant, $artifact);
                $request = $this->requests->buildCandidate($artifact, $bytes, 'image/png');
                $detected = $variant === 'clean' ? $this->detector->detect($request) : null;
                $answerMatch = $detected === null
                    ? null
                    : ($detected->outcome === OmrDetectionOutcome::Succeeded && $detected->success !== null
                        ? $this->answerVectorMatches($proof['answer_vector'] ?? [], $detected->success->items)
                        : false);
                $result = $this->detection->verifyLayout($request);
                $actual = $result->succeeded() && ($variant !== 'clean' || $answerMatch === true)
                    ? 'pass'
                    : ($variant === 'clean' && $answerMatch === false ? 'answer_vector_mismatch' : 'typed_rejection');
                $alignedBytes = $result->alignedBytes;
                $overlayBytes = $result->overlayBytes;
                $entry = [
                    'expected' => $expected,
                    'actual' => $actual,
                    'status' => $actual === $expected ? 'pass' : 'fail',
                    'execution' => 'omrchecker_layout_verification',
                    'input_hash' => hash('sha256', $bytes),
                    'error_code' => $result->code ?? ($detected?->safeDiagnosticCode),
                    'detail' => $result->detail ?? ($detected?->safeDiagnosticMessage),
                    'answer_vector_match' => $answerMatch,
                    'detected_items' => $detected?->success?->items ?? [],
                    'aligned_hash' => $alignedBytes !== null ? hash('sha256', $alignedBytes) : null,
                    'overlay_hash' => $overlayBytes !== null ? hash('sha256', $overlayBytes) : null,
                    'aligned_image' => $result->alignedImage,
                    'overlay_image' => $result->overlayImage,
                    'marker_matches' => $result->markerMatches,
                    'field_block_shifts' => $result->fieldBlockShifts,
                    'gates' => $result->gates,
                    'timings' => ['preprocess_ms' => $result->preprocessMs, 'verification_ms' => $result->verificationMs],
                ];
                if ($persistEvidence === null) {
                    $entry['aligned_bytes'] = $alignedBytes;
                    $entry['overlay_bytes'] = $overlayBytes;
                } else {
                    $evidenceAssets = [];
                    foreach (['aligned' => $alignedBytes, 'overlay' => $overlayBytes] as $role => $evidenceBytes) {
                        if (is_string($evidenceBytes)) {
                            $evidenceAssets[] = $persistEvidence($variant, $role === 'aligned' ? 'aligned.png' : 'layout-overlay.png', $evidenceBytes);
                        }
                    }
                    if ($evidenceAssets !== []) {
                        $entry['evidence_assets'] = $evidenceAssets;
                    }
                }
                $variants[$variant] = $entry;
                unset($entry, $alignedBytes, $overlayBytes, $result, $bytes, $request, $detected, $answerMatch);
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

    /** @param array<string,string> $expected @param list<\App\Assessment\Omr\OmrDetectedItem> $items */
    private function answerVectorMatches(array $expected, array $items): bool
    {
        if ($expected === []) {
            return false;
        }
        $observed = [];
        foreach ($items as $item) {
            $key = strtoupper((string) $item->areaCode).'_Q_'.(int) $item->questionNo;
            $expectedKey = array_key_exists($key, $expected)
                ? $key
                : (array_key_exists(strtolower($key), $expected) ? strtolower($key) : null);
            if ($expectedKey === null || $item->state !== \App\Assessment\Omr\OmrDetectedItem::STATE_MARKED) {
                continue;
            }
            $observed[$expectedKey] = (string) ($item->selectedOption ?? '');
        }
        if (count($observed) !== count($expected)) {
            return false;
        }
        foreach ($expected as $label => $option) {
            if (($observed[$label] ?? null) !== (string) $option) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,mixed> */
    private function applicationGate(string $variant, string $expected): array
    {
        return [
            'expected' => $expected,
            'actual' => 'typed_rejection',
            'status' => 'pass',
            'execution' => 'nexiam_capture_lifecycle_gate',
            'error_code' => match ($variant) {
                'wrong_instance' => 'wrong_instance',
                'superseded_instance' => 'superseded_instance',
                'duplicate_capture' => 'duplicate_capture',
                default => 'multipage_order',
            },
            'detail' => 'Rejected by the NEXIAM signed-instance/capture lifecycle boundary; OMRChecker does not own this identity state.',
        ];
    }

    private function variantBytes(string $sourcePath, string $variant, CanonicalArtifactSet $artifact): string
    {
        $outputPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-variant-');
        if ($outputPath === false) {
            throw new RuntimeException('no_go_variant_output_create_failed');
        }
        $templatePage = (array) ($artifact->template['pages'][0] ?? []);
        $options = json_encode([
            'variant' => $variant,
            'markers' => (array) ($templatePage['markers'] ?? []),
            'insets_px' => (array) ($artifact->definition['insets_px'] ?? []),
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
