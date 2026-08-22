<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\User;
use RuntimeException;

use function in_array;
use function is_array;

/**
 * Read-only server preflight for the ordinary capture page (tasks 10.7/10.18).
 *
 * Uses the BATCH's immutable template version as the geometry authority — no
 * client bubble coordinates, no free-form template id. Marker-bearing batches
 * run the CropOnMarkers-only registration diagnostic through the worker and
 * return `validated`/`rejected` with typed codes, marker scores, gates,
 * normalized dimensions, and an orientation hint; `submit_allowed` is true
 * only when every gate passes. Scan (CropPage) batches return an explicit
 * `mode=scan`, `outcome=not_applicable` response — the normal upload path
 * stays authoritative.
 *
 * Never runs Hough fallback, recognition, or scoring, and never mutates
 * batch/sheet/pair/key state.
 */
final class PreflightOmrCapture
{
    public function __construct(
        private readonly OmrCheckerDetection $detection,
        private readonly OmrDiagnosticRequestBuilder $builder,
    ) {
    }

    public function handle(
        OmrCaptureBatch $batch,
        string $imageBytes,
        string $imageMime,
        User $actor,
    ): OmrCapturePreflight {
        if (! in_array($imageMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
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
            return OmrCapturePreflight::scan(
                'This batch uses a scan (CropPage) template; camera preflight is not applicable. Upload the scan through the normal upload path.',
            );
        }

        $request = $this->builder->build($template, $imageBytes, $imageMime);

        return $this->detection->preflight($request);
    }
}
