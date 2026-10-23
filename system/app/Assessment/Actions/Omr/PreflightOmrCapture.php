<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCapturePreflightResult;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\User;
use RuntimeException;

use function in_array;
use function is_array;

/**
 * Read-only server preflight for the ordinary capture page.
 *
 * Resource validation is shared with canonical physical capture through
 * {@see OmrCapturePreflight}. Marker-bearing grading batches then retain the
 * existing worker-backed registration preflight; scan batches remain explicit
 * no-op projections for the normal upload path.
 */
final class PreflightOmrCapture
{
    public function __construct(
        private readonly OmrCapturePreflight $capturePreflight,
        private readonly OmrCheckerDetection $detection,
        private readonly OmrDiagnosticRequestBuilder $builder,
    ) {}

    public function handle(
        OmrCaptureBatch $batch,
        string $imageBytes,
        string $imageMime,
        User $actor,
    ): OmrCapturePreflightResult {
        $resourceResult = $this->capturePreflight->validate(new OmrCaptureInput(
            bytes: $imageBytes,
            mime: $imageMime,
        ));
        if (! $resourceResult->succeeded()) {
            throw new RuntimeException($resourceResult->code.': '.$resourceResult->detail);
        }

        if (! in_array(strtolower($imageMime), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Preflight accepts JPEG, PNG, or WebP uploads.');
        }

        $template = $batch->templateVersion;
        if ($template === null) {
            throw new RuntimeException('The capture batch has no pinned template version.');
        }
        $templateJson = is_array($template->native_bundle_json) ? ($template->native_bundle_json['template'] ?? null) : null;

        // Scan (CropPage) batches: camera validation is not applicable; the
        // existing upload/recognition path remains authoritative.
        if (! is_array($templateJson) || OmrCapturePolicy::markerMetadata($templateJson) === null) {
            return OmrCapturePreflightResult::scan(
                'This batch uses a scan (CropPage) template; camera preflight is not applicable. Upload the scan through the normal upload path.',
            );
        }

        $request = $this->builder->build($template, $imageBytes, $imageMime);

        return $this->detection->preflight($request);
    }
}
