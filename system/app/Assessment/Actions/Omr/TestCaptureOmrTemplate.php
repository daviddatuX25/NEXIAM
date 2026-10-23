<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\User;
use RuntimeException;

use function count;
use function in_array;
use function is_array;

/**
 * Read-only builder Test capture (OpenSpec tasks 10.5/10.6 backend seam).
 *
 * Runs the worker layout-verification diagnostic against a draft or activated
 * MARKER-BEARING template version: upload a photo of the printed sheet, get
 * the normalized clean raster, the engine `draw_template_layout` overlay,
 * per-bubble means, marker diagnostics, and field-block shifts with gates.
 * It NEVER mutates activation, pairs, answer keys, or scores; marker-less
 * (CropPage) versions are rejected with a typed error.
 *
 * Geometry and the worker envelope are built by the shared
 * OmrDiagnosticRequestBuilder (also used by the capture preflight).
 */
final class TestCaptureOmrTemplate
{
    public function __construct(
        private readonly OmrLayoutVerificationPort $detection,
        private readonly OmrDiagnosticRequestBuilder $builder,
        private readonly OmrCapturePreflight $preflight,
        private readonly ?OmrCaptureAnalysisPort $analysis = null,
    ) {}

    public function handle(
        OmrAnswerSheetTemplateVersion $version,
        string $imageBytes,
        string $imageMime,
        User $actor,
    ): OmrLayoutVerificationResult {
        $preflight = $this->preflight->validate(new OmrCaptureInput(
            bytes: $imageBytes,
            mime: $imageMime,
        ));
        if (! $preflight->succeeded()) {
            return OmrLayoutVerificationResult::failed(
                $preflight->code ?? 'upload_invalid',
                $preflight->detail ?? 'The test capture upload is invalid.',
            );
        }

        if (! in_array($imageMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Test capture accepts JPEG, PNG, or WebP uploads.');
        }
        if ($this->analysis !== null) {
            $prepared = $this->analysis->analyze(new OmrCaptureAnalysisRequest(
                capture: new OmrCaptureInput(
                    bytes: $imageBytes,
                    mime: $imageMime,
                    pageNo: 1,
                    pageCount: 1,
                ),
                target: new CompiledVersionDiagnosticTarget($version),
                pageNo: 1,
            ));

            if ($prepared->layout !== null) {
                $pageTemplate = $this->builder->build($version, $imageBytes, $imageMime)->nativeTemplate;

                return $this->withGates(
                    $prepared->layout,
                    $pageTemplate,
                );
            }
        }
        $request = $this->builder->build($version, $imageBytes, $imageMime);
        $result = $this->detection->verifyLayout($request);
        if (! $result->succeeded()) {
            return $result;
        }

        return $this->withGates($result, $request->nativeTemplate);
    }

    /**
     * @param  array<string, mixed>  $pageTemplate
     */
    private function withGates(
        OmrLayoutVerificationResult $result,
        array $pageTemplate,
    ): OmrLayoutVerificationResult {
        $unsafe = $this->builder->unsafeBlocks($pageTemplate, $result->fieldBlockShifts ?? []);

        return new OmrLayoutVerificationResult(
            outcome: $result->outcome,
            code: $result->code,
            detail: $result->detail,
            alignedBytes: $result->alignedBytes,
            alignedImage: $result->alignedImage,
            overlayBytes: $result->overlayBytes,
            overlayImage: $result->overlayImage,
            markerMatches: $result->markerMatches,
            fieldBlockShifts: $result->fieldBlockShifts,
            bubbleMeans: $result->bubbleMeans,
            itemEvidence: $result->itemEvidence,
            preprocessMs: $result->preprocessMs,
            verificationMs: $result->verificationMs,
            gates: $this->gates($pageTemplate, $result, $unsafe),
            rectification: $result->rectification,
        );
    }

    /**
     * NEXIAM-side gates over the worker diagnostics, mirroring the review
     * validator's unsafe-shift rule (abs(shift) >= option pitch × 0.5).
     *
     * @param  array<string, mixed>  $pageTemplate
     * @return array<string, mixed>
     */
    private function gates(array $pageTemplate, OmrLayoutVerificationResult $result, array $unsafe): array
    {
        $frame = is_array($pageTemplate['pageDimensions'] ?? null) ? $pageTemplate['pageDimensions'] : [0, 0];
        $aligned = is_array($result->alignedImage) ? $result->alignedImage : [];
        $dimensionsPass = (int) ($aligned['width'] ?? 0) === (int) ($frame[0] ?? 0)
            && (int) ($aligned['height'] ?? 0) === (int) ($frame[1] ?? 0);
        $markerCountPass = is_array($result->markerMatches) && count($result->markerMatches) === 4;

        return [
            'marker_count' => $markerCountPass ? 'pass' : 'fail',
            'registration' => 'pass',
            'dimensions' => $dimensionsPass ? 'pass' : 'fail',
            'shifts' => $unsafe === [] ? 'pass' : 'fail',
            'unsafe_blocks' => $unsafe,
        ];
    }
}
